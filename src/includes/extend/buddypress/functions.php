<?php

/**
 * Main bbPress BuddyPress Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Hooks
add_filter( 'bp_modify_page_title', 'bbp_filter_modify_page_title', 10, 3 );
add_filter( 'bbp_get_user_id',      'bbp_filter_user_id',           10, 3 );
add_filter( 'bbp_is_single_user',   'bbp_filter_is_single_user',    10, 1 );
add_filter( 'bbp_is_user_home',     'bbp_filter_is_user_home',      10, 1 );

// Group Forum Root
add_action( 'load-settings_page_bbpress', 'bbp_maybe_create_group_forum_root' );
add_action( 'bbp_delete_forum',           'bbp_maybe_delete_group_forum_root' );

/** BuddyPress Helpers ********************************************************/

/**
 * Return component name/ID ('forums' by default)
 *
 * This is used primarily for Notifications integration.
 *
 * @since 2.6.0 bbPress (r5232)
 *
 * @return string
 */
function bbp_get_component_name() {

	// Use existing ID or default
	$retval = ! empty( bbpress()->extend->buddypress->id )
		? bbpress()->extend->buddypress->id
		: 'forums';

	// Filter & return
	return apply_filters( 'bbp_get_component_name', $retval );
}

/**
 * Filter the current bbPress user ID with the current BuddyPress user ID
 *
 * @since 2.1.0 bbPress (r3552)
 *
 * @param int $user_id
 * @param bool $displayed_user_fallback
 * @param bool $current_user_fallback
 *
 * @return int User ID
 */
function bbp_filter_user_id( $user_id = 0, $displayed_user_fallback = true, $current_user_fallback = false ) {

	// Define local variable
	$bbp_user_id = 0;

	// Get possible user ID's
	$did = bp_displayed_user_id();
	$lid = bp_loggedin_user_id();

	// Easy empty checking
	if ( ! empty( $user_id ) && is_numeric( $user_id ) ) {
		$bbp_user_id = $user_id;

	// Currently viewing or editing a user
	} elseif ( ( true === $displayed_user_fallback ) && ! empty( $did ) ) {
		$bbp_user_id = $did;

	// Maybe fallback on the current_user ID
	} elseif ( ( true === $current_user_fallback ) && ! empty( $lid ) ) {
		$bbp_user_id = $lid;
	}

	return $bbp_user_id;
}

/**
 * Filter the bbPress is_single_user function with BuddyPress equivalent
 *
 * @since 2.1.0 bbPress (r3552)
 *
 * @param bool $is Optional. Default false
 * @return bool True if viewing single user, false if not
 */
function bbp_filter_is_single_user( $is = false ) {
	if ( ! empty( $is ) ) {
		return $is;
	}

	return bp_is_user();
}

/**
 * Filter the bbPress is_user_home function with BuddyPress equivalent
 *
 * @since 2.1.0 bbPress (r3552)
 *
 * @param bool $is Optional. Default false
 * @return bool True if viewing single user, false if not
 */
function bbp_filter_is_user_home( $is = false ) {
	if ( ! empty( $is ) ) {
		return $is;
	}

	return bp_is_my_profile();
}

/**
 * Add the topic title to the <title> if viewing a single group forum topic
 *
 * @since 2.5.0 bbPress (r5161)
 *
 * @param string $new_title The title to filter
 * @param string $old_title (Not used)
 * @param string $sep The separator to use
 * @return string The possibly modified title
 */
function bbp_filter_modify_page_title( $new_title = '', $old_title = '', $sep = '' ) {

	// Only filter if group forums are active
	if ( bbp_is_group_forums_active() ) {

		// Only filter for single group forum topics
		if ( bp_is_group_forum_topic() || bp_is_group_forum_topic_edit() ) {

			// Get the topic
			$topic = get_posts( array(
				'name'        => bp_action_variable( 1 ),
				'post_status' => array_keys( bbp_get_topic_statuses() ),
				'post_type'   => bbp_get_topic_post_type(),
				'numberposts' => 1
			) );

			// Add the topic title to the <title>
			$new_title .= bbp_get_topic_title( $topic[0]->ID ) . ' ' . $sep . ' ';
		}
	}

	// Return the title
	return $new_title;
}

/** BuddyPress Screens ********************************************************/

