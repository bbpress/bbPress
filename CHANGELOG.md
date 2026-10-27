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

### Added

- Added configurable actions for blocked users, including cross-site forum
  blocking through a per-user default role, session invalidation, and global
  multisite spam controls. A network administrator's unblock clears propagated
  Blocked roles and spam status set by those actions.
- Added pending moderation counts to the Topics and Replies administration menus.
- Added configurable email notifications for Keymasters, Moderators, per-forum
  Moderators, and integrated recipient types when a new topic or reply is held
  for moderation.
- Added a Links setting to turn forum link moderation on or off and set a
  forum-specific limit, inheriting the WordPress Discussion setting until
  customized.
- Added filters to skip single-forum and single-topic description generation
  before their statistics are calculated.
- Added a repair tool to recalculate each forum's direct subforum count.
- Added 17 editor blocks for forum and topic indexes, forms, search, login,
  registration, password recovery, statistics, tags, views, forums, topics,
  and replies.
- Added user-role CSS classes to topic and reply author details.
- Added post-state labels for Pages used as forum and topic archives.
- Added hooks after bbPress administration classes load and dedicated hooks
  for canonical redirects.
- Added contributor, security, support, governance, and repository guidance.

### Changed

- Stop after finding the latest public topic during forum freshness
  recalculation.
- Added `ugc` alongside `nofollow` to links in topic and reply content.
- Kept the classic editor for forums, topics, and replies while making bbPress
  blocks available in Posts and Pages.
- Shortened new-content labels and administration metabox titles.
- Removed WordPress "Private" and "Protected" prefixes from bbPress content
  titles.
- Improved Akismet history descriptions when moderators override spam results.
- Updated the development and release workflow with wp-env, GitHub Actions,
  WP-CLI translation generation, installed-package smoke tests, and guarded
  WordPress.org deployment preparation.

### Security

- Avoided retaining incomplete forum visibility caches after database errors.
- Omitted password-protected content from BuddyPress activity queries.
- Prevented password-protected forum content from influencing search results.
- Required permission to manage both forums before moving a topic between them.
- Kept password-protected topic and reply content out of BuddyPress activity.
- Kept restricted attachment metadata out of alternate front-end and feed requests.
- Required validated forum destinations for front-end topic submissions.
- Enforced BuddyPress group posting permissions during topic and reply submission.
- Limited block editor topic-tag choices to public discussions in accessible forums.
- Enforced BuddyPress community visibility for group forum content.
- Prevented old topic slugs from redirecting visitors to topics they cannot read.
- Prevented old reply slugs from redirecting visitors to replies they cannot read.
- Prevented users from creating or moving forums, topics, and replies into
  parent content they cannot read.

### Fixed

- Repaired standalone list items beside implied HTML elements, SVG or MathML,
  and literal list-item examples without rewriting the original markup.
- Returned a 404 for non-scalar user profile query variables instead of
  triggering a PHP type error.
- Kept the reply editor's Forum and Reply To dropdowns synchronized with its
  Topic, and made Forum read-only.
- Wrapped standalone list items in straightforward topic and reply HTML on
  WordPress 6.9+, while preserving original markup and encoded text.
- Kept generated bbPress content available to excerpt-based templates without
  causing themes that display both excerpts and content to duplicate it.
- Restored topic titles on moderated reply lists for people who can read the
  reply and its topic.
- Preserved forum freshness metadata when its activity query fails.
- Prevented malformed and mixed-case requests below bbPress route roots from
  triggering broad permalink guess queries.
- Allowed canonical and 404 handling for bbPress routes with empty identifiers.
- Corrected relative registration times on user profiles when sites use a
  non-UTC timezone.
- Preserved original topic and reply content when the first logged edit is saved.
- Used the recorded editor when displaying topic and reply revision logs.
- Redirected merged topic URLs to their destination topics.
- Corrected parent-forum freshness when the newest topic belongs to a nested
  subforum.
