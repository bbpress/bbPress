## Trac ticket

<!-- A Trac ticket is required because Trac is bbPress's authoritative issue tracker. -->

https://bbpress.trac.wordpress.org/ticket/

## What changed and why

<!-- Describe the user-visible outcome, the problem it solves, and important implementation choices. -->

## Verification

- [ ] I added or updated tests for changed behavior, or explained why a test is not practical.
- [ ] I ran the focused test and listed the exact command and result below.
- [ ] I ran the applicable PHPUnit, PHPCS, JavaScript, CSS, text-domain, version, or build checks.
- [ ] I manually tested the affected roles, permissions, themes, plugins, multisite modes, or WordPress versions where relevant.
- [ ] I reviewed the complete diff for generated churn, debug output, credentials, and unrelated changes.
- [ ] I updated the `Unreleased` section of `CHANGELOG.md` for a notable user-visible change, or this change does not need an entry.

<!-- List commands, results, manual steps, and anything that remains untested. -->

## Compatibility and release impact

- [ ] Existing public hooks, filters, accepted arguments, return shapes, and capability behavior remain compatible, or the intentional change is documented.
- [ ] I identified the intended milestone and whether a maintenance-branch backport is needed.
- [ ] I updated documentation, translatable strings/POT expectations, version metadata, and release notes where applicable.
- [ ] This change does not include generated release packages or edits to release tags.

## Visual evidence

<!-- Add before/after screenshots for visible changes. Remove this section when it is not applicable. -->

## Use of AI tools

<!-- AI-assisted contributions are welcome, but you remain responsible for understanding, reviewing, and testing the result. If AI tools materially assisted this contribution, name the tools and models and briefly describe how they were used. See https://make.wordpress.org/ai/handbook/ai-guidelines/ -->

---

This pull request is a review and CI surface. bbPress maintainers commit accepted
changes to canonical Subversion first; the result then returns to GitHub through
the mirror. Do not merge or push authoritative refs directly on GitHub.
