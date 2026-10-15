#!/usr/bin/env python3

import importlib.util
import io
import json
import os
from pathlib import Path
import stat
import tempfile
import unittest
from unittest import mock
import xml.etree.ElementTree as ET


SCRIPT = Path(__file__).parents[2] / "bin" / "sync-github-mirror.py"
SPEC = importlib.util.spec_from_file_location("sync_github_mirror", SCRIPT)
mirror = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(mirror)


def canonical_entry(revision=123, path="/trunk/example.php", author="dd32"):
    return ET.fromstring(
        f"""
        <logentry revision="{revision}">
          <author>{author}</author>
          <date>2026-10-12T15:22:10.999999Z</date>
          <paths><path action="M" kind="file">{path}</path></paths>
          <msg>Example: Synchronize history.</msg>
        </logentry>
        """
    )


def deletion_entry(revision=200, path="/branches/old", action="D"):
    return ET.fromstring(
        f"""
        <logentry revision="{revision}">
          <author>dd32</author>
          <date>2026-10-12T15:22:10.999999Z</date>
          <paths><path action="{action}" kind="dir">{path}</path></paths>
          <msg>Remove obsolete mirror ref.</msg>
        </logentry>
        """
    )


def candidate(revision=123, path="trunk", object_id=None):
    return {
        "object_id": object_id or "a" * 40,
        "author_name": "Dion Hulse",
        "author_email": "dd32@git.wordpress.org",
        "author_date": "2026-10-12T15:22:10+00:00",
        "author_timezone": "+0000",
        "committer_name": "Dion Hulse",
        "committer_email": "dd32@git.wordpress.org",
        "committer_date": "2026-10-12T15:22:10+00:00",
        "committer_timezone": "+0000",
        "svn_url": f"{mirror.SVN_ROOT}/{path}",
        "svn_path": path,
        "svn_revision": revision,
        "svn_message": "Example: Synchronize history.",
    }


def profile_identity(author="dd32", name="Dion Hulse", github_user="dd32"):
    return {
        "svn_author": author,
        "name": name,
        "email": f"{author}@git.wordpress.org",
        "github_user": github_user,
    }


