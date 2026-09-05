# bbPress

[![PHPUnit Tests](https://github.com/bbpress/bbPress/actions/workflows/test.yml/badge.svg?branch=trunk)](https://github.com/bbpress/bbPress/actions/workflows/test.yml)
[![Lint](https://github.com/bbpress/bbPress/actions/workflows/lint.yml/badge.svg?branch=trunk)](https://github.com/bbpress/bbPress/actions/workflows/lint.yml)
[![Build and Smoke Test](https://github.com/bbpress/bbPress/actions/workflows/build-smoke.yml/badge.svg?branch=trunk)](https://github.com/bbpress/bbPress/actions/workflows/build-smoke.yml)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

[bbPress](https://bbpress.org/) is forum software for WordPress. It is focused
on simplicity, flexibility, deep WordPress integration, web standards, and
speed.

> [!IMPORTANT]
> This GitHub repository is a mirror of the canonical bbPress Subversion
> repository. Trac is the project’s issue tracker, and official releases are
> published through WordPress.org. See [Repository authority](#repository-authority)
> before contributing or releasing.

## Get bbPress

Install the latest stable release from the
[WordPress.org Plugin Directory](https://wordpress.org/plugins/bbpress/) or
search for **bbPress** under **Plugins → Add New** in WordPress.

Code on `trunk` is under active development and is not intended for production
sites. Browse released source tags in
[canonical Subversion](https://bbpress.svn.wordpress.org/tags/).

## Project links

| Resource | Purpose |
| --- | --- |
| [bbPress.org](https://bbpress.org/) | Project news, downloads, and community |
| [Documentation](https://codex.bbpress.org/) | User and developer documentation |
| [Support forums](https://bbpress.org/forums/) | Installation, configuration, and troubleshooting help |
| [bbPress Trac](https://bbpress.trac.wordpress.org/) | Bugs, enhancements, patches, and milestones |
| [Canonical Subversion](https://bbpress.svn.wordpress.org/) | Authoritative source, branches, and tags |
| [Translate WordPress](https://translate.wordpress.org/projects/wp-plugins/bbpress/) | Hosted translations |
| [WordPress.org Plugin Directory](https://wordpress.org/plugins/bbpress/) | Stable package and release metadata |

## Repository authority

The systems that make up the project have distinct roles:

- `bbpress.svn.wordpress.org` is the canonical development repository.
- `bbpress.trac.wordpress.org` is the authoritative issue and milestone tracker.
- `github.com/bbpress/bbPress` mirrors canonical Subversion for code review and
  continuous integration.
- `plugins.svn.wordpress.org/bbpress` contains built deployment artifacts and
  is not a development repository.

Do not push authoritative branches or tags directly to GitHub, and do not copy
deployed Plugin Directory files back into development history.

## Contributing

Start with [CONTRIBUTING.md](CONTRIBUTING.md). Search Trac before reporting a
bug, describe a reproducible user outcome, and attach a focused patch when
possible. GitHub pull requests are welcome as a review and CI surface when they
reference a Trac ticket, but maintainers land accepted changes in canonical
Subversion first.

The detailed [Contributor and Agent Handbook](AGENTS.md) documents repository
structure, compatibility expectations, testing, canonical commits, and release
operations.

Never report a vulnerability publicly. Follow [SECURITY.md](SECURITY.md) for
private reporting. Support questions belong in the channels listed in
[SUPPORT.md](SUPPORT.md).

## Development

bbPress requires WordPress 6.0 or newer and PHP 7.2 or newer at runtime. Local
development also requires Node.js, npm, Composer, and Docker for the repository’s
WordPress environment.

Use Node.js 24, matching `.nvmrc` and CI. Follow the
[development setup walkthrough](CONTRIBUTING.md#development-setup) to check out
trunk, install dependencies, open the local site, verify bbPress, run a focused
test, and stop the environment. The same guide lists the
[quality checks](CONTRIBUTING.md#test-your-work) to run for your change.

Build a release-shaped package with:

```sh
npx grunt release
```

The build requires WP-CLI with `wp i18n` and the PHP `mbstring` extension. Build
output is written to `build/`; it is generated and must not be edited or
committed by hand.

## Community

- Participation follows the [WordPress Community Code of Conduct](CODE_OF_CONDUCT.md).
- Project roles and decision-making are described in [GOVERNANCE.md](GOVERNANCE.md).
- Recent user-visible changes are summarized in the [Changelog](CHANGELOG.md),
  with complete historical release records on the
  [bbPress Releases page](https://codex.bbpress.org/releases/).

## License

bbPress is free software, released under the
[GNU General Public License version 2 or later](LICENSE). The
[bbPress license page](https://bbpress.org/about/license/) explains how the
license applies to the project.
