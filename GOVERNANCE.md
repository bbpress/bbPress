# Project Governance

bbPress is open-source forum software made by the WordPress community. This
document describes where project decisions and authoritative records live.

## Project leadership and participation

The project leads and committers steward technical direction, compatibility,
security response, releases, and access to project systems. Contributors shape
bbPress by reporting and reproducing bugs, proposing and testing patches,
improving documentation, translating the plugin, helping in support, and
participating in technical discussion.

Current contributors and project roles are listed on the
[bbPress About page](https://bbpress.org/about/). Commit access is earned through
sustained, constructive participation and demonstrated care for compatibility,
security, review, and the project’s release process.

## Decisions and records

Routine technical decisions are made in public on
[bbPress Trac](https://bbpress.trac.wordpress.org/), informed by patches,
testing, review, project history, user impact, and maintainer capacity. Larger
or difficult-to-reverse proposals should document the problem, desired outcome,
alternatives, compatibility impact, migration needs, and maintenance cost.

When priorities conflict, bbPress generally favors:

1. site security and predictable capability behavior;
2. backward compatibility and stable extension points;
3. simple, focused behavior over unnecessary complexity;
4. WordPress integration and established project conventions;
5. maintainable implementation with reproducible tests;
6. explicit limitations over silent partial behavior.

Maintainers may defer or decline a technically valid contribution when it does
not fit project direction, compatibility requirements, or available maintenance
capacity.

## Repository authority

- Canonical source, branches, and tags live in
  [bbPress Subversion](https://bbpress.svn.wordpress.org/).
- Tickets and milestones live in [bbPress Trac](https://bbpress.trac.wordpress.org/).
- GitHub is a mirror and review/CI surface; it is not the release origin.
- Plugin SVN receives verified build artifacts for WordPress.org releases; it
  is not a development repository.

Accepted changes are committed to canonical Subversion before appearing in the
GitHub mirror. Release tags are created from canonical source, and official
packages are deployed separately to the WordPress.org Plugin Directory.

## Reviews and automation

Changes require review and testing proportional to risk. Security, permission,
query visibility, data migration, compatibility, build, and release changes
receive additional scrutiny.

Automation may lint, test, build candidate packages, and prepare reviewable
changes. It does not grant itself approval, commit authority, deployment
authority, or publication authority. A green CI result supports human review;
it does not replace it.

## Releases

Only designated maintainers may publish official releases. Source tagging,
Plugin Directory deployment, public package propagation, mirror synchronization,
and release communications are separate states and must each be verified.

## Community standards

Participation follows [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Security reports
follow [SECURITY.md](SECURITY.md) and must remain private until coordinated
disclosure.

Changes to this governance document should be discussed on Trac and approved by
project leadership before being committed to canonical Subversion.