class CommitValidationTests(unittest.TestCase):
    @staticmethod
    def validate(commit, entries, expected_path):
        return mirror.validate_commit(
            commit,
            entries,
            expected_path,
            profile_resolver=lambda author: profile_identity(author=author),
        )

    def test_derives_wordpress_identity_from_svn_username(self):
        identity = self.validate(candidate(), {123: canonical_entry()}, "trunk")
        self.assertEqual("dd32", identity["svn_author"])
        self.assertEqual("dd32@git.wordpress.org", identity["email"])
        self.assertEqual("dd32", identity["github_user"])

    def test_accepts_strict_legacy_uuid_identity(self):
        commit = candidate()
        commit.update(
            {
                "author_name": "dd32",
                "author_email": f"dd32@{mirror.SVN_UUID}",
                "committer_name": "dd32",
                "committer_email": f"dd32@{mirror.SVN_UUID}",
            }
        )
        resolver = mock.Mock(side_effect=AssertionError("profile resolver must not run"))
        mirror.validate_commit(
            commit,
            {123: canonical_entry()},
            "trunk",
            profile_resolver=resolver,
        )
        resolver.assert_not_called()

    def test_rejects_identity_not_derived_from_svn_username(self):
        commit = candidate()
        commit["author_email"] = "someone@example.com"
        commit["committer_email"] = "someone@example.com"
        with self.assertRaisesRegex(mirror.MirrorError, "not derived"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_legacy_uuid_identity_for_different_user(self):
        commit = candidate()
        commit.update(
            {
                "author_name": "dd33",
                "author_email": f"dd33@{mirror.SVN_UUID}",
                "committer_name": "dd33",
                "committer_email": f"dd33@{mirror.SVN_UUID}",
            }
        )
        with self.assertRaisesRegex(mirror.MirrorError, "not derived"):
            self.validate(commit, {123: canonical_entry(author="dd32")}, "trunk")

    def test_rejects_different_committer_identity(self):
        commit = candidate()
        commit["committer_name"] = "Someone Else"
        with self.assertRaisesRegex(mirror.MirrorError, "different author and committer"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_message_mismatch(self):
        commit = candidate()
        commit["svn_message"] = "Different message."
        with self.assertRaisesRegex(mirror.MirrorError, "message does not match"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_date_mismatch(self):
        commit = candidate()
        commit["author_date"] = "2026-10-12T15:22:11+00:00"
        commit["committer_date"] = "2026-10-12T15:22:11+00:00"
        with self.assertRaisesRegex(mirror.MirrorError, "date does not match"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_revision_missing_from_canonical_log(self):
        with self.assertRaisesRegex(mirror.MirrorError, "was not returned"):
            self.validate(candidate(), {}, "trunk")

    def test_rejects_unsafe_display_name(self):
        commit = candidate()
        commit["author_name"] = "Someone <Else>"
        commit["committer_name"] = "Someone <Else>"
        with self.assertRaisesRegex(mirror.MirrorError, "unsafe"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_name_that_differs_from_verified_profile(self):
        commit = candidate()
        commit["author_name"] = "Different Name"
        commit["committer_name"] = "Different Name"
        with self.assertRaisesRegex(mirror.MirrorError, "verified WordPress.org Profile"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_non_utc_git_svn_timezone(self):
        commit = candidate()
        commit["author_timezone"] = "-0500"
        commit["committer_timezone"] = "-0500"
        with self.assertRaisesRegex(mirror.MirrorError, "UTC timezone"):
            self.validate(commit, {123: canonical_entry()}, "trunk")

    def test_rejects_wrong_path(self):
        with self.assertRaisesRegex(mirror.MirrorError, "expected branches/2.6"):
            self.validate(candidate(), {123: canonical_entry()}, "branches/2.6")


class ProfileIdentityTests(unittest.TestCase):
    def setUp(self):
        mirror.resolve_profile_identity.cache_clear()

    @mock.patch.object(mirror, "fetch_https")
    @mock.patch.object(mirror, "fetch_json")
    def test_resolves_profile_and_round_trips_connection(self, fetch_json, fetch_https):
        fetch_json.side_effect = [
            {
                "slug": "johnjamesjacoby",
                "name": "John James Jacoby",
                "link": "https://profiles.wordpress.org/johnjamesjacoby/",
            },
            {
                "slug": "johnjamesjacoby",
                "profile": "https://profiles.wordpress.org/johnjamesjacoby",
            },
        ]
        fetch_https.return_value = (
            '<a href="https://github.com/JJJ" rel="me nofollow">github.com/JJJ</a>'
        )
        identity = mirror.resolve_profile_identity("johnjamesjacoby")
        self.assertEqual("John James Jacoby", identity["name"])
        self.assertEqual("JJJ", identity["github_user"])
        self.assertEqual(
            "https://profiles.wordpress.org/wp-json/wporg/v1/users/johnjamesjacoby",
            fetch_json.call_args_list[0].args[0],
        )
        self.assertEqual(
            "https://profiles.wordpress.org/wp-json/wporg-github/v1/lookup/JJJ",
            fetch_json.call_args_list[1].args[0],
        )

    @mock.patch.object(mirror, "fetch_https")
    @mock.patch.object(mirror, "fetch_json")
    def test_rejects_connection_that_does_not_round_trip(self, fetch_json, fetch_https):
        fetch_json.side_effect = [
            {
                "slug": "dd32",
                "name": "Dion Hulse",
                "link": "https://profiles.wordpress.org/dd32/",
            },
            {
                "slug": "someone-else",
                "profile": "https://profiles.wordpress.org/someone-else",
            },
        ]
        fetch_https.return_value = '<a href="https://github.com/dd32" rel="me">GitHub</a>'
        with self.assertRaisesRegex(mirror.MirrorError, "did not map"):
            mirror.resolve_profile_identity("dd32")

    @mock.patch.object(mirror, "fetch_https")
    @mock.patch.object(mirror, "fetch_json")
    def test_rejects_ambiguous_profile_connections(self, fetch_json, fetch_https):
        fetch_json.return_value = {
            "slug": "dd32",
            "name": "Dion Hulse",
            "link": "https://profiles.wordpress.org/dd32/",
        }
        fetch_https.return_value = (
            '<a href="https://github.com/dd32" rel="me">GitHub</a>'
            '<a href="https://github.com/another-user" rel="me">GitHub</a>'
        )
        with self.assertRaisesRegex(mirror.MirrorError, "resolved 2 GitHub connections"):
            mirror.resolve_profile_identity("dd32")

    @mock.patch.object(mirror, "fetch_https")
    @mock.patch.object(mirror, "fetch_json")
    def test_rejects_profile_without_github_connection(self, fetch_json, fetch_https):
        fetch_json.return_value = {
            "slug": "dd32",
            "name": "Dion Hulse",
            "link": "https://profiles.wordpress.org/dd32/",
        }
        fetch_https.return_value = "<p>No connected account</p>"
        with self.assertRaisesRegex(mirror.MirrorError, "resolved 0 GitHub connections"):
            mirror.resolve_profile_identity("dd32")

    @mock.patch.object(mirror, "fetch_https")
    @mock.patch.object(mirror, "fetch_json")
    def test_rejects_reverse_lookup_profile_mismatch(self, fetch_json, fetch_https):
        fetch_json.side_effect = [
            {
                "slug": "dd32",
                "name": "Dion Hulse",
                "link": "https://profiles.wordpress.org/dd32/",
            },
            {
                "slug": "dd32",
                "profile": "https://profiles.wordpress.org/someone-else",
            },
        ]
        fetch_https.return_value = '<a href="https://github.com/dd32" rel="me">GitHub</a>'
        with self.assertRaisesRegex(mirror.MirrorError, "did not map"):
            mirror.resolve_profile_identity("dd32")

    @mock.patch.object(mirror, "fetch_https", return_value="not-json")
    def test_rejects_malformed_identity_json(self, fetch_https):
        with self.assertRaisesRegex(mirror.MirrorError, "not valid JSON"):
            mirror.fetch_json("https://profiles.wordpress.org/example")


class FetchHttpsTests(unittest.TestCase):
    @mock.patch.object(mirror, "urlopen")
    def test_rejects_redirect_outside_wordpress_org(self, urlopen):
        response = mock.MagicMock()
        response.geturl.return_value = "https://example.com/identity"
        urlopen.return_value.__enter__.return_value = response
        with self.assertRaisesRegex(mirror.MirrorError, "unexpected WordPress.org"):
            mirror.fetch_https("https://wordpress.org/wp-json/example")


class RawCommitTests(unittest.TestCase):
    def raw_commit(self, extra_header=b""):
        return (
            b"tree " + b"a" * 40 + b"\n"
            b"parent " + b"b" * 40 + b"\n"
            b"author Dion Hulse <dd32@git.wordpress.org> 1760282530 +0000\n"
            b"committer Dion Hulse <dd32@git.wordpress.org> 1760282530 +0000\n"
            + extra_header
            + b"\nExample: Synchronize history.\n\n"
            + f"git-svn-id: {mirror.SVN_ROOT}/trunk@123 {mirror.SVN_UUID}\n".encode()
        )

    @mock.patch.object(mirror, "run_bytes")
    @mock.patch.object(mirror, "git", return_value="commit\n")
    def test_parses_only_the_expected_raw_commit_headers(self, git, run_bytes):
        run_bytes.return_value = self.raw_commit()
        commit = mirror.parse_commit(Path("unused"), "c" * 40)
        self.assertEqual(["b" * 40], commit["parents"])
        self.assertEqual("Dion Hulse", commit["author_name"])
        self.assertEqual("Example: Synchronize history.", commit["svn_message"])

    @mock.patch.object(mirror, "run_bytes")
    @mock.patch.object(mirror, "git", return_value="commit\n")
    def test_rejects_encoding_or_signature_headers(self, git, run_bytes):
        run_bytes.return_value = self.raw_commit(b"encoding UTF-8\n")
        with self.assertRaisesRegex(mirror.MirrorError, "unexpected or out-of-order"):
            mirror.parse_commit(Path("unused"), "c" * 40)

        run_bytes.return_value = self.raw_commit(b"gpgsig untrusted-signature\n")
        with self.assertRaisesRegex(mirror.MirrorError, "unexpected or out-of-order"):
            mirror.parse_commit(Path("unused"), "c" * 40)

    @mock.patch.object(mirror, "run_bytes")
    @mock.patch.object(mirror, "git", return_value="commit\n")
    def test_rejects_merge_commit(self, git, run_bytes):
        run_bytes.return_value = self.raw_commit().replace(
            b"author Dion Hulse",
            b"parent " + b"d" * 40 + b"\nauthor Dion Hulse",
        )
        with self.assertRaisesRegex(mirror.MirrorError, "merge commit"):
            mirror.parse_commit(Path("unused"), "c" * 40)

    @mock.patch.object(mirror, "run_bytes")
    @mock.patch.object(mirror, "git", return_value="commit\n")
    def test_rejects_missing_or_invalid_git_svn_trailer(self, git, run_bytes):
        run_bytes.return_value = self.raw_commit().replace(b"git-svn-id:", b"not-git-svn-id:")
        with self.assertRaisesRegex(mirror.MirrorError, "exactly one canonical git-svn-id"):
            mirror.parse_commit(Path("unused"), "c" * 40)

        run_bytes.return_value = self.raw_commit().replace(mirror.SVN_UUID.encode(), b"wrong-uuid")
        with self.assertRaisesRegex(mirror.MirrorError, "exactly one canonical git-svn-id"):
            mirror.parse_commit(Path("unused"), "c" * 40)

        run_bytes.return_value = self.raw_commit().replace(
            f"{mirror.SVN_ROOT}/trunk".encode(), b"https://example.invalid/trunk"
        )
        with self.assertRaisesRegex(mirror.MirrorError, "exactly one canonical git-svn-id"):
            mirror.parse_commit(Path("unused"), "c" * 40)

    @mock.patch.object(mirror, "git", return_value="tag\n")
    def test_rejects_tag_object_before_parsing_commit_target(self, git):
        with self.assertRaisesRegex(mirror.MirrorError, "not directly a Git commit"):
            mirror.parse_commit(Path("unused"), "c" * 40)


class TreeManifestTests(unittest.TestCase):
    def test_filesystem_manifest_preserves_blob_modes_and_symlinks(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "plain.txt").write_text("plain\n", encoding="utf-8")
            executable = root / "run.sh"
            executable.write_text("#!/bin/sh\n", encoding="utf-8")
            executable.chmod(executable.stat().st_mode | stat.S_IXUSR)
            try:
                os.symlink("plain.txt", root / "plain-link")
            except OSError:
                self.skipTest("Symbolic links are not supported.")
            manifest = mirror.filesystem_manifest(root)
            self.assertEqual("100644", manifest["plain.txt"][0])
            self.assertEqual("100755", manifest["run.sh"][0])
            self.assertEqual("120000", manifest["plain-link"][0])

    @mock.patch.object(mirror, "git_manifest")
    @mock.patch.object(mirror, "filesystem_manifest")
    @mock.patch.object(mirror, "run")
    def test_rejects_content_mode_extra_and_missing_tree_entries(
        self, run, filesystem_manifest, git_manifest
    ):
        cases = [
            ({"file": ("100644", "a")}, {"file": ("100644", "b")}),
            ({"file": ("100644", "a")}, {"file": ("100755", "a")}),
            ({"file": ("100644", "a")}, {"file": ("100644", "a"), "extra": ("100644", "b")}),
            ({"file": ("100644", "a"), "missing": ("100644", "b")}, {"file": ("100644", "a")}),
        ]
        for expected, actual in cases:
            with self.subTest(expected=expected, actual=actual):
                filesystem_manifest.return_value = expected
                git_manifest.return_value = actual
                with self.assertRaisesRegex(mirror.MirrorError, "differs from canonical Subversion"):
                    mirror.validate_tree(Path("unused"), "a" * 40, candidate())


class FetchIsolationTests(unittest.TestCase):
    @mock.patch.object(mirror, "git_refs", return_value={})
    @mock.patch.object(mirror, "git")
    def test_fetches_sources_into_disjoint_namespaces(self, git, git_refs):
        with tempfile.TemporaryDirectory() as directory:
            repository = Path(directory) / "mirror.git"
            mirror.fetch_sources(repository, "candidate", "github")
        calls = [call.args[1:] for call in git.call_args_list]
        self.assertIn(
            (
                "-c", "transfer.fsckObjects=true", "fetch", "--prune", "--no-tags", "candidate",
                "+refs/heads/*:refs/candidate/heads/*",
                "+refs/tags/*:refs/candidate/tags/*",
            ),
            calls,
        )
        self.assertIn(
            (
                "-c", "transfer.fsckObjects=true", "fetch", "--prune", "--no-tags", "github",
                "+refs/heads/*:refs/github/heads/*",
                "+refs/tags/*:refs/github/tags/*",
            ),
            calls,
        )

    @mock.patch.object(mirror, "git_refs", return_value={"refs/pull/1/head": "a" * 40})
    @mock.patch.object(mirror, "git")
    def test_rejects_any_unexpected_fetched_ref(self, git, git_refs):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(mirror.MirrorError, "Unexpected local refs"):
                mirror.fetch_sources(Path(directory) / "mirror.git", "candidate", "github")


class PreflightTests(unittest.TestCase):
    @mock.patch.object(mirror, "remote_refs")
    def test_skips_history_fetch_only_when_all_canonical_refs_match(self, remote_refs):
        refs = {
            "refs/heads/trunk": "a" * 40,
            "refs/heads/2.6": "b" * 40,
            "refs/tags/2.6.19": "c" * 40,
        }
        remote_refs.side_effect = [refs, refs]
        self.assertTrue(
            mirror.sources_already_match(
                "candidate", "github", {"trunk", "2.6"}, {"2.6.19"}
            )
        )

    @mock.patch.object(mirror, "remote_refs")
    def test_new_canonical_ref_forces_full_validation(self, remote_refs):
        refs = {"refs/heads/trunk": "a" * 40}
        remote_refs.side_effect = [refs, refs]
        self.assertFalse(
            mirror.sources_already_match(
                "candidate", "github", {"trunk", "2.7"}, set()
            )
        )

    @mock.patch.object(
        mirror, "probe_ref_paths", return_value={"refs/heads/old": "branches/old"}
    )
    @mock.patch.object(mirror, "remote_refs")
    def test_extra_ref_forces_deletion_validation(self, remote_refs, probe_paths):
        candidate = {"refs/heads/trunk": "a" * 40}
        github = dict(candidate, **{"refs/heads/old": "b" * 40})
        remote_refs.side_effect = [candidate, github]
        self.assertFalse(
            mirror.sources_already_match("candidate", "github", {"trunk"}, set())
        )

    @mock.patch.object(
        mirror, "probe_ref_paths", return_value={
            "refs/heads/master": "trunk",
            "refs/heads/dependabot/npm/example": None,
            "refs/tags/legacy-0.73": "tags/0.73",
        }
    )
    @mock.patch.object(mirror, "remote_refs")
    def test_matching_noncanonical_refs_do_not_force_full_fetch(
        self, remote_refs, probe_paths
    ):
        candidate = {
            "refs/heads/trunk": "a" * 40,
            "refs/heads/master": "b" * 40,
            "refs/tags/legacy-0.73": "c" * 40,
        }
        github = dict(candidate)
        github["refs/heads/dependabot/npm/example"] = "d" * 40
        remote_refs.side_effect = [candidate, github]
        self.assertTrue(
            mirror.sources_already_match("candidate", "github", {"trunk"}, set())
        )

    @mock.patch.object(mirror, "probe_ref_paths", return_value={})
    @mock.patch.object(mirror, "remote_refs")
    def test_candidate_only_deleted_ref_does_not_prevent_clean_mirror(
        self, remote_refs, probe_paths
    ):
        candidate = {
            "refs/heads/trunk": "a" * 40,
            "refs/tags/deleted": "b" * 40,
        }
        github = {"refs/heads/trunk": "a" * 40}
        remote_refs.side_effect = [candidate, github]
        self.assertTrue(
            mirror.sources_already_match("candidate", "github", {"trunk"}, set())
        )

    @mock.patch.object(mirror, "svn_latest_revision", return_value=201)
    @mock.patch.object(mirror, "probe_branch_revisions", return_value={"trunk": 200})
    @mock.patch.object(mirror, "remote_refs")
    def test_matching_hashes_do_not_hide_candidate_lag(
        self, remote_refs, probe, latest
    ):
        refs = {"refs/heads/trunk": "a" * 40}
        remote_refs.side_effect = [refs, refs]
        self.assertFalse(
            mirror.sources_already_match(
                "candidate", "github", {"trunk"}, set(), snapshot=201
            )
        )


class PlanTests(unittest.TestCase):
    def ref_sets(self):
        return [
            {"refs/candidate/heads/trunk": "b" * 40},
            {"refs/candidate/tags/2.6.19": "c" * 40},
            {"refs/github/heads/trunk": "a" * 40},
            {},
        ]

    @staticmethod
    def plan_commit(path):
        return {
            "svn_path": path,
            "svn_url": f"{mirror.SVN_ROOT}/{path}",
            "svn_revision": 200,
        }

    def plan_result(
        self, refs=None, branches=None, tags=None, commits=None, tag_object=None,
        ancestor=True
    ):
        refs = refs or self.ref_sets()
        branches = branches or set()
        tags = tags or {"2.6.19"}
        commits = commits or [self.plan_commit("trunk"), self.plan_commit("tags/2.6.19")]
        def resolve_ref(repository, ref):
            if ref.startswith("refs/candidate/heads/"):
                return refs[0][ref]
            return tag_object or "c" * 40

        with mock.patch.object(mirror, "git_refs", side_effect=refs), mock.patch.object(
            mirror, "svn_inventory", side_effect=[branches, tags]
        ), mock.patch.object(mirror, "ref_commit", side_effect=resolve_ref), mock.patch.object(
            mirror, "parse_commit", side_effect=commits
        ), mock.patch.object(mirror, "svn_latest_revision", return_value=200), mock.patch.object(
            mirror, "svn_first_revision_after", return_value=200
        ), mock.patch.object(
            mirror, "is_ancestor", return_value=ancestor
        ):
            return mirror.create_plan(Path("unused"))

    def plan(self, **arguments):
        return self.plan_result(**arguments)[0]

    def test_plans_fast_forward_and_missing_tag(self):
        plan = self.plan()
        self.assertEqual(["branch", "tag"], [item["kind"] for item in plan])
        self.assertEqual(["trunk", "2.6.19"], [item["name"] for item in plan])
        self.assertEqual("200", plan[0]["svn_revision"])

    def test_normalizes_x_y_svn_tag_to_x_y_0_git_tag(self):
        refs = self.ref_sets()
        refs[1] = {"refs/candidate/tags/2.6.0": "c" * 40}
        commits = [self.plan_commit("trunk"), self.plan_commit("tags/2.6")]
        plan = self.plan(refs=refs, tags={"2.6"}, commits=commits)
        self.assertEqual("2.6.0", plan[1]["name"])
        self.assertEqual("2.6", plan[1]["canonical_name"])

    def test_rejects_mislabeled_canonical_tag(self):
        commits = [self.plan_commit("trunk"), self.plan_commit("tags/not-2.6.19")]
        with self.assertRaisesRegex(mirror.MirrorError, "does not map"):
            self.plan(commits=commits)

    def test_rejects_existing_tag_change(self):
        refs = self.ref_sets()
        refs[3] = {"refs/github/tags/2.6.19": "d" * 40}
        with self.assertRaisesRegex(mirror.MirrorError, "Existing GitHub tag"):
            self.plan(refs=refs, commits=[self.plan_commit("trunk")])

    def test_rejects_new_annotated_tag(self):
        with self.assertRaisesRegex(mirror.MirrorError, "lightweight Git tag"):
            self.plan(tag_object="e" * 40)

    def test_rejects_non_fast_forward_branch_update(self):
        with self.assertRaisesRegex(mirror.MirrorError, "not an ancestor"):
            self.plan(ancestor=False)

    def test_candidate_lag_is_a_clean_empty_plan(self):
        commits = [
            {
                "svn_path": "trunk",
                "svn_url": f"{mirror.SVN_ROOT}/trunk",
                "svn_revision": 199,
            },
            self.plan_commit("tags/2.6.19"),
        ]
        plan, lag = self.plan_result(commits=commits)
        self.assertEqual([], plan)
        self.assertEqual("199", lag[0]["candidate_revision"])
        self.assertEqual("200", lag[0]["canonical_revision"])
        self.assertEqual("200", lag[0]["first_missing_revision"])

    def test_missing_new_canonical_branch_is_reported_as_lag(self):
        refs = self.ref_sets()
        refs[0] = {"refs/candidate/heads/trunk": "b" * 40}
        commits = [self.plan_commit("trunk"), self.plan_commit("tags/2.6.19")]
        plan, lag = self.plan_result(
            refs=refs, branches={"2.7"}, commits=commits
        )
        self.assertEqual([], plan)
        self.assertEqual("missing", lag[0]["candidate_revision"])
        self.assertEqual("2.7", lag[0]["name"])


class DeletionPlanningTests(unittest.TestCase):
    @mock.patch.object(mirror, "load_svn_log")
    def test_requires_latest_exact_path_change_to_be_directory_deletion(self, load_log):
        load_log.return_value = {
            150: deletion_entry(150, "/branches/old", "D"),
            175: deletion_entry(175, "/branches/other", "D"),
            200: deletion_entry(200, "/branches/old", "D"),
        }
        self.assertEqual(200, mirror.svn_deletion_revision("branches/old", 100, 250))
        load_log.assert_called_once_with(101, 250, 250)

        mirror.svn_deletion_revision.cache_clear()
        load_log.return_value = {200: deletion_entry(200, "/branches/old", "A")}
        with self.assertRaisesRegex(mirror.MirrorError, "not a directory deletion"):
            mirror.svn_deletion_revision("branches/old", 100, 250)

        mirror.svn_deletion_revision.cache_clear()
        load_log.return_value = {
            150: deletion_entry(150, "/branches/old", "D"),
            200: deletion_entry(200, "/branches/old", "A"),
        }
        with self.assertRaisesRegex(mirror.MirrorError, "not a directory deletion"):
            mirror.svn_deletion_revision("branches/old", 100, 250)

        mirror.svn_deletion_revision.cache_clear()
        load_log.return_value = {
            200: deletion_entry(200, "/branches/old/file", "D"),
            201: deletion_entry(201, "/branches/old-2", "D"),
        }
        with self.assertRaisesRegex(mirror.MirrorError, "no explicit change"):
            mirror.svn_deletion_revision("branches/old", 100, 250)

        mirror.svn_deletion_revision.cache_clear()
        load_log.return_value = {200: ET.fromstring(
            '<logentry revision="200"><paths><path action="D" kind="file">'
            '/branches/old</path></paths></logentry>'
        )}
        with self.assertRaisesRegex(mirror.MirrorError, "not a directory deletion"):
            mirror.svn_deletion_revision("branches/old", 100, 250)

        mirror.svn_deletion_revision.cache_clear()
        with self.assertRaisesRegex(mirror.MirrorError, "no revision after"):
            mirror.svn_deletion_revision("branches/old", 250, 250)

    @mock.patch.object(mirror, "svn_deletion_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="a" * 40)
    def test_builds_exact_branch_and_normalized_tag_deletions(
        self, ref_commit, parse_commit, deletion_revision
    ):
        parse_commit.return_value = candidate(
            revision=100, path="branches/old", object_id="a" * 40
        )
        branch = mirror.deleted_ref_item(
            Path("unused"), "branch", "old", "a" * 40,
            "refs/github/heads/old", 250
        )
        self.assertEqual("delete", branch["action"])
        self.assertEqual("refs/heads/old", branch["destination_ref"])
        self.assertEqual("", branch["new"])

        parse_commit.return_value = candidate(
            revision=100, path="tags/2.6", object_id="a" * 40
        )
        tag = mirror.deleted_ref_item(
            Path("unused"), "tag", "2.6.0", "a" * 40,
            "refs/github/tags/2.6.0", 250
        )
        self.assertEqual("2.6", tag["canonical_name"])
        self.assertEqual("refs/tags/2.6.0", tag["destination_ref"])

    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="a" * 40)
    def test_classifies_only_exact_canonical_mirror_names(self, ref_commit, parse_commit):
        parse_commit.return_value = candidate(path="trunk", object_id="a" * 40)
        self.assertIsNone(
            mirror.canonical_mirror_ref_path(
                Path("unused"), "branch", "master", "a" * 40,
                "refs/github/heads/master"
            )
        )

        parse_commit.return_value = candidate(path="branches/foo", object_id="a" * 40)
        self.assertEqual(
            "branches/foo",
            mirror.canonical_mirror_ref_path(
                Path("unused"), "branch", "foo", "a" * 40,
                "refs/github/heads/foo"
            ),
        )
        self.assertIsNone(
            mirror.canonical_mirror_ref_path(
                Path("unused"), "branch", "bar", "a" * 40,
                "refs/github/heads/bar"
            )
        )

        parse_commit.return_value = candidate(path="tags/2.6", object_id="a" * 40)
        for name in ("2.6", "2.6.0"):
            with self.subTest(name=name):
                self.assertEqual(
                    "tags/2.6",
                    mirror.canonical_mirror_ref_path(
                        Path("unused"), "tag", name, "a" * 40,
                        f"refs/github/tags/{name}"
                    ),
                )
        for name in ("2.7", "legacy-2.6"):
            with self.subTest(name=name):
                self.assertIsNone(
                    mirror.canonical_mirror_ref_path(
                        Path("unused"), "tag", name, "a" * 40,
                        f"refs/github/tags/{name}"
                    )
                )

    @mock.patch.object(mirror, "parse_commit", side_effect=mirror.MirrorError("not canonical"))
    @mock.patch.object(mirror, "ref_commit", return_value="a" * 40)
    def test_classifier_ignores_unparseable_engineering_commit(
        self, ref_commit, parse_commit
    ):
        self.assertIsNone(
            mirror.canonical_mirror_ref_path(
                Path("unused"), "branch", "dependabot/npm/example", "a" * 40,
                "refs/github/heads/dependabot/npm/example"
            )
        )

    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="b" * 40)
    def test_classifier_ignores_annotated_tag_object(self, ref_commit, parse_commit):
        self.assertIsNone(
            mirror.canonical_mirror_ref_path(
                Path("unused"), "tag", "2.6.0", "a" * 40,
                "refs/github/tags/2.6.0"
            )
        )
        parse_commit.assert_not_called()

    @mock.patch.object(mirror, "svn_deletion_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="a" * 40)
    def test_rejects_mismatched_deleted_ref_mapping(
        self, ref_commit, parse_commit, deletion_revision
    ):
        parse_commit.return_value = candidate(
            revision=100, path="trunk", object_id="a" * 40
        )
        with self.assertRaisesRegex(mirror.MirrorError, "does not map"):
            mirror.deleted_ref_item(
                Path("unused"), "branch", "old", "a" * 40,
                "refs/github/heads/old", 250
            )
        with self.assertRaisesRegex(mirror.MirrorError, "does not map"):
            mirror.deleted_ref_item(
                Path("unused"), "tag", "2.6.0", "a" * 40,
                "refs/github/tags/2.6.0", 250
            )

        parse_commit.return_value = candidate(
            revision=100, path="tags/2.6", object_id="a" * 40
        )
        with self.assertRaisesRegex(mirror.MirrorError, "does not match"):
            mirror.deleted_ref_item(
                Path("unused"), "tag", "2.7", "a" * 40,
                "refs/github/tags/2.7", 250
            )

    @mock.patch.object(mirror, "ref_commit", return_value="b" * 40)
    def test_rejects_annotated_deleted_tag(self, ref_commit):
        with self.assertRaisesRegex(mirror.MirrorError, "directly to a Git commit"):
            mirror.deleted_ref_item(
                Path("unused"), "tag", "2.6.0", "a" * 40,
                "refs/github/tags/2.6.0", 250
            )

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "svn_latest_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit")
    @mock.patch.object(mirror, "git_refs")
    def test_plans_deletion_only_after_candidate_removes_branch(
        self, git_refs, ref_commit, parse_commit, latest, ancestor, deleted_ref
    ):
        git_refs.side_effect = [
            {"refs/candidate/heads/trunk": "b" * 40},
            {},
            {
                "refs/github/heads/trunk": "b" * 40,
                "refs/github/heads/old": "d" * 40,
            },
            {},
        ]
        ref_commit.side_effect = lambda repository, ref: (
            "d" * 40 if ref.endswith("/old") else "b" * 40
        )
        parse_commit.side_effect = lambda repository, object_id: (
            candidate(revision=150, path="branches/old", object_id=object_id)
            if object_id == "d" * 40
            else PlanTests.plan_commit("trunk")
        )
        deletion = {
            "action": "delete", "kind": "branch", "name": "old",
            "source_ref": "", "destination_ref": "refs/heads/old",
            "old": "d" * 40, "new": "", "svn_revision": "201",
        }
        deleted_ref.return_value = deletion
        plan, lag = mirror.create_plan(
            Path("unused"), 250, {"trunk"}, set()
        )
        self.assertEqual([deletion], plan)
        self.assertEqual([], lag)

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "svn_latest_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit")
    @mock.patch.object(mirror, "git_refs")
    def test_matching_candidate_ref_does_not_block_proven_deletion(
        self, git_refs, ref_commit, parse_commit, latest, ancestor, deleted_ref
    ):
        git_refs.side_effect = [
            {
                "refs/candidate/heads/trunk": "b" * 40,
                "refs/candidate/heads/old": "d" * 40,
            },
            {},
            {
                "refs/github/heads/trunk": "b" * 40,
                "refs/github/heads/old": "d" * 40,
            },
            {},
        ]
        ref_commit.side_effect = lambda repository, ref: (
            "d" * 40 if ref.endswith("/old") else "b" * 40
        )
        parse_commit.side_effect = lambda repository, object_id: (
            candidate(revision=150, path="branches/old", object_id=object_id)
            if object_id == "d" * 40
            else PlanTests.plan_commit("trunk")
        )
        deleted_ref.return_value = {
            "action": "delete", "kind": "branch", "name": "old",
            "source_ref": "", "destination_ref": "refs/heads/old",
            "old": "d" * 40, "new": "", "svn_revision": "201",
        }
        plan, lag = mirror.create_plan(
            Path("unused"), 250, {"trunk"}, set()
        )
        self.assertEqual("delete", plan[0]["action"])
        self.assertEqual([], lag)

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "svn_latest_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit")
    @mock.patch.object(mirror, "git_refs")
    def test_candidate_only_deleted_ref_needs_no_github_action(
        self, git_refs, ref_commit, parse_commit, latest, ancestor, deleted_ref
    ):
        git_refs.side_effect = [
            {
                "refs/candidate/heads/trunk": "b" * 40,
                "refs/candidate/heads/old": "d" * 40,
            },
            {},
            {"refs/github/heads/trunk": "b" * 40},
            {},
        ]
        ref_commit.side_effect = lambda repository, ref: (
            "d" * 40 if ref.endswith("/old") else "b" * 40
        )
        parse_commit.side_effect = lambda repository, object_id: (
            candidate(revision=150, path="branches/old", object_id=object_id)
            if object_id == "d" * 40
            else PlanTests.plan_commit("trunk")
        )
        deleted_ref.return_value = {
            "action": "delete", "kind": "branch", "name": "old",
            "source_ref": "", "destination_ref": "refs/heads/old",
            "old": "d" * 40, "new": "", "svn_revision": "201",
        }
        plan, lag = mirror.create_plan(Path("unused"), 250, {"trunk"}, set())
        self.assertEqual([], plan)
        self.assertEqual([], lag)

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "svn_latest_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit")
    @mock.patch.object(mirror, "git_refs")
    def test_ignores_github_only_engineering_branch(
        self, git_refs, ref_commit, parse_commit, latest, ancestor,
        deleted_ref
    ):
        ref_commit.side_effect = lambda repository, ref: (
            "e" * 40 if ref.endswith("dependabot/npm/example")
            else "f" * 40 if ref.endswith("/master")
            else "b" * 40
        )
        parse_commit.side_effect = lambda repository, object_id: (
            (_ for _ in ()).throw(mirror.MirrorError("not canonical"))
            if object_id == "e" * 40
            else PlanTests.plan_commit("trunk")
        )
        git_refs.side_effect = [
            {"refs/candidate/heads/trunk": "b" * 40},
            {},
            {
                "refs/github/heads/trunk": "b" * 40,
                "refs/github/heads/master": "f" * 40,
                "refs/github/heads/dependabot/npm/example": "e" * 40,
            },
            {},
        ]
        plan, lag = mirror.create_plan(Path("unused"), 250, {"trunk"}, set())
        self.assertEqual([], plan)
        self.assertEqual([], lag)
        deleted_ref.assert_not_called()

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "svn_latest_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit", return_value=PlanTests.plan_commit("trunk"))
    @mock.patch.object(mirror, "ref_commit", return_value="b" * 40)
    @mock.patch.object(mirror, "git_refs")
    def test_rejects_deleted_ref_that_differs_between_sources(
        self, git_refs, ref_commit, parse_commit, latest, ancestor, deleted_ref
    ):
        git_refs.side_effect = [
            {
                "refs/candidate/heads/trunk": "b" * 40,
                "refs/candidate/heads/old": "c" * 40,
            },
            {},
            {
                "refs/github/heads/trunk": "b" * 40,
                "refs/github/heads/old": "d" * 40,
            },
            {},
        ]
        ref_commit.side_effect = lambda repository, ref: (
            "c" * 40 if ref.startswith("refs/candidate/heads/old")
            else "d" * 40 if ref.startswith("refs/github/heads/old")
            else "b" * 40
        )
        parse_commit.side_effect = lambda repository, object_id: (
            candidate(revision=150, path="branches/old", object_id=object_id)
            if object_id in {"c" * 40, "d" * 40}
            else PlanTests.plan_commit("trunk")
        )
        with self.assertRaisesRegex(mirror.MirrorError, "differs between"):
            mirror.create_plan(Path("unused"), 250, {"trunk"}, set())

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "svn_latest_revision", return_value=200)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit")
    @mock.patch.object(mirror, "git_refs")
    def test_plans_normalized_tag_deletion(
        self, git_refs, ref_commit, parse_commit, latest, ancestor, deleted_ref
    ):
        git_refs.side_effect = [
            {"refs/candidate/heads/trunk": "b" * 40},
            {},
            {"refs/github/heads/trunk": "b" * 40},
            {"refs/github/tags/2.6.0": "d" * 40},
        ]
        ref_commit.side_effect = lambda repository, ref: (
            "d" * 40 if ref.endswith("2.6.0") else "b" * 40
        )
        parse_commit.side_effect = lambda repository, object_id: (
            candidate(revision=150, path="tags/2.6", object_id=object_id)
            if object_id == "d" * 40
            else PlanTests.plan_commit("trunk")
        )
        deletion = {
            "action": "delete", "kind": "tag", "name": "2.6.0",
            "canonical_name": "2.6", "source_ref": "",
            "destination_ref": "refs/tags/2.6.0", "old": "d" * 40,
            "new": "", "svn_revision": "201",
        }
        deleted_ref.return_value = deletion
        plan, lag = mirror.create_plan(Path("unused"), 250, {"trunk"}, set())
        self.assertEqual([deletion], plan)
        self.assertEqual([], lag)