- Returned a 404 instead of guessing unrelated permalinks for incomplete
  bbPress route roots.
- Kept BuddyPress group topics available when viewing later reply pages.
- Avoided unindexed relationship metadata scans when recounting forum and topic
  children.
- Removed topic and reply shortcodes from WordPress-generated feed content.
- Prevented stray post and Page query variables from misclassifying bbPress
  user routes.
- Avoided loading every public topic ID when forum freshness is recalculated.
- Preserved the original date of pending topics and replies across status changes.
- Fixed legacy user-option engagement lookups for numeric-string object IDs.
- Returned a 404 response for feed requests to unregistered views.
- Kept the Login widget logout link readable when themes style widget links and
  buttons with conflicting colors.
- Cache a distinct count of current-site forum-role holders for forum statistics,
  invalidating it when users or their capabilities change. Use the WordPress
  installation count for large-installation upgrade decisions.
- Show an ellipsis for long topic metadata in the default templates, revealing
  the full text when a link receives focus.
- Restored the Forums administration menu for moderators.
- Used the selected WordPress Page title for topic archives.
- Prevented duplicate forum-root breadcrumbs on archive subpages.
- Applied filtered topic permalinks in subscriptions and topic pagination.
- Prevented anonymous topics from incorrectly appearing empty.
- Respected filtered Keymaster checks in administration user suggestions.
- Scoped registered forum, topic, and reply metadata to the correct post type.
- Counted multibyte title characters correctly and improved UTF-8 display-name
  handling on modern WordPress and PHP versions.
- Prevented warnings when building BuddyPress member URLs and when bbPress
  runtime data is accessed before initialization.
- Prevented PHP warnings when building logout URLs without expected server
  variables.
- Prevented PHP deprecation notices in Akismet history and reply-position
  calculations.

## 2.6.20 - 2026-10-15

### Added

- Added an optional date format parameter to topic and reply post-date template functions.
- Added a site-wide forum status setting for open, closed, or frozen posting.

### Changed

- Sent Akismet the edit recheck signal for updated topics and replies.

### Security

- Kept bbPress post shortcodes literal in WordPress content loops.
- Strengthened password checks for topic and reply submissions.

### Fixed

- Prevented invalid engagement strategy options and filter values from causing
  startup failures or selecting the empty base implementation.
- Fixed component-specific rewrite callbacks so they receive the WordPress rewrite object instead of failing or being skipped.
- Corrected engagement cleanup return values for taxonomy storage and partial
  failures in legacy user-option storage.
- Kept front-end topic merge, topic split, and reply move action values
  consistent between request methods.
- Kept front-end moderation forms visible when validation fails during a
  submitted merge, split, or move request.
- Updated BuddyPress activity visibility queries to use the supported SQL
  clauses filter in BuddyPress 15 and newer.
- Kept forum and topic subscriptions separate when using the legacy user-meta
  engagement strategy.
- Kept validation notices and nonce checks scoped to the submitted form when
  multiple forum, topic, or reply forms share a page.
- Allowed authors to edit existing topics and replies after their topic or
  forum is closed and kept closed topics publicly readable, preserving the
  established new-content meaning of closing.
- Allowed multilingual plugins to filter Recent Topics widget queries for all forums.
- Handled malformed and numeric topic-view identifiers without PHP errors or
  broken view links.
- Required a username or email address before submitting the lost password form.
- Made default logout links return to the current page instead of the
  previous page.
- Imported FluxBB replies without duplicating topic starters and preserved the
  source forum and topic reply counts.
- Restored favorite and subscription removal from BuddyPress member profiles.
- Allowed the `bbp_is_topic_merge` filter to override detected merge requests.
- Restored specific body classes for topic merging and splitting, reply moving,
  current-user profile editing, and forum search results while preserving their
  existing general classes.
- Allowed forum and topic descriptions to use locale-aware count grammar.
- Completed every converter post-processing batch before reporting success,
  then flushed caches once after conversion.