/**
 * Hook bbPress topics template into plugins template
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_screen_topics() {
	add_action( 'bp_template_content', 'bbp_member_forums_topics_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_topics', 'members/single/plugins' ) );
}

/**
 * Hook bbPress replies template into plugins template
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_screen_replies() {
	add_action( 'bp_template_content', 'bbp_member_forums_replies_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_replies', 'members/single/plugins' ) );
}

/**
 * Hook bbPress engagements template into plugins template
 *
 * @since 2.6.0 bbPress (r6320)
 */
function bbp_member_forums_screen_engagements() {
	add_action( 'bp_template_content', 'bbp_member_forums_engagements_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_engagements', 'members/single/plugins' ) );
}

/**
 * Hook bbPress favorites template into plugins template
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_screen_favorites() {
	add_action( 'bp_template_content', 'bbp_member_forums_favorites_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_favorites', 'members/single/plugins' ) );
}

/**
 * Hook bbPress subscriptions template into plugins template
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_screen_subscriptions() {
	add_action( 'bp_template_content', 'bbp_member_forums_subscriptions_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_subscriptions', 'members/single/plugins' ) );
}

/** BuddyPress Templates ******************************************************/

/**
 * Get the topics created template part
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_topics_content() {
?>

	<div id="bbpress-forums" class="bbpress-wrapper">

		<?php bbp_get_template_part( 'user', 'topics-created' ); ?>

	</div>

<?php
}

/**
 * Get the topics replied to template part
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_replies_content() {
?>

	<div id="bbpress-forums" class="bbpress-wrapper">

		<?php bbp_get_template_part( 'user', 'replies-created' ); ?>

	</div>

<?php
}

/**
 * Get the topic engagements template part
 *
 * @since 2.6.0 bbPress (r6320)
 */
function bbp_member_forums_engagements_content() {
?>

	<div id="bbpress-forums" class="bbpress-wrapper">

		<?php bbp_get_template_part( 'user', 'engagements' ); ?>

	</div>

<?php
}

/**
 * Get the topics favorited template part
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_favorites_content() {
?>

	<div id="bbpress-forums" class="bbpress-wrapper">

		<?php bbp_get_template_part( 'user', 'favorites' ); ?>

	</div>

<?php
}

/**
 * Get the topics subscribed template part
 *
 * @since 2.1.0 bbPress (r3552)
 */
function bbp_member_forums_subscriptions_content() {
?>

	<div id="bbpress-forums" class="bbpress-wrapper">

		<?php bbp_get_template_part( 'user', 'subscriptions' ); ?>

	</div>

<?php
}

/** Forum Group Root **********************************************************/

/**
 * Clean up the group root setting if the forum is being deleted
 *
 * @since 2.6.0 bbPress (r6479)
 *
 * @param int $forum_id The forum ID being deleted
 */
function bbp_maybe_delete_group_forum_root( $forum_id = 0 ) {

	// Bail if no forum ID
	$forum_id = bbp_get_forum_id();
	if ( empty( $forum_id ) ) {
		return;
	}

	// Get the group root
	$group_root = (int) get_option( '_bbp_group_forums_root_id', 0 );

	// Delete the group root if the forum just got deleted
	if ( $group_root === $forum_id ) {
		delete_option( '_bbp_group_forums_root_id' );
	}
}

/**
 * Handle the new group forum root creation
 *
 * @since 2.6.0 bbPress (r6479)
 *
 * @return
 */
function bbp_maybe_create_group_forum_root() {

	// Bail if no nonce
	if ( empty( $_GET['_wpnonce'] ) || ( empty( $_GET['create'] ) || ( 'bbp-group-forum-root' !== $_GET['create'] ) ) ) {
		return;
	}

	// Bail if user cannot publish forums
	if ( ! current_user_can( 'publish_forums' ) ) {
		return;
	}

	// Bail if nonce check fails
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], '_bbp_group_forums_root_id' ) ) {
		return;
	}

	// Create new forum
	$forum_id = bbp_insert_forum(

		// Post
		array( 'post_title' => esc_html__( 'Group Forums', 'bbpress' ) ),

		// Meta
		array( 'forum_type' => 'category' )
	);

	// Update & redirect
	if ( ! empty( $forum_id ) ) {

		// Create
		update_option( '_bbp_group_forums_root_id', $forum_id );

		// Redirect
		bbp_redirect( add_query_arg( array(
			'page'    => 'bbpress',
			'updated' => true
		), admin_url( 'options-general.php' ) ) );
	}
}