class CopyValidationTests(unittest.TestCase):
    @staticmethod
    def copy_entry(destination):
        return ET.fromstring(
            f"""
            <logentry revision="200"><paths>
              <path action="A" kind="dir" copyfrom-path="/trunk" copyfrom-rev="199">/{destination}</path>
            </paths></logentry>
            """
        )

    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "svn_last_changed_revision", return_value=197)
    @mock.patch.object(mirror, "git", return_value="a" * 40)
    def test_uses_source_last_changed_revision_for_copy_parent(
        self, git, last_changed, parse_commit, is_ancestor
    ):
        parse_commit.side_effect = [
            {"parents": ["a" * 40]},
            {"svn_path": "trunk", "svn_revision": 197},
        ]
        mirror.validate_copy_origin(
            Path("unused"), "b" * 40, "branches/2.7",
            self.copy_entry("branches/2.7"), {"a" * 40}, set()
        )
        last_changed.assert_called_once_with("/trunk", 199)
        is_ancestor.assert_called_once_with(
            Path("unused"), "a" * 40, "refs/candidate/heads/trunk"
        )

    @mock.patch.object(mirror, "is_ancestor", return_value=True)
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "svn_last_changed_revision", return_value=197)
    @mock.patch.object(mirror, "git", return_value="a" * 40)
    def test_rejects_parent_that_matches_copyfrom_not_last_changed(
        self, git, last_changed, parse_commit, is_ancestor
    ):
        parse_commit.side_effect = [
            {"parents": ["a" * 40]},
            {"svn_path": "trunk", "svn_revision": 199},
        ]
        with self.assertRaisesRegex(mirror.MirrorError, "last changed revision r197"):
            mirror.validate_copy_origin(
                Path("unused"), "b" * 40, "tags/2.7.0",
                self.copy_entry("tags/2.7.0"), {"a" * 40}, set()
            )

    @mock.patch.object(mirror, "is_ancestor", side_effect=[True, False])
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "svn_last_changed_revision", return_value=197)
    def test_rejects_copy_parent_not_validated_or_already_on_github(
        self, last_changed, parse_commit, is_ancestor
    ):
        parse_commit.side_effect = [
            {"parents": ["a" * 40]},
            {"svn_path": "trunk", "svn_revision": 197},
        ]
        with self.assertRaisesRegex(mirror.MirrorError, "neither already reachable"):
            mirror.validate_copy_origin(
                Path("unused"), "b" * 40, "branches/2.7",
                self.copy_entry("branches/2.7"), set(), {"g" * 40}
            )

    @mock.patch.object(mirror, "is_ancestor", side_effect=[False, True, True])
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "svn_last_changed_revision", return_value=197)
    def test_accepts_copy_parent_from_github_branch_planned_for_deletion(
        self, last_changed, parse_commit, is_ancestor
    ):
        parse_commit.side_effect = [
            {"parents": ["a" * 40]},
            {"svn_path": "branches/old", "svn_revision": 197},
        ]
        mirror.validate_copy_origin(
            Path("unused"), "b" * 40, "branches/new",
            ET.fromstring(
                '<logentry revision="200"><paths><path action="A" kind="dir" '
                'copyfrom-path="/branches/old" copyfrom-rev="199">'
                '/branches/new</path></paths></logentry>'
            ),
            set(), {"g" * 40}
        )


