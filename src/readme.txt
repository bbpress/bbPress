=== bbPress ===
Contributors:      matt, johnjamesjacoby, jmdodd, netweb, sergeybiryukov
Tags:              forum, forums, discussion, support
License:           GNU General Public License v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      5.6.20
Requires at least: 6.0
Tested up to:      7.1
Stable tag:        2.6.15

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

== Developer Notes ==

= Count updates in 2.6.16 =

bbPress now synchronizes its built-in public and hidden topic and reply counts, aggregate forum counts, and user contribution counts on `bbp_transition_post_status` at priority 10, after WordPress persists the new post status. This action receives the new status, old status, and `WP_Post` object.

The existing `bbp_new_*`, `bbp_insert_*`, `bbp_trash_*`, `bbp_untrash_*`, `bbp_spam_*`, `bbp_unspam_*`, `bbp_approve_*`, and `bbp_unapprove_*` topic and reply actions continue to fire with their existing arguments and timing. Their corresponding completed actions, such as `bbp_trashed_*`, also remain available. The public count helper functions also remain callable.

bbPress no longer attaches its built-in increase, decrease, and insertion count callbacks to those creation and moderation actions. Manually firing one of those actions without changing the post status therefore no longer updates counts. Extensions that need finalized counts after creation or moderation should use `bbp_transition_post_status` at priority 11 or later.

Permanent deletion does not produce a post-status transition. Its count maintenance continues through the existing `bbp_deleted_topic` and `bbp_deleted_reply` actions.

= Subforum counts in 2.6.16 =

bbPress now maintains subforum counts when forums are trashed, restored, permanently deleted, or moved between parents. The new `bbp_post_updated` action receives the post ID, the updated `WP_Post` object, and the previous `WP_Post` object after any bbPress post type is updated.

== Screenshots ==

1. Forums - Admin Interface
2. Topics - Admin Interface
3. Replies - Admin Interface
4. Settings - Admin Interface
5. Settings 2 - Admin Interface
6. Themes - Admin Interface
7. Single Forum - Default Theme

== Changelog ==

Check out the [releases page](https://codex.bbpress.org/releases/)

== Upgrade Notice ==

= 2.6.16 =

Count maintenance now uses the bbPress post-status transition action. Extensions that customize topic or reply counts should review the Developer Notes.
