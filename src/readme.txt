=== bbPress ===
Contributors: matt, johnjamesjacoby, jmdodd, netweb, sergeybiryukov
Tags: forums, discussion, support, theme, akismet, multisite
Requires at least: 4.7
Tested up to: 5.3
Stable tag: 2.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

bbPress is forum software for WordPress.

== Description ==

Are you looking for a timeless, elegant, and streamlined discussion board? bbPress is easy to integrate, easy to use, and is built to scale with your growing community.

bbPress is intentionally simple yet infinitely powerful forum software, built by contributors to WordPress.

== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'
2. Search for 'bbPress'
3. Activate bbPress from your Plugins page. (You will be greeted with a Welcome page.)

= From WordPress.org =

1. Download bbPress.
2. Upload the 'bbpress' directory to your '/wp-content/plugins/' directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate bbPress from your Plugins page. (You will be greeted with a Welcome page.)

= Once Activated =

1. Visit 'Forums > Add New' and create some forums. (You can always delete these later.)
2. If you have pretty permalinks enabled, visit example.com/forums/, or if you do not have pretty permalinks enabled, visit example.com?post_type=forum
3. Visit 'Settings > Forums' and configure the settings to best match the needs of your community.
4. Visit 'Tools > Forums > Import Forums' if you have an existing forum to convert to bbPress.

= Once Configured =

* bbPress comes with a robust theme-compatibility API that does its best to make bbPress look and feel right with just-about any WordPress theme. You may need to adjust some styling on your own to make everything look pristine.
* You may want to customize the register/activation/sign-in/lost-password flows, to better suit your site. bbPress comes with a bevy of shortcodes to make this possible, listed here: https://codex.bbpress.org/shortcodes/
* bbPress also comes with built-in support for Akismet and BuddyPress, two very popular and very powerful WordPress plugins. If you're using either, visit your Forum Settings page and ensure that integration appears correct.

== Screenshots ==

1. Forums - Admin Interface
2. Topics - Admin Interface
3. Replies - Admin Interface
4. Settings - Admin Interface
5. Settings 2 - Admin Interface
6. Themes - Admin Interface
7. Single Forum - Default Theme

== Changelog ==

= 2.6.0 =
* Released on November 12, 2019

* Per-Forum Moderation
* Engagements API
* Support for PHP 7.1 and higher
* Improved Importer (and more platforms!)
* Improved Voices, Favorites, Subscriptions
* Improved Topic & Reply moderation UI
* Improved Item Counts
* Improved Upgrades
* Improved Tools
* Improved Admin-area Experience
* 420 total improvements

= 2.5.14 =
* Released on September 9, 2016

* Improved support for PHP 7.1 and higher
* Improved pagination for non-public post statuses
* Fixed converter row-limit boundaries

= 2.5.13 =
* Released on May 18, 2017

* Improved support for `post_parent__in` type queries

= 2.5.12 =
* Released on December 8, 2016

* Bump minimum WordPress version to 4.7
* Use 'wp_roles_init' action for dynamic roles

= 2.5.11 =
* Released on September 9, 2016

* Improved support for WordPress 4.7
* Improved localization of role names
* Increase minimum WordPress version to 4.2

= 2.5.10 =
* Released on July 13, 2016

* Improved user display-name rendering

= 2.5.9 =
* Released on May 2, 2016

* Improved user mentions

= 2.5.8 =
* Released on July 7, 2015

* Improved ajax actions
* Improved user query parsing
* Fixed BuddyPress Groups notice

= 2.5.7 =
* Released on April 20, 2015

* Improved output of certain URLs

= 2.5.6 =
* Released on March 16, 2015

* Improved notification & subscription email sending

= 2.5.5 =
* Released on March 6, 2015

* Improved bulk editing of users
* Improved suggesting of topics & authors for moderators
* Improved converter tool access

= 2.5.4 =
* Released on June 6, 2014

* Fixed reply editing causing polluted hierarchy
* Added tool for repairing reply positions within topics
* Improved custom slug and displayed user field sanitization
* Improved SSL support when relying on theme compatibility

= 2.5.3 =
* Released on January 11, 2014

* WordPress 3.8 support (dashicons, new color schemes)
* Fixed dropdown selects in settings pages
* Fixed accidental topic subscription removal on reply form
* Fixed poor grammar in profile title element
* Fixed admin area SSL support

= 2.5.2 =
* Released on December 27, 2013

* Fixed BuddyPress (1.9.1) Notification integration

= 2.5.1 =
* Released on December 3, 2013

* Updated subscriptions setting description
* Fixed forum subscriptions not appearing on profiles for some users
* Allow links to have targets
* Improved Windows compatibility

= 2.5.0 =
* Released on November 25, 2013

* Added forum subscriptions
* Added importers for AEF, Drupal, FluxBB, Kunena Forums (Joomla), MyBB, Phorum, PHPFox, PHPWind, PunBB, SMF, Xenforo and XMB
* Added BuddyPress Notifications integration
* Added ability to enqueue scripts and styles in the template stack
* Fixed various existing importer scripts
* Fixed forum visibility meta saving
* Fixed Akismet anonymous user meta checking
* Fixed inconsistent bbp_dropdown() results
* Fixed topic and reply ping-status inconsistencies

= 2.4.1 =
* Released on October 10, 2013

