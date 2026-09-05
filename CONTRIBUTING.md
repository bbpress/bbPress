# Contributing to bbPress 2.6

Use [bbPress Trac](https://bbpress.trac.wordpress.org/) for bugs, patches, and
maintenance-release scope. Follow [SECURITY.md](SECURITY.md) for private security
reports. GitHub pull requests are review surfaces; accepted changes land in
canonical Subversion.

## Check out and build

Install Node.js 24, npm, PHP with mbstring, WP-CLI with its i18n command, and
Subversion. These are development tools; they do not change the plugin's runtime
requirements. Use a separate working copy for the maintenance branch:

```sh
svn checkout https://bbpress.svn.wordpress.org/branches/2.6 bbPress-2.6
cd bbPress-2.6
npm ci
php tests/ci/check-versions.php
npx grunt release
svn status
```

If you use nvm, run `nvm install` and `nvm use` before installing dependencies.
The build generates `build/`, including CSS, JavaScript, and the translation
template. Do not edit or commit that output to canonical source. Inspect source
changes after building; PostCSS can update source CSS.

## Verify the package

Install the resulting package in a disposable WordPress site. Activate bbPress
and a compatible classic theme, create a forum, topic, and reply, check the
counts, and verify the front end. The Build and Smoke Test workflow performs
these checks against an installed ZIP and uploads the ZIP only after success.

The legacy PHPUnit suite remains under `tests/phpunit/` and requires a compatible
WordPress test installation. This branch does not provide Composer dependencies,
wp-env, or `npm run test-php`; trunk's setup commands do not apply here. Record
the test environment and any coverage gaps with the patch.

## Prepare a backport

Normally fix and test the issue on trunk first, then prepare an intentional
backport for 2.6. Identify the Trac ticket, originating changeset, compatibility
impact, and intended maintenance release. Preserve public hooks and runtime
compatibility. Do not edit existing release tags or change versions as part of
routine tooling work.

Review the complete diff and proposed commit message before requesting a
canonical commit. Use unwrapped explanatory paragraphs and applicable `Props`,
`Fixes`, `See`, and `Merges` trailers following branch history.

The deployment workflow is maintained on trunk. A future 2.6 source tag must
include the compatible build tools and version checks before that workflow can
build it. Existing release tags remain immutable.

By contributing, you agree to license your work under the
[GNU General Public License version 2 or later](LICENSE).
