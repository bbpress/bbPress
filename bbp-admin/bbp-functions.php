<?php

/**
 * bbPress Admin Functions
 *
 * @package bbPress
 * @subpackage Administration
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Admin Menus ***************************************************************/

/**
 * Add a separator to the WordPress admin menus
 *
 * @since bbPress (r2957)
 */
function bbp_admin_separator () {
	global $menu;

	if ( !current_user_can( 'edit_replies' ) )
		return;

	$menu[] = array( '', 'read', 'separator-bbpress', '', 'wp-menu-separator' );
}

/**
 * Tell WordPress we have a custom menu order
 *
 * @since bbPress (r2957)
 *
 * @param bool $menu_order Menu order
 * @return bool Always true
 */
function bbp_admin_custom_menu_order( $menu_order ) {
	if ( !current_user_can( 'edit_replies' ) )
		return false;

	return true;
}

/**
 * Move our custom separator above our custom post types
 *
 * @since bbPress (r2957)
 *
 * @param array $menu_order Menu Order
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @return array Modified menu order
 */
function bbp_admin_menu_order( $menu_order ) {

	// Initialize our custom order array
	$bbp_menu_order = array();

	// Get the index of our custom separator
	$bbp_separator = array_search( 'separator-bbpress', $menu_order );

	// Forums
	if ( current_user_can( 'edit_forums' ) )
		$top_menu_type = bbp_get_forum_post_type();

	// Topics
	elseif ( current_user_can( 'edit_topics' ) )
		$top_menu_type = bbp_get_topic_post_type();

	// Replies
	elseif ( current_user_can( 'edit_replies' ) )
		$top_menu_type = bbp_get_reply_post_type();

	// Bail if there are no bbPress menus present
	else
		return;

	// Loop through menu order and do some rearranging
	foreach ( $menu_order as $index => $item ) {

		// Current item is ours, so set our separator here
		if ( ( ( 'edit.php?post_type=' . $top_menu_type ) == $item ) ) {
			$bbp_menu_order[] = 'separator-bbpress';
			unset( $menu_order[$bbp_separator] );
		}

		// Skip our separator
		if ( !in_array( $item, array( 'separator-bbpress' ) ) )
			$bbp_menu_order[] = $item;

	}

	// Return our custom order
	return $bbp_menu_order;
}

/**
 * Display the admin notices
 *
 * @since bbPress (r2613)
 *
 * @param string|WP_Error $message A message to be displayed or {@link WP_Error}
 * @param string $class Optional. A class to be added to the message div
 * @uses WP_Error::get_error_messages() To get the error messages of $message
 * @uses add_action() Adds the admin notice action with the message HTML
 * @return string The message HTML
 */
function bbp_admin_notices( $message, $class = false ) {
	if ( is_string( $message ) ) {
		$message = '<p>' . $message . '</p>';
		$class = $class ? $class : 'updated';
	} elseif ( is_wp_error( $message ) ) {
		$errors = $message->get_error_messages();

		switch ( count( $errors ) ) {
			case 0:
				return false;
				break;

			case 1:
				$message = '<p>' . $errors[0] . '</p>';
				break;

			default:
				$message = '<ul>' . "\n\t" . '<li>' . join( '</li>' . "\n\t" . '<li>', $errors ) . '</li>' . "\n" . '</ul>';
				break;
		}

		$class = $class ? $class : 'error';
	} else {
		return false;
	}

	$message = '<div id="message" class="' . esc_attr( $class ) . '">' . $message . '</div>';
	$message = str_replace( "'", "\'", $message );
	$lambda  = create_function( '', "echo '$message';" );

	add_action( 'admin_notices', $lambda );

	return $lambda;
}

/**
 * Get the array of the recount list
 *
 * @since bbPress (r2613)
 *
 * @uses apply_filters() Calls 'bbp_recount_list' with the recount list array
 * @return array Recount list
 */
