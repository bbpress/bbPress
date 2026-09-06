# Changelog

This file records notable user-visible changes to bbPress. Development details,
patches, and milestones remain authoritative in
[bbPress Trac](https://bbpress.trac.wordpress.org/).

The detailed history begins with bbPress 2.0.0, the first release of bbPress as
a WordPress plugin. Earlier standalone releases are preserved in the
[canonical Subversion tags](https://bbpress.svn.wordpress.org/tags/).

## Unreleased

Development for the next bbPress release is in progress. See the active
[Trac milestones](https://bbpress.trac.wordpress.org/roadmap) for planned work.

### Fixed

- Prevented BuddyPress integration from loading bbPress translations before
  WordPress initialization.

## 2.6.15 - 2026-09-03

### Security

- Strengthened authorization around topic and reply editing, topic splitting
  and merging, user profile updates, and private or hidden forum visibility.
- Hardened imported password verification.

### Fixed

- Prevented PHP warnings on singular forum requests without a post ID.
- Improved handling of mixed post-type queries without exposing protected forum
  content.
- Avoided deprecated UTF-8 handling on modern WordPress releases.

[Release announcement](https://bbpress.org/blog/2026/09/bbpress-2-6-15-is-out/)

## 2.6.14 - 2025-07-02

### Changed

- Improved Akismet cleanup performance and compatibility with BuddyPress 12.0
  and PHP 8.2.
- Made moderation matching case-insensitive for non-Latin text.
- Improved BuddyPress notification links and member URLs.

### Fixed

- Corrected multiword and malformed search handling.
- Prevented invalid query values from causing PHP errors.
- Corrected forum visibility exclusions, reply-to links, hidden topic counts,
  feed author filtering, and several low-probability fatal-error paths.

[Release announcement](https://bbpress.org/blog/2025/07/bbpress-2-6-14-is-out/)

## 2.6.13 - 2025-04-16

### Fixed

- Restored replies that could disappear from some topics after WordPress 6.8
  began normalizing post-type arrays.

[Release announcement](https://bbpress.org/blog/2025/04/bbpress-2-6-13-is-out/)

## 2.6.12 - 2025-02-26

### Security

- Prevented the profile role-update handler from running during new-user
  registration.

### Fixed

- Restored public statuses to the default forum search query.

[Release announcement](https://bbpress.org/blog/2025/02/bbpress-2-6-12-is-out/)

## 2.6.11 - 2024-06-29

### Changed

- Improved HTML escaping in topic and reply forms.
- Updated deprecated WordPress moderation terminology and compatibility code.

### Fixed

- Prevented public, logged-out requests from being incorrectly handled as 404s.
- Prevented several PHP notices and deprecated notices.

[Release announcement](https://bbpress.org/blog/2024/06/bbpress-2-6-11-is-out/)

## 2.6.10 - 2024-06-28

### Changed

- Improved topic and reply status updates, statistics, repair tools, converter
  authentication, subscription email subjects, and no-JavaScript styling.
- Raised the minimum supported WordPress version to 6.0 and updated plugin
  headers and compatibility with current PHP releases.

### Fixed

- Prevented hidden forums from appearing in search results and unregistered
  views from trapping RSS feeds.
- Corrected moderation status restoration, status URLs, reply-list markup,
  allowed-HTML escaping, admin layouts, and numerous PHP notices.

This release introduced a public-query regression corrected in 2.6.11.

## 2.6.9 - 2021-11-29

### Fixed

- Improved Akismet cleanup routines to prevent debug notices.

[Release announcement](https://bbpress.org/blog/2021/11/bbpress-2-6-9-is-out/)

## 2.6.8 - 2021-11-19

### Fixed

- Limited the `no-js` body class to bbPress pages, correcting a regression in
  2.6.7 that affected other site pages.

[Release announcement](https://bbpress.org/blog/2021/11/bbpress-2-6-8-released/)

## 2.6.7 - 2021-11-17

### Changed

- Improved Akismet and BuddyPress integration.
- Allowed moderators to reply to unapproved topics.

### Fixed

- Corrected the hierarchical replies interface and other issues across 19 Trac
  tickets.

[Release announcement](https://bbpress.org/blog/2021/11/bbpress-2-6-7-is-out/)

## 2.6.6 - 2020-11-05

### Changed

- Improved several moderator workflows.

### Fixed

- Corrected PHP warnings and notices, output formatting bugs, and other issues
  across 22 Trac tickets.

[Release announcement](https://bbpress.org/blog/2020/11/bbpress-2-6-6/)

## 2.6.5 - 2020-05-28

### Security

- Corrected privilege-escalation issues involving new-user registration and the
  Super Moderator feature.
- Prevented a self-XSS issue in the Forums list table.

### Fixed

- Corrected search-result escaping, PHP warnings and notices, typographical
  errors, and plugin headers.

[Release announcement](https://bbpress.org/blog/2020/05/bbpress-2-6-5-is-out/)

## 2.6.4 - 2020-01-30

### Security

- Prevented BuddyPress group members from saving topics to invalid forum IDs.

### Changed

- Improved compatibility with PHP 7.2 and newer.

### Fixed

- Corrected performance degradation on databases using the 2.5 schema, user
  email updates, PHP notices, and typographical errors.

[Release announcement](https://bbpress.org/blog/2020/01/bbpress-2-6-4-is-out/)

## 2.6.3 - 2019-12-10

### Changed

- Raised the required WordPress version to 5.3.

### Fixed

- Corrected the Forums widget order, a JavaScript error with hierarchical
  replies, and several typographical and grammatical errors.

[Release announcement](https://bbpress.org/blog/2019/12/bbpress-2-6-3-is-out/)

## 2.6.2 - 2019-11-22

### Fixed

- Restored hierarchical replies when the visual editor is enabled.
- Restored custom `bbpress.css` theme-compatibility styles in affected themes.
- Restored BuddyPress notifications, made the edit URL slug configurable, and
  corrected a repair-tool typo.

[Release announcement](https://bbpress.org/blog/2019/11/bbpress-2-6-2-is-out/)

## 2.6.1 - 2019-11-14

### Fixed

- Restored subforums missing from their parent forum listings.
- Corrected styling in bundled WordPress themes and restored administration-area
  posting when other plugins interact with the REST API.

[Release announcement](https://bbpress.org/blog/2019/11/bbpress-2-6-1-is-out/)

## 2.6.0 - 2019-11-12

### Added

- Added per-forum moderators and a new Engagements API.

### Changed

- Improved importers and platform coverage, voices, favorites, subscriptions,
  topic and reply moderation, item counts, upgrades, repair tools, and the
  administration experience.
- Added support for PHP 7.1 and newer.
- Completed 420 improvements over the 2.5 release line.

[Release announcement](https://bbpress.org/blog/2019/11/bbpress-2-6-better-great-than-never/)

## 2.5.14 - 2017-09-09

### Changed

- Improved support for PHP 7.1 and newer and pagination of non-public post
  statuses.

### Fixed

- Corrected converter row-limit boundaries and notices in the Topics and
  Replies widgets.

## 2.5.13 - 2017-05-18

### Changed

- Improved support for `post_parent__in` queries.

## 2.5.12 - 2016-12-09

### Changed

- Raised the minimum supported WordPress version to 4.7.
- Initialized dynamic forum roles from the WordPress roles initialization
  action.

### Fixed

- Restored missing bbPress administration menu items on WordPress 4.7.

[Release announcement](https://bbpress.org/blog/2016/12/bbpress-2-5-12-requires-wordpress-4-7/)

## 2.5.11 - 2016-11-09

### Changed

- Added support for WordPress 4.7, improved localization of role names, and
  raised the minimum supported WordPress version to 4.2.

[Release announcement](https://bbpress.org/blog/2016/11/bbpress-2-5-11-maintenance-release/)

## 2.5.10 - 2016-07-13

### Security

- Added escaping for user display names where names and avatars are displayed
  together.

[Release announcement](https://bbpress.org/blog/2016/07/bbpress-2-5-10-security-release/)

## 2.5.9 - 2016-05-02

### Security

- Corrected a cross-site scripting issue in user-profile links created from
  topic and reply mentions.

[Release announcement](https://bbpress.org/blog/2016/05/bbpress-2-5-9-security-bugfix-release/)

## 2.5.8 - 2015-07-07

### Changed

- Improved AJAX actions and user-query parsing.

### Fixed

- Prevented a notice in BuddyPress Groups integration.

## 2.5.7 - 2015-04-20

### Fixed

- Corrected the output of several URLs.

## 2.5.6 - 2015-03-16

### Fixed

- Improved notification and subscription email delivery.

## 2.5.5 - 2015-03-06

### Changed

- Improved bulk user editing, topic and author suggestions for moderators, and
  access to the converter tool.

## 2.5.4 - 2014-06-06

### Added

- Added a repair tool for reply positions within topics.

### Fixed

- Prevented reply editing from corrupting reply hierarchy.
- Improved sanitization of custom slugs and displayed-user fields and improved
  SSL support in theme compatibility.

## 2.5.3 - 2014-01-11

### Changed

- Added WordPress 3.8 support, including Dashicons and new administration color
  schemes.

### Fixed

- Corrected settings dropdowns, accidental subscription removal from reply
  forms, profile title grammar, and administration-area SSL support.

## 2.5.2 - 2013-12-27

### Fixed

- Corrected BuddyPress 1.9.1 Notifications integration.

The release is documented in the historical readme and release archive, but a
corresponding canonical source tag is not present.

## 2.5.1 - 2013-12-03

### Changed

- Clarified the subscriptions setting and allowed links to specify targets.

### Fixed

- Restored forum subscriptions missing from some user profiles and improved
  Windows compatibility.

## 2.5.0 - 2013-11-25

### Added

- Added forum subscriptions and BuddyPress Notifications integration.
- Added importers for AEF, Drupal, FluxBB, Kunena, MyBB, Phorum, PHPFox,
  PHPWind, PunBB, SMF, XenForo, and XMB.
- Allowed scripts and styles to be enqueued from the template stack.

### Fixed

- Improved existing importers, forum-visibility metadata, anonymous Akismet
  checks, dropdown results, and topic and reply ping statuses.

## 2.4.1 - 2013-10-10

### Fixed

- Corrected forum status and widget-setting persistence.
- Improved custom title, search permalink, and user topic and reply pages.
- Corrected hierarchical reply handling in the converter.

## 2.4.0 - 2013-08-24

### Added

- Added hierarchical replies and an option to disable forum search.

### Changed

- Reorganized settings and improved rewrite rules, responsive CSS, code
  posting, capabilities, and cache handling.
- Audited strict comparisons, translated-string escaping, title attributes,
  and WordPress core API usage.

## 2.3.2 - 2013-05-06

### Changed

- Improved preformatted code posting, theme-compatibility CSS, and BuddyPress
  Activity Streams integration.

## 2.3.1 - 2013-04-26

### Fixed

- Improved preformatted code posting and post-cache invalidation.
- Restored the `view_trash` capability for moderators.

## 2.3.0 - 2013-04-13

### Added

- Added forum search, template-stack support for theme compatibility, and more
  forum migration options.

### Changed

- Improved BuddyPress Group Forums integration and allowed HTML in topics and
  replies.

## 2.2.4 - 2013-01-24

### Changed

- Prepared converter queries and improved validation and sanitization of form
  values.

## 2.2.3 - 2012-12-11

### Fixed

- Improved theme compatibility and corrected BuddyPress Group Forums and
  Activity Streams integration.

## 2.2.2 - 2012-11-23

### Fixed

- Added RTL and internationalization corrections.
- Improved user-profile theme compatibility and corrected the credits link,
  topic-suggestion JavaScript, and a user-edit template reference.

## 2.2.1 - 2012-11-19

### Fixed

- Corrected mapping for non-WordPress roles and access to private forums.
- Allowed moderators to see hidden forums.

## 2.2.0 - 2012-11-09

### Added

- Added What's New and Credits screens.

### Changed

- Improved roles and capabilities, theme compatibility, BuddyPress Group
  Forums, conversion and repair tools, settings, and multisite support.
- Added compatibility with WordPress 3.5 and BuddyPress 1.7.

## 2.1.3 - 2012-11-09

### Fixed

- Corrected a `post_excerpt` conflict with Jetpack 2.0, theme-compatibility CSS,
  non-Latin imported slugs, and an index used by topic queries.

This tagged maintenance release is absent from the historical Codex release
table.

## 2.1.2 - 2012-07-31

### Fixed

- Corrected administration help text, reply-height CSS, password conversion,
  and child-post trash and delete behavior.

## 2.1.1 - 2012-07-23

### Fixed

- Corrected the Invision, phpBB, and vBulletin importers.
- Corrected private and hidden forums, split-topic metadata, theme compatibility,
  shared-user role masks, and displayed-user filters.

## 2.1.0 - 2012-07-08

### Added

- Added visual-editor support, a fallback theme picker, HiDPI menu icons, and
  tools for importing, resetting, and removing forum data.

### Changed

- Added WordPress 3.4 compatibility and moved from the `$bbp` global to the
  `bbpress()` singleton.
- Made private forums visible to registered users and sent topic and reply edits
  through Akismet.
- Improved converters, BuddyPress and multisite integration, theme
  compatibility, templates, query performance, caching, breadcrumbs, RTL
  styling, nonce checks, and the settings API.

### Fixed

- Corrected Akismet editing, widget globals, translation loading, user editing,
  and settings regressions.

## 2.0.3 - 2012-06-13

### Changed

- Confirmed compatibility with WordPress 3.4.

### Fixed

- Corrected single forums after changing visibility between public, private,
  and hidden and corrected a hierarchical forum capability check.

[Release announcement](https://bbpress.org/blog/2012/06/bbpress-2-0-3/)

This tagged maintenance release is absent from the historical Codex release
table.

## 2.0.2 - 2011-11-28

### Fixed

- Corrected forum and topic freshness recounting, non-administrator post
  editing, topic closing, and template redirects for logged-in users.

[Release announcement](https://bbpress.org/blog/2011/11/bbpress-2-0-2/)

## 2.0.1 - 2011-11-13

### Security

- Prevented logged-out users from editing existing topics and replies when
  anonymous posting is enabled.

[Release announcement](https://bbpress.org/blog/2011/11/bbpress-2-0-1/)

## 2.0.0 - 2011-09-21

### Added

- Released bbPress as a WordPress plugin.
- Added BuddyPress mentions and activity actions, GlotPress, multisite, Akismet,
  and RTL integration.
- Added strict moderation, topic and reply form actions, a multisite Forum
  Participant role, topic-title limits, breadcrumb home links, anti-spam
  filters, and missing template breadcrumbs.
- Added administration styling for WordPress 3.2 and defensive `index.php` files.

### Changed

- Improved templates, CSS, theme compatibility, the importer, multisite,
  unpretty permalinks, Akismet user-agent handling, AJAX foundations, and
  internal APIs.
- Normalized themes, shortcodes, and template parts and audited moderator-facing
  query-string usage.

### Fixed

- Corrected Genesis and BuddyPress activity compatibility, subscription email
  delivery, theme-compatibility rendering, spammed and deleted users,
  activation and deactivation, tag pagination, administration replies,
  notification links, breadcrumbs, archives, forum archives, and topic and
  reply trash behavior.

## Earlier standalone releases

bbPress existed as a standalone forum application before it became a WordPress
plugin in 2.0.0. Releases from 0.7.2 through 1.2.1 are preserved in the
[canonical Subversion tags](https://bbpress.svn.wordpress.org/tags/). Their
historical readmes do not contain a cumulative changelog, so this file does not
invent summaries for them.