class PlanValidationTests(unittest.TestCase):
    @staticmethod
    def plan():
        return [{
            "kind": "branch",
            "name": "trunk",
            "source_ref": "refs/candidate/heads/trunk",
            "destination_ref": "refs/heads/trunk",
            "old": "old",
            "new": "tip",
            "svn_revision": "102",
        }]

    @staticmethod
    def parsed(object_id):
        revisions = {"old": 100, "one": 101, "tip": 102}
        return candidate(revision=revisions[object_id], object_id=object_id)

    def run_validation(
        self, rev_objects, validate_tree_side_effect=None, scheduled=False,
        plan=None, snapshot=None, canonical_branches=None, canonical_tags=None
    ):
        entries = {101: canonical_entry(101), 102: canonical_entry(102)}
        with mock.patch.object(mirror, "ref_commit", return_value="tip"), mock.patch.object(
            mirror, "parse_commit", side_effect=lambda repository, object_id: self.parsed(object_id)
        ), mock.patch.object(mirror, "rev_list", return_value=rev_objects), mock.patch.object(
            mirror, "load_svn_log", return_value=entries
        ) as load_log, mock.patch.object(
            mirror, "validate_commit", return_value={
                "svn_author": "dd32", "name": "Dion Hulse", "email": "dd32@git.wordpress.org"
            }
        ), mock.patch.object(
            mirror, "validate_tree", side_effect=validate_tree_side_effect
        ) as validate_tree, mock.patch.object(mirror, "git", return_value=""):
            result = mirror.validate_plan(
                Path("unused"), plan or self.plan(), snapshot,
                canonical_branches, canonical_tags, scheduled=scheduled
            )
        return result, load_log, validate_tree

    def test_rejects_omitted_earliest_svn_revision(self):
        with self.assertRaisesRegex(mirror.MirrorError, "exactly the canonical"):
            self.run_validation(["tip"])

    def test_loads_from_old_tip_plus_one_and_validates_every_tree(self):
        result, load_log, validate_tree = self.run_validation(["one", "tip"])
        load_log.assert_called_once_with(101, 102, None)
        self.assertCountEqual(
            ["one", "tip"], [call.args[1] for call in validate_tree.call_args_list]
        )
        self.assertEqual("dd32", result[0]["svn_author"])

    def test_propagates_intermediate_tree_mismatch(self):
        def reject_intermediate(repository, object_id, commit):
            if object_id == "one":
                raise mirror.MirrorError("intermediate tree mismatch")

        with self.assertRaisesRegex(mirror.MirrorError, "intermediate tree mismatch"):
            self.run_validation(["one", "tip"], reject_intermediate)

    @mock.patch.object(mirror, "validate_scheduled_update_scope")
    def test_scheduled_validation_checks_protected_paths(self, validate_scope):
        self.run_validation(["one", "tip"], scheduled=True)
        validate_scope.assert_called_once()

    @mock.patch.object(mirror, "validate_deletion_item")
    def test_mixed_update_and_delete_revalidate_both_paths(self, validate_deletion):
        deletion = {
            "action": "delete", "kind": "branch", "name": "old",
            "source_ref": "", "destination_ref": "refs/heads/old",
            "old": "a" * 40, "new": "", "svn_revision": "200",
        }
        result, load_log, validate_tree = self.run_validation(
            ["one", "tip"], plan=self.plan() + [deletion], snapshot=250,
            canonical_branches={"trunk"}, canonical_tags=set()
        )
        validate_deletion.assert_called_once_with(
            Path("unused"), deletion, 250, {"trunk"}, set()
        )
        self.assertEqual("dd32", result[0]["svn_author"])

    @mock.patch.object(mirror, "validate_copy_origin")
    @mock.patch.object(mirror, "git", return_value="")
    @mock.patch.object(mirror, "validate_tree")
    @mock.patch.object(mirror, "validate_commit", return_value={
        "svn_author": "dd32", "name": "Dion Hulse", "email": "dd32@git.wordpress.org"
    })
    @mock.patch.object(mirror, "git_refs", return_value={})
    @mock.patch.object(mirror, "load_svn_log")
    @mock.patch.object(mirror, "rev_list")
    @mock.patch.object(mirror, "branch_path_commits", return_value=["first", "tip"])
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="tip")
    def test_new_branch_validates_complete_history_and_copy_origin(
        self, ref_commit, parse_commit, branch_commits, rev_list, load_log,
        git_refs, validate_commit, validate_tree, git, validate_copy
    ):
        parsed = {
            "first": candidate(revision=101, path="branches/2.7", object_id="first"),
            "tip": candidate(revision=102, path="branches/2.7", object_id="tip"),
        }
        parse_commit.side_effect = lambda repository, object_id: parsed[object_id]
        rev_list.return_value = ["first", "tip"]
        entries = {
            101: canonical_entry(101, path="/branches/2.7"),
            102: canonical_entry(102, path="/branches/2.7/example.php"),
        }
        load_log.return_value = entries
        plan = [{
            "kind": "branch", "name": "2.7",
            "source_ref": "refs/candidate/heads/2.7",
            "destination_ref": "refs/heads/2.7",
            "old": "", "new": "tip", "svn_revision": "102",
        }]
        mirror.validate_plan(Path("unused"), plan)
        branch_commits.assert_called_once_with(
            Path("unused"), "tip", "branches/2.7"
        )
        validate_copy.assert_called_once_with(
            Path("unused"), "first", "branches/2.7", entries[101],
            {"first", "tip"}, set()
        )

    @mock.patch.object(mirror, "rev_list", return_value=["first", "foreign", "tip"])
    @mock.patch.object(mirror, "branch_path_commits", return_value=["first", "tip"])
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="tip")
    def test_new_branch_rejects_history_from_another_path(
        self, ref_commit, parse_commit, branch_commits, rev_list
    ):
        parsed = {
            "first": candidate(revision=101, path="branches/2.7", object_id="first"),
            "tip": candidate(revision=102, path="branches/2.7", object_id="tip"),
        }
        parse_commit.side_effect = lambda repository, object_id: parsed[object_id]
        plan = [{
            "kind": "branch", "name": "2.7",
            "source_ref": "refs/candidate/heads/2.7",
            "destination_ref": "refs/heads/2.7",
            "old": "", "new": "tip", "svn_revision": "102",
        }]
        with self.assertRaisesRegex(mirror.MirrorError, "history from another path"):
            mirror.validate_plan(Path("unused"), plan)

    @mock.patch.object(mirror, "validate_copy_origin")
    @mock.patch.object(mirror, "validate_tree")
    @mock.patch.object(mirror, "validate_commit", return_value={
        "svn_author": "dd32", "name": "Dion Hulse", "email": "dd32@git.wordpress.org"
    })
    @mock.patch.object(mirror, "git_refs", return_value={})
    @mock.patch.object(mirror, "load_svn_log")
    @mock.patch.object(mirror, "parse_commit")
    @mock.patch.object(mirror, "ref_commit", return_value="tip")
    def test_new_tag_validates_copy_origin(
        self, ref_commit, parse_commit, load_log, git_refs, validate_commit,
        validate_tree, validate_copy
    ):
        parsed = candidate(revision=101, path="tags/2.7", object_id="tip")
        parse_commit.return_value = parsed
        entry = canonical_entry(101, path="/tags/2.7")
        load_log.return_value = {101: entry}
        plan = [{
            "kind": "tag", "name": "2.7.0", "canonical_name": "2.7",
            "source_ref": "refs/candidate/tags/2.7.0",
            "destination_ref": "refs/tags/2.7.0",
            "old": "", "new": "tip", "svn_revision": "101",
        }]
        mirror.validate_plan(Path("unused"), plan)
        validate_copy.assert_called_once_with(
            Path("unused"), "tip", "tags/2.7", entry, {"tip"}, set()
        )