* Fixed forum status saving
* Fixed widget settings saving
* Fixed custom wp_title compatibility
* Fixed search results custom permalink compatibility
* Fixed custom user topics & replies pages
* Fixed hierarchical reply handling in converter

= 2.4.0 =
* Released on August 24, 2013

* Added hierarchical reply support
* Added ability to disable forum search
* Reorganized settings page
* Improved rewrite rules
* Improved responsive CSS
* Improved code posting
* Improved user capability integration
* Improved cache getting and setting
* Audit strict type comparisons
* Audit GlotPress string escaping
* Audit title attribute usage
* Audit WordPress core function usage
* General code clean-up

= 2.3.2 =
* Released on May 6, 2013

* Improved posting of preformatted code
* Improved theme compatibility CSS
* Improved BuddyPress Activity Streams integration

= 2.3.1 =
* Released on April 26, 2013

* Improved posting of preformatted code
* Fixed deleting of post cache group
* Fixed moderators not having view_trash capability

= 2.3.0 =
* Released on April 13, 2013

* Added forum search functionality
* Improved BuddyPress Group Forums integration
* Improved allowed tags in topics and replies
* Added template stack support to theme compatability
* Added more forum migration options

= 2.2.4 =
* Released on January 24, 2013

* Prepare converter queries
* Improved validation and sanitization of form values

= 2.2.3 =
* Released on December 11, 2012

* Improved compatibility with some themes
* Fixed integration with BuddyPress Group Forums
* Fixed BuddyPress Activity Stream integration

= 2.2.2 =
* Released on November 23, 2012

* RTL and i18n fixes
* Improved user profile theme compatibility
* Fixed incorrect link in credits page
* Fixed admin area JS issues related to topic suggest
* Fixed template part reference in extras user edit template

= 2.2.1 =
* Released on November 19, 2012

* Fixed role mapping for non-WordPress roles
* Fixed issue with private forums being blocked
* Allow moderators to see hidden forums

= 2.2.0 =
* Released on November 9, 2012

* Improved user roles and capabilities
* Improved theme compatibility
* Improved BuddyPress Group Forums integration
* Improved forums conversion tool
* Improved forums tools and settings
* Improved multisite support
* Added What's New and Credits pages
* WordPress 3.5 and BuddyPress 1.7 ready

= 2.1.2 =
* Released on July 31, 2012

* Fixed admin-side help verbiage
* Fixed reply height CSS
* Fixed password converter
* Fixed child post trash and delete functions

= 2.1.1 =
* Released on July 23, 2012

* Fixed Invision, phpBB, and vBulletin importers
* Fixed private/hidden forum bugs
* Fixed topic split meta values
* Fixed theme compatibility logic error
* Fixed role mask issues for shared user installs
* Fixed missing function cruft
* Fixed missing filter on displayed user fields

= 2.1.0 =
* Released on July 8, 2012

* WordPress 3.4 compatibility
* Deprecate $bbp global, use bbpress() singleton
* Private forums now visible to registered users
* Updated forum converter
* Topic and reply edits now ran through Akismet
* Fixed Akismet edit bug
* Fixed Widgets nooping globals
* Fixed translation load order
* Fixed user-edit bugs
* Fixed settings screen regressions
* Improved post cache invalidation
* Improved admin-side nonce checks
* Improved admin settings API
* Improved bbPress 1.1 converter
* Improved BuddyPress integration
* Improved Theme-Compatibility
* Improved template coverage
* Improved query performance
* Improved breadcrumb behavior
* Improved multisite integration
* Improved code clarity
* Improved RTL styling
* Added 2x menu icons for HiDPI displays
* Added fancy editor support
* Added fallback theme picker
* Added tools for importing, resetting, and removing

= 2.0.2 =
* Released on November 28, 2011

= 2.0.1 =
* Released on November 23, 2011

= 2.0.0 =
* Released on September 21, 2011

* Initial plugin release <3
* BuddyPress @mention integration
* BuddyPress activity action integration
* GlotPress integration
* Multisite integration
* Akismet integration
* RTL support
* More future proofing internal API's
* Green admin color scheme for WordPress 3.2
* Audit usage of get strings for moderator level and above users
* Normalize theme, shortcodes, and template parts
* Added strict moderation support
* Added actions to topic/reply forms
* Added Forum Participant role for multisite use
* Added humans.txt
* Added empty index.php files to prevent snooping
* Added max length to topic titles (default 80 chars)
* Added home link support to breadcrumb
* Added filters for future anti-spam support
* Added missing breadcrumbs to various template files
* Improved templates and CSS
* Improved Theme Compatibility
* Improved Theme Compat class
* Improved Akismet user agent handling
* Improved support for future ajaxification
* Improved unpretty permalink support
* Improved importer
* Improved multisite support
* Fixed Genesis incompatibilities
* Fixed BuddyPress activity stream issues
* Fixed Subscription email sending issues
* Fixed Theme Compat display issues for some themes
* Fixed spam/deleted user handling
* Fixed activation/deactivation
* Fixed a bushel of bugs
* Fixed tag pagination again
* Fixed ajax priority loading
* Fixed tag pagination
* Fixed regression in forum index theme compatibility template
* Fixed replies within wp-admin
* Fixed reply notification links
* Fixed inconsistent breadcrumb behavior
* Fixed theme compatibility issues
* Fixed archive and page conflicts
* Fixed forum archive bug
* Fixed and improvements to importer
* Fixed topic/reply trash