- Kept pending topics and replies visible to their authors on bbPress profiles.
- Used locale-aware plural forms for grouped BuddyPress reply notifications.
- Sent deferred subscription notifications when moderated topics and replies
  first become public.
- Restored super moderator forum-role management for network users before their
  first visit when global access is enabled.
- Prevented flagged starter forum content from being recreated on later activation passes.
- Made pending BuddyPress group topics viewable to their authors and moderators
  inside the group forum.
- Kept BuddyPress group topic routes and page titles scoped to the requested topic.
- Prevented unknown views from falling back to unrestricted topic queries.
- Informed authors when newly submitted topics or replies are held for
  moderation.
- Restored controls for privileged users to manage another user's favorites and
  subscriptions.
- Kept BuddyPress profile engagement actions targeted to the displayed member.

- Fixed the topic-edit Replies metabox pagination for large topics and
  non-public replies.
- Restored the original subscription link nonce so AJAX handlers in third-party
  theme packages accept subscription toggles.
- Corrected reply position classes and repeated position lookups when replies
  are rendered outside their topic.
- Sent accurate author post counts to Akismet when members have at least 1,000
  combined topics and replies.
- Kept pending, spam, and trashed replies with their topic when merging topics.
- Kept BuddyPress group Forum navigation in sync with group settings under rewrite URLs.
- Restored private BuddyPress group forum access for members without a bbPress role and reduced queries for large restricted forum lists.
- Applied the public threaded-replies setting filter while preserving its legacy underscored hook.
- Avoided unnecessary post queries during BuddyPress group capability checks.
- Kept objects without assigned moderators from listing unrelated users.
- Used explicitly supplied topic tags in edit links and passed their slug to description filters.
- Prevented threaded reply ancestry from including an invalid parent and avoided redundant reply-position writes.
- Restored assigned moderator names in forum edit forms and corrected forum type and visibility dropdown behavior.
- Kept closed category ancestors from being ignored by forum status checks.
- Preserved topic subscriptions when topics and replies are edited in administration.

