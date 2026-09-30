#!/usr/bin/env python3
"""Report or fix bbPress @since annotations needing Subversion revisions.

Fix mode is a pre-commit operation. It inspects uncommitted annotation lines,
reads the current revision from the remote canonical Subversion URL, and uses
the next revision in the patch. Verify the assigned revision after committing
in case another changeset landed between the remote check and the commit.

Audit mode uses blame history to report committed annotations. Use
--min-revision with --check after a Subversion commit to verify the result.
"""

import argparse
import csv
import json
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path


SINCE = re.compile(r"@since\s+(\S+)")
STANDARD_REVISION = re.compile(r"\(r\d+\)")
ANY_REVISION = re.compile(r"\br\d+\b")
GIT_SVN_REVISION = re.compile(r"git-svn-id:\s+\S+@(\d+)\b")
GIT_SVN_ID = re.compile(r"git-svn-id:\s+(\S+)@(\d+)\b")
GIT_BLAME_HEADER = re.compile(r"^([0-9a-f]{40}) \d+ (\d+)(?: \d+)?$")
DIFF_HUNK = re.compile(r"^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@")
FIELDS = (
    "path", "line", "version", "status", "target_url", "remote_revision",
    "candidate_revision", "source_commit", "annotation", "action",
)


def command(root, *args):
    result = subprocess.run(
        args, cwd=root, text=True, stdout=subprocess.PIPE,
        stderr=subprocess.PIPE, check=False,
    )
    if result.returncode:
        raise RuntimeError("{}: {}".format(" ".join(args), result.stderr.strip()))
    return result.stdout


def git_blame(root, path):
    output = command(root, "git", "blame", "--line-porcelain", "--", path)
    revisions = {}
    commit = None
    line_number = None
    for line in output.splitlines():
        header = GIT_BLAME_HEADER.match(line)
        if header:
            commit, line_number = header.group(1), int(header.group(2))
        elif line.startswith("\t") and line_number is not None:
            revisions[line_number] = commit
            line_number = None
    return revisions


def svn_blame(root, path):
    output = command(root, "svn", "blame", "--xml", "--", path)
    entries = ET.fromstring(output).findall("./target/entry")
    return {
        int(entry.attrib["line-number"]): entry.find("commit").attrib["revision"]
        for entry in entries if entry.find("commit") is not None
    }


def git_svn_revision(root, commit, cache):
    if not commit or commit == "0" * 40:
        return ""
    if commit not in cache:
        message = command(root, "git", "log", "-1", "--format=%B", commit)
        match = GIT_SVN_REVISION.search(message)
        cache[commit] = match.group(1) if match else ""
    return cache[commit]


def annotations(root, version_filter):
    by_file = {}
    for file in sorted((root / "src").rglob("*.php")):
        rows = []
        for number, line in enumerate(file.read_text(encoding="utf-8", errors="replace").splitlines(), 1):
            match = SINCE.search(line)
            if not match or STANDARD_REVISION.search(line):
                continue
            version = match.group(1)
            if version_filter and version != version_filter:
                continue
            rows.append({
                "path": str(file.relative_to(root)),
                "line": number,
                "version": version,
                "status": "nonstandard" if ANY_REVISION.search(line) else "missing",
                "annotation": line.strip(),
            })
        if rows:
            by_file[str(file.relative_to(root))] = rows
    return by_file


def audit(root, vcs, version_filter, min_revision):
    cache = {}
    rows = []
    for path, file_rows in annotations(root, version_filter).items():
        blame = svn_blame(root, path) if vcs == "svn" else git_blame(root, path)
        for row in file_rows:
            source = blame.get(row["line"], "")
            revision = str(source) if vcs == "svn" else git_svn_revision(root, source, cache)
            if min_revision and (not revision or int(revision) < min_revision):
                continue
            row["candidate_revision"] = revision
            row["source_commit"] = "" if vcs == "svn" else source
            rows.append(row)
    return rows


def svn_target_url(root, vcs, override):
    if override:
        return override.rstrip("/")
    if vcs == "svn":
        return command(root, "svn", "info", "--show-item", "url", ".").strip()
    message = command(
        root, "git", "log", "-1", "--format=%B", "--grep=git-svn-id:",
    )
    match = GIT_SVN_ID.search(message)
    if not match:
        raise RuntimeError(
            "cannot infer the canonical Subversion URL from Git history; "
            "pass --svn-url"
        )
    return match.group(1)


def next_remote_revision(root, target_url):
    current = command(
        root, "svn", "info", "--show-item", "revision", target_url,
    ).strip()
    if not current.isdigit():
        raise RuntimeError("remote Subversion revision is not numeric: {}".format(current))
    return current, str(int(current) + 1)


