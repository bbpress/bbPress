#!/usr/bin/env python3
"""Report bbPress @since annotations needing Subversion revision review.

The revision is where the annotation line last changed. It is a candidate,
not proof that the documented behavior originated in that revision.

Use --min-revision with --check after a Subversion commit to catch new
annotations before updating their revision references in a follow-up commit.
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
GIT_BLAME_HEADER = re.compile(r"^([0-9a-f]{40}) \d+ (\d+)(?: \d+)?$")
FIELDS = (
    "path", "line", "version", "status", "candidate_revision",
    "source_commit", "annotation", "action",
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


def latest_revision(root, vcs):
    if vcs == "svn":
        info = ET.fromstring(command(root, "svn", "info", "--recursive", "--xml", "."))
        revisions = [
            int(commit.attrib["revision"])
            for commit in info.findall(".//commit")
        ]
        return str(max(revisions)) if revisions else ""
    commit = command(root, "git", "rev-parse", "HEAD").strip()
    return git_svn_revision(root, commit, {})


def changed_lines(root, vcs, revision, path):
    if vcs == "svn":
        diff = command(root, "svn", "diff", "-c", revision, "--", path)
    else:
        diff = command(root, "git", "show", "--format=", "--unified=0", "HEAD", "--", path)
    added = set()
    removed_annotation = False
    for line in diff.splitlines():
        if line.startswith("+") and not line.startswith("+++"):
            added.add(line[1:])
        elif line.startswith("-") and not line.startswith("---") and SINCE.search(line):
            removed_annotation = True
    return added, removed_annotation


def heal(root, vcs, rows, revision, dry_run):
    for path in sorted({row["path"] for row in rows}):
        file_rows = [row for row in rows if row["path"] == path]
        dirty = command(root, "svn", "status", "--", path) if vcs == "svn" else command(root, "git", "status", "--porcelain", "--", path)
        if dirty:
            for row in file_rows:
                row["action"] = "skipped: file has local changes"
            continue
        added, removed_annotation = changed_lines(root, vcs, revision, path)
        lines = (root / path).read_text(encoding="utf-8").splitlines(keepends=True)
        for row in file_rows:
            if row["status"] != "missing" or row["candidate_revision"] != revision:
                row["action"] = "skipped: not a missing annotation from latest revision"
            elif removed_annotation or lines[row["line"] - 1].rstrip("\r\n") not in added:
                row["action"] = "skipped: annotation may have been changed or moved"
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
    parser.add_argument("--format", choices=("csv", "json"), default="csv")
    parser.add_argument("--check", action="store_true", help="exit nonzero when the report contains any rows")
    parser.add_argument("--fix", action="store_true", help="add revision references for annotations introduced in the latest changeset")
    parser.add_argument("--dry-run", action="store_true", help="preview --fix without writing files")
    args = parser.parse_args()
    if args.dry_run and not args.fix:
        parser.error("--dry-run requires --fix")
    if args.fix and args.check:
        parser.error("--fix and --check cannot be combined")
    if args.fix and args.min_revision:
        parser.error("--fix always targets the latest changeset; omit --min-revision")
    root = args.root.resolve()
    if not (root / "src").is_dir():
        parser.error("checkout has no src/ directory")
    vcs = args.vcs
    if vcs == "auto":
        vcs = "svn" if (root / ".svn").exists() else "git" if (root / ".git").exists() else ""
    if not vcs:
        parser.error("checkout must have .svn or .git metadata")
    try:
        revision = latest_revision(root, vcs) if args.fix else ""
        if args.fix and not revision:
            parser.error("latest commit has no Subversion revision")
        minimum = int(revision) if args.fix else args.min_revision
        rows = audit(root, vcs, args.version, minimum)
        if args.fix:
            heal(root, vcs, rows, revision, args.dry_run)
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