[Upgrade notes](https://codex.bbpress.org/releases/bbpress-2-6-20/)

[Release announcement](https://bbpress.org/blog/2026/10/bbpress-2-6-20-is-out/)

## 2.6.19 - 2026-09-29

### Security

- Respected inherited forum passwords in REST responses and BuddyPress activity.
- Applied inherited forum passwords to topic feeds, excerpts, and indirect
  forum content lookups.
- Kept non-public forum content out of public freshness metadata.
- Kept WordPress author discovery aligned with non-forum public posts.
- Prevented profile saves from silently changing multisite super admin privileges.
- Tightened forum and BuddyPress group topic visibility checks.
- Kept private BuddyPress group forums restricted when their group associations
  are removed.
- Removed BuddyPress group-specific capability mapping after group display.
- Excluded restricted forums from explicit forum ID queries.
- Limited super moderator profile edits to site members and protected peer
  forum roles.
- Checked destinations when topics and replies are moved in administration.
- Checked each target and assignable role in bulk forum role changes, while
  allowing site administrators to assign staff roles.
- Restricted XML-RPC publication and date changes, and REST topic edits in closed or category forums.
- Respected topic visibility for replies and their attachments in REST responses.
- Enforced edit windows on topic and reply form submissions.
- Respected BuddyPress group forum restrictions in descendant queries.
- Respected parent-forum restrictions in embedded content and feeds.
- Prevented API edits to bbPress-maintained count and activity metadata.
- Kept imported user creation opt-in, preserved network users during reset, and
  sanitized converted forum markup.
- Kept converted password hashes out of saved importer queries and protected
  source database credentials in converter settings.
- Used account identity for Akismet checks and omitted credential-named server
  fields while preserving extension-provided form and server data.
- Limited legacy password conversion to WordPress's submitted-password size
  boundary.
- Applied bbPress posting checks to API creation and restricted non-public
  count metadata.
- Hardened forum content editing, profile permissions, and update actions.
- Corrected access to forum statistics and feed output.
- Strengthened REST API checks for topic and reply edits.
- Tightened permissions for moderation actions and forum role assignment.
- Limited global super stickies and topic-tag management to the appropriate
  moderators.
- Restricted topic merges, topic splits, and reply moves to moderators of the
  affected content.
- Restricted single-item shortcodes, the replies widget, and BuddyPress group
  reply views to content the viewer can read.
- Excluded replies in non-public topics from search results, public profiles,
  and feeds.
- Restricted front-end favorites and subscriptions to post objects, and checked read permissions when adding them.
- Limited automatic forum upgrades to capable users viewing administration screens.
- Escaped filtered profile and administration labels, links, and attributes,
  and validated registered forum-role and theme-package settings.
- Validated reply parents and topic merge and split destinations before
  changing content.
- Validated source database prefixes before import and legacy password checks.

### Fixed

- Updated BuddyPress activity when topics and replies are edited with revision logging enabled.
- Avoided block editor choice queries when their target script is unavailable.
- Kept forum exclusions active in topic and reply queries using OR metadata conditions.
- Corrected topic edit-lock owner detection.
- Stopped prefilling login password fields from request data.
- Derived subscription email addresses from the configured site host.
- Restored content and title filters after subscription emails, including canceled sends.
- Prevented replies from receiving a position in an unrelated topic.
- Restored moderator access to eligible private-topic search results and
  all-reply profile views.
- Recovered first-login password upgrades for older PHPWind imports when the
  source database remains available.
- Corrected forum date mappings for Simple:Press 5 imports.
- Kept converter cleanup limited to imported forum posts.
- Preserved special characters in source database passwords saved for imports.
- Synchronized forum visibility options after changes.
- Corrected BuddyPress group forum associations and limited reply notifications
  to published replies.

Thanks to [thewindghost](https://hackerone.com/thewindghost),
[ngonhuy](https://hackerone.com/ngonhuy), and
[moltenbit](https://hackerone.com/moltenbit) for responsibly reporting issues
addressed in this release.

[Upgrade notes](https://codex.bbpress.org/releases/bbpress-2-6-19/)

[Release announcement](https://bbpress.org/blog/2026/09/bbpress-2-6-19-is-out/)

## 2.6.18 - 2026-09-21

### Changed

- Raised the minimum supported PHP version to 7.2.

### Fixed

- Corrected PHPWind topic statuses, reply counts, and forum dates during
  imports.
- Corrected user and first-login password data for new and repeated PHPWind 9
  imports.
- Prevented malformed SMF password metadata from causing first-login errors.
- Supported first-login password upgrades for legacy plaintext Vanilla 2
  passwords in new and repeated imports.
- Corrected first-login password upgrades for phpFox 3 imports.
- Corrected first-login password upgrades for IP.Board 3 punctuation passwords.
- Corrected password salt storage for new and repeated vBulletin 3 imports.
- Corrected password salt storage for new and repeated vBulletin 4 imports.
- Restored Super Moderator edit links on front-end user screens.
- Supported first-login password upgrades for native XenForo password formats.
- Corrected first-login password upgrades for Drupal 7 imports.
- Corrected first-login password upgrades for XMB imports.
- Corrected first-login password upgrades for e107 v1 imports.
- Corrected first-login password upgrades for Kunena 3 imports.
- Corrected first-login password upgrades for Kunena 2 imports.
- Corrected first-login password upgrades for Kunena 1 imports.
- Corrected first-login password upgrades for new and rerun AEF imports.
- Recovered first-login password upgrades for existing imports missing
  per-user converter metadata by using the saved importer platform.
- Allowed imported users to upgrade their passwords after the source database
  is no longer available.
- Allowed imported-user first-login upgrades with email addresses, slashed
  credentials, and the password `0` while rejecting malformed input.
- Supported first-login password upgrades for phpBB imports that use bcrypt,
  Argon2, or combined phpass and bcrypt hashes.
- Supported first-login password upgrades for MyBB imports.
- Supported first-login password upgrades for FluxBB imports.
- Supported first-login password upgrades for PunBB imports.
- Supported first-login password upgrades for Phorum imports.

[Upgrade notes](https://codex.bbpress.org/releases/bbpress-2-6-18/)

[Release announcement](https://bbpress.org/blog/2026/09/bbpress-2-6-18-is-out/)

## 2.6.17 - 2026-09-16

### Added

- Added a populated WordPress Playground preview for the Plugin Directory.
- Added Block Theme support while continuing to use bbPress PHP templates for
  classic and hybrid themes.
- Added `bbp_post_updated` with the post ID and before/after post objects.

### Changed

- Updated `form-user-roles.php` to honor field-level profile permissions. Themes
  overriding this template part should update their copy.
- Updated the 2.6 build, lint, test, translation, and installed-package smoke
  test tooling.
- Moved built-in topic, reply, forum, and user count synchronization to
  `bbp_transition_post_status`. Existing creation and moderation actions still
  fire, but no longer host bbPress's core count callbacks. Topic transitions
  apply the stored reply-count difference to forum aggregates instead of
  recounting every reply. Extensions that need finalized counts should use the
  transition action at priority 11 or later; permanent deletion continues
  through `bbp_deleted_topic` and `bbp_deleted_reply`.

### Security

- Scoped Super Moderator user editing to front-end bbPress profiles and added
  field-level profile permissions.
- Validated and filtered topic titles when converting replies or splitting
  topics.
- Prevented subscription emails from disclosing content to users who can no
  longer access a restricted forum.
- Hardened link relationship rewriting for topic and reply content.
- Normalized forum IDs used by administration list filters.
- Restricted forum hierarchy and attribute changes to users with the appropriate forum authority.
- Enforced bbPress posting and moderation rules for XML-RPC topic and reply
  edits.
- Enforced private and hidden forum read permissions in REST API responses.
- Enforced inherited private and hidden forum visibility across descendant
  content, queries, and activity metadata.
- Hardened topic-tag name output with consistent HTML escaping.
- Prevented topic-tag names from being interpreted as JavaScript in confirmation
  prompts.
- Escaped forum, topic, and reply titles in form fields.
- Prevented failed reply-move and topic-split authorization checks from changing
  content.
- Prevented unauthorized reply submissions from removing topic tags.

### Fixed

- Restored anonymous read capability mapping for public forum content.
- Allowed moderators to mark trashed replies as spam from the front end.
- Preserved forum visibility when restoring a forum from the trash.
- Prevented stale post caches during nested updates and repeated moderation.
- Preserved reply statuses when users are marked as spam and later restored.
- Kept public, hidden, and author contribution counts accurate when topics and
  replies change moderation status or are permanently deleted.
- Prevented simultaneous topic and reply submissions from overwriting each
  other's existing stored count changes.
- Kept forum, ancestor, contribution, engagement, and voice counts synchronized
  when moderators move replies or merge and split topics.
- Reconciled replacement-user contribution, engagement, and voice counts when
  a deleted user's posts are reassigned.
- Excluded replies beneath non-public topics from forum reply counts during
  moderation and repair recounts.
- Kept subforum counts and recursive forum aggregates accurate across public,
  private, hidden, trashed, moved, restored, and permanently deleted forums.
- Corrected count repair tools to clear stale metadata caches, exclude anonymous
  authors from voice counts, honor reply-status filters, rebuild private and
  hidden forums, and preserve similarly named metadata on other post types.
- Made topic engagement and voice recounts honor filtered public reply statuses.
- Preserved term-backed favorites and subscriptions during engagement recounts.
- Prevented BuddyPress integration from loading bbPress translations before
  WordPress initialization.

[Upgrade notes](https://codex.bbpress.org/releases/bbpress-2-6-17/)

[Release announcement](https://bbpress.org/blog/2026/09/bbpress-2-6-17-is-out/)

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
