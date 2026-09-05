# bbPress 2.6 Maintenance Guidance

This file applies to the entire 2.6 maintenance branch. Read
[CONTRIBUTING.md](CONTRIBUTING.md) for the branch's actual setup and build commands.
The [trunk handbook](https://bbpress.svn.wordpress.org/trunk/AGENTS.md) describes
project authority, review, compatibility, and release policy; its Composer,
wp-env, and PHPUnit setup commands do not apply to this legacy checkout.

- Preserve modified and untracked files. Use a clean, dedicated Subversion
  working copy for approved canonical commits and releases.
- Canonical source is `https://bbpress.svn.wordpress.org/branches/2.6`.
  GitHub is a mirror. Plugin SVN contains built deployment artifacts only.
- Identify the ticket, originating trunk changeset, intended maintenance release,
  and compatibility impact before preparing a backport.
- Inspect the exact diff and present the complete proposed message before every
  public mutation. Obtain explicit maintainer approval before committing,
  tagging, deploying, modifying Trac, or publishing.
- Never expose passwords or authentication tokens in arguments, files, logs,
  patches, or chat. Use approved credential helpers or interactive tools.
- Preserve runtime compatibility and public extension points. Never edit release
  tags or copy built Plugin SVN files into canonical source.
- Run relevant tests; build-tool changes require a full release build and an
  installed-package smoke test. Report failures and missing infrastructure.
- Keep commit summaries concise, ending with a period. Aim for about 50
  characters and stop at 70. Do not hard-wrap explanatory paragraphs. Use actual
  line feeds and verify the recorded log after committing.
- Dependabot configuration and deployment orchestration are maintained on trunk.
  Mirror pull requests are advisory; accepted updates land via Subversion.

For practices not covered here, consult the relevant sections of the
[WordPress Core Contributor Handbook](https://make.wordpress.org/core/handbook/)
and [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).
Branch-specific guidance and established bbPress conventions take precedence.