class ScheduledScopeTests(unittest.TestCase):
    @staticmethod
    def commit(revision=101):
        return candidate(revision=revision, object_id="a" * 40)

    def test_rejects_scheduled_workflow_update(self):
        commits = {"a" * 40: (self.commit(), "trunk")}
        logs = {101: canonical_entry(101, path="/trunk/.github/workflows/sync.yml")}
        with self.assertRaisesRegex(mirror.MirrorError, "reviewed local manifest"):
            mirror.validate_scheduled_update_scope(commits, logs)

    def test_rejects_scheduled_validator_update(self):
        commits = {"a" * 40: (self.commit(), "trunk")}
        logs = {101: canonical_entry(101, path="/trunk/bin/sync-github-mirror.py")}
        with self.assertRaisesRegex(mirror.MirrorError, "reviewed local manifest"):
            mirror.validate_scheduled_update_scope(commits, logs)

    def test_rejects_protected_path_on_release_branch(self):
        commit = self.commit()
        commit["svn_path"] = "branches/2.6"
        commits = {"a" * 40: (commit, "branches/2.6")}
        logs = {101: canonical_entry(101, path="/branches/2.6/bin/helper.py")}
        with self.assertRaisesRegex(mirror.MirrorError, "reviewed local manifest"):
            mirror.validate_scheduled_update_scope(commits, logs)

    def test_accepts_scheduled_source_update(self):
        commits = {"a" * 40: (self.commit(), "trunk")}
        logs = {101: canonical_entry(101, path="/trunk/src/example.php")}
        mirror.validate_scheduled_update_scope(commits, logs)


