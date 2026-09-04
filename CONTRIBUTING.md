# Contributing to bbPress

Thank you for helping improve forum software for WordPress.

## Choose the right channel

- Use [bbPress Trac](https://bbpress.trac.wordpress.org/) for reproducible bugs,
  enhancements, patches, and development discussion.
- Use the [support forums](https://bbpress.org/forums/) for installation,
  configuration, compatibility, and troubleshooting questions.
- Use [Translate WordPress](https://translate.wordpress.org/projects/wp-plugins/bbpress/)
  for translations.
- Follow [SECURITY.md](SECURITY.md) for vulnerabilities. Never disclose a
  vulnerability in Trac, GitHub, or a public forum.

Search existing Trac tickets before opening a new one. A useful report includes
the affected bbPress, WordPress, and PHP versions; precise reproduction steps;
the expected result; the actual result; and any relevant multisite, theme,
plugin, role, or permission context.

## Understand the repository model

[Canonical Subversion](https://bbpress.svn.wordpress.org/) is the source of
truth. GitHub is a mirror used for code review and CI. Trac is the authoritative
issue tracker.

GitHub pull requests are welcome when they make review or automated testing
easier, but they must reference a Trac ticket. An accepted change is committed
to canonical Subversion and then arrives back on GitHub through the mirror. Do
not expect a pull request to be merged directly into the GitHub `trunk` branch.

Plugin SVN is only for built WordPress.org release packages. Never develop
against it or copy its generated contents into canonical source.

## Development setup

You will need PHP, Composer, Node.js, npm, and Docker. Install the pinned
dependencies and start the repository’s WordPress environment:

```sh
composer install
npm ci
npm run wp-env start
```

The plugin’s authoritative runtime source is under `src/`. Root `bbpress.php`
is a development loader, and `build/` is generated release output.

Read the [Contributor and Agent Handbook](AGENTS.md) before changing code. It
contains the detailed repository map, compatibility rules, test expectations,
canonical commit workflow, and release procedure.

## Make a focused change

1. Create or select a Trac ticket and confirm its target milestone.
2. Reproduce the problem with the smallest deterministic case available.
3. Change the narrowest reasonable surface while preserving public hooks,
   filters, return shapes, and capability behavior.
4. Add a regression test when practical. If a test is not feasible, document
   precise manual verification steps.
5. Add a concise entry under `Unreleased` in [CHANGELOG.md](CHANGELOG.md) for a
   notable user-visible change. Do not disclose embargoed security details.
6. Run the focused test, then the applicable broader checks.
7. Review the complete diff for generated churn, credentials, debug output,
   unrelated formatting, and accidental version changes.
8. Attach a patch to Trac or open a linked GitHub pull request for review.

New features normally target `trunk`. Fixes intended for a supported patch
release need an intentional, separately reviewed maintenance-branch backport.
Do not edit release tags.

## Test your work

Run the main PHP suite inside the development environment:

```sh
npm run test-php
```

Run checks appropriate to the changed files:

```sh
composer lint
npx grunt eslint:grunt eslint:core
npx grunt stylelint
npx grunt checktextdomain
php tests/ci/check-versions.php
```

Build or release-tooling changes also require a release build and an installed
package smoke test:

```sh
npx grunt release
```

The GitHub Actions workflows under `.github/workflows/` define the current CI
matrix. A green build is required, but it does not replace review of security,
permissions, compatibility, extension points, or data changes.

## Patches and pull requests

Keep each contribution small enough to understand and revert. Include:

- the Trac ticket URL and intended milestone;
- the user-visible outcome and why the change is needed;
- compatibility or extension-point considerations;
- automated tests added or changed;
- exact commands run and manual verification performed;
- screenshots for visible interface changes;
- anything important that remains untested.

Do not include generated release packages, local configuration, credentials,
private vulnerability details, or unrelated cleanup.

AI-assisted contributions are welcome, but the contributor remains responsible
for understanding the change, reviewing every line, testing it, and disclosing
security issues privately.

## Coding and attribution

Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
and the repository’s `phpcs.xml.dist`. Preserve the `bbpress` text domain and
regenerate the POT when translatable strings change.

Contributors receive props in canonical commit history. Use the WordPress.org
username by which you want to be credited on the Trac ticket.

By contributing, you agree that your contribution is licensed under the
[GNU General Public License version 2 or later](LICENSE).
