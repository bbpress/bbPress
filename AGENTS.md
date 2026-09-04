# bbPress Contributor and Agent Handbook

This file applies to the entire repository. It is an operational guide for
humans and coding agents working on bbPress. Prefer repository evidence over
memory, keep public changes reviewable, and leave the project easier to release
than you found it.

## Mission

bbPress is long-lived WordPress infrastructure. Optimize for compatibility,
predictability, security, and maintainability rather than novelty. A good
change is narrowly scoped, preserves existing extension points, includes a
regression test when practical, and can be traced from a Trac ticket through a
canonical Subversion revision.

## Repository Authority

| System | Role | Mutation rule |
| --- | --- | --- |
| `https://bbpress.svn.wordpress.org/` | Canonical source, branches, and tags | Commit approved source changes here |
| `https://github.com/bbpress/bbPress` | Engineering mirror, pull requests, review, and CI | Never push or merge authoritative refs directly |
| `https://bbpress.trac.wordpress.org/` | Public issue and milestone system of record | Keep ticket history authoritative |
| `https://plugins.svn.wordpress.org/bbpress/` | WordPress.org deployment repository | Release packages only; never normal development |
| `https://translate.wordpress.org/projects/wp-plugins/bbpress/` | Hosted translations | Do not treat it as the source repository |

GitHub is a mirror, not the release origin. A reviewed change is committed to
canonical SVN first and then allowed to arrive in GitHub through the existing
mirror. Do not repair delayed or missing GitHub branches or tags by pushing to
the mirror; verify the SVN state and escalate the mirror problem instead.

Plugin SVN contains built deployment artifacts. Never copy source back from
Plugin SVN into canonical development history.

## Non-Negotiable Safety Rules

- Inspect `git status` or `svn status` before and after work. Preserve unrelated
  edits and untracked files.
- Never use destructive cleanup (`git reset --hard`, broad recursive deletion,
  or equivalent) to make a working tree convenient.
- Use a clean, dedicated SVN working copy for canonical commits, tagging, and
  releases. Do not mix a release with an unrelated development checkout.
- Before every public mutation, inspect the exact diff and target, and present
  the proposed commit message for maintainer review.
- Stop for explicit maintainer approval before an SVN commit, GitHub push, PR
  merge or close, Trac mutation, release, deployment, site publication,
  permission change, or public infrastructure change. One clear approval may
  cover a named sequence; ask again if its target or scope changes.
- Never expose credentials in command arguments, tracked files, patches, logs,
  chat, or shell history. Let approved credential helpers or interactive tools
  handle authentication.
- Treat release tags as immutable records. Correct forward unless a maintainer
  explicitly directs an exceptional tag repair.

## Working Tree Map

- `src/` is the authoritative plugin source. Make runtime changes here.
- `build/` is generated, ignored release output. Never hand-edit or commit it.
- `bbpress.php` is the development loader. It prefers `build/bbpress.php` when
  `build/` exists unless `BBP_LOAD_SOURCE` is defined. Remove stale build output
  or define that constant when testing source changes.
- `src/bbpress.php` contains the packaged plugin header and runtime version.
- `src/readme.txt` is the WordPress.org readme and stable-release metadata.
- `CHANGELOG.md` is the GitHub-facing record of notable user-visible changes
  beginning with bbPress 2.0.0, the first WordPress plugin release.
- `tests/phpunit/` contains standard, multisite, and BuddyPress PHPUnit suites.
- `tests/ci/check-versions.php` validates duplicated version metadata.
- `tests/smoke/cli.php` exercises an installed release build with WP-CLI.
- `.github/workflows/` is the executable truth for the current CI matrix.
- `Gruntfile.js` defines linting, asset compilation, POT generation, build, and
  release-package behavior.

Generated minified JavaScript, CSS, RTL CSS, color schemes, and `bbpress.pot`
belong in the release build. Determine whether a changed artifact is source or
generated output from `Gruntfile.js`; do not guess.

## Choose the Correct Target

Before editing, record the ticket, intended release, canonical SVN path, and
whether a maintenance backport is required.

- New development normally targets canonical `trunk`.
- A supported patch release also requires an intentional, separately reviewed
  backport to its maintenance branch, such as `branches/2.6`.
- Never assume a trunk commit is present in a maintenance release merely
  because it predates the release date. Verify the branch and tag contents.