class DeletionValidationTests(unittest.TestCase):
    @staticmethod
    def item():
        return {
            "action": "delete", "kind": "branch", "name": "old",
            "source_ref": "", "destination_ref": "refs/heads/old",
            "old": "a" * 40, "new": "", "svn_revision": "200",
        }

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "git_refs")
    def test_revalidates_absence_old_hash_and_canonical_deletion(
        self, git_refs, deleted_ref
    ):
        item = self.item()
        git_refs.side_effect = [{}, {"refs/github/heads/old": "a" * 40}]
        deleted_ref.return_value = item
        identities = mirror.validate_plan(
            Path("unused"), [item], 250, {"trunk"}, set()
        )
        self.assertEqual([], identities)
        deleted_ref.assert_called_once_with(
            Path("unused"), "branch", "old", "a" * 40,
            "refs/github/heads/old", 250
        )

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "canonical_mirror_ref_path", return_value="branches/old")
    @mock.patch.object(mirror, "git_refs")
    def test_revalidates_proven_deletion_with_matching_candidate(
        self, git_refs, mirror_path, deleted_ref
    ):
        item = self.item()
        git_refs.side_effect = [
            {"refs/candidate/heads/old": "a" * 40},
            {"refs/github/heads/old": "a" * 40},
        ]
        deleted_ref.return_value = item
        self.assertEqual(
            [],
            mirror.validate_plan(Path("unused"), [item], 250, {"trunk"}, set()),
        )

    @mock.patch.object(mirror, "git_refs", return_value={})
    def test_rejects_deletion_when_ref_is_still_canonical(self, git_refs):
        with self.assertRaisesRegex(mirror.MirrorError, "current canonical branch"):
            mirror.validate_plan(
                Path("unused"), [self.item()], 250, {"trunk", "old"}, set()
            )

    @mock.patch.object(mirror, "git_refs")
    def test_rejects_deletion_when_candidate_object_differs(self, git_refs):
        git_refs.return_value = {"refs/candidate/heads/old": "b" * 40}
        with self.assertRaisesRegex(mirror.MirrorError, "differs before deletion"):
            mirror.validate_plan(
                Path("unused"), [self.item()], 250, {"trunk"}, set()
            )

    @mock.patch.object(mirror, "git_refs")
    def test_rejects_deletion_when_github_hash_is_stale(self, git_refs):
        git_refs.side_effect = [{}, {"refs/github/heads/old": "b" * 40}]
        with self.assertRaisesRegex(mirror.MirrorError, "changed before deletion"):
            mirror.validate_plan(
                Path("unused"), [self.item()], 250, {"trunk"}, set()
            )

    @mock.patch.object(mirror, "deleted_ref_item")
    @mock.patch.object(mirror, "git_refs")
    def test_rejects_changed_recomputed_deletion_plan(self, git_refs, deleted_ref):
        git_refs.side_effect = [{}, {"refs/github/heads/old": "a" * 40}]
        changed = dict(self.item(), svn_revision="201")
        deleted_ref.return_value = changed
        with self.assertRaisesRegex(mirror.MirrorError, "does not match"):
            mirror.validate_plan(
                Path("unused"), [self.item()], 250, {"trunk"}, set()
            )

    def test_deletion_requires_pinned_snapshot_and_inventory(self):
        with self.assertRaisesRegex(mirror.MirrorError, "pinned canonical"):
            mirror.validate_plan(Path("unused"), [self.item()])

    @mock.patch.object(mirror, "git_refs", return_value={})
    def test_rejects_deletion_when_normalized_tag_is_still_canonical(self, git_refs):
        item = {
            "action": "delete", "kind": "tag", "name": "2.6.0",
            "canonical_name": "2.6", "source_ref": "",
            "destination_ref": "refs/tags/2.6.0", "old": "a" * 40,
            "new": "", "svn_revision": "200",
        }
        with self.assertRaisesRegex(mirror.MirrorError, "current canonical tag 2.6"):
            mirror.validate_plan(
                Path("unused"), [item], 250, {"trunk"}, {"2.6"}
            )


