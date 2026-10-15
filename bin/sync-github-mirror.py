#!/usr/bin/env python3
"""Validate and synchronize the canonical bbPress GitHub mirror."""

from __future__ import annotations

import argparse
import datetime as dt
import functools
import hashlib
from html.parser import HTMLParser
from http.client import HTTPException
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile
from typing import Dict, List, Optional, Set, Tuple
import unicodedata
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlparse
from urllib.request import Request, urlopen
import xml.etree.ElementTree as ET


SVN_ROOT = "https://bbpress.svn.wordpress.org"
SVN_UUID = "9866e705-20ec-0310-96e7-cbb4277adcfb"
WPORG_PROFILES = "https://profiles.wordpress.org"
WPORG_USER_API = f"{WPORG_PROFILES}/wp-json/wporg/v1/users"
WPORG_GITHUB_LOOKUP = f"{WPORG_PROFILES}/wp-json/wporg-github/v1/lookup"
HTTP_USER_AGENT = "bbPress GitHub mirror validator/1.0 (+https://github.com/bbpress/bbPress)"
HTTP_RESPONSE_LIMIT = 1024 * 1024
SCHEDULED_PROTECTED_PATHS = (".github", "bin")
TRAILER_RE = re.compile(
    rf"^git-svn-id: ({re.escape(SVN_ROOT)}/(trunk|branches/[^@\n]+|tags/[^@\n]+))@(\d+) {SVN_UUID}$",
    re.MULTILINE,
)


class MirrorError(RuntimeError):
    """A validation failure that must stop the mirror."""


