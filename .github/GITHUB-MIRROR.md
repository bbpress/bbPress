# GitHub mirror synchronization

Canonical bbPress development remains in Subversion at `https://bbpress.svn.wordpress.org/`. The `Sync GitHub mirror` workflow validates the native WordPress Git conversion and synchronizes its canonical branches and tags to `bbpress/bbPress`.

The workflow does not convert Subversion history itself. It treats `git://bbpress.git.wordpress.org/` only as a candidate source of exact Git objects, then independently validates those objects against canonical Subversion over HTTPS before a push is possible.

## Safety model

Every run first checks the advertised candidate and GitHub refs against a pinned canonical Subversion inventory. When any canonical ref differs, the run starts a new bare repository, fetches the current sources into separate namespaces, and then:

- limits branch updates to `trunk` and names currently present under canonical `branches/`;
- requires every existing GitHub branch update to be a fast-forward;
- creates a missing canonical maintenance branch only after validating its complete branch-specific history and its Subversion copy origin against the corresponding Git parent;
- requires each new commit to contain one exact `git-svn-id` trailer for the canonical URL and repository UUID;
- verifies the complete sequence of Subversion revisions affecting each updated branch;
- compares each commit's path, author, timestamp, and log message with canonical Subversion;
- derives the permitted Git email from the canonical Subversion username and accepts only the WordPress.org Git address or the strict legacy repository-UUID fallback;
- resolves each current WordPress.org Git identity through the public WordPress.org user API, reads the verified GitHub connection from the public Profile, and requires the Profiles API to map that GitHub login back to the same WordPress.org username;
- requires the candidate display name to match the WordPress.org user record, requires the git-svn UTC offset, and records the verified WordPress.org and GitHub identities in the run manifest;
- exports every new commit from canonical Subversion with externals and keyword expansion disabled, then compares every blob and executable or symbolic-link mode with the candidate Git tree;
- requires every current canonical Subversion tag to be represented in the candidate mirror;
- maps a canonical `X.Y` Subversion tag to the established `X.Y.0` Git tag name and otherwise requires an exact name;
- rejects any change to an existing GitHub tag and creates a missing lightweight tag only after validating its Subversion copy origin and Git parent;
- deletes a GitHub branch or tag only when it is absent from the pinned canonical Subversion inventory and the latest exact-path change after the GitHub tip is an explicit canonical Subversion directory deletion;
- leaves noncanonical compatibility and engineering refs, such as the legacy `master` alias or dependency-update branches, outside the canonical mirror namespace when their tips do not map to the same-named canonical Subversion branch or normalized tag;
- requires a matching old object when the native Git candidate retains a ref that canonical Subversion explicitly deleted; candidate presence or absence alone never authorizes deletion;
- requires a fresh local revalidation to reproduce the exact maintainer-reviewed manifest before local push mode can run;
- refuses scheduled synchronization of `.github/` or `bin/` changes, so executable mirror automation can reach GitHub only through the reviewed local manifest process;
- pushes the complete refspec atomically without force updates, using an exact old-object lease only as the compare-and-swap guard for each deletion; and
- refetches GitHub after a real push and verifies every resulting object ID.

The workflow never uses `git push --mirror`, never force-updates a ref to another object, and never touches pull-request or review refs. A planned deletion names one exact branch or tag and includes its old object ID and the canonical Subversion deletion revision in the reviewed manifest. Git's `--force-with-lease=<ref>:<old>` spelling is used only for deletion compare-and-swap, so the deletion fails if that exact ref changed after validation.

Canonical branch or tag renames are represented as a verified copy plus deletion. The copy parent may be anchored in the existing GitHub canonical tip that the same atomic plan removes. A rename that changes the established Git tag normalization, or otherwise requires updating an existing tag to another object, fails closed for explicit maintainer review.

WordPress.org exposes the public GitHub lookup in the GitHub-to-WordPress.org direction. The validator therefore accepts a Profile connection only after the public Profile supplies one unambiguous `rel="me"` GitHub account and the API resolves that account back to the original Subversion username. It fails closed when the user record, Profile, connection, or API is unavailable or contradictory. A new committer or canonical branch does not require a repository allowlist or prior GitHub-history enrollment.

## Repository setup

