#!/usr/bin/env python3
"""Exercise pre-commit @since revision fixes against local repositories."""

import json
import subprocess
import tempfile
from pathlib import Path


HELPER = Path(__file__).with_name("audit-since-revisions.py")


def run(root, *args):
    return subprocess.run(
        args, cwd=root, text=True, stdout=subprocess.PIPE,
        stderr=subprocess.PIPE, check=True,
    ).stdout


with tempfile.TemporaryDirectory(prefix="bbp-since-audit-") as directory:
    root = Path(directory)
    repository = root / "repo"
    run(root, "svnadmin", "create", str(repository))
    svn_url = repository.as_uri() + "/trunk"
    run(root, "svn", "mkdir", svn_url, "-m", "Create trunk.")

    mirror = root / "mirror"
    mirror.mkdir()
    run(mirror, "git", "init", "-q")
    run(mirror, "git", "config", "user.name", "bbPress test")
    run(mirror, "git", "config", "user.email", "test@example.invalid")
    run(mirror, "git", "config", "commit.gpgsign", "false")
    source = mirror / "src"
    source.mkdir()
    (source / "new.php").write_text("<?php\n")
    (source / "changed.php").write_text("<?php\n")
    (source / "plain.php").write_text("<?php\n")
    (source / "committed.php").write_text(
        "<?php\n * @since 2.6.18 bbPress Existing omission.\n"
    )
    run(mirror, "git", "add", "src")
    run(
        mirror, "git", "commit", "-qm",
        "Initial.\n\ngit-svn-id: {}@1 test".format(svn_url),
    )

    (source / "new.php").write_text(
        "<?php\n * @since 2.6.19 bbPress Added behavior.\n"
    )
    (source / "changed.php").write_text(
        "<?php\n * @since 2.6.19 bbPress\n"
    )
    (source / "plain.php").write_text("<?php\n * @since 2.6.19\n")

    output = run(
        mirror, "python3", str(HELPER), "--fix", "--dry-run",
        "--format", "json",
    )
    rows = json.loads(output)
    assert {row["path"] for row in rows} == {
        "src/new.php", "src/changed.php", "src/plain.php",
    }, rows
    assert all(row["target_url"] == svn_url for row in rows), rows
    assert all(row["remote_revision"] == "1" for row in rows), rows
    assert all(row["candidate_revision"] == "2" for row in rows), rows
    assert all(row["action"] == "would fix" for row in rows), rows

    run(mirror, "python3", str(HELPER), "--fix")
    assert "@since 2.6.19 bbPress (r2) Added behavior." in (
        source / "new.php"
    ).read_text()
    assert "@since 2.6.19 bbPress (r2)" in (
        source / "changed.php"
    ).read_text()
    assert "@since 2.6.19 (r2)" in (source / "plain.php").read_text()
    assert "@since 2.6.18 bbPress Existing omission." in (
        source / "committed.php"
    ).read_text()

print("Git pre-commit fixture passed.")

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

    output = run(
        checkout, "python3", str(HELPER), "--fix", "--dry-run",
        "--format", "json",
    )
    rows = json.loads(output)
    assert len(rows) == 1, rows
    assert rows[0]["target_url"] == url, rows
    assert rows[0]["remote_revision"] == "2", rows
    assert rows[0]["candidate_revision"] == "3", rows
    assert rows[0]["action"] == "would fix", rows

    run(checkout, "python3", str(HELPER), "--fix")
    assert "@since 2.6.19 bbPress (r3)" in file.read_text()

print("Subversion pre-commit fixture passed.")