function bbp_recount_list() {
	$recount_list = array(
		5  => array( 'bbp-forum-topics',           __( 'Count topics in each forum',                        'bbpress' ), 'bbp_recount_forum_topics'         ),
		10 => array( 'bbp-forum-replies',          __( 'Count replies in each forum',                       'bbpress' ), 'bbp_recount_forum_replies'        ),
		15 => array( 'bbp-topic-replies',          __( 'Count replies in each topic',                       'bbpress' ), 'bbp_recount_topic_replies'        ),
		20 => array( 'bbp-topic-voices',           __( 'Count voices in each topic',                        'bbpress' ), 'bbp_recount_topic_voices'         ),
		25 => array( 'bbp-topic-hidden-replies',   __( 'Count spammed & trashed replies in each topic',     'bbpress' ), 'bbp_recount_topic_hidden_replies' ),
		30 => array( 'bbp-topics-replied',         __( 'Count replies for each user',                       'bbpress' ), 'bbp_recount_user_topics_replied'  ),
		35 => array( 'bbp-clean-favorites',        __( 'Remove trashed topics from user favorites',         'bbpress' ), 'bbp_recount_clean_favorites'      ),
		40 => array( 'bbp-clean-subscriptions',    __( 'Remove trashed topics from user subscriptions',     'bbpress' ), 'bbp_recount_clean_subscriptions'  ),
		//45 => array( 'bbp-topic-tag-count',        __( 'Count tags for every topic',                        'bbpress' ), 'bbp_recount_topic_tags'           ),
		//50 => array( 'bbp-tags-tag-count',         __( 'Count topics for every tag',                        'bbpress' ), 'bbp_recount_tag_topics'           ),
		//55 => array( 'bbp-tags-delete-empty',      __( 'Delete tags with no topics',                        'bbpress' ), 'bbp_recount_tag_delete_empty'     ),
		60 => array( 'bbp-sync-all-topics-forums', __( 'Recalculate last activity in each topic and forum', 'bbpress' ), 'bbp_recount_rewalk'               )
	);

	ksort( $recount_list );
	return apply_filters( 'bbp_recount_list', $recount_list );
}

/**
 * Recount topic replies
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_reply_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_reply_count', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` = 'publish' GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount topic voices
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_voices() {
	global $wpdb;

	$statement = __( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_voice_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_voice_count', COUNT(DISTINCT `post_author`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` = 'publish' GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount topic hidden replies (spammed/trashed)
 *
 * @since bbPress (r2747)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_hidden_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of spammed and trashed replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_hidden_reply_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_hidden_reply_count', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` IN ( '" . join( "','", array( 'trash', $bbp->spam_status_id ) ) . "') GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount forum topics
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_posts() To get the forums
 * @uses bbp_update_forum_topic_count() To update the forum topic count
 * @return array An array of the status code and the message
 */