def diff_added_lines(diff):
    added = set()
    new_line = None
    for line in diff.splitlines():
        hunk = DIFF_HUNK.match(line)
        if hunk:
            new_line = int(hunk.group(1))
        elif new_line is None or line.startswith("\\"):
            continue
        elif line.startswith("+") and not line.startswith("+++"):
            added.add(new_line)
            new_line += 1
        elif line.startswith("-") and not line.startswith("---"):
            continue
        else:
            new_line += 1
    return added


def working_added_lines(root, vcs, path):
    if vcs == "svn":
        status = command(root, "svn", "status", "--", path)
        if status.startswith("?"):
            return set(range(1, len((root / path).read_text().splitlines()) + 1))
        diff = command(root, "svn", "diff", "--", path)
    else:
        status = command(root, "git", "status", "--porcelain", "--", path)
        if status.startswith("??"):
            return set(range(1, len((root / path).read_text().splitlines()) + 1))
        diff = command(root, "git", "diff", "HEAD", "--unified=0", "--", path)
    return diff_added_lines(diff)


def precommit_rows(root, vcs, version_filter, target_url, remote_revision, candidate_revision):
    rows = []
    for path, file_rows in annotations(root, version_filter).items():
        added_lines = working_added_lines(root, vcs, path)
        for row in file_rows:
            if row["line"] not in added_lines:
                continue
            row["target_url"] = target_url
            row["remote_revision"] = remote_revision
            row["candidate_revision"] = candidate_revision
            row["source_commit"] = ""
            rows.append(row)
    return rows


def heal(root, rows, revision, dry_run):
    for path in sorted({row["path"] for row in rows}):
        file_rows = [row for row in rows if row["path"] == path]
        lines = (root / path).read_text(encoding="utf-8").splitlines(keepends=True)
        for row in file_rows:
            if row["status"] != "missing":
                row["action"] = "skipped: annotation has a nonstandard revision"
            else:
                index = row["line"] - 1
                lines[index] = re.sub(
                    r"(@since\s+\S+)(\s+bbPress\b)?",
                    lambda match: match.group(1) + (match.group(2) or "") +
                    " (r{})".format(revision),
                    lines[index], count=1,
                )
                row["action"] = "would fix" if dry_run else "fixed"
        if not dry_run and any(row["action"] == "fixed" for row in file_rows):
            (root / path).write_text("".join(lines), encoding="utf-8")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path.cwd(), help="bbPress checkout root")
    parser.add_argument("--vcs", choices=("auto", "git", "svn"), default="auto")
    parser.add_argument("--version", help="only report this @since version")
    parser.add_argument("--min-revision", type=int, help="only annotations last changed at or after this revision")
    parser.add_argument("--svn-url", help="canonical Subversion target URL for pre-commit fix mode")
    parser.add_argument("--format", choices=("csv", "json"), default="csv")
    parser.add_argument("--check", action="store_true", help="exit nonzero when the report contains any rows")
    parser.add_argument("--fix", action="store_true", help="add the next remote Subversion revision to uncommitted annotations")
    parser.add_argument("--dry-run", action="store_true", help="preview --fix without writing files")
    args = parser.parse_args()
    if args.dry_run and not args.fix:
        parser.error("--dry-run requires --fix")
    if args.fix and args.check:
        parser.error("--fix and --check cannot be combined")
    if args.fix and args.min_revision:
        parser.error("--fix targets uncommitted changes; omit --min-revision")
    if args.svn_url and not args.fix:
        parser.error("--svn-url requires --fix")
    root = args.root.resolve()
    if not (root / "src").is_dir():
        parser.error("checkout has no src/ directory")
    vcs = args.vcs
    if vcs == "auto":
        vcs = "svn" if (root / ".svn").exists() else "git" if (root / ".git").exists() else ""
    if not vcs:
        parser.error("checkout must have .svn or .git metadata")
    try:
        if args.fix:
            target_url = svn_target_url(root, vcs, args.svn_url)
            remote_revision, revision = next_remote_revision(root, target_url)
            rows = precommit_rows(
                root, vcs, args.version, target_url, remote_revision, revision,
            )
            heal(root, rows, revision, args.dry_run)
        else:
            rows = audit(root, vcs, args.version, args.min_revision)
    except RuntimeError as error:
        parser.exit(1, "{}\n".format(error))
    if args.format == "json":
        json.dump(rows, sys.stdout, indent=2)
        print()
    else:
        writer = csv.DictWriter(sys.stdout, FIELDS)
        writer.writeheader()
        writer.writerows(rows)
    if args.check and rows:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