Create a GitHub Environment named `github mirror` and restrict it to the `trunk` branch. Add the private half of the dedicated write-enabled repository deploy key as the environment secret `BBPRESS_GITHUB_MIRROR_SSH_KEY`. The corresponding public key must remain registered as the `bbPress GitHub mirror deploy key` deploy key on `bbpress/bbPress`. It must be the repository's only write-enabled deploy key, because GitHub ruleset bypass applies to the `DeployKey` actor category rather than to one selected key. Adding or enabling another write deploy key requires explicit maintainer review and disabling the schedule first.

Create the repository variable `BBPRESS_GITHUB_MIRROR_ENABLED` with the value `false`. Scheduled runs are skipped until this variable is deliberately changed to `true` after the initial backfill is verified. Do not configure required reviewers after enabling the schedule, because scheduled jobs cannot satisfy an interactive environment approval.

The workflow pins GitHub's published Ed25519 host key instead of accepting a key discovered at runtime. Review the official GitHub SSH host-key notice before changing that value.

## Rollout

The first backfill must be performed from a trusted isolated checkout because the workflow is not available on GitHub until that backfill carries it there.

1. Configure and inspect separate GitHub rulesets for canonical branches and tags. Authorization rules must restrict creation, fast-forward updates, and deletion to the `DeployKey` actor category, with no human, role, team, or app bypass, and the mirror key must be the only write-enabled deploy key. Independent safety rules must block force pushes without any bypass and must block updates to existing tags without blocking a separately authorized deletion. Target patterns must cover `trunk`, every current or future canonical maintenance-branch name, and every current or future canonical tag name, including the established `X.Y.0` Git normalization. Verify the effective rules and write-enabled key inventory before any real push; use a disposable-ref test only with separate explicit approval.
2. Run the validator in `validate` mode and inspect the generated JSON ref and author manifest.
3. Run it again in `dry-run` mode with `--expected-manifest` pointing to the first manifest, then inspect Git's atomic push simulation.
4. Obtain explicit maintainer approval for the exact manifest.
5. Run it in `push` mode, then verify the post-push object IDs and GitHub's branch and tag views.
6. Manually dispatch the GitHub workflow in `validate` mode.
7. Manually dispatch it in `dry-run` mode. The hosted expected manifest is created immediately before revalidation, so this proves repeatability rather than prior maintainer review. When no ref update is pending, dry-run mode still performs an authenticated SSH ref query so the key is exercised before unattended scheduling; this does not prove write access or a ruleset bypass.
8. Change `BBPRESS_GITHUB_MIRROR_ENABLED` to `true` only after both hosted runs succeed.

The workflow does not expose manual push mode. The initial backfill uses the local manifest-review procedure above. Scheduled runs validate without loading the deploy key. Only a nonempty, fully validated manifest starts a second exact revalidation with the key and an atomic push. Candidate lag is a safe no-op for 30 minutes from the first missing canonical revision, then becomes a validation failure so a stuck converter cannot remain invisible. Scheduled runs remain enabled only while the enable variable is `true`. Disable that variable immediately if either official source diverges, credentials are suspected, or a validation rule needs review. Disabling is always a permitted fail-safe action; enabling or broadening the workflow requires explicit maintainer approval.

Scheduled mode will not carry changes under `.github/` or `bin/`. Land those changes in canonical Subversion first, validate them from a trusted isolated checkout, and synchronize them with the reviewed local manifest process. This prevents newly mirrored automation code from automatically receiving the deploy key on a later run.

Creating a canonical maintenance branch by copying validated trunk history does not count as an automation update merely because the copied tree contains `.github/` and `bin/`. The copy commit must still pass the branch-history and copy-origin checks. Any later branch-specific change under those paths is refused in scheduled mode.

A successful Git dry-run does not prove server-side rules, so inspect their effective configuration separately before the initial real push.

GitHub can disable scheduled workflows in a public repository after 60 days without repository activity. The project should monitor the workflow's last completed run independently, including the lag status, and manually re-enable it if GitHub pauses the schedule. Successful scheduled updates retain their manifests for 90 days as an operational record, not a permanent audit archive. The local bootstrap procedure remains the recovery path when the workflow itself is absent, paused, or broken.

## Local validation

The credential-free validation command is:

```sh
python3 bin/sync-github-mirror.py \
  --manifest /tmp/bbpress-github-mirror-manifest.json \
  --mode validate
```

Do not place a private key in the repository or pass it as a command argument. Use an isolated process environment with `GIT_SSH_COMMAND` pointing to a mode `0600` temporary key file, and remove that file when the run ends.