function bbp_recount_forum_topics() {
	global $wpdb;

	$statement = __( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ( '_bbp_forum_topic_count', '_bbp_total_topic_count' );";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	if ( $forums = get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'numberposts' => -1 ) ) ) {
		foreach( $forums as $forum ) {
			bbp_update_forum_topic_count( $forum->ID );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount forum replies
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_posts() To get the forums
 * @uses bbp_update_forum_reply_count() To update the forum reply count
 * @return array An array of the status code and the message
 */
function bbp_recount_forum_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` IN ( '_bbp_reply_count', '_bbp_total_reply_count' );";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	if ( $forums = get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'numberposts' => -1 ) ) ) {
		foreach( $forums as $forum ) {
			bbp_update_forum_reply_count( $forum->ID );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount topic replied by the users
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_user_topics_replied() {
	global $wpdb;

	$statement = __( 'Counting the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_select = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` = 'publish' GROUP BY `post_author`;";
	$insert_rows = $wpdb->get_results( $sql_select );

	if ( is_wp_error( $insert_rows ) )
		return array( 1, sprintf( $statement, $result ) );

	$insert_values = array();
	foreach ( $insert_rows as $insert_row )
		$insert_values[] = "('{$insert_row->post_author}', '_bbp_topics_replied', '{$insert_row->_count}')";

	if ( !count( $insert_values ) )
		return array( 2, sprintf( $statement, $result ) );

	$sql_delete = "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` = '_bbp_topics_replied';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 3, sprintf( $statement, $result ) );

	$insert_values = array_chunk( $insert_values, 10000 );
	foreach ( $insert_values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$wpdb->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";

		if ( is_wp_error( $wpdb->query( $sql_insert ) ) )
			return array( 4, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

// This function bypasses the taxonomy API
/**
 * Recount topic tags in each topic
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_tags() {
	global $wpdb;

	$statement = __( 'Counting the number of topic tags in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

//	// Delete empty tags
//	$delete = bbp_recount_tag_delete_empty();
//	if ( $delete[0] > 0 ) {
//		$result = __( 'Could not delete empty tags.' );
//		return array( 1, sprintf( $statement, $result ) );
//	}
//
//	// Get all tags
//	$sql_terms = "SELECT
//		`$wpdb->term_relationships`.`object_id`,
//		`$wpdb->term_taxonomy`.`term_id`
//	FROM `$wpdb->term_relationships`
//	JOIN `$wpdb->term_taxonomy`
//		ON `$wpdb->term_taxonomy`.`term_taxonomy_id` = `$wpdb->term_relationships`.`term_taxonomy_id`
//	WHERE
//		`$wpdb->term_taxonomy`.`taxonomy` = 'bb_topic_tag'
//	ORDER BY
//		`$wpdb->term_relationships`.`object_id`,
//		`$wpdb->term_taxonomy`.`term_id`;";
//
//	$terms = $wpdb->get_results( $sql_terms );
//	if ( is_wp_error( $terms ) || !is_array( $terms ) )
//		return array( 2, sprintf( $statement, $result ) );
//
//	if ( empty( $terms ) ) {
//		$result = __( 'No topic tags found.' );
//		return array( 3, sprintf( $statement, $result ) );
//	}
//
//	// Count the tags in each topic
//	$topics = array( );
//	foreach ( $terms as $term ) {
//		if ( !isset( $topics[$term->object_id] ) ) {
//			$topics[$term->object_id] = 1;
//		} else {
//			$topics[$term->object_id]++;
//		}
//	}
//
//	if ( empty( $topics ) )
//		return array( 4, sprintf( $statement, $result ) );
//
//	// Build the values to insert into the SQL statement
//	$values = array( );
//	foreach ( $topics as $topic_id => $tag_count )
//		$values[] = '(' . $topic_id . ', ' . $tag_count . ')';
//
//	if ( empty( $values ) )
//		return array( 5, sprintf( $statement, $result ) );
//
//	// Update the topics with the new tag counts
//	$values = array_chunk( $values, 10000 );
//	foreach ( $values as $chunk ) {
//		$sql = "INSERT INTO `$wpdb->topics` (`topic_id`, `tag_count`) VALUES " . implode( ", ", $chunk ) . " ON DUPLICATE KEY UPDATE `tag_count` = VALUES(`tag_count`);";
//		if ( is_wp_error( $wpdb->query( $sql ) ) ) {
//			return array( 6, sprintf( $statement, $result ) );
//		}
//	}
//
//	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

// This function bypasses the taxonomy API
/**
 * Recount the number of topics in each topic tag
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_tag_topics() {
	global $wpdb;

	$statement = __( 'Counting the number of topics in each topic tag&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

//	// Delete empty tags
//	$delete = bbp_recount_tag_delete_empty();
//	if ( $delete[0] > 0 ) {
//		$result = __( 'Could not delete empty tags.' );
//		return array( 1, sprintf( $statement, $result ) );
//	}
//
//	// Get all tags
//	$sql_terms = "SELECT
//		`$wpdb->term_taxonomy`.`term_taxonomy_id`,
//		`$wpdb->term_relationships`.`object_id`
//	FROM `$wpdb->term_relationships`
//	JOIN `$wpdb->term_taxonomy`
//		ON `$wpdb->term_taxonomy`.`term_taxonomy_id` = `$wpdb->term_relationships`.`term_taxonomy_id`
//	WHERE
//		`$wpdb->term_taxonomy`.`taxonomy` = 'bb_topic_tag'
//	ORDER BY
//		`$wpdb->term_taxonomy`.`term_taxonomy_id`,
//		`$wpdb->term_relationships`.`object_id`;";
//
//	$terms = $wpdb->get_results( $sql_terms );
//	if ( is_wp_error( $terms ) || !is_array( $terms ) )
//		return array( 2, sprintf( $statement, $result ) );
//
//	if ( empty( $terms ) ) {
//		$result = __( 'No topic tags found.', 'bbpress' );
//		return array( 3, sprintf( $statement, $result ) );
//	}
//
//	// Count the topics in each tag
//	$tags = array( );
//	foreach ( $terms as $term ) {
//		if ( !isset( $tags[$term->term_taxonomy_id] ) ) {
//			$tags[$term->term_taxonomy_id] = 1;
//		} else {
//			$tags[$term->term_taxonomy_id]++;
//		}
//	}
//
//	if ( empty( $tags ) )
//		return array( 4, sprintf( $statement, $result ) );
//
//	// Build the values to insert into the SQL statement
//	$values = array( );
//	foreach ( $tags as $term_taxonomy_id => $count )
//		$values[] = '(' . $term_taxonomy_id . ', ' . $count . ')';
//
//	if ( empty( $values ) )
//		return array( 5, sprintf( $statement, $result ) );
//
//	// Update the terms with the new tag counts
//	$values = array_chunk( $values, 10000 );
//	foreach ( $values as $chunk ) {
//		$sql = "INSERT INTO `$wpdb->term_taxonomy` (`term_taxonomy_id`, `count`) VALUES " . implode( ", ", $chunk ) . " ON DUPLICATE KEY UPDATE `count` = VALUES(`count`);";
//		if ( is_wp_error( $wpdb->query( $sql ) ) ) {
//			return array( 6, sprintf( $statement, $result ) );
//		}
//	}
//
//	if ( $return_boolean )
//		return true;
//
//	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

// This function bypasses the taxonomy API
/**
 * Recount topic tags with no topics
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_tag_delete_empty() {
	global $wpdb;

	$statement = __( 'Deleting topic tags with no topics&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

//	static $run_once;
//	if ( isset( $run_once ) ) {
//		if ( $run_once > 0 ) {
//			$exit = sprintf( __( 'failure (returned code %s)', 'bbpress' ), $run_once );
//		} else {
//			$exit = __( 'success', 'bbpress' );
//		}
//		$result = sprintf( __( 'Already run with %s.', 'bbpress' ), $exit );
//		return array( $run_once, sprintf( $statement, $result ) );
//	}
//
//	// Get all topic ids
//	$sql_topics = "SELECT `topic_id` FROM $wpdb->topics ORDER BY `topic_id`;";
//	$topics = $wpdb->get_results( $sql_topics );
//	if ( is_wp_error( $topics ) ) {
//		$result = __( 'No topics found.', 'bbpress' );
//		$run_once = 1;
//		return array( 1, sprintf( $statement, $result ) );
//	}
//
//	$topic_ids = array( );
//
//	foreach ( $topics as $topic )
//		$topic_ids[] = $topic->topic_id;
//
//	// Get all topic tag term relationships without a valid topic id
//	$in_topic_ids = implode( ', ', $topic_ids );
//	$sql_bad_term_relationships = "SELECT
//		`$wpdb->term_taxonomy`.`term_taxonomy_id`,
//		`$wpdb->term_taxonomy`.`term_id`,
//		`$wpdb->term_relationships`.`object_id`
//	FROM `$wpdb->term_relationships`
//	JOIN `$wpdb->term_taxonomy`
//		ON `$wpdb->term_taxonomy`.`term_taxonomy_id` = `$wpdb->term_relationships`.`term_taxonomy_id`
//	WHERE
//		`$wpdb->term_taxonomy`.`taxonomy` = 'bb_topic_tag' AND
//		`$wpdb->term_relationships`.`object_id` NOT IN ($in_topic_ids)
//	ORDER BY
//		`$wpdb->term_relationships`.`object_id`,
//		`$wpdb->term_taxonomy`.`term_id`,
//		`$wpdb->term_taxonomy`.`term_taxonomy_id`;";
//
//	$bad_term_relationships = $wpdb->get_results( $sql_bad_term_relationships );
//	if ( is_wp_error( $bad_term_relationships ) || !is_array( $bad_term_relationships ) ) {
//		$run_once = 2;
//		return array( 2, sprintf( $statement, $result ) );
//	}
//
//	// Delete those bad term relationships
//	if ( !empty( $bad_term_relationships ) ) {
//		$values = array( );
//		foreach ( $bad_term_relationships as $bad_term_relationship ) {
//			$values[] = '(`object_id` = ' . $bad_term_relationship->object_id . ' AND `term_taxonomy_id` = ' . $bad_term_relationship->term_taxonomy_id . ')';
//		}
//		if ( !empty( $values ) ) {
//			$values = join( ' OR ', $values );
//			$sql_bad_term_relationships_delete = "DELETE
//			FROM `$wpdb->term_relationships`
//			WHERE $values;";
//			if ( is_wp_error( $wpdb->query( $sql_bad_term_relationships_delete ) ) ) {
//				$run_once = 3;
//				return array( 3, sprintf( $statement, $result ) );
//			}
//		}
//	}
//
//	// Now get all term taxonomy ids with term relationships
//	$sql_term_relationships = "SELECT `term_taxonomy_id` FROM $wpdb->term_relationships ORDER BY `term_taxonomy_id`;";
//	$term_taxonomy_ids = $wpdb->get_col( $sql_term_relationships );
//	if ( is_wp_error( $term_taxonomy_ids ) ) {
//		$run_once = 4;
//		return array( 4, sprintf( $statement, $result ) );
//	}
//	$term_taxonomy_ids = array_unique( $term_taxonomy_ids );
//
//	// Delete topic tags that don't have any term relationships
//	if ( !empty( $term_taxonomy_ids ) ) {
//		$in_term_taxonomy_ids = implode( ', ', $term_taxonomy_ids );
//		$sql_delete_term_relationships = "DELETE
//		FROM $wpdb->term_taxonomy
//		WHERE
//			`taxonomy` = 'bb_topic_tag' AND
//			`term_taxonomy_id` NOT IN ($in_term_taxonomy_ids);";
//		if ( is_wp_error( $wpdb->query( $sql_delete_term_relationships ) ) ) {
//			$run_once = 5;
//			return array( 5, sprintf( $statement, $result ) );
//		}
//	}
//
//	// Get all valid term ids
//	$sql_terms = "SELECT `term_id` FROM $wpdb->term_taxonomy ORDER BY `term_id`;";
//	$term_ids = $wpdb->get_col( $sql_terms );
//	if ( is_wp_error( $term_ids ) ) {
//		$run_once = 6;
//		return array( 6, sprintf( $statement, $result ) );
//	}
//	$term_ids = array_unique( $term_ids );
//
//	// Delete terms that don't have any associated term taxonomies
//	if ( !empty( $term_ids ) ) {
//		$in_term_ids = implode( ', ', $term_ids );
//		$sql_delete_terms = "DELETE
//		FROM $wpdb->terms
//		WHERE
//			`term_id` NOT IN ($in_term_ids);";
//		if ( is_wp_error( $wpdb->query( $sql_delete_terms ) ) ) {
//			$run_once = 7;
//			return array( 7, sprintf( $statement, $result ) );
//		}
//	}
//
//	$result = __( 'Complete!', 'bbpress' );
//	$run_once = 0;
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Clean the users' favorites
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_clean_favorites() {
	global $wpdb;

	$statement = __( 'Removing trashed topics from user favorites&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$users = $wpdb->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `$wpdb->usermeta` WHERE `meta_key` = '_bbp_favorites';" );
	if ( is_wp_error( $users ) )
		return array( 1, sprintf( $statement, $result ) );

	$topics = $wpdb->get_col( "SELECT `ID` FROM `$wpdb->posts` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = 'publish';" );

	if ( is_wp_error( $topics ) )
		return array( 2, sprintf( $statement, $result ) );

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->favorites ) || !is_string( $user->favorites ) )
			continue;

		$favorites = array_intersect( $topics, (array) explode( ',', $user->favorites ) );
		if ( empty( $favorites ) || !is_array( $favorites ) )
			continue;

		$favorites = join( ',', $favorites );
		$values[] = "('$user->user_id', '_bbp_favorites', '$favorites')";
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `$wpdb->usermeta` WHERE `meta_key` = '_bbp_favorites';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 4, sprintf( $statement, $result ) );

	$values = array_chunk( $values, 10000 );
	foreach ( $values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `$wpdb->usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";
		if ( is_wp_error( $wpdb->query( $sql_insert ) ) )
			return array( 5, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Clean the users' subscriptions
 *
 * @since bbPress (r2668)
 *
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_clean_subscriptions() {
	global $wpdb;

	$statement = __( 'Removing trashed topics from user subscriptions&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$users = $wpdb->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `$wpdb->usermeta` WHERE `meta_key` = '_bbp_subscriptions';" );
	if ( is_wp_error( $users ) )
		return array( 1, sprintf( $statement, $result ) );

	$topics = $wpdb->get_col( "SELECT `ID` FROM `$wpdb->posts` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = 'publish';" );
	if ( is_wp_error( $topics ) )
		return array( 2, sprintf( $statement, $result ) );

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->subscriptions ) || !is_string( $user->subscriptions ) )
			continue;

		$subscriptions = array_intersect( $topics, (array) explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || !is_array( $subscriptions ) )
			continue;

		$subscriptions = join( ',', $subscriptions );
		$values[] = "('$user->user_id', '_bbp_subscriptions', '$subscriptions')";
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `$wpdb->usermeta` WHERE `meta_key` = '_bbp_subscriptions';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 4, sprintf( $statement, $result ) );

	$values = array_chunk( $values, 10000 );
	foreach ( $values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `$wpdb->usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";
		if ( is_wp_error( $wpdb->query( $sql_insert ) ) )
			return array( 5, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recaches the last post in every topic and forum
 *
 * @since bbPress (r3040)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_rewalk() {
	global $wpdb;

	$statement = __( 'Recomputing latest post in every topic and forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $wpdb->query( "DELETE FROM `$wpdb->postmeta` WHERE `meta_key` IN ( '_bbp_last_reply_id', '_bbp_last_topic_id', '_bbp_last_active_id' );" ) ) )
		return array( 1, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_reply_id', MAX( `reply`.`ID` )
			FROM `$wpdb->posts` AS `topic` INNER JOIN `$wpdb->posts` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` IN ( 'publish' ) AND `topic`.`post_type` = 'topic' AND `reply`.`post_type` = 'reply'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 2, sprintf( $statement, $result ) );

	// For any remaining topics, give a reply ID of 0.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_reply_id', 0
			FROM `$wpdb->posts` AS `topic` LEFT JOIN `$wpdb->postmeta` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_reply_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = 'topic' );" ) ) )
		return array( 3, sprintf( $statement, $result ) );

	// Now we give all the forums with topics the ID their last topic.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_last_topic_id', `topic`.`ID`
			FROM `$wpdb->posts` AS `forum` INNER JOIN `$wpdb->posts` AS `topic` ON `forum`.`ID` = `topic`.`post_parent`
			WHERE `reply`.`post_status` IN ( 'publish' ) AND `forum`.`post_type` = 'forum' AND `topic`.`post_type` = 'topic'
			GROUP BY `forum`.`ID` );" ) ) )
		return array( 4, sprintf( $statement, $result ) );

	// For any remaining forums, give a topic ID of 0.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_topic_id', 0
			FROM `$wpdb->posts` AS `forum` LEFT JOIN `$wpdb->postmeta` AS `topic`
			ON `forum`.`ID` = `topic`.`post_id` AND `topic`.`meta_key` = '_bbp_last_topic_id'
			WHERE `topic`.`meta_id` IS NULL AND `forum`.`post_type` = 'forum' );" ) ) )
		return array( 5, sprintf( $statement, $result ) );

	// After that, we give all the topics with replies the ID their last reply (again, this time for a different reason).
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_id', MAX( `reply`.`ID` )
			FROM `$wpdb->posts` AS `topic` INNER JOIN `$wpdb->posts` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` IN ( 'publish' ) AND `topic`.`post_type` = 'topic' AND `reply`.`post_type` = 'reply'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 6, sprintf( $statement, $result ) );

	// For any remaining topics, give a reply ID of themself.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_id', `ID`
			FROM `$wpdb->posts` AS `topic` LEFT JOIN `$wpdb->postmeta` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = 'topic' );" ) ) )
		return array( 7, sprintf( $statement, $result ) );

	// Give topics with replies their last update time.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_time', MAX( `reply`.`post_date` )
			FROM `$wpdb->posts` AS `topic` INNER JOIN `$wpdb->posts` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` IN ( 'publish' ) AND `topic`.`post_type` = 'topic' AND `reply`.`post_type` = 'reply'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 8, sprintf( $statement, $result ) );

	// Give topics without replies their last update time.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_time', `post_date`
			FROM `$wpdb->posts` AS `topic` LEFT JOIN `$wpdb->postmeta` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_time'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = 'topic' );" ) ) )
		return array( 9, sprintf( $statement, $result ) );

	// Forums need to know what their last active item is as well. Now it gets a bit more complex to do in the database.
	$forums = $wpdb->get_col( "SELECT `ID` FROM `$wpdb->posts` WHERE `post_type` = 'forum';" );
	if ( is_wp_error( $forums ) )
		return array( 10, sprintf( $statement, $result ) );

	foreach ( $forums as $forum ) {
		bbp_update_forum_last_active_id( $forum );
		bbp_update_forum_last_active_time( $forum );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

?>
