<?php

/**
 * Main bbPress BuddyPress Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 * @todo maybe move to BuddyPress Forums once bbPress 1.1 can be removed
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** BuddyPress Helpers ********************************************************/

/**
 * Filter the current bbPress user ID with the current BuddyPress user ID
 *
 * @since bbPress (r3552)
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
	if ( !empty( $user_id ) && is_numeric( $user_id ) )
		$bbp_user_id = $user_id;

	// Currently viewing or editing a user
	elseif ( ( true == $displayed_user_fallback ) && !empty( $did ) )
		$bbp_user_id = $did;

	// Maybe fallback on the current_user ID
	elseif ( ( true == $current_user_fallback ) && !empty( $lid ) )
		$bbp_user_id = $lid;

	return $bbp_user_id;
}
add_filter( 'bbp_get_user_id', 'bbp_filter_user_id', 10, 3 );

/**
 * Filter the bbPress is_single_user function with BuddyPress eqivalent
 *
 * @since bbPress (r3552)
 *
 * @param bool $is Optional. Default false
 * @return bool True if viewing single user, false if not
 */
function bbp_filter_is_single_user( $is = false ) {
	if ( !empty( $is ) )
		return $is;

	return bp_is_user();
}
add_filter( 'bbp_is_single_user', 'bbp_filter_is_single_user', 10, 1 );

/**
 * Filter the bbPress is_user_home function with BuddyPress eqivalent
 *
 * @since bbPress (r3552)
 *
 * @param bool $is Optional. Default false
 * @return bool True if viewing single user, false if not
 */
function bbp_filter_is_user_home( $is = false ) {
	if ( !empty( $is ) )
		return $is;

	return bp_is_my_profile();
}
add_filter( 'bbp_is_user_home', 'bbp_filter_is_user_home', 10, 1 );

/** BuddyPress Screens ********************************************************/

/**
 * Hook bbPress topics template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_topics() {
	add_action( 'bp_template_content', 'bbp_member_forums_topics_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_topics', 'members/single/plugins' ) );
}

/**
 * Hook bbPress replies template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_replies() {
	add_action( 'bp_template_content', 'bbp_member_forums_replies_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_replies', 'members/single/plugins' ) );
}

/**
 * Hook bbPress favorites template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_favorites() {
	add_action( 'bp_template_content', 'bbp_member_forums_favorites_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_favorites', 'members/single/plugins' ) );
}

/**
 * Hook bbPress subscriptions template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_subscriptions() {
	add_action( 'bp_template_content', 'bbp_member_forums_subscriptions_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_subscriptions', 'members/single/plugins' ) );
}

/** BuddyPress Templates ******************************************************/

/**
 * Get the topics created template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()s
 */
function bbp_member_forums_topics_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'topics-created' ); ?>

	</div>

<?php
}

/**
 * Get the topics replied to template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_replies_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'replies-created' ); ?>

	</div>

<?php
}

/**
 * Get the topics favorited template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_favorites_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'favorites' ); ?>

	</div>

<?php
}

/**
 * Get the topics subscribed template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_subscriptions_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'subscriptions' ); ?>

	</div>

<?php
}

/** Forum/Group Sync **********************************************************/

/**
 * These functions are used to keep the many-to-many relationships between
 * groups and forums synchronized. Each forum and group stores ponters to each
 * other in their respective meta. This way if a group or forum is deleted
 * their associattions can be updated without much effort.
 */

/**
 * Get forum ID's for a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_get_group_forum_ids( $group_id = 0 ) {

	// Assume no forums
	$forum_ids = array();

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get the forums
	if ( !empty( $group_id ) )
		$forum_ids = groups_get_groupmeta( $group_id, 'forum_id' );

	// Make sure result is an array
	if ( !is_array( $forum_ids ) )
		$forum_ids = (array) $forum_ids;

	// Trim out any empty array items
	$forum_ids = array_filter( $forum_ids );

	return (array) apply_filters( 'bbp_get_group_forum_ids', $forum_ids, $group_id );
}

/**
 * Get group ID's for a forum
 *
 * @param type $forum_id
 * @since bbPress (r3653)
 */
function bbp_get_forum_group_ids( $forum_id = 0 ) {

	// Assume no forums
	$group_ids = array();

	// Use current group if none is set
	if ( empty( $forum_id ) )
		$forum_id = bbp_get_forum_id();

	// Get the forums
	if ( !empty( $forum_id ) )
		$group_ids = get_post_meta( $forum_id, '_bbp_group_ids', true );

	// Make sure result is an array
	if ( !is_array( $group_ids ) )
		$group_ids = (array) $group_ids;

	// Trim out any empty array items
	$group_ids = array_filter( $group_ids );

	return (array) apply_filters( 'bbp_get_forum_group_ids', $group_ids, $forum_id );
}

/**
 * Get forum ID's for a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_update_group_forum_ids( $group_id = 0, $forum_ids = array() ) {

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Trim out any empties
	$forum_ids = array_filter( $forum_ids );

	// Get the forums
	return groups_update_groupmeta( $group_id, 'forum_id', $forum_ids );
}

/**
 * Update group ID's for a forum
 *
 * @param type $forum_id
 * @since bbPress (r3653)
 */
function bbp_update_forum_group_ids( $forum_id = 0, $group_ids = array() ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Trim out any empties
	$group_ids = array_filter( $group_ids );

	// Get the forums
	return update_post_meta( $forum_id, '_bbp_group_ids', $group_ids );
}

/**
 * Add a group to a forum
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_add_group_id_to_forum( $forum_id = 0, $group_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Maybe update the groups forums
	if ( !in_array( $group_id, $group_ids ) ) {
		$group_ids[] = $group_id;
		return bbp_update_forum_group_ids( $forum_id, $group_ids );
	}
}

/**
 * Remove a forum from a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_add_forum_id_to_group( $group_id = 0, $forum_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Maybe update the groups forums
	if ( !in_array( $forum_id, $forum_ids ) ) {
		$forum_ids[] = $forum_id;
		return bbp_update_group_forum_ids( $group_id, $forum_ids );
	}
}

/**
 * Remove a group from a forum
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_remove_group_id_from_forum( $forum_id = 0, $group_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Maybe update the groups forums
	if ( in_array( $group_id, $group_ids ) ) {
		unset( $group_ids[$group_id] );
		return bbp_update_forum_group_ids( $forum_id, $group_ids );
	}
}

/**
 * Remove a forum from a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_remove_forum_id_from_group( $group_id = 0, $forum_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Maybe update the groups forums
	if ( in_array( $forum_id, $forum_ids ) ) {
		unset( $forum_ids[$forum_id] );
		return bbp_update_group_forum_ids( $group_id, $forum_ids );
	}
}

/**
 * Remove a group from aall forums
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_remove_group_id_from_all_forums( $group_id = 0 ) {

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Loop through forums and remove this group from each one
	foreach( (array) $forum_ids as $forum_id ) {
		bbp_remove_group_id_from_forum( $group_id, $forum_id );
	}
}

/**
 * Remove a forum from all groups
 *
 * @param type $forum_id
 * @since bbPress (r3653)
 */
function bbp_remove_forum_id_from_all_groups( $forum_id = 0 ) {

	// Validate
	$forum_id  = bbp_get_forum_id( $forum_id );
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Loop through groups and remove this forum from each one
	foreach( (array) $group_ids as $group_id ) {
		bbp_remove_forum_id_from_group( $forum_id, $group_id );
	}
}