/** Forum/Group Sync **********************************************************/

/**
 * These functions are used to keep the many-to-many relationships between
 * groups and forums synchronized. Each forum and group stores pointers to each
 * other in their respective meta. This way if a group or forum is deleted
 * their associations can be updated without much effort.
 */

/**
 * Get forum ID's for a group
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_get_group_forum_ids( $group_id = 0 ) {

	// Assume no forums
	$forum_ids = array();

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Get the forums
	if ( ! empty( $group_id ) ) {
		$forum_ids = groups_get_groupmeta( $group_id, 'forum_id' );
	}

	// Make sure result is an array of ints
	$forum_ids = array_filter( wp_parse_id_list( $forum_ids ) );

	// Filter & return
	return (array) apply_filters( 'bbp_get_group_forum_ids', $forum_ids, $group_id );
}

/**
 * Get group ID's for a forum
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $forum_id
 */
function bbp_get_forum_group_ids( $forum_id = 0 ) {

	// Assume no forums
	$group_ids = array();

	// Use current group if none is set
	if ( empty( $forum_id ) ) {
		$forum_id = bbp_get_forum_id();
	}

	// Get the forums
	if ( ! empty( $forum_id ) ) {
		$group_ids = get_post_meta( $forum_id, '_bbp_group_ids', true );
	}

	// Make sure result is an array of ints
	$group_ids = array_filter( wp_parse_id_list( $group_ids ) );

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_group_ids', $group_ids, $forum_id );
}

/**
 * Get forum ID's for a group
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_update_group_forum_ids( $group_id = 0, $forum_ids = array() ) {

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Trim out any empties
	$forum_ids = array_filter( wp_parse_id_list( $forum_ids ) );

	// Get the forums
	return groups_update_groupmeta( $group_id, 'forum_id', $forum_ids );
}

/**
 * Update group ID's for a forum
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $forum_id
 */
function bbp_update_forum_group_ids( $forum_id = 0, $group_ids = array() ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Trim out any empties
	$group_ids = array_filter( wp_parse_id_list( $group_ids ) );

	// Get the forums
	return update_post_meta( $forum_id, '_bbp_group_ids', $group_ids );
}

/**
 * Add a group to a forum
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_add_group_id_to_forum( $forum_id = 0, $group_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Get current group IDs
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Maybe update the groups forums
	if ( ! in_array( $group_id, $group_ids, true ) ) {
		$group_ids[] = $group_id;
		return bbp_update_forum_group_ids( $forum_id, $group_ids );
	}
}

/**
 * Remove a forum from a group
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_add_forum_id_to_group( $group_id = 0, $forum_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Maybe update the groups forums
	if ( ! in_array( $forum_id, $forum_ids, true ) ) {
		$forum_ids[] = $forum_id;
		return bbp_update_group_forum_ids( $group_id, $forum_ids );
	}
}

/**
 * Remove a group from a forum
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_remove_group_id_from_forum( $forum_id = 0, $group_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Get current group IDs
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Maybe update the groups forums
	if ( in_array( $group_id, $group_ids, true ) ) {
		$group_ids = array_diff( array_values( $group_ids ), (array) $group_id );
		return bbp_update_forum_group_ids( $forum_id, $group_ids );
	}
}

/**
 * Remove a forum from a group
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_remove_forum_id_from_group( $group_id = 0, $forum_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Maybe update the groups forums
	if ( in_array( $forum_id, $forum_ids, true ) ) {
		$forum_ids = array_diff( array_values( $forum_ids ), (array) $forum_id );
		return bbp_update_group_forum_ids( $group_id, $forum_ids );
	}
}

/**
 * Remove a group from all forums
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $group_id
 */
function bbp_remove_group_id_from_all_forums( $group_id = 0 ) {

	// Use current group if none is set
	if ( empty( $group_id ) ) {
		$group_id = bp_get_current_group_id();
	}

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Loop through forums and remove this group from each one
	foreach ( $forum_ids as $forum_id ) {
		bbp_remove_group_id_from_forum( $group_id, $forum_id );
	}
}

/**
 * Remove a forum from all groups
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @param int $forum_id
 */