- Never develop in `tags/*` or Plugin SVN.
- Use one branch, worktree, or checkout per ticket when practical. Name Git
  review branches `trac-<ticket-number>` unless the maintainer requests another
  convention.

For every issue, capture:

- Trac ticket and target milestone;
- target SVN path and affected maintenance branches;
- reproduction steps, expected behavior, and compatibility constraints;
- test coverage and commands run;
- unresolved questions or release risks.

## Standard Change Workflow

1. Read the Trac ticket and inspect relevant history, hooks, tests, and adjacent
   code before proposing a solution.
2. Reproduce the problem with the narrowest deterministic case available.
3. Make the smallest coherent source change. Preserve backward compatibility
   and existing filters/actions unless the ticket explicitly changes them.
4. Add or update a regression test. A fix without a feasible automated test
   should include a written reason and precise manual verification steps.
5. Update the `Unreleased` section of `CHANGELOG.md` for a notable user-visible
   change. Keep confidential security details out of public drafts.
6. Run the narrow test first, then the applicable suite and lint/build checks.
7. Review the complete diff for generated churn, debug output, credentials,
   accidental version changes, and unrelated formatting.
8. Prepare an SVN-compatible patch and bbPress-style commit message.
9. Obtain maintainer review. Commit to canonical SVN only after approval.
10. Verify the resulting SVN revision and clean working-copy state. Then verify
   that the GitHub mirror and its CI picked up the canonical revision.

GitHub pull requests are useful review and CI surfaces, but Trac and canonical
SVN remain authoritative. Reference the PR from the SVN commit when one exists.

## Setup and Everyday Commands

Install pinned dependencies:

```sh
composer install
npm ci
```

Start the repository's WordPress development environment:

```sh
npm run wp-env start
```

Run the main PHP suite inside that environment:

```sh
npm run test-php
```

PHPUnit can also run against a compatible `wordpress-develop` checkout. Set
`WP_DEVELOP_DIR` to its root (recommended), or `WP_TESTS_DIR` to its
`tests/phpunit` directory. A valid `wp-tests-config.php` and test database are
required. Missing test infrastructure is a failure, not a skipped success.

```sh
WP_DEVELOP_DIR=/path/to/wordpress-develop composer test
WP_DEVELOP_DIR=/path/to/wordpress-develop vendor/bin/phpunit -c phpunit.xml.dist --filter TestName
WP_DEVELOP_DIR=/path/to/wordpress-develop vendor/bin/phpunit -c tests/phpunit/multisite.xml
```

BuddyPress integration requires a compatible BuddyPress checkout at
`wordpress-develop/src/wp-content/plugins/buddypress` (or an equivalent layout
that allows `BP_TESTS_DIR` to resolve):

```sh
WP_DEVELOP_DIR=/path/to/wordpress-develop vendor/bin/phpunit -c tests/phpunit/buddypress.xml
```

Run quality checks appropriate to the changed files:

```sh
composer lint
npx grunt eslint:grunt eslint:core
npx grunt stylelint
npx grunt checktextdomain
php tests/ci/check-versions.php
```

Useful narrower checks include:

```sh
vendor/bin/phpcs path/to/changed.php
npx grunt eslint:core --file=filename.js
php -l path/to/changed.php
```

Do not run an auto-formatter across unrelated legacy code. Review every
formatting change, particularly in old templates and converter integrations.

## Test Expectations

Match validation effort to risk:

- PHP behavior: focused PHPUnit test, main suite, and PHPCS.
- Query, permission, visibility, or cache changes: test anonymous and privileged
  users, mixed content, malformed/missing metadata, and relevant multisite
  behavior.
- BuddyPress integration: dedicated BuddyPress suite.
- JavaScript: ESLint plus the affected behavior in WordPress.
- CSS or SCSS: stylelint, generated LTR/RTL output, and visual inspection.
- Translatable strings or block metadata: text-domain check and regenerated POT.
- Build tooling, package contents, versions, or releases: full release build and
  installed-package smoke test.
- Database upgrades: fresh install and upgrade-path coverage; change the database
  version only when schema or migration behavior requires it.

The CI matrix changes over time. Read `.github/workflows/test.yml` rather than
copying a remembered list. At minimum, changes intended for trunk should remain
compatible with the oldest supported WordPress/PHP combination and current
WordPress/PHP coverage represented there. Experimental jobs may be allowed to
fail only when the workflow says so; still inspect and report their failures.

Never weaken, skip, or silence a failing check merely to obtain green CI. Fix the
cause or document the incompatibility and obtain review for a scoped adjustment.