class ApprovedManifestTests(unittest.TestCase):
    def test_rejects_changed_plan_before_push(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "approved.json"
            path.write_text(json.dumps({"updates": []}), encoding="utf-8")
            with self.assertRaisesRegex(mirror.MirrorError, "differs"):
                mirror.require_expected_manifest({"updates": [{"new": "a"}]}, path)

    def test_accepts_matching_manifest_independent_of_json_key_order(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "approved.json"
            path.write_text('{"updates": [], "schema": 3}', encoding="utf-8")
            mirror.require_expected_manifest({"schema": 3, "updates": []}, path)

    def test_rejects_missing_expected_manifest(self):
        with self.assertRaisesRegex(mirror.MirrorError, "does not exist"):
            mirror.require_expected_manifest({}, Path("missing-approved-manifest.json"))


class LagAgeTests(unittest.TestCase):
    @mock.patch.object(mirror, "svn_revision_date")
    def test_only_lag_older_than_grace_is_a_failure(self, revision_date):
        now = mirror.dt.datetime.now(mirror.dt.timezone.utc)
        lag = [{
            "kind": "branch", "name": "trunk", "candidate_revision": "100",
            "canonical_revision": "105", "first_missing_revision": "101",
            "state": "source-lag",
        }]
        revision_date.return_value = now - mirror.dt.timedelta(minutes=31)
        self.assertEqual(lag, mirror.stale_source_lag(lag, 101))
        revision_date.assert_called_with(101, 101)
        revision_date.return_value = now - mirror.dt.timedelta(minutes=5)
        self.assertEqual([], mirror.stale_source_lag(lag, 101))


class PushTests(unittest.TestCase):
    @mock.patch.object(mirror, "run")
    def test_pushes_validated_object_ids_atomically_without_force(self, run):
        run.side_effect = [
            "",
            "",
            "a" * 40 + "\trefs/heads/trunk\n",
        ]
        plan = [{
            "new": "a" * 40,
            "old": "",
            "destination_ref": "refs/heads/trunk",
            "source_ref": "refs/candidate/heads/trunk",
        }]
        mirror.push(Path("unused"), plan, "github", False)
        command = run.call_args_list[1].args[0]
        self.assertIn("--atomic", command)
        self.assertIn("a" * 40 + ":refs/heads/trunk", command)
        self.assertFalse(any(argument.startswith("+") for argument in command))
        self.assertNotIn("--force", command)
        self.assertNotIn("--mirror", command)
        self.assertNotIn("--delete", command)

    @mock.patch.object(mirror, "run")
    def test_dry_run_uses_atomic_dry_run_without_post_push_lookup(self, run):
        run.side_effect = ["", ""]
        plan = [{
            "new": "a" * 40,
            "old": "",
            "destination_ref": "refs/tags/2.7.0",
            "source_ref": "refs/candidate/tags/2.7.0",
        }]
        mirror.push(Path("unused"), plan, "github", True)
        command = run.call_args_list[1].args[0]
        self.assertIn("--atomic", command)
        self.assertIn("--dry-run", command)
        self.assertEqual(2, run.call_count)

    @mock.patch.object(mirror, "run")
    def test_deletes_only_the_exact_validated_ref_and_verifies_absence(self, run):
        run.side_effect = [
            "a" * 40 + "\trefs/heads/old\n",
            "",
            "",
        ]
        plan = [{
            "action": "delete",
            "kind": "branch",
            "name": "old",
            "new": "",
            "old": "a" * 40,
            "destination_ref": "refs/heads/old",
            "source_ref": "",
        }]
        mirror.push(Path("unused"), plan, "github", False)
        command = run.call_args_list[1].args[0]
        self.assertIn("--atomic", command)
        self.assertIn(
            "--force-with-lease=refs/heads/old:" + "a" * 40,
            command,
        )
        self.assertIn(":refs/heads/old", command)
        self.assertNotIn("--delete", command)
        self.assertNotIn("--mirror", command)
        self.assertNotIn("--force", command)

    @mock.patch.object(mirror, "run")
    def test_mixed_update_create_and_delete_use_one_atomic_push(self, run):
        run.side_effect = [
            "a" * 40 + "\trefs/heads/trunk\n"
            + "c" * 40 + "\trefs/heads/old\n",
            "",
            "b" * 40 + "\trefs/heads/trunk\n"
            + "d" * 40 + "\trefs/tags/2.7.0\n",
        ]
        plan = [
            {
                "action": "update", "new": "b" * 40, "old": "a" * 40,
                "destination_ref": "refs/heads/trunk", "source_ref": "source",
            },
            {
                "action": "create", "new": "d" * 40, "old": "",
                "destination_ref": "refs/tags/2.7.0", "source_ref": "source",
            },
            {
                "action": "delete", "new": "", "old": "c" * 40,
                "destination_ref": "refs/heads/old", "source_ref": "",
            },
        ]
        mirror.push(Path("unused"), plan, "github", False)
        command = run.call_args_list[1].args[0]
        self.assertIn("b" * 40 + ":refs/heads/trunk", command)
        self.assertIn("d" * 40 + ":refs/tags/2.7.0", command)
        self.assertIn(":refs/heads/old", command)
        self.assertIn(
            "--force-with-lease=refs/heads/old:" + "c" * 40,
            command,
        )

    @mock.patch.object(mirror, "run")
    def test_rejects_deleted_ref_that_remains_after_push(self, run):
        run.side_effect = [
            "a" * 40 + "\trefs/heads/old\n",
            "",
            "a" * 40 + "\trefs/heads/old\n",
        ]
        plan = [{
            "action": "delete", "new": "", "old": "a" * 40,
            "destination_ref": "refs/heads/old", "source_ref": "",
        }]
        with self.assertRaisesRegex(mirror.MirrorError, "Post-push verification failed"):
            mirror.push(Path("unused"), plan, "github", False)

    @mock.patch.object(mirror, "remote_refs")
    def test_rejects_deleted_ref_changed_before_push(self, remote_refs):
        remote_refs.return_value = {"refs/heads/old": "b" * 40}
        plan = [{
            "action": "delete", "new": "", "old": "a" * 40,
            "destination_ref": "refs/heads/old", "source_ref": "",
        }]
        with self.assertRaisesRegex(mirror.MirrorError, "changed after validation"):
            mirror.push(Path("unused"), plan, "github", False)

    @mock.patch.object(mirror, "remote_refs")
    def test_rejects_ref_changed_after_validation(self, remote_refs):
        remote_refs.return_value = {"refs/heads/trunk": "b" * 40}
        plan = [{
            "new": "c" * 40,
            "old": "a" * 40,
            "destination_ref": "refs/heads/trunk",
            "source_ref": "refs/candidate/heads/trunk",
        }]
        with self.assertRaisesRegex(mirror.MirrorError, "changed after validation"):
            mirror.push(Path("unused"), plan, "github", False)

    @mock.patch.object(mirror, "remote_refs")
    def test_rejects_new_ref_created_after_validation(self, remote_refs):
        remote_refs.return_value = {"refs/tags/2.7.0": "b" * 40}
        plan = [{
            "new": "c" * 40,
            "old": "",
            "destination_ref": "refs/tags/2.7.0",
            "source_ref": "refs/candidate/tags/2.7.0",
        }]
        with self.assertRaisesRegex(mirror.MirrorError, "changed after validation"):
            mirror.push(Path("unused"), plan, "github", False)

    @mock.patch.object(mirror, "run")
    def test_rejects_post_push_hash_mismatch(self, run):
        run.side_effect = [
            "a" * 40 + "\trefs/heads/trunk\n",
            "",
            "c" * 40 + "\trefs/heads/trunk\n",
        ]
        plan = [{
            "new": "b" * 40,
            "old": "a" * 40,
            "destination_ref": "refs/heads/trunk",
            "source_ref": "refs/candidate/heads/trunk",
        }]
        with self.assertRaisesRegex(mirror.MirrorError, "Post-push verification failed"):
            mirror.push(Path("unused"), plan, "github", False)


class MainTests(unittest.TestCase):
    @staticmethod
    def arguments(mode):
        return mirror.argparse.Namespace(
            candidate_url="candidate",
            github_fetch_url="github-fetch",
            github_push_url="github-push",
            manifest=Path("unused.json"),
            expected_manifest=None,
            mode=mode,
            repository=None,
        )

    @mock.patch.object(mirror, "remote_refs")
    @mock.patch.object(mirror, "write_manifest")
    @mock.patch.object(mirror, "sources_already_match", return_value=True)
    @mock.patch.object(mirror, "svn_inventory", side_effect=[set(), set()])
    @mock.patch.object(mirror, "svn_repository_revision", return_value=100)
    @mock.patch.object(mirror, "parse_args")
    def test_up_to_date_dry_run_authenticates_push_url(
        self, parse_args, revision, inventory, matches, write_manifest, remote_refs
    ):
        parse_args.return_value = self.arguments("dry-run")
        self.assertEqual(0, mirror.main())
        remote_refs.assert_called_once_with("github-push")

    @mock.patch.object(mirror, "remote_refs")
    @mock.patch.object(mirror, "write_manifest")
    @mock.patch.object(mirror, "sources_already_match", return_value=True)
    @mock.patch.object(mirror, "svn_inventory", side_effect=[set(), set()])
    @mock.patch.object(mirror, "svn_repository_revision", return_value=100)
    @mock.patch.object(mirror, "parse_args")
    def test_up_to_date_validation_does_not_load_push_credentials(
        self, parse_args, revision, inventory, matches, write_manifest, remote_refs
    ):
        parse_args.return_value = self.arguments("validate")
        self.assertEqual(0, mirror.main())
        remote_refs.assert_not_called()

    @mock.patch.object(mirror, "push")
    @mock.patch.object(mirror, "write_manifest")
    @mock.patch.object(mirror, "validate_plan", return_value=[])
    @mock.patch.object(mirror, "create_plan", return_value=([{
        "kind": "branch",
        "name": "trunk",
        "source_ref": "refs/candidate/heads/trunk",
        "destination_ref": "refs/heads/trunk",
        "old": "a" * 40,
        "new": "b" * 40,
        "svn_revision": "101",
    }], []))
    @mock.patch.object(mirror, "fetch_sources")
    @mock.patch.object(mirror, "sources_already_match", return_value=False)
    @mock.patch.object(mirror, "svn_inventory", side_effect=[set(), set()])
    @mock.patch.object(mirror, "svn_repository_revision", return_value=101)
    @mock.patch.object(mirror, "parse_args")
    def test_scheduled_push_enables_self_update_scope_check(
        self, parse_args, revision, inventory, matches, fetch, create_plan,
        validate_plan, write_manifest, push
    ):
        arguments = self.arguments("scheduled-push")
        arguments.repository = Path("unused")
        parse_args.return_value = arguments
        self.assertEqual(0, mirror.main())
        self.assertTrue(validate_plan.call_args.kwargs["scheduled"])
        push.assert_called_once()

    @mock.patch.object(mirror, "parse_args")
    def test_manual_push_requires_expected_manifest(self, parse_args):
        parse_args.return_value = self.arguments("push")
        with mock.patch.object(mirror, "svn_repository_revision") as revision, mock.patch.object(
            mirror.sys, "stderr", new_callable=io.StringIO
        ) as stderr:
            self.assertEqual(1, mirror.main())
        revision.assert_not_called()
        self.assertIn("requires --expected-manifest", stderr.getvalue())

    @mock.patch.object(mirror, "push")
    @mock.patch.object(mirror, "write_manifest")
    @mock.patch.object(mirror, "validate_plan", return_value=[])
    @mock.patch.object(mirror, "create_plan", return_value=([{
        "kind": "branch",
        "name": "trunk",
        "source_ref": "refs/candidate/heads/trunk",
        "destination_ref": "refs/heads/trunk",
        "old": "a" * 40,
        "new": "b" * 40,
        "svn_revision": "101",
    }], []))
    @mock.patch.object(mirror, "fetch_sources")
    @mock.patch.object(mirror, "sources_already_match", return_value=False)
    @mock.patch.object(mirror, "svn_inventory", side_effect=[set(), set()])
    @mock.patch.object(mirror, "svn_repository_revision", return_value=101)
    @mock.patch.object(mirror, "parse_args")
    def test_manifest_mismatch_stops_manual_push(
        self, parse_args, revision, inventory, matches, fetch, create_plan,
        validate_plan, write_manifest, push
    ):
        with tempfile.TemporaryDirectory() as directory:
            expected = Path(directory) / "expected.json"
            expected.write_text('{"schema": 2, "updates": []}', encoding="utf-8")
            arguments = self.arguments("push")
            arguments.repository = Path("unused")
            arguments.expected_manifest = expected
            parse_args.return_value = arguments
            with mock.patch.object(
                mirror.sys, "stderr", new_callable=io.StringIO
            ) as stderr:
                self.assertEqual(1, mirror.main())
        self.assertIn("differs from the approved manifest", stderr.getvalue())
        push.assert_not_called()
        write_manifest.assert_not_called()

    @mock.patch.object(mirror, "push")
    @mock.patch.object(mirror, "write_manifest")
    @mock.patch.object(mirror, "stale_source_lag", return_value=[])
    @mock.patch.object(mirror, "create_plan", return_value=([], [{
        "kind": "branch", "name": "old", "candidate_revision": "150",
        "canonical_revision": "200", "first_missing_revision": "200",
        "state": "source-lag",
    }]))
    @mock.patch.object(mirror, "fetch_sources")
    @mock.patch.object(mirror, "sources_already_match", return_value=False)
    @mock.patch.object(mirror, "svn_inventory", side_effect=[set(), set()])
    @mock.patch.object(mirror, "svn_repository_revision", return_value=250)
    @mock.patch.object(mirror, "parse_args")
    def test_candidate_lag_is_noop_during_grace_period(
        self, parse_args, revision, inventory, matches, fetch, create_plan,
        stale_lag, write_manifest, push
    ):
        arguments = self.arguments("scheduled-push")
        arguments.repository = Path("unused")
        parse_args.return_value = arguments
        self.assertEqual(0, mirror.main())
        push.assert_not_called()
        manifest = write_manifest.call_args.args[0]
        self.assertEqual("candidate-behind", manifest["status"])
        self.assertEqual([], manifest["updates"])


if __name__ == "__main__":
    unittest.main()
