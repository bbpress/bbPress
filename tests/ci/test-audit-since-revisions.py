#!/usr/bin/env python3
"""Exercise safe auto-healing against a small local Git mirror fixture."""

import json
import subprocess
import tempfile
from pathlib import Path


HELPER = Path(__file__).with_name("audit-since-revisions.py")


def run(root, *args):
    return subprocess.run(args, cwd=root, text=True, stdout=subprocess.PIPE,
                          stderr=subprocess.PIPE, check=True).stdout


with tempfile.TemporaryDirectory(prefix="bbp-since-audit-") as directory:
    root = Path(directory)
    run(root, "git", "init", "-q")
    run(root, "git", "config", "user.name", "bbPress test")
    run(root, "git", "config", "user.email", "test@example.invalid")
    run(root, "git", "config", "commit.gpgsign", "false")
    source = root / "src"
    source.mkdir()
    (source / "new.php").write_text("<?php\n")
    (source / "changed.php").write_text("<?php\n * @since 2.6.0 bbPress\n")
    (source / "dirty.php").write_text("<?php\n")
    (source / "plain.php").write_text("<?php\n")
    run(root, "git", "add", "src")
    run(root, "git", "commit", "-qm", "Initial.\n\ngit-svn-id: https://example.invalid/trunk@100 1234")

    (source / "new.php").write_text("<?php\n * @since 2.6.19 bbPress Added behavior.\n")
    (source / "changed.php").write_text("<?php\n * @since 2.6.19 bbPress\n")
    (source / "dirty.php").write_text("<?php\n * @since 2.6.19 bbPress\n")
    (source / "plain.php").write_text("<?php\n * @since 2.6.19\n")
    run(root, "git", "add", "src")
    run(root, "git", "commit", "-qm", "Update.\n\ngit-svn-id: https://example.invalid/trunk@101 1234")
    (source / "dirty.php").write_text("<?php\n * @since 2.6.19 bbPress\n// Local edit.\n")

    output = run(root, "python3", str(HELPER), "--fix", "--dry-run", "--format", "json")
    actions = {row["path"]: row["action"] for row in json.loads(output)}
    assert actions == {
        "src/new.php": "would fix",
        "src/changed.php": "skipped: annotation may have been changed or moved",
        "src/dirty.php": "skipped: file has local changes",
        "src/plain.php": "would fix",
    }, actions
    output = run(root, "python3", str(HELPER), "--fix", "--format", "json")
    assert any(row["action"] == "fixed" for row in json.loads(output))
    assert "@since 2.6.19 bbPress (r101) Added behavior." in (source / "new.php").read_text()
    assert "@since 2.6.19 bbPress\n" in (source / "changed.php").read_text()
    assert "@since 2.6.19 (r101)" in (source / "plain.php").read_text()
    assert "@since 2.6.19 bbPress\n" in (source / "dirty.php").read_text()

print("Safe auto-healing fixture passed.")

with tempfile.TemporaryDirectory(prefix="bbp-since-svn-") as directory:
    root = Path(directory)
    repository = root / "repo"
    run(root, "svnadmin", "create", str(repository))
    url = repository.as_uri() + "/trunk"
    run(root, "svn", "mkdir", url, "-m", "Create trunk.")
    checkout = root / "checkout"
    run(root, "svn", "checkout", url, str(checkout))
    source = checkout / "src"
    source.mkdir()
    file = source / "example.php"
    file.write_text("<?php\n")
    run(checkout, "svn", "add", "src")
    run(checkout, "svn", "commit", "-m", "Add source file.")
    file.write_text("<?php\n * @since 2.6.19 bbPress Added behavior.\n")
    run(checkout, "svn", "commit", "-m", "Add annotation.")
    output = run(checkout, "python3", str(HELPER), "--fix", "--dry-run", "--format", "json")
    rows = json.loads(output)
    assert len(rows) == 1 and rows[0]["candidate_revision"] == "3", rows
    assert rows[0]["action"] == "would fix", rows
    run(checkout, "python3", str(HELPER), "--fix")
    assert "@since 2.6.19 bbPress (r3)" in file.read_text()

print("Subversion revision fixture passed.")