## Coding and Compatibility Guidance

- Follow WordPress Coding Standards and the project-specific exceptions in
  `phpcs.xml.dist`.
- Prefix public globals, functions, classes, hooks, options, and metadata with
  `bbp`/`bbpress` according to existing conventions.
- Use WordPress APIs and bbPress abstractions before introducing direct database
  or filesystem behavior.
- Preserve filters, actions, accepted argument counts, return shapes, and
  capability semantics. Treat extension points as public API.
- Sanitize input, validate intent and capability, and escape at output. Do not
  confuse nonce verification with authorization.
- For SQL, use the bbPress/WordPress database abstraction and prepared values.
  Consider multisite table context and query-filter suppression.
- Avoid broad query changes. Explicitly test non-bbPress post types when hooking
  global WordPress queries.
- Maintain the `bbpress` text domain. Include translator comments for ambiguous
  placeholders and regenerate the POT when strings change.
- Keep source compatible with the declared requirements in plugin headers,
  readme metadata, PHPCS compatibility rules, and CI. When those disagree,
  surface the inconsistency rather than choosing one silently.
- Use the actual first shipped version in new `@since` annotations. If a feature
  is backported before release, reconcile trunk annotations too.

## Version Metadata

Development/package versions are intentionally duplicated. When changing the
development version, reconcile all of these:

- root `bbpress.php` plugin header;
- `src/bbpress.php` plugin header;
- the runtime version in `src/bbpress.php`;
- `package.json`;
- the root version and package entry in `package-lock.json`.

Run:

```sh
php tests/ci/check-versions.php
```

Release metadata additionally includes `Stable tag`, `Tested up to`, changelog,
and upgrade notice in `src/readme.txt`, plus the release heading, date, and
curated entries in `CHANGELOG.md`. Root and source PHP requirements may serve
different development-loader/package purposes; do not normalize them without
understanding that distinction.

## Changelog Policy

`CHANGELOG.md` is a concise, GitHub-facing summary, not a replacement for Trac,
the WordPress.org readme, release posts, or Codex release records.

- Add notable user-visible changes under `Unreleased` in the same change that
  introduces them. Routine refactoring, tests, and internal tooling do not need
  entries unless they materially affect contributors or releases.
- Use `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, and `Security`
  subsections only when they contain entries.
- Describe security changes safely before coordinated disclosure. Never put
  embargoed impact, reproduction steps, reporter data, or exploit detail in a
  public changelog draft.
- During a release, audit `Unreleased` against the exact canonical revision
  range and Trac milestone. Move the final entries under a version and ISO date,
  add authoritative release links, and leave a fresh `Unreleased` section.
- Keep `src/readme.txt`, the Codex version page, the releases table, and the
  bbPress.org announcement consistent with the final changelog. None of these
  records substitutes for the others.
- The detailed history begins with 2.0.0, the first WordPress plugin release.
  Do not manufacture summaries for earlier standalone releases without reliable
  primary-source evidence; link to their canonical tags instead.

## Assets, Translation Template, and Release Build

The release build requires Node dependencies, PHP, WP-CLI with `wp i18n`, and
the PHP `mbstring` extension. Build from a clean canonical maintenance-branch
working copy or export:

```sh
npm ci
npx grunt release
```

`grunt release` runs source linting, text-domain checks, PostCSS, copies runtime
files into `build/`, produces color/RTL/minified assets, and generates
`build/bbpress.pot` using WP-CLI. The task can update tracked source CSS through
PostCSS, so always inspect `svn status`/`git status` afterward. A release build
must not leave unexplained source changes.

Verify at least:

- version metadata agrees;
- `build/bbpress.php`, `build/readme.txt`, and a non-empty
  `build/bbpress.pot` exist;
- PHP, JavaScript, and block metadata strings appear in the POT;
- `tests/`, `.github/`, `Gruntfile.js`, package metadata, SCSS, and other
  development-only files are absent from the package;
- the ZIP has one top-level `bbpress/` directory and passes `unzip -t`;
- the package tree is reviewed against the previous public release;
- the exact ZIP installs, activates, creates a forum/topic/reply, reports correct
  counts, and renders on the front end with a known-compatible theme.

The Build and Smoke Test workflow is executable release evidence and uploads a
candidate ZIP artifact. It does not authorize or perform a release deployment.

## Canonical SVN Commits

Before committing:

1. Update the exact working copy and resolve any incoming changes.
2. Review `svn status`, `svn diff`, and added/deleted paths.
3. Run the relevant tests against the final diff.
4. Present the complete proposed commit message.
5. Confirm the target (`trunk`, a maintenance branch, or both) and approval.

When supplying multiline SVN log messages from a shell, script, IDE, or other
automation, use a reviewed message file or another method that preserves actual
line-feed characters. Do not pass serialized or escaped `\n` sequences.
Immediately inspect `svn log -r <revision>` after each commit and correct any
malformed log before proceeding to a backport or another public mutation.

Follow established bbPress history. A typical message contains:

```text
Component: concise imperative summary.

