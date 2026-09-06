# WordPress.org deployment

The `Deploy to WordPress.org` workflow prepares and optionally deploys a stable bbPress release from an exact canonical Subversion source tag. It also deploys the tag's reviewed Playground blueprint to Plugin SVN. It does not use a GitHub tag or branch as release source.

## Repository setup

Create a GitHub Environment named `wordpress.org plugin`, require approval from `JJJ` before deployment, and add `WPORG_SVN_USERNAME` and `WPORG_SVN_PASSWORD` as environment secrets. Restrict the environment to the `trunk` branch and allow self-review. The workflow also restricts the deployment job to `trunk`. These settings must be verified before adding the password. The Subversion account must be a bbPress Plugin Directory committer. Keep the deployment password in the protected environment secret only; never put it in workflow inputs, tracked files, command arguments, or logs. The workflow passes the password through standard input and disables the Subversion authentication cache. The public WordPress.org username is passed with `--username`.

## Release preparation

Create and verify the canonical source tag before running the workflow. Record the tag's canonical Subversion revision, then run the workflow with the stable version, that exact revision, and `deploy` disabled. The credential-free preparation job exports the canonical tag, runs the release build, verifies versions and package shape, creates and installs the exact candidate ZIP in a disposable WordPress site, verifies activation, forum/topic/reply counts and front-end rendering with Twenty Twenty-One, stages the prospective Plugin SVN branch, trunk, tag, and Playground blueprint in a temporary working copy, and uploads the ZIP, blueprint, checksum, source URL/revision, successful smoke-test log, proposed commit message, and Subversion status/diff as evidence. The canonical `.wordpress-org/blueprints/blueprint.json` file is excluded from the plugin ZIP and staged separately as Plugin SVN `assets/blueprints/blueprint.json`.

Review the workflow log and artifact against the approved release candidate. A successful preparation run does not publish anything. Review the complete staged diff and deployment message before approving a deployment. Dependency downloads and ZIP timestamps can change between runs; the deployment approval applies to the new preparation artifact from that same run, not to the checksum of an earlier run.

## Deployment

Run the workflow again with the same version and canonical revision and enable `deploy`. Approve the protected environment only after confirming that the inputs and preparation output match the release record. The workflow rebuilds the exact source tag, repeats all checks, requires the Plugin SVN tag to be absent, synchronizes the maintenance branch and trunk, creates the deployment tag from the branch, and commits all three paths together. This is a proposed change from the separate branch update and tag commits used for 2.6.15: one Plugin SVN transaction keeps the three deployment trees identical and avoids publishing a partial staging operation. It does not change canonical source commit or tagging conventions.

After the commit, the workflow verifies the Plugin SVN log, exports and compares all three plugin trees and the Playground blueprint at the deployment revision, and waits for the public WordPress.org ZIP to match the candidate tree. It does not create the canonical source tag, publish bbPress.org or Codex content, make the Plugin Directory preview public, or repair GitHub mirror refs. Test the committer-only preview from the Plugin Directory Advanced page before enabling it for everyone.

The workflow can only reduce manual mechanics. The release checklist, maintainer approval, source and deployment tag verification, public smoke test, release communications, and credential handling requirements in `AGENTS.md` still apply.

If a run fails after the commit, inspect Plugin SVN before retrying. An existing deployment tag is deliberately rejected; verify propagation or resolve the failed verification manually rather than deleting or recreating the tag.