function bbp_remove_forum_id_from_all_groups( $forum_id = 0 ) {

	// Validate
	$forum_id  = bbp_get_forum_id( $forum_id );
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Loop through groups and remove this forum from each one
	foreach ( $group_ids as $group_id ) {
		bbp_remove_forum_id_from_group( $forum_id, $group_id );
	}
}

/**
 * Return true if a forum is a group forum
 *
 * @since 2.3.0 bbPress (r4571)
 *
 * @param int $forum_id
 * @return bool True if it is a group forum, false if not
 */
function bbp_is_forum_group_forum( $forum_id = 0 ) {

	// Validate
	$forum_id  = bbp_get_forum_id( $forum_id );

	// Check for group ID's
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Check if the forum has groups
	$retval    = (bool) ! empty( $group_ids );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_group_forum', $retval, $forum_id, $group_ids );
}

/*** Group Member Status ******************************************************/

/**
 * Is the current user an admin of the current group
 *
 * @since 2.3.0 bbPress (r4632)
 *
 * @return bool If current user is an admin of the current group
 */
function bbp_group_is_admin() {

	// Bail if user is not logged in or not looking at a group
	if ( ! is_user_logged_in() || ! bp_is_group() ) {
		return false;
	}

	$bbp = bbpress();

	// Set the global if not set
	if ( ! isset( $bbp->current_user->is_group_admin ) ) {
		$bbp->current_user->is_group_admin = groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() );
	}

	// Return the value
	return (bool) $bbp->current_user->is_group_admin;
}

/**
 * Is the current user a moderator of the current group
 *
 * @since 2.3.0 bbPress (r4632)
 *
 * @return bool If current user is a moderator of the current group
 */
function bbp_group_is_mod() {

	// Bail if user is not logged in or not looking at a group
	if ( ! is_user_logged_in() || ! bp_is_group() ) {
		return false;
	}

	$bbp = bbpress();

	// Set the global if not set
	if ( ! isset( $bbp->current_user->is_group_mod ) ) {
		$bbp->current_user->is_group_mod = groups_is_user_mod( bp_loggedin_user_id(), bp_get_current_group_id() );
	}

	// Return the value
	return (bool) $bbp->current_user->is_group_mod;
}

/**
 * Is the current user a member of the current group
 *
 * @since 2.3.0 bbPress (r4632)
 *
 * @return bool If current user is a member of the current group
 */
function bbp_group_is_member() {

	// Bail if user is not logged in or not looking at a group
	if ( ! is_user_logged_in() || ! bp_is_group() ) {
		return false;
	}

	$bbp = bbpress();

	// Set the global if not set
	if ( ! isset( $bbp->current_user->is_group_member ) ) {
		$bbp->current_user->is_group_member = groups_is_user_member( bp_loggedin_user_id(), bp_get_current_group_id() );
	}

	// Return the value
	return (bool) $bbp->current_user->is_group_member;
}

/**
 * Is the current user banned from the current group
 *
 * @since 2.3.0 bbPress (r4632)
 *
 * @return bool If current user is banned from the current group
 */
function bbp_group_is_banned() {

	// Bail if user is not logged in or not looking at a group
	if ( ! is_user_logged_in() || ! bp_is_group() ) {
		return false;
	}

	$bbp = bbpress();

	// Set the global if not set
	if ( ! isset( $bbp->current_user->is_group_banned ) ) {
		$bbp->current_user->is_group_banned = groups_is_user_banned( bp_loggedin_user_id(), bp_get_current_group_id() );
	}

	// Return the value
	return (bool) $bbp->current_user->is_group_banned;
}

/**
 * Is the current user the creator of the current group
 *
 * @since 2.3.0 bbPress (r4632)
 *
 * @return bool If current user the creator of the current group
 */
function bbp_group_is_creator() {

	// Bail if user is not logged in or not looking at a group
	if ( ! is_user_logged_in() || ! bp_is_group() ) {
		return false;
	}

	$bbp = bbpress();

	// Set the global if not set
	if ( ! isset( $bbp->current_user->is_group_creator ) ) {
		$bbp->current_user->is_group_creator = groups_is_user_creator( bp_loggedin_user_id(), bp_get_current_group_id() );
	}

	// Return the value
	return (bool) $bbp->current_user->is_group_creator;
}

/* BuddyPress Activity Action Callbacks ***************************************/