Explain the behavior, motivation, compatibility considerations, and why this
approach is safe.

In trunk, for 2.7.

Props contributor-name.
Fixes #1234.
From https://github.com/bbpress/bbPress/pull/123.
```

Use only applicable trailers. Use `See #1234.` when the commit does not close
the ticket. Preserve maintainer judgment about capitalization and component
names. After committing, record the revision, inspect `svn log -r <revision>`
on the changed path, and ensure `svn status` is clean.

## Release Procedure

Releases require an explicit maintainer go-ahead and clean, separate canonical
and deployment working copies. Keep a release log containing every revision,
URL, checksum, test result, and manual approval.

1. Confirm scope, version, target maintenance branch, compatibility metadata,
   changelog, upgrade notice, and contributor credits. Audit `CHANGELOG.md`
   against the exact release range, move final entries under the version and
   release date, add release links, and create a fresh `Unreleased` section.
2. Update and test the canonical maintenance branch. Update trunk's stable
   metadata without replacing trunk's next-development version.
3. Build the exact candidate from the reviewed maintenance revision. Inspect the
   tree/POT, compare with the previous release, install the ZIP, and smoke-test.
4. Create the canonical source tag with `svn copy` from the exact recorded branch
   revision. Never manufacture the tag in GitHub.
5. Deploy only the verified `build/` contents to Plugin SVN. Keep its maintenance
   branch and trunk synchronized when that is the release convention, then make
   the deployment tag from the exact reviewed state.
6. Verify WordPress.org reports the version. Download the public ZIP and compare
   its checksum and file manifest with the candidate.
7. Verify activation and a front-end forum from the public ZIP.
8. Confirm Translate WordPress recognizes the source and the bundled POT exists.
9. Publish the Codex version page and Releases row, bbPress.org post, Downloads
   update, and any warranted support announcement. Publication is a separate
   explicit public mutation even when drafts were prepared earlier.
10. Confirm canonical branches/tags, Plugin SVN branches/trunk/tags, public ZIP,
    GitHub mirror refs, and Actions. Clean temporary checkouts and credentials.

Never infer that a release completed from one green surface. Source tagging,
deployment tagging, public ZIP propagation, mirror synchronization, and release
communications are distinct states and must each be verified.

## Security Work

Non-public vulnerability reports, reproductions, exploit details, reporter
identities, patches, tests, and coordination plans are confidential.

- Use a separate private checkout and private communication channel.
- Do not put confidential material in public Git branches, pull requests, Trac,
  Actions logs, terminal history, build artifacts, or tracked fixtures.
- Minimize reproductions and redact credentials, personal data, and unnecessary
  exploit detail.
- Do not query public services in a way that reveals an embargoed vulnerability.
- Coordinate affected branches, tests, packaging, disclosure, and publication
  before any public commit. Never publish or deploy early merely because a patch
  is ready.

## Documentation and Public Sites

Treat Codex and bbPress.org edits like code changes:

- capture the current content before editing;
- preserve tables, links, formatting, and historical release data;
- verify dates, version numbers, SVN revisions, and download URLs from primary
  sources;
- preview before publishing and review the live page afterward;
- do not assume an authenticated browser tab authorizes publication.

Release tables are historical records. Audit suspicious old values before
copying them into a new row.

## Completion Standard

A task is complete when the requested behavior is implemented or explained,
relevant tests pass, the final diff is reviewed, public mutations are verified,
and unrelated work remains untouched. Report:

- what changed and why;
- exact tests/checks and their outcomes;
- canonical revisions, ticket/PR links, or release URLs when applicable;
- remaining risks, skipped checks, mirror/propagation state, and follow-ups;
- whether each working copy is clean.

Do not call a task complete merely because a command exited zero. Verify the
user-visible or release-visible outcome.
