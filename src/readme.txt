=== bbPress ===
Contributors:      matt, johnjamesjacoby, jmdodd, netweb, sergeybiryukov
Tags:              forum, forums, discussion, support
License:           GNU General Public License v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      7.2
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

For topic and reply creation, the status transition occurs inside `wp_insert_post()`. The later `bbp_new_*` and `bbp_insert_*` actions therefore continue to run after status counts are updated and after bbPress stores its relationship metadata. Creation-specific integrations can remain on those actions.

For moderation, status-count synchronization occurs between the existing pre-transition action, such as `bbp_spam_reply`, and its completed action, such as `bbp_spammed_reply`. Extensions that need every persisted topic, reply, forum, and user status-count change should use `bbp_transition_post_status` at priority 11 or later. Completed moderation actions begin after those counts are finalized.

bbPress no longer attaches its built-in topic, reply, forum, and user status-count callbacks to the creation and moderation actions. Manually firing one of those actions without changing the post status therefore no longer updates those counts. Topic voice counts and engagements remain attached to the creation and completed moderation actions, so manually firing one of those actions can still recalculate voices and engagements without a status change. The priority-11 transition hook does not observe those later updates. Extensions that need both recalculations to finish can use priority 31 or later on the legacy action; bbPress runs engagement callbacks before voice-count callbacks. Count membership comes from the filtered public and non-public status arrays. Extensions that already maintain counts for custom statuses should remove duplicate count callbacks and keep the public and non-public arrays disjoint. The public increase and decrease convenience functions now validate the current topic or reply status; use the lower-level bump functions for an intentional numeric difference. Do not manually fire `bbp_transition_post_status` to simulate a write because its count callback trusts the supplied statuses as a completed database transition.

Permanent deletion does not produce a post-status transition. Its count maintenance continues through the existing `bbp_deleted_topic` and `bbp_deleted_reply` actions.

Count bump functions now use conditional metadata writes and bounded retries so simultaneous requests do not overwrite each other's existing count changes. Existing bbPress count filters and the standard WordPress metadata filters and actions continue to run. A before-update metadata action may run more than once when a request loses a comparison and retries; its matching after-update action runs only for the successful write. The update-metadata short-circuit filter runs once for the bump call using its first sanitized candidate value. A user-count filter that changes the absolute result retains the previous absolute-update behavior and opts that call out of the atomic difference path.

WordPress metadata tables do not enforce unique object-and-key pairs, so simultaneous first-time inserts retain the same limitation as the core metadata API. A request can also exhaust the bounded retries under unusually high contention. An atomic bump applies a difference to its supplied stored or default value and is not a recount. The `bbp_pre_bump_count_meta` filter can short-circuit an update, the `bbp_bump_count_meta_max_attempts` filter controls the default limit of five write attempts, and the `bbp_bump_count_meta_types` filter controls the post, user, term, and comment metadata types supported by default. `bbp_update_user_topic_count()` and `bbp_update_user_reply_count()` accept an optional third `$difference` argument for this internal bump lifecycle; existing calls remain compatible.

Post-author changes and user deletion with post reassignment now reconcile affected user contribution, topic engagement, and voice counts. Moderator move, merge, and split operations also reconcile source, destination, and ancestor forum counts. Forum count updater functions accept an optional final argument for propagating a recount's difference through ancestor totals; existing calls remain compatible. Forum reply recounts include public replies only when their parent topics are also public. A public reply beneath a non-public topic is excluded from the public forum total without being included in the pending, spammed, and trashed reply count. Topic engagement recounts honor filtered public topic and reply statuses, and preserve other term-backed relationships.

= Subforum counts in 2.6.16 =

bbPress now maintains subforum counts when forums are trashed, restored, permanently deleted, or moved between parents. Recursive forum counts include public, private, and hidden subforums while excluding subforums with uncountable statuses. `bbp_forum_query_subforum_ids()` no longer inherits the broader `bbp_get_all_child_ids` result; extensions that customized subforum count membership through that lower-level filter should use `bbp_forum_query_subforum_ids`, `bbp_get_countable_forum_statuses`, or the forum-status filters. The new `bbp_post_updated` action receives the post ID, the updated `WP_Post` object, and the previous `WP_Post` object after any bbPress post type is updated.

The upgrade does not synchronously recount every forum. Sites with known stale metadata can selectively run the applicable count and engagement tools under Tools > Forums > Repair Forums. These tools can be expensive on large sites.

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

Count maintenance now uses the bbPress post-status transition action and conditional metadata writes. Extensions that attach count callbacks to creation or moderation actions, add custom statuses, filter count values, or customize subforum queries should review the Developer Notes. Sites with known stale counts can selectively run the repair tools after upgrading.