/**
 * Return an array of allowed activity actions
 *
 * @since 2.6.0 bbPress (r6370)
 *
 * @return array
 */
function bbp_get_activity_actions() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_activity_actions', array(
		'topic' => esc_html__( '%1$s started the topic %2$s in the forum %3$s',    'bbpress' ),
		'reply' => esc_html__( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' )
	) );
}

/**
 * Generic function to format the dynamic BuddyPress activity action for new
 * topics/replies.
 *
 * @since 2.6.0 bbPress (r6370)
 *
 * @param string               $type     The type of post. Expects `topic` or `reply`.
 * @param string               $action   The current action string.
 * @param BP_Activity_Activity $activity The BuddyPress activity object.
 *
 * @return string The formatted activity action.
 */
function bbp_format_activity_action_new_post( $type = '', $action = '', $activity = false ) {

	// Get actions
	$actions = bbp_get_activity_actions();

	// Bail early if we don't have a valid type
	if ( ! in_array( $type, array_keys( $actions ), true ) ) {
		return $action;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( __FUNCTION__, func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Groups component
	if ( 'groups' === $activity->component ) {
		if ( 'topic' === $type ) {
			$topic_id = bbp_get_topic_id( $activity->secondary_item_id );
			$forum_id = bbp_get_topic_forum_id( $topic_id );
		} else {
			$topic_id = bbp_get_reply_topic_id( $activity->secondary_item_id );
			$forum_id = bbp_get_topic_forum_id( $topic_id );
		}

	// General component (bbpress/forums/other)
	} else {
		if ( 'topic' === $type ) {
			$topic_id = bbp_get_topic_id( $activity->item_id );
			$forum_id = bbp_get_forum_id( $activity->secondary_item_id );
		} else {
			$topic_id = bbp_get_topic_id( $activity->secondary_item_id );
			$forum_id = bbp_get_topic_forum_id( $topic_id );
		}
	}

	// User link for topic author
	$user_link = bbp_get_user_profile_link( $activity->user_id );

	// Topic link
	$topic_permalink = bbp_get_topic_permalink( $topic_id );
	$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
	$topic_link      = '<a href="' . esc_url( $topic_permalink ) . '">' . esc_html( $topic_title ) . '</a>';

	// Forum link
	$forum_permalink = bbp_get_forum_permalink( $forum_id );
	$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );
	$forum_link      = '<a href="' . esc_url( $forum_permalink ) . '">' . esc_html( $forum_title ) . '</a>';

	// Format
	$activity_action = sprintf( $actions[ $type ], $user_link, $topic_link, $forum_link );

	/**
	 * Filters the formatted activity action new activity string.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @param string               $activity_action Activity action string value
	 * @param string               $type            The type of post. Expects `topic` or `reply`.
	 * @param string               $action          The current action string.
	 * @param BP_Activity_Activity $activity        The BuddyPress activity object.
	 */
	return apply_filters( 'bbp_format_activity_action_new_post', $activity_action, $type, $action, $activity );
}

/**
 * Formats the dynamic BuddyPress activity action for new topics.
 *
 * @since 2.6.0 bbPress (r6370)
 *
 * @param string $action   The current action string
 * @param object $activity The BuddyPress activity object
 *
 * @return string The formatted activity action.
 */
function bbp_format_activity_action_new_topic( $action, $activity ) {
	$action = bbp_format_activity_action_new_post( bbp_get_topic_post_type(), $action, $activity );

	/**
	 * Filters the formatted activity action new topic string.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @param string               $action   Activity action string value
	 * @param BP_Activity_Activity $activity Activity item object
	 */
	return apply_filters( 'bbp_format_activity_action_new_topic', $action, $activity );
}

/**
 * Formats the dynamic BuddyPress activity action for new replies.
 *
 * @since 2.6.0 bbPress (r6370)
 *
 * @param string $action   The current action string
 * @param object $activity The BuddyPress activity object
 *
 * @return string The formatted activity action
 */
function bbp_format_activity_action_new_reply( $action, $activity ) {
	$action = bbp_format_activity_action_new_post( bbp_get_reply_post_type(), $action, $activity );

	/**
	 * Filters the formatted activity action new reply string.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @param string               $action   Activity action string value
	 * @param BP_Activity_Activity $activity Activity item object
	 */
	return apply_filters( 'bbp_format_activity_action_new_reply', $action, $activity );
}
