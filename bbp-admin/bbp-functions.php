<?php

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

function bbp_recount_list () {
	$recount_list = array(
		5  => array( 'bbp-topic-replies',         __( 'Count replies in each topic',          'bbpress' ), 'bbp_recount_topic_replies'         ),
		10 => array( 'bbp-topic-voices',          __( 'Count voices in each topic',           'bbpress' ), 'bbp_recount_topic_voices'          ),
		15 => array( 'bbp-topic-trashed-replies', __( 'Count trashed replies in each topic',  'bbpress' ), 'bbp_recount_topic_trashed_replies' ),
		20 => array( 'bbp-forum-topics',          __( 'Count topics in each forum',           'bbpress' ), 'bbp_recount_forum_topics'          ),
		25 => array( 'bbp-forum-replies',         __( 'Count replies in each forum',          'bbpress' ), 'bbp_recount_forum_replies'         ),
		30 => array( 'bbp-topics-replied',        __( 'Count replies for each user',          'bbpress' ), 'bbp_recount_user_topics_replied'   ),
		//35 => array( 'bbp-topic-tag-count',       __( 'Count tags for every topic',                  'bbpress' ), 'bbp_recount_topic_tags'            ),
		//40 => array( 'bbp-tags-tag-count',        __( 'Count topics for every tag',                  'bbpress' ), 'bbp_recount_tag_topics'            ),
		//45 => array( 'bbp-tags-delete-empty',     __( 'Delete tags with no topics',                  'bbpress' ), 'bbp_recount_tag_delete_empty'      ),
		//50 => array( 'bbp-clean-favorites',       __( 'Remove deleted topics from user favorites',   'bbpress' ), 'bbp_recount_clean_favorites'       )
	);

	ksort( $recount_list );
	return apply_filters( 'bbp_recount_list', $recount_list );
}

function bbp_recount_topic_replies () {
	global $wpdb, $bbp;

	$statement = __( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_topic_reply_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_topic_reply_count', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '{$bbp->reply_id}' AND `post_status` = 'publish' GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_topic_voices () {
	global $wpdb, $bbp;

	$statement = __( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_topic_voice_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `ID`, '_bbp_topic_voice_count', COUNT(DISTINCT `post_author`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` IN ( '{$bbp->topic_id}', '{{$bbp->reply_id}}' ) AND `post_status` = 'publish' GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_topic_trashed_replies () {
	global $wpdb, $bbp;

	$statement = __( 'Counting the number of deleted replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_deleted_replies';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `ID`, '_bbp_deleted_replies', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '{$bbp->reply_id}' AND `post_status` = 'trash' GROUP BY `ID`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_forum_topics () {
	global $wpdb, $bbp;

	$statement = __( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_forum_topic_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_forum_topic_count', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '{$bbp->topic_id}' AND `post_status` = 'publish' GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_forum_replies () {
	global $wpdb, $bbp;

	$statement = __( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_forum_reply_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_forum_reply_count', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '{$bbp->reply_id}' AND `post_status` = 'publish' GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );


//	$sql = "INSERT INTO `{$wpdb->posts}` (`forum_id`, `posts`) (SELECT `forum_id`, COUNT(`post_status`) as `posts` FROM `$wpdb->posts` WHERE `post_status` = '0' GROUP BY `forum_id`) ON DUPLICATE KEY UPDATE `posts` = VALUES(`posts`);";
//	if ( is_wp_error( $wpdb->query( $sql ) ) )
//		return array( 1, sprintf( $statement, $result ) );
//
//	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_user_topics_replied () {
	global $wpdb, $bbp;

	$statement = __( 'Counting the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_select = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$wpdb->posts}` WHERE `post_type` = '{$bbp->reply_id}' AND `post_status` = 'publish' GROUP BY `post_author`;";
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
function bbp_recount_topic_tags () {
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
function bbp_recount_tag_topics () {
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
function bbp_recount_tag_delete_empty () {
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

function bbp_recount_clean_favorites () {
	global $wpdb;

	$statement = __( 'Removing deleted topics from user favorites&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

//	$meta_key  = $wpdb->prefix . 'favorites';
//
//	$users = $wpdb->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `$wpdb->usermeta` WHERE `meta_key` = '$meta_key';" );
//	if ( is_wp_error( $users ) )
//		return array( 1, sprintf( $statement, $result ) );
//
//	$topics = $wpdb->get_col( "SELECT `topic_id` FROM `$wpdb->topics` WHERE `topic_status` = '0';" );
//
//	if ( is_wp_error( $topics ) )
//		return array( 2, sprintf( $statement, $result ) );
//
//	$values = array( );
//	foreach ( $users as $user ) {
//		if ( empty( $user->favorites ) || !is_string( $user->favorites ) ) {
//			continue;
//		}
//		$favorites = explode( ',', $user->favorites );
//		if ( empty( $favorites ) || !is_array( $favorites ) ) {
//			continue;
//		}
//		$favorites = join( ',', array_intersect( $topics, $favorites ) );
//		$values[] = "('$user->user_id', '$meta_key', '$favorites')";
//	}
//
//	if ( !count( $values ) ) {
//		$result = __( 'Nothing to remove!', 'bbpress' );
//		return array( 0, sprintf( $statement, $result ) );
//	}
//
//	$sql_delete = "DELETE FROM `$wpdb->usermeta` WHERE `meta_key` = '$meta_key';";
//	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
//		return array( 4, sprintf( $statement, $result ) );
//
//	$values = array_chunk( $values, 10000 );
//	foreach ( $values as $chunk ) {
//		$chunk = "\n" . join( ",\n", $chunk );
//		$sql_insert = "INSERT INTO `$wpdb->usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";
//		if ( is_wp_error( $wpdb->query( $sql_insert ) ) ) {
//			return array( 5, sprintf( $statement, $result ) );
//		}
//	}
//
//	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

?>