class GithubConnectionParser(HTMLParser):
    """Collect verified-profile GitHub links from public Profile HTML."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.github_users: Dict[str, str] = {}

    def handle_starttag(self, tag: str, attrs: List[Tuple[str, Optional[str]]]) -> None:
        if tag != "a":
            return
        attributes = dict(attrs)
        if "me" not in (attributes.get("rel") or "").split():
            return
        href = attributes.get("href") or ""
        parsed = urlparse(href)
        if (
            parsed.scheme != "https"
            or parsed.hostname not in {"github.com", "www.github.com"}
            or parsed.username is not None
            or parsed.password is not None
            or parsed.port not in {None, 443}
            or parsed.query
            or parsed.fragment
        ):
            return
        path = parsed.path.strip("/")
        if "/" in path or not re.fullmatch(r"[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?", path):
            return
        self.github_users[path.casefold()] = path


def validate_wporg_https_url(url: str) -> None:
    parsed = urlparse(url)
    if (
        parsed.scheme != "https"
        or parsed.hostname not in {"wordpress.org", "profiles.wordpress.org"}
        or parsed.username is not None
        or parsed.password is not None
        or parsed.port not in {None, 443}
    ):
        raise MirrorError(f"Refusing unexpected WordPress.org identity URL: {url}")


def fetch_https(url: str) -> str:
    validate_wporg_https_url(url)
    request = Request(
        url,
        headers={
            "Accept": "application/json, text/html;q=0.9",
            "User-Agent": HTTP_USER_AGENT,
        },
    )
    try:
        with urlopen(request, timeout=20) as response:
            validate_wporg_https_url(response.geturl())
            content = response.read(HTTP_RESPONSE_LIMIT + 1)
    except (HTTPError, URLError, HTTPException, TimeoutError) as error:
        raise MirrorError(f"WordPress.org identity lookup failed for {url}: {error}") from error
    if len(content) > HTTP_RESPONSE_LIMIT:
        raise MirrorError(f"WordPress.org identity response is too large: {url}")
    try:
        return content.decode("utf-8")
    except UnicodeDecodeError as error:
        raise MirrorError(f"WordPress.org identity response is not UTF-8: {url}") from error


def fetch_json(url: str) -> Dict[str, object]:
    try:
        value = json.loads(fetch_https(url))
    except json.JSONDecodeError as error:
        raise MirrorError(f"WordPress.org identity response is not valid JSON: {url}") from error
    if not isinstance(value, dict):
        raise MirrorError(f"WordPress.org identity response is not an object: {url}")
    return value


def validate_display_name(name: str, context: str) -> None:
    if (
        not name
        or len(name) > 100
        or "<" in name
        or ">" in name
        or any(unicodedata.category(character) in {"Cc", "Cf"} for character in name)
    ):
        raise MirrorError(f"{context} is empty, unsafe, or too long.")


@functools.lru_cache(maxsize=128)
def resolve_profile_identity(svn_author: str) -> Dict[str, str]:
    encoded_author = quote(svn_author, safe="")
    user = fetch_json(f"{WPORG_USER_API}/{encoded_author}")
    if user.get("slug") != svn_author:
        raise MirrorError(
            f"WordPress.org user lookup for {svn_author!r} returned a different slug."
        )
    name = user.get("name")
    profile = user.get("link")
    if not isinstance(name, str):
        raise MirrorError(f"WordPress.org user {svn_author!r} has no display name.")
    validate_display_name(name, f"WordPress.org display name for {svn_author!r}")
    expected_profile = f"{WPORG_PROFILES}/{svn_author}/"
    if profile != expected_profile:
        raise MirrorError(
            f"WordPress.org user {svn_author!r} returned an unexpected Profile URL."
        )

    parser = GithubConnectionParser()
    parser.feed(fetch_https(profile))
    github_users = sorted(parser.github_users.values(), key=str.casefold)
    if len(github_users) != 1:
        raise MirrorError(
            f"WordPress.org Profile {svn_author!r} resolved "
            f"{len(github_users)} GitHub connections; expected exactly one."
        )
    github_user = github_users[0]
    linked = fetch_json(f"{WPORG_GITHUB_LOOKUP}/{quote(github_user, safe='')}")
    if (
        linked.get("slug") != svn_author
        or linked.get("profile") != expected_profile.removesuffix("/")
    ):
        raise MirrorError(
            f"WordPress.org Profiles API did not map GitHub user {github_user!r} "
            f"back to {svn_author!r}."
        )
    return {
        "svn_author": svn_author,
        "name": name,
        "email": f"{svn_author}@git.wordpress.org",
        "github_user": github_user,
    }


def run(command: List[str], *, cwd: Optional[Path] = None, capture: bool = True) -> str:
    result = subprocess.run(
        command,
        cwd=cwd,
        check=False,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.PIPE if capture else None,
        text=True,
    )
    if result.returncode:
        detail = (result.stderr or result.stdout or "").strip()
        raise MirrorError(f"Command failed ({' '.join(command)}): {detail}")
    return result.stdout if capture else ""


def run_bytes(command: List[str], *, cwd: Optional[Path] = None) -> bytes:
    result = subprocess.run(
        command,
        cwd=cwd,
        check=False,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if result.returncode:
        detail = (result.stderr or result.stdout).decode("utf-8", "replace").strip()
        raise MirrorError(f"Command failed ({' '.join(command)}): {detail}")
    return result.stdout


def git(repository: Path, *arguments: str) -> str:
    return run(["git", *arguments], cwd=repository)


def git_refs(repository: Path, namespace: str) -> Dict[str, str]:
    output = git(
        repository,
        "for-each-ref",
        "--format=%(refname)%00%(objectname)",
        namespace,
    )
    refs: Dict[str, str] = {}
    for line in output.splitlines():
        if not line:
            continue
        ref, object_id = line.split("\0", 1)
        refs[ref] = object_id
    return refs


def remote_refs(url: str) -> Dict[str, str]:
    output = run(["git", "ls-remote", "--heads", "--tags", url])
    return {
        ref: object_id
        for line in output.splitlines()
        if line and not line.endswith("^{}")
        for object_id, ref in [line.split("\t", 1)]
    }


def svn_repository_revision() -> int:
    return int(run(["svn", "info", "--show-item", "revision", SVN_ROOT]).strip())


def svn_inventory(kind: str, snapshot: Optional[int] = None) -> Set[str]:
    peg = f"@{snapshot}" if snapshot is not None else ""
    xml = run(["svn", "list", "--xml", f"{SVN_ROOT}/{kind}/{peg}"])
    root = ET.fromstring(xml)
    return {
        name.text.removesuffix("/")
        for name in root.findall(".//entry[@kind='dir']/name")
        if name.text
    }


def svn_latest_revision(url: str, snapshot: Optional[int] = None) -> int:
    command = ["svn", "log", "--xml", "--limit", "1"]
    if snapshot is not None:
        command.extend(["-r", f"{snapshot}:1"])
    command.append(f"{url}@{snapshot}" if snapshot is not None else url)
    xml = run(command)
    entry = ET.fromstring(xml).find("logentry")
    if entry is None:
        raise MirrorError(f"Canonical Subversion returned no history for {url}.")
    return int(entry.attrib["revision"])


def parse_raw_identity(
    value: bytes, object_id: str, field: str
) -> Tuple[str, str, str, str]:
    try:
        decoded = value.decode("utf-8")
    except UnicodeDecodeError as error:
        raise MirrorError(f"Commit {object_id} has a non-UTF-8 {field} header.") from error
    match = re.fullmatch(r"(.+) <([^<>]+)> (\d+) ([+-])(\d{2})(\d{2})", decoded)
    if not match:
        raise MirrorError(f"Commit {object_id} has an invalid {field} header.")
    hours, minutes = int(match.group(5)), int(match.group(6))
    if hours > 23 or minutes > 59:
        raise MirrorError(f"Commit {object_id} has an invalid {field} timezone.")
    offset_minutes = hours * 60 + minutes
    if match.group(4) == "-":
        offset_minutes *= -1
    timezone = dt.timezone(dt.timedelta(minutes=offset_minutes))
    date = dt.datetime.fromtimestamp(int(match.group(3)), timezone).isoformat()
    return match.group(1), match.group(2), date, f"{match.group(4)}{match.group(5)}{match.group(6)}"


def parse_commit(repository: Path, object_id: str) -> Dict[str, object]:
    if git(repository, "cat-file", "-t", object_id).strip() != "commit":
        raise MirrorError(f"Object {object_id} is not directly a Git commit.")
    raw = run_bytes(["git", "cat-file", "commit", object_id], cwd=repository)
    headers, separator, message = raw.partition(b"\n\n")
    if not separator:
        raise MirrorError(f"Commit {object_id} has no header/message separator.")
    lines = headers.split(b"\n")
    if not lines or not lines[0].startswith(b"tree "):
        raise MirrorError(f"Commit {object_id} has no leading tree header.")
    tree = lines.pop(0).removeprefix(b"tree ").decode("ascii", "strict")
    if not re.fullmatch(r"[0-9a-f]{40}", tree):
        raise MirrorError(f"Commit {object_id} has an invalid tree object ID.")
    parents: List[str] = []
    while lines and lines[0].startswith(b"parent "):
        parent = lines.pop(0).removeprefix(b"parent ").decode("ascii", "strict")
        if not re.fullmatch(r"[0-9a-f]{40}", parent):
            raise MirrorError(f"Commit {object_id} has an invalid parent object ID.")
        parents.append(parent)
    if len(parents) > 1:
        raise MirrorError(f"Commit {object_id} is a merge commit.")
    if len(lines) != 2 or not lines[0].startswith(b"author ") or not lines[1].startswith(b"committer "):
        raise MirrorError(
            f"Commit {object_id} contains an unexpected or out-of-order raw header."
        )
    author_name, author_email, author_date, author_timezone = parse_raw_identity(
        lines[0].removeprefix(b"author "), object_id, "author"
    )
    committer_name, committer_email, committer_date, committer_timezone = parse_raw_identity(
        lines[1].removeprefix(b"committer "), object_id, "committer"
    )
    try:
        body = message.decode("utf-8")
    except UnicodeDecodeError as error:
        raise MirrorError(f"Commit {object_id} has a non-UTF-8 message.") from error
    commit: Dict[str, object] = {
        "object_id": object_id,
        "tree": tree,
        "parents": parents,
        "author_name": author_name,
        "author_email": author_email,
        "author_date": author_date,
        "author_timezone": author_timezone,
        "committer_name": committer_name,
        "committer_email": committer_email,
        "committer_date": committer_date,
        "committer_timezone": committer_timezone,
        "body": body,
    }
    matches = list(TRAILER_RE.finditer(str(commit["body"])))
    if len(matches) != 1:
        raise MirrorError(f"Commit {object_id} must contain exactly one canonical git-svn-id trailer.")
    match = matches[0]
    if str(commit["body"])[match.end() :].strip():
        raise MirrorError(f"Commit {object_id} has content after its git-svn-id trailer.")
    commit["svn_url"] = match.group(1)
    commit["svn_path"] = match.group(2)
    commit["svn_revision"] = int(match.group(3))
    commit["svn_message"] = str(commit["body"])[: match.start()].rstrip("\n")
    return commit


def load_svn_log(
    first: int, last: int, snapshot: Optional[int] = None
) -> Dict[int, ET.Element]:
    xml = run(
        [
            "svn",
            "log",
            "--xml",
            "--verbose",
            "-r",
            f"{last}:{first}",
            f"{SVN_ROOT}@{snapshot if snapshot is not None else last}",
        ]
    )
    root = ET.fromstring(xml)
    return {int(entry.attrib["revision"]): entry for entry in root.findall("logentry")}


@functools.lru_cache(maxsize=128)
def svn_deletion_revision(path: str, after_revision: int, snapshot: int) -> int:
    """Return the explicit canonical directory deletion after a mirrored tip."""
    if after_revision >= snapshot:
        raise MirrorError(
            f"Canonical Subversion has no revision after r{after_revision} in snapshot r{snapshot}."
        )
    entries = load_svn_log(after_revision + 1, snapshot, snapshot)
    changes = [
        (revision, changed_path)
        for revision, entry in entries.items()
        for changed_path in entry.findall("./paths/path")
        if (changed_path.text or "") == f"/{path}"
    ]
    if not changes:
        raise MirrorError(
            f"Canonical Subversion has no explicit change for /{path} after r{after_revision}."
        )
    revision, changed_path = max(changes, key=lambda change: change[0])
    if (
        changed_path.attrib.get("action") != "D"
        or changed_path.attrib.get("kind") != "dir"
    ):
        raise MirrorError(
            f"The latest canonical change for /{path} after r{after_revision} is not a directory deletion."
        )
    return revision


def unix_second(value: str) -> int:
    return int(dt.datetime.fromisoformat(value.replace("Z", "+00:00")).timestamp())


def validate_identity(
    commit: Dict[str, object],
    svn_author: str,
    revision: int,
    profile_resolver=None,
) -> Dict[str, str]:
    name = str(commit["author_name"])
    email = str(commit["author_email"])
    if not svn_author:
        raise MirrorError(f"Canonical Subversion r{revision} has no author.")
    validate_display_name(name, f"Candidate author name for r{revision}")
    wordpress_email = f"{svn_author}@git.wordpress.org"
    legacy_email = f"{svn_author}@{SVN_UUID}"
    if email == wordpress_email:
        resolver = profile_resolver or resolve_profile_identity
        identity = resolver(svn_author)
        if (
            identity.get("svn_author") != svn_author
            or identity.get("email") != wordpress_email
            or identity.get("name") != name
            or not identity.get("github_user")
        ):
            raise MirrorError(
                f"Candidate identity for r{revision} does not match the verified "
                f"WordPress.org Profile for {svn_author!r}: {name} <{email}>."
            )
    elif email == legacy_email and name == svn_author:
        identity = {
            "svn_author": svn_author,
            "name": name,
            "email": email,
            "github_user": "",
        }
    else:
        raise MirrorError(
            f"Candidate identity for r{revision} is not derived from canonical Subversion "
            f"author {svn_author!r}: {name} <{email}>."
        )
    if (
        commit["committer_name"] != name
        or commit["committer_email"] != email
        or commit["committer_date"] != commit["author_date"]
        or commit.get("committer_timezone") != commit.get("author_timezone")
    ):
        raise MirrorError(
            f"Commit {commit['object_id']} has different author and committer metadata."
        )
    if commit.get("author_timezone") != "+0000":
        raise MirrorError(f"Commit {commit['object_id']} does not use the git-svn UTC timezone.")
    return dict(identity)


def validate_commit(
    commit: Dict[str, object],
    svn_logs: Dict[int, ET.Element],
    expected_path: Optional[str] = None,
    profile_resolver=None,
) -> Dict[str, str]:
    object_id = str(commit["object_id"])
    revision = int(commit["svn_revision"])
    svn_path = str(commit["svn_path"])
    if expected_path and svn_path != expected_path:
        raise MirrorError(
            f"Commit {object_id} maps to {svn_path}, expected {expected_path}."
        )
    entry = svn_logs.get(revision)
    if entry is None:
        raise MirrorError(f"Canonical Subversion r{revision} was not returned.")
    svn_author = entry.findtext("author") or ""
    identity = validate_identity(commit, svn_author, revision, profile_resolver)
    svn_date = entry.findtext("date") or ""
    if unix_second(str(commit["author_date"])) != unix_second(svn_date):
        raise MirrorError(f"Candidate date does not match canonical Subversion r{revision}.")
    svn_message = (entry.findtext("msg") or "").rstrip("\n")
    if commit["svn_message"] != svn_message:
        raise MirrorError(f"Candidate message does not match canonical Subversion r{revision}.")
    prefix = f"/{svn_path}"
    changed_paths = [path.text or "" for path in entry.findall("./paths/path")]
    if not any(path == prefix or path.startswith(f"{prefix}/") for path in changed_paths):
        raise MirrorError(f"Canonical Subversion r{revision} does not change {prefix}.")
    return identity


def blob_id(content: bytes) -> str:
    header = f"blob {len(content)}\0".encode()
    return hashlib.sha1(header + content).hexdigest()


def filesystem_manifest(root: Path) -> Dict[str, Tuple[str, str]]:
    manifest: Dict[str, Tuple[str, str]] = {}
    for path in sorted(root.rglob("*")):
        relative = path.relative_to(root).as_posix()
        if path.is_symlink():
            content = os.readlink(path).encode()
            manifest[relative] = ("120000", blob_id(content))
        elif path.is_file():
            content = path.read_bytes()
            mode = "100755" if path.stat().st_mode & stat.S_IXUSR else "100644"
            manifest[relative] = (mode, blob_id(content))
    return manifest


def git_manifest(repository: Path, object_id: str) -> Dict[str, Tuple[str, str]]:
    raw = run_bytes(
        ["git", "ls-tree", "-rz", "--full-tree", object_id], cwd=repository
    )
    manifest: Dict[str, Tuple[str, str]] = {}
    for record in raw.split(b"\0"):
        if not record:
            continue
        metadata, encoded_path = record.split(b"\t", 1)
        mode, object_type, object_hash = metadata.decode().split(" ")
        if object_type != "blob":
            raise MirrorError(f"Unexpected Git tree object type {object_type}.")
        manifest[encoded_path.decode("utf-8", "surrogateescape")] = (mode, object_hash)
    return manifest


def validate_tree(repository: Path, object_id: str, commit: Dict[str, object]) -> None:
    with tempfile.TemporaryDirectory(prefix="bbpress-svn-export-") as directory:
        export = Path(directory)
        run(
            [
                "svn",
                "export",
                "--quiet",
                "--force",
                "--ignore-externals",
                "--ignore-keywords",
                "--native-eol",
                "LF",
                "-r",
                str(commit["svn_revision"]),
                f"{commit['svn_url']}@{commit['svn_revision']}",
                str(export),
            ]
        )
        expected = filesystem_manifest(export)
    actual = git_manifest(repository, object_id)
    if actual != expected:
        missing = sorted(set(expected) - set(actual))
        extra = sorted(set(actual) - set(expected))
        changed = sorted(path for path in set(actual) & set(expected) if actual[path] != expected[path])
        detail = f"missing={missing[:5]}, extra={extra[:5]}, changed={changed[:5]}"
        raise MirrorError(
            f"Candidate tree {object_id} differs from canonical Subversion r{commit['svn_revision']}: {detail}"
        )


def ref_commit(repository: Path, ref: str) -> str:
    return git(repository, "rev-parse", f"{ref}^{{commit}}").strip()


def rev_list(repository: Path, revision_range: str) -> List[str]:
    output = git(repository, "rev-list", "--reverse", revision_range)
    return [line for line in output.splitlines() if line]


def branch_path_commits(repository: Path, tip: str, svn_path: str) -> List[str]:
    output = git(
        repository,
        "log",
        "--reverse",
        "--format=%H",
        "--fixed-strings",
        f"--grep=git-svn-id: {SVN_ROOT}/{svn_path}@",
        tip,
    )
    return [line for line in output.splitlines() if line]


def is_ancestor(repository: Path, ancestor: str, descendant: str) -> bool:
    result = subprocess.run(
        ["git", "merge-base", "--is-ancestor", ancestor, descendant],
        cwd=repository,
        check=False,
    )
    return result.returncode == 0


def canonical_tag_name(svn_name: str) -> str:
    return f"{svn_name}.0" if re.fullmatch(r"\d+\.\d+", svn_name) else svn_name


def sources_already_match(
    candidate_url: str,
    github_url: str,
    canonical_branches: Set[str],
    canonical_tags: Set[str],
    snapshot: Optional[int] = None,
) -> bool:
    candidate = remote_refs(candidate_url)
    github = remote_refs(github_url)
    canonical_git_tags = {canonical_tag_name(name) for name in canonical_tags}
    candidate_branches = {
        ref.removeprefix("refs/heads/") for ref in candidate if ref.startswith("refs/heads/")
    }
    github_branches = {
        ref.removeprefix("refs/heads/") for ref in github if ref.startswith("refs/heads/")
    }
    candidate_tag_names = {
        ref.removeprefix("refs/tags/") for ref in candidate if ref.startswith("refs/tags/")
    }
    github_tag_names = {
        ref.removeprefix("refs/tags/") for ref in github if ref.startswith("refs/tags/")
    }
    if not canonical_branches.issubset(candidate_branches & github_branches):
        return False
    if not canonical_git_tags.issubset(candidate_tag_names & github_tag_names):
        return False
    for name in canonical_branches:
        ref = f"refs/heads/{name}"
        if not candidate.get(ref) or candidate[ref] != github.get(ref):
            return False
    for svn_name in canonical_tags:
        ref = f"refs/tags/{canonical_tag_name(svn_name)}"
        if not candidate.get(ref) or candidate[ref] != github.get(ref):
            return False
    canonical_refs = {
        *(f"refs/heads/{name}" for name in canonical_branches),
        *(f"refs/tags/{name}" for name in canonical_git_tags),
    }
    github_extras = {ref: value for ref, value in github.items() if ref not in canonical_refs}
    github_extra_paths = probe_ref_paths(github_url, set(github_extras))
    for ref, path in github_extra_paths.items():
        if ref.startswith("refs/heads/"):
            name = ref.removeprefix("refs/heads/")
            if path == f"branches/{name}":
                return False
        elif ref.startswith("refs/tags/") and path and path.startswith("tags/"):
            name = ref.removeprefix("refs/tags/")
            canonical_name = path.removeprefix("tags/")
            if canonical_name in canonical_tags:
                continue
            if name in {canonical_name, canonical_tag_name(canonical_name)}:
                return False
    if snapshot is not None:
        revisions = probe_branch_revisions(github_url, canonical_branches)
        for name in canonical_branches:
            expected_path = "trunk" if name == "trunk" else f"branches/{name}"
            latest = svn_latest_revision(f"{SVN_ROOT}/{expected_path}", snapshot)
            if revisions.get(name) != latest:
                return False
    return True


def probe_ref_paths(url: str, refs: Set[str]) -> Dict[str, Optional[str]]:
    if not refs:
        return {}
    with tempfile.TemporaryDirectory(prefix="bbpress-github-extra-probe-") as directory:
        repository = Path(directory) / "probe.git"
        repository.mkdir()
        git(repository.parent, "init", "--bare", str(repository))
        destinations = {
            ref: f"refs/probe/extra/{index}" for index, ref in enumerate(sorted(refs))
        }
        git(
            repository,
            "-c",
            "transfer.fsckObjects=true",
            "fetch",
            "--depth=1",
            "--filter=tree:0",
            "--no-tags",
            url,
            *(f"+{ref}:{destination}" for ref, destination in destinations.items()),
        )
        paths: Dict[str, Optional[str]] = {}
        for ref, destination in destinations.items():
            try:
                commit = parse_commit(repository, destination)
            except (MirrorError, UnicodeError, ValueError):
                paths[ref] = None
            else:
                paths[ref] = str(commit["svn_path"])
        return paths


def probe_branch_revisions(url: str, branches: Set[str]) -> Dict[str, int]:
    with tempfile.TemporaryDirectory(prefix="bbpress-github-probe-") as directory:
        repository = Path(directory) / "probe.git"
        repository.mkdir()
        git(repository.parent, "init", "--bare", str(repository))
        refspecs = [
            f"+refs/heads/{name}:refs/probe/heads/{name}" for name in sorted(branches)
        ]
        git(
            repository,
            "-c",
            "transfer.fsckObjects=true",
            "fetch",
            "--depth=1",
            "--filter=tree:0",
            "--no-tags",
            url,
            *refspecs,
        )
        return {
            name: int(
                parse_commit(repository, f"refs/probe/heads/{name}")["svn_revision"]
            )
            for name in branches
        }


def svn_revision_date(revision: int, snapshot: int) -> dt.datetime:
    xml = run(
        [
            "svn",
            "log",
            "--xml",
            "-r",
            str(revision),
            f"{SVN_ROOT}@{snapshot}",
        ]
    )
    entry = ET.fromstring(xml).find("logentry")
    if entry is None or not entry.findtext("date"):
        raise MirrorError(f"Canonical Subversion returned no date for r{revision}.")
    return dt.datetime.fromisoformat(str(entry.findtext("date")).replace("Z", "+00:00"))


def svn_first_revision_after(
    url: str,
    revision: int,
    snapshot: int,
    *,
    stop_on_copy: bool = False,
) -> int:
    if revision >= snapshot:
        raise MirrorError(
            f"Cannot find a canonical revision after r{revision} at snapshot r{snapshot}."
        )
    command = [
        "svn",
        "log",
        "--xml",
        "--limit",
        "1",
        "-r",
        f"{revision + 1}:{snapshot}",
    ]
    if stop_on_copy:
        command.append("--stop-on-copy")
    command.append(f"{url}@{snapshot}")
    entry = ET.fromstring(run(command)).find("logentry")
    if entry is None:
        raise MirrorError(
            f"Canonical Subversion returned no revision after r{revision} for {url}."
        )
    return int(entry.attrib["revision"])


def stale_source_lag(
    behind: List[Dict[str, str]], snapshot: int, grace_seconds: int = 1800
) -> List[Dict[str, str]]:
    now = dt.datetime.now(dt.timezone.utc)
    return [
        item
        for item in behind
        if item.get("state") != "snapshot-advanced"
        if (
            now
            - svn_revision_date(
                int(item.get("first_missing_revision", item["canonical_revision"])),
                snapshot,
            )
        ).total_seconds()
        > grace_seconds
    ]


def svn_last_changed_revision(path: str, revision: int) -> int:
    return int(
        run(
            [
                "svn",
                "info",
                "--show-item",
                "last-changed-revision",
                "-r",
                str(revision),
                f"{SVN_ROOT}{path}@{revision}",
            ]
        ).strip()
    )


def fetch_sources(repository: Path, candidate_url: str, github_url: str) -> None:
    repository.mkdir(parents=True, exist_ok=False)
    git(repository.parent, "init", "--bare", str(repository))
    git(
        repository,
        "-c",
        "transfer.fsckObjects=true",
        "fetch",
        "--prune",
        "--no-tags",
        candidate_url,
        "+refs/heads/*:refs/candidate/heads/*",
        "+refs/tags/*:refs/candidate/tags/*",
    )
    git(
        repository,
        "-c",
        "transfer.fsckObjects=true",
        "fetch",
        "--prune",
        "--no-tags",
        github_url,
        "+refs/heads/*:refs/github/heads/*",
        "+refs/tags/*:refs/github/tags/*",
    )
    unexpected = sorted(
        ref
        for ref in git_refs(repository, "refs/")
        if not ref.startswith("refs/candidate/heads/")
        and not ref.startswith("refs/candidate/tags/")
        and not ref.startswith("refs/github/heads/")
        and not ref.startswith("refs/github/tags/")
    )
    if unexpected:
        raise MirrorError(f"Unexpected local refs after isolated fetch: {', '.join(unexpected)}")


def deleted_ref_item(
    repository: Path,
    kind: str,
    name: str,
    object_id: str,
    local_ref: str,
    snapshot: int,
) -> Dict[str, str]:
    commit_id = ref_commit(repository, local_ref)
    if object_id != commit_id:
        raise MirrorError(f"Deleted candidate {kind} {name} must point directly to a Git commit.")
    commit = parse_commit(repository, commit_id)
    if kind == "branch":
        expected_path = f"branches/{name}"
        canonical_name = name
    else:
        if not str(commit["svn_path"]).startswith("tags/"):
            raise MirrorError(f"GitHub tag {name} does not map to a canonical Subversion tag.")
        canonical_name = str(commit["svn_path"]).removeprefix("tags/")
        if name not in {canonical_name, canonical_tag_name(canonical_name)}:
            raise MirrorError(
                f"GitHub tag {name} does not match canonical tag name {canonical_name}."
            )
        expected_path = f"tags/{canonical_name}"
    if commit["svn_path"] != expected_path:
        raise MirrorError(f"GitHub {kind} {name} does not map to {expected_path}.")
    deletion_revision = svn_deletion_revision(
        expected_path, int(commit["svn_revision"]), snapshot
    )
    item = {
        "action": "delete",
        "kind": kind,
        "name": name,
        "source_ref": "",
        "destination_ref": f"refs/{'heads' if kind == 'branch' else 'tags'}/{name}",
        "old": object_id,
        "new": "",
        "svn_revision": str(deletion_revision),
    }
    if kind == "tag":
        item["canonical_name"] = canonical_name
    return item


def canonical_mirror_ref_path(
    repository: Path, kind: str, name: str, object_id: str, local_ref: str
) -> Optional[str]:
    """Identify an exact canonical mirror ref without authorizing deletion."""
    try:
        commit_id = ref_commit(repository, local_ref)
        if object_id != commit_id:
            return None
        commit = parse_commit(repository, commit_id)
    except MirrorError:
        return None
    if kind == "branch":
        return str(commit["svn_path"]) if commit["svn_path"] == f"branches/{name}" else None
    if not str(commit["svn_path"]).startswith("tags/"):
        return None
    canonical_name = str(commit["svn_path"]).removeprefix("tags/")
    return str(commit["svn_path"]) if name in {canonical_name, canonical_tag_name(canonical_name)} else None


def create_plan(
    repository: Path,
    snapshot: Optional[int] = None,
    canonical_branches: Optional[Set[str]] = None,
    canonical_tags: Optional[Set[str]] = None,
) -> Tuple[List[Dict[str, str]], List[Dict[str, str]]]:
    candidate_heads = git_refs(repository, "refs/candidate/heads/")
    candidate_tags = git_refs(repository, "refs/candidate/tags/")
    github_heads = git_refs(repository, "refs/github/heads/")
    github_tags = git_refs(repository, "refs/github/tags/")
    if canonical_branches is None:
        canonical_branches = svn_inventory("branches", snapshot) | {"trunk"}
    if canonical_tags is None:
        canonical_tags = svn_inventory("tags", snapshot)
    plan: List[Dict[str, str]] = []
    behind: List[Dict[str, str]] = []
    for name in sorted(canonical_branches):
        source_ref = f"refs/candidate/heads/{name}"
        destination_ref = f"refs/heads/{name}"
        candidate = candidate_heads.get(source_ref)
        if candidate is None:
            canonical_url = (
                f"{SVN_ROOT}/trunk"
                if name == "trunk"
                else f"{SVN_ROOT}/branches/{name}"
            )
            behind.append(
                {
                    "kind": "branch",
                    "name": name,
                    "candidate_revision": "missing",
                    "state": "source-lag",
                    "canonical_revision": str(
                        svn_latest_revision(canonical_url, snapshot)
                    ),
                    "first_missing_revision": str(
                        svn_first_revision_after(
                            canonical_url,
                            0,
                            snapshot,
                            stop_on_copy=name != "trunk",
                        )
                    ),
                }
            )
            continue
        candidate_commit = parse_commit(repository, candidate)
        if candidate != ref_commit(repository, source_ref):
            raise MirrorError(f"Candidate branch {name} does not point directly to a commit.")
        expected_path = "trunk" if name == "trunk" else f"branches/{name}"
        if candidate_commit["svn_path"] != expected_path:
            raise MirrorError(f"Candidate branch {name} does not map to {expected_path}.")
        latest = svn_latest_revision(str(candidate_commit["svn_url"]), snapshot)
        if int(candidate_commit["svn_revision"]) > latest:
            fresh_latest = svn_latest_revision(str(candidate_commit["svn_url"]))
            if int(candidate_commit["svn_revision"]) > fresh_latest:
                raise MirrorError(
                    f"Candidate branch {name} claims r{candidate_commit['svn_revision']}, "
                    f"newer than canonical Subversion r{fresh_latest}."
                )
            behind.append(
                {
                    "kind": "branch",
                    "name": name,
                    "candidate_revision": str(candidate_commit["svn_revision"]),
                    "canonical_revision": str(fresh_latest),
                    "state": "snapshot-advanced",
                }
            )
            continue
        current = github_heads.get(f"refs/github/heads/{name}")
        if current and not is_ancestor(repository, current, candidate):
            raise MirrorError(f"GitHub branch {name} is not an ancestor of the candidate tip.")
        if int(candidate_commit["svn_revision"]) < latest:
            behind.append(
                {
                    "kind": "branch",
                    "name": name,
                    "candidate_revision": str(candidate_commit["svn_revision"]),
                    "canonical_revision": str(latest),
                    "first_missing_revision": str(
                        svn_first_revision_after(
                            str(candidate_commit["svn_url"]),
                            int(candidate_commit["svn_revision"]),
                            snapshot,
                        )
                    ),
                    "state": "source-lag",
                }
            )
            continue
        if current == candidate:
            continue
        plan.append(
            {
                "action": "update" if current else "create",
                "kind": "branch",
                "name": name,
                "source_ref": source_ref,
                "destination_ref": destination_ref,
                "old": current or "",
                "new": candidate,
                "svn_revision": str(candidate_commit["svn_revision"]),
            }
        )

    candidate_head_names = {
        ref.removeprefix("refs/candidate/heads/") for ref in candidate_heads
    }
    github_head_names = {
        ref.removeprefix("refs/github/heads/") for ref in github_heads
    }
    for name in sorted(candidate_head_names | github_head_names):
        if name in canonical_branches:
            continue
        candidate_ref = f"refs/candidate/heads/{name}"
        github_ref = f"refs/github/heads/{name}"
        candidate = candidate_heads.get(candidate_ref)
        current = github_heads.get(github_ref)
        candidate_path = candidate and canonical_mirror_ref_path(
            repository, "branch", name, str(candidate), candidate_ref
        )
        current_path = current and canonical_mirror_ref_path(
            repository, "branch", name, str(current), github_ref
        )
        if not candidate_path and not current_path:
            continue
        if (candidate and not candidate_path) or (current and not current_path):
            raise MirrorError(
                f"Canonical classification for deleted branch {name} differs between sources."
            )
        if candidate and current and candidate != current:
            raise MirrorError(
                f"Deleted branch {name} differs between the native Git candidate and GitHub."
            )
        object_id = current or candidate
        local_ref = github_ref if current else candidate_ref
        deletion = deleted_ref_item(
            repository,
            "branch",
            name,
            str(object_id),
            local_ref,
            snapshot if snapshot is not None else svn_repository_revision(),
        )
        if current:
            plan.append(deletion)

    normalized_tags: Dict[str, str] = {}
    for canonical_name in sorted(canonical_tags):
        name = canonical_tag_name(canonical_name)
        if name in normalized_tags:
            raise MirrorError(
                f"Canonical Subversion tags {normalized_tags[name]} and {canonical_name} "
                f"both map to Git tag {name}."
            )
        normalized_tags[name] = canonical_name

    for github_ref, current in sorted(github_tags.items()):
        name = github_ref.removeprefix("refs/github/tags/")
        if name not in normalized_tags:
            continue
        candidate = candidate_tags.get(f"refs/candidate/tags/{name}")
        if candidate is not None and candidate != current:
            raise MirrorError(f"Existing GitHub tag {name} differs from the candidate mirror.")

    for canonical_name in sorted(canonical_tags):
        name = canonical_tag_name(canonical_name)
        source_ref = f"refs/candidate/tags/{name}"
        candidate = candidate_tags.get(source_ref)
        if candidate is None:
            canonical_url = f"{SVN_ROOT}/tags/{canonical_name}"
            behind.append(
                {
                    "kind": "tag",
                    "name": name,
                    "candidate_revision": "missing",
                    "state": "source-lag",
                    "canonical_revision": str(
                        svn_latest_revision(canonical_url, snapshot)
                    ),
                    "first_missing_revision": str(
                        svn_first_revision_after(
                            canonical_url,
                            0,
                            snapshot,
                            stop_on_copy=True,
                        )
                    ),
                }
            )
            continue
        github_has_tag = f"refs/github/tags/{name}" in github_tags
        if github_has_tag:
            continue
        tag_commit = ref_commit(repository, source_ref)
        commit = parse_commit(repository, tag_commit)
        expected_path = f"tags/{canonical_name}"
        if commit["svn_path"] != expected_path:
            raise MirrorError(f"Candidate tag {name} does not map to {expected_path}.")
        if candidate != tag_commit:
            raise MirrorError(f"New candidate tag {name} must be a lightweight Git tag.")
        latest = svn_latest_revision(str(commit["svn_url"]), snapshot)
        if commit["svn_revision"] != latest:
            raise MirrorError(
                f"Candidate tag {name} stops at r{commit['svn_revision']}, "
                f"but canonical Subversion is at r{latest}."
            )
        plan.append(
            {
                "action": "create",
                "kind": "tag",
                "name": name,
                "source_ref": source_ref,
                "destination_ref": f"refs/tags/{name}",
                "old": "",
                "new": candidate,
                "canonical_name": canonical_name,
                "svn_revision": str(commit["svn_revision"]),
            }
        )

    candidate_tag_names = {
        ref.removeprefix("refs/candidate/tags/") for ref in candidate_tags
    }
    github_tag_names = {
        ref.removeprefix("refs/github/tags/") for ref in github_tags
    }
    for name in sorted(candidate_tag_names | github_tag_names):
        if name in normalized_tags:
            continue
        candidate_ref = f"refs/candidate/tags/{name}"
        github_ref = f"refs/github/tags/{name}"
        candidate = candidate_tags.get(candidate_ref)
        current = github_tags.get(github_ref)
        candidate_path = candidate and canonical_mirror_ref_path(
            repository, "tag", name, str(candidate), candidate_ref
        )
        current_path = current and canonical_mirror_ref_path(
            repository, "tag", name, str(current), github_ref
        )
        mapped_path = str(current_path or candidate_path or "")
        mapped_canonical_name = mapped_path.removeprefix("tags/")
        if mapped_canonical_name in canonical_tags:
            continue
        if not candidate_path and not current_path:
            continue
        if (candidate and not candidate_path) or (current and not current_path):
            raise MirrorError(
                f"Canonical classification for deleted tag {name} differs between sources."
            )
        if candidate and current and candidate != current:
            raise MirrorError(
                f"Deleted tag {name} differs between the native Git candidate and GitHub."
            )
        object_id = current or candidate
        local_ref = github_ref if current else candidate_ref
        deletion = deleted_ref_item(
            repository,
            "tag",
            name,
            str(object_id),
            local_ref,
            snapshot if snapshot is not None else svn_repository_revision(),
        )
        if current:
            plan.append(deletion)
    if behind:
        return [], behind
    return plan, []


def validate_copy_origin(
    repository: Path,
    object_id: str,
    expected_path: str,
    entry: ET.Element,
    validated_objects: Set[str],
    github_tips: Set[str],
) -> None:
    prefix = f"/{expected_path}"
    created_path = next(
        (
            path
            for path in entry.findall("./paths/path")
            if (path.text or "") == prefix
            and path.attrib.get("action") == "A"
            and path.attrib.get("kind") == "dir"
        ),
        None,
    )
    if created_path is None:
        raise MirrorError(f"{prefix} was not created by a canonical Subversion directory copy.")
    copyfrom_path = created_path.attrib.get("copyfrom-path", "")
    copyfrom_revision = created_path.attrib.get("copyfrom-rev", "")
    if not copyfrom_path or not copyfrom_revision:
        raise MirrorError(f"{prefix} has no canonical Subversion copy origin.")
    if copyfrom_path == "/trunk":
        source_ref = "refs/candidate/heads/trunk"
    elif copyfrom_path.startswith("/branches/"):
        source_ref = f"refs/candidate/heads/{copyfrom_path.removeprefix('/branches/')}"
    else:
        raise MirrorError(f"{prefix} has unsupported copy origin {copyfrom_path}.")
    parents = list(parse_commit(repository, object_id)["parents"])
    if len(parents) != 1:
        raise MirrorError(f"Candidate copy commit {object_id} must have exactly one Git parent.")
    source_revision = svn_last_changed_revision(copyfrom_path, int(copyfrom_revision))
    parent = parse_commit(repository, parents[0])
    if (
        parent["svn_path"] != copyfrom_path.removeprefix("/")
        or parent["svn_revision"] != source_revision
    ):
        raise MirrorError(
            f"Candidate copy commit {object_id} does not descend from the last changed "
            f"revision r{source_revision} of {copyfrom_path}@{copyfrom_revision}."
        )
    reachable_from_source = is_ancestor(repository, parents[0], source_ref) or any(
        is_ancestor(repository, parents[0], tip) for tip in github_tips
    )
    if not reachable_from_source:
        raise MirrorError(
            f"Candidate copy parent {parents[0]} is absent from source ref {source_ref} "
            "and the validated GitHub history."
        )
    if parents[0] not in validated_objects and not any(
        is_ancestor(repository, parents[0], tip) for tip in github_tips
    ):
        raise MirrorError(
            f"Candidate copy parent {parents[0]} is neither already reachable from GitHub "
            "nor independently validated in this run."
        )


def validate_scheduled_update_scope(
    commits: Dict[str, Tuple[Dict[str, object], str]],
    svn_logs: Dict[int, ET.Element],
) -> None:
    for commit, expected_path in commits.values():
        revision = int(commit["svn_revision"])
        entry = svn_logs[revision]
        prefix = f"/{expected_path}/"
        for changed_path in entry.findall("./paths/path"):
            path = changed_path.text or ""
            if not path.startswith(prefix):
                continue
            relative = path.removeprefix(prefix)
            if any(
                relative == protected
                or relative.startswith(f"{protected}/")
                for protected in SCHEDULED_PROTECTED_PATHS
            ):
                raise MirrorError(
                    f"Scheduled synchronization refuses automation update {path} at r{revision}; "
                    "use the reviewed local manifest process."
                )


def validate_deletion_item(
    repository: Path,
    item: Dict[str, str],
    snapshot: int,
    canonical_branches: Set[str],
    canonical_tags: Set[str],
) -> None:
    name = item["name"]
    if item["kind"] == "branch":
        if name in canonical_branches:
            raise MirrorError(f"Refusing to delete current canonical branch {name}.")
        candidate_ref = f"refs/candidate/heads/{name}"
        github_ref = f"refs/github/heads/{name}"
    else:
        canonical_name = item["canonical_name"]
        if canonical_name in canonical_tags:
            raise MirrorError(f"Refusing to delete current canonical tag {canonical_name}.")
        candidate_ref = f"refs/candidate/tags/{name}"
        github_ref = f"refs/github/tags/{name}"
    candidate_refs = git_refs(repository, candidate_ref)
    if candidate_ref in candidate_refs:
        if candidate_refs[candidate_ref] != item["old"]:
            raise MirrorError(
                f"Native Git candidate {item['kind']} {name} differs before deletion."
            )
        if not canonical_mirror_ref_path(
            repository, item["kind"], name, item["old"], candidate_ref
        ):
            raise MirrorError(
                f"Native Git candidate {item['kind']} {name} is not the canonical mirror ref."
            )
    github_refs = git_refs(repository, github_ref)
    if github_refs.get(github_ref) != item["old"]:
        raise MirrorError(f"GitHub {item['kind']} {name} changed before deletion validation.")
    expected = deleted_ref_item(
        repository, item["kind"], name, item["old"], github_ref, snapshot
    )
    if expected != item:
        raise MirrorError(
            f"Deletion plan for {item['kind']} {name} does not match canonical Subversion."
        )


def validate_plan(
    repository: Path,
    plan: List[Dict[str, str]],
    snapshot: Optional[int] = None,
    canonical_branches: Optional[Set[str]] = None,
    canonical_tags: Optional[Set[str]] = None,
    scheduled: bool = False,
) -> List[Dict[str, str]]:
    commits: Dict[str, Tuple[Dict[str, object], str]] = {}
    branch_commits: Dict[str, List[str]] = {}
    first_required_revisions: List[int] = []
    for item in plan:
        if item.get("action") == "delete":
            if snapshot is None or canonical_branches is None or canonical_tags is None:
                raise MirrorError(
                    "Deletion validation requires a pinned canonical Subversion inventory."
                )
            validate_deletion_item(
                repository, item, snapshot, canonical_branches, canonical_tags
            )
            continue
        tip = ref_commit(repository, item["source_ref"])
        parsed_tip = parse_commit(repository, tip)
        if item["kind"] == "branch":
            expected_path = "trunk" if item["name"] == "trunk" else f"branches/{item['name']}"
        else:
            expected_path = f"tags/{item['canonical_name']}"
        commits[tip] = (parsed_tip, expected_path)
        if item["kind"] == "branch":
            if item["old"]:
                old_commit = parse_commit(repository, item["old"])
                if old_commit["svn_path"] != expected_path:
                    raise MirrorError(
                        f"Existing GitHub branch {item['name']} does not map to {expected_path}."
                    )
                first_required_revisions.append(int(old_commit["svn_revision"]) + 1)
                object_ids = rev_list(repository, f"{item['old']}..{tip}")
            else:
                object_ids = branch_path_commits(repository, tip, expected_path)
                if not object_ids or object_ids[-1] != tip:
                    raise MirrorError(
                        f"New candidate branch {item['name']} has no complete canonical branch history."
                    )
                branch_range = rev_list(repository, f"{object_ids[0]}^..{tip}")
                if branch_range != object_ids:
                    raise MirrorError(
                        f"New candidate branch {item['name']} contains history from another path."
                    )
                first_required_revisions.append(
                    int(parse_commit(repository, object_ids[0])["svn_revision"])
                )
            branch_commits[item["name"]] = object_ids
            for object_id in object_ids:
                commits[object_id] = (parse_commit(repository, object_id), expected_path)
        else:
            first_required_revisions.append(int(parsed_tip["svn_revision"]))

    if not commits:
        return []
    revisions = [int(commit["svn_revision"]) for commit, _ in commits.values()]
    svn_logs = load_svn_log(min(first_required_revisions), max(revisions), snapshot)
    if scheduled:
        validate_scheduled_update_scope(commits, svn_logs)
    github_heads = git_refs(repository, "refs/github/heads/")
    github_tags = git_refs(repository, "refs/github/tags/")
    if canonical_branches is None:
        canonical_branches = {
            item["name"] for item in plan if item["kind"] == "branch"
        }
    if canonical_tags is None:
        canonical_tags = {
            item["canonical_name"] for item in plan if item["kind"] == "tag"
        }
    github_tips = {
        github_heads[ref]
        for name in canonical_branches
        for ref in [f"refs/github/heads/{name}"]
        if ref in github_heads
    } | {
        github_tags[ref]
        for name in canonical_tags
        for ref in [f"refs/github/tags/{canonical_tag_name(name)}"]
        if ref in github_tags
    } | {
        item["old"] for item in plan if item.get("action") == "delete"
    }
    identities: Dict[Tuple[str, str, str], Dict[str, str]] = {}
    for object_id, (commit, expected_path) in commits.items():
        identity = validate_commit(commit, svn_logs, expected_path)
        identities[(identity["svn_author"], identity["name"], identity["email"])] = identity
        validate_tree(repository, object_id, commit)
    for item in plan:
        if item.get("action") == "delete":
            continue
        tip = ref_commit(repository, item["source_ref"])
        if item["kind"] == "tag":
            commit = commits[tip][0]
            validate_copy_origin(
                repository,
                tip,
                f"tags/{item['canonical_name']}",
                svn_logs[int(commit["svn_revision"])],
                set(commits),
                github_tips,
            )
            continue
        object_ids = branch_commits[item["name"]]
        branch_range = f"{item['old']}..{tip}" if item["old"] else f"{object_ids[0]}^..{tip}"
        merge_output = git(repository, "rev-list", "--merges", branch_range)
        if merge_output.strip():
            raise MirrorError(f"Candidate branch {item['name']} contains a Git merge commit.")
        expected_path = "trunk" if item["name"] == "trunk" else f"branches/{item['name']}"
        actual_revisions = [int(commits[object_id][0]["svn_revision"]) for object_id in object_ids]
        if item["old"]:
            old_commit = parse_commit(repository, item["old"])
            first_revision = int(old_commit["svn_revision"]) + 1
        else:
            first_revision = actual_revisions[0]
        last_revision = int(commits[tip][0]["svn_revision"])
        prefix = f"/{expected_path}"
        canonical_revisions = sorted(
            revision
            for revision, entry in svn_logs.items()
            if first_revision <= revision <= last_revision
            and any(
                (path.text or "") == prefix or (path.text or "").startswith(f"{prefix}/")
                for path in entry.findall("./paths/path")
            )
        )
        if actual_revisions != canonical_revisions:
            raise MirrorError(
                f"Candidate branch {item['name']} does not contain exactly the canonical "
                f"Subversion revisions from r{first_revision} through r{last_revision}."
            )
        if not item["old"]:
            validate_copy_origin(
                repository,
                object_ids[0],
                expected_path,
                svn_logs[first_revision],
                set(commits),
                github_tips,
            )
    return [identities[key] for key in sorted(identities)]


def build_manifest(
    plan: List[Dict[str, str]],
    identities: List[Dict[str, str]],
    snapshot: int,
    status: str = "validated",
    source_lag: Optional[List[Dict[str, str]]] = None,
) -> Dict[str, object]:
    return {
        "schema": 3,
        "canonical_svn": SVN_ROOT,
        "svn_uuid": SVN_UUID,
        "svn_snapshot": snapshot,
        "status": status,
        "updates": plan,
        "authors": identities,
        "source_lag": source_lag or [],
    }


def write_manifest(manifest: Dict[str, object], path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")


def require_expected_manifest(manifest: Dict[str, object], path: Path) -> None:
    try:
        expected = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as error:
        raise MirrorError(f"Expected manifest does not exist: {path}") from error
    if expected != manifest:
        raise MirrorError("The freshly validated manifest differs from the approved manifest.")


def push(repository: Path, plan: List[Dict[str, str]], url: str, dry_run: bool) -> None:
    if not plan:
        return
    current_refs = remote_refs(url)
    for item in plan:
        current = current_refs.get(item["destination_ref"], "")
        if current != item["old"]:
            raise MirrorError(
                f"GitHub ref {item['destination_ref']} changed after validation: "
                f"expected {item['old'] or '(missing)'}, found {current or '(missing)'}."
            )
    command = ["git", "push", "--atomic", "--porcelain"]
    command.extend(
        f"--force-with-lease={item['destination_ref']}:{item['old']}"
        for item in plan
        if item.get("action") == "delete"
    )
    if dry_run:
        command.append("--dry-run")
    command.append(url)
    command.extend(
        (
            f":{item['destination_ref']}"
            if item.get("action") == "delete"
            else f"{item['new']}:{item['destination_ref']}"
        )
        for item in plan
    )
    run(command, cwd=repository, capture=False)
    if dry_run:
        return
    resulting_refs = remote_refs(url)
    for item in plan:
        if item.get("action") == "delete":
            valid = item["destination_ref"] not in resulting_refs
        else:
            valid = resulting_refs.get(item["destination_ref"]) == item["new"]
        if not valid:
            raise MirrorError(f"Post-push verification failed for {item['destination_ref']}.")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--candidate-url", default="git://bbpress.git.wordpress.org/")
    parser.add_argument("--github-fetch-url", default="https://github.com/bbpress/bbPress.git")
    parser.add_argument("--github-push-url", default="git@github.com:bbpress/bbPress.git")
    parser.add_argument("--manifest", type=Path, required=True)
    parser.add_argument("--expected-manifest", type=Path)
    parser.add_argument(
        "--mode",
        choices=("validate", "dry-run", "push", "scheduled-push"),
        default="validate",
    )
    parser.add_argument("--repository", type=Path)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    cleanup = None
    try:
        if args.mode == "push" and args.expected_manifest is None:
            raise MirrorError("Push mode requires --expected-manifest.")
        snapshot = svn_repository_revision()
        canonical_branches = svn_inventory("branches", snapshot) | {"trunk"}
        canonical_tags = svn_inventory("tags", snapshot)
        if sources_already_match(
            args.candidate_url,
            args.github_fetch_url,
            canonical_branches,
            canonical_tags,
            snapshot,
        ):
            manifest = build_manifest([], [], snapshot, status="up-to-date")
            if args.expected_manifest:
                require_expected_manifest(manifest, args.expected_manifest)
            write_manifest(manifest, args.manifest)
            if args.mode == "dry-run":
                remote_refs(args.github_push_url)
                print("GitHub SSH authentication succeeded; no push is needed.")
            print("GitHub already matches the candidate refs; no push is needed.")
            return 0
        if args.repository:
            repository = args.repository
        else:
            cleanup = tempfile.TemporaryDirectory(prefix="bbpress-github-mirror-")
            repository = Path(cleanup.name) / "mirror.git"
        fetch_sources(repository, args.candidate_url, args.github_fetch_url)
        plan, behind = create_plan(
            repository,
            snapshot,
            canonical_branches,
            canonical_tags,
        )
        if behind:
            stale = stale_source_lag(behind, snapshot)
            if stale:
                details = ", ".join(
                    f"{item['kind']} {item['name']} at {item['candidate_revision']} "
                    f"versus canonical r{item['canonical_revision']}"
                    for item in stale
                )
                raise MirrorError(
                    f"The native Git candidate has been behind for more than 30 minutes: {details}."
                )
            manifest = build_manifest(
                [], [], snapshot, status="candidate-behind", source_lag=behind
            )
            if args.expected_manifest:
                require_expected_manifest(manifest, args.expected_manifest)
            write_manifest(manifest, args.manifest)
            if args.mode == "push":
                raise MirrorError(
                    "Approved push mode requires a fully validated candidate; "
                    "the native Git candidate is behind canonical Subversion."
                )
            print("The native Git candidate is behind canonical Subversion; no push is needed.")
            return 0
        identities = validate_plan(
            repository,
            plan,
            snapshot,
            canonical_branches,
            canonical_tags,
            scheduled=args.mode == "scheduled-push",
        )
        manifest = build_manifest(plan, identities, snapshot)
        if args.expected_manifest:
            require_expected_manifest(manifest, args.expected_manifest)
        write_manifest(manifest, args.manifest)
        if plan:
            for item in plan:
                old = item["old"] or "(new)"
                new = item["new"] or "(deleted)"
                print(f"{item['kind']} {item['name']}: {old} -> {new}")
        else:
            print("GitHub already matches the verified canonical mirror.")
        if args.mode != "validate":
            push(repository, plan, args.github_push_url, args.mode == "dry-run")
    except (
        MirrorError,
        OSError,
        ET.ParseError,
        json.JSONDecodeError,
        KeyError,
        UnicodeError,
        ValueError,
    ) as error:
        print(f"mirror validation failed: {error}", file=sys.stderr)
        return 1
    finally:
        if cleanup:
            cleanup.cleanup()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
