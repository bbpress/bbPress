<?php

function bb_recount_topic_posts()
{
	global $bbdb;

	$statement = __( 'Counting the number of posts in each topic&hellip; %s' );
	$result = __( 'Failed!' );

	$sql = "INSERT INTO `$bbdb->topics` (`topic_id`, `topic_posts`) (SELECT `topic_id`, COUNT(`post_status`) as `topic_posts` FROM `$bbdb->posts` WHERE `post_status` = '0' GROUP BY `topic_id`) ON DUPLICATE KEY UPDATE `topic_posts` = VALUES(`topic_posts`);";
	if ( is_wp_error( $bbdb->query( $sql ) ) ) {
		return sprintf( $statement, $result );
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}

function bb_recount_topic_voices()
{
	global $bbdb;

	$statement = __( 'Counting the number of voices in each topic&hellip; %s' );
	$result = __( 'Failed!' );

	$sql_delete = "DELETE FROM `$bbdb->meta` WHERE `object_type` = 'bb_topic' AND `meta_key` = 'voices_count';";
	if ( is_wp_error( $bbdb->query( $sql_delete ) ) ) {
		return sprintf( $statement, $result );
	}

	$sql = "INSERT INTO `$bbdb->meta` (`object_type`, `object_id`, `meta_key`, `meta_value`) (SELECT 'bb_topic', `topic_id`, 'voices_count', COUNT(DISTINCT `poster_id`) as `meta_value` FROM `$bbdb->posts` WHERE `post_status` = '0' GROUP BY `topic_id`);";
	if ( is_wp_error( $bbdb->query( $sql ) ) ) {
		return sprintf( $statement, $result );
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}

function bb_recount_topic_deleted_posts()
{
	global $bbdb;

	$statement = __( 'Counting the number of deleted posts in each topic&hellip; %s' );
	$result = __( 'Failed!' );

	$sql_delete = "DELETE FROM `$bbdb->meta` WHERE `object_type` = 'bb_topic' AND `meta_key` = 'deleted_posts';";
	if ( is_wp_error( $bbdb->query( $sql_delete ) ) ) {
		return sprintf( $statement, $result );
	}

	$sql = "INSERT INTO `$bbdb->meta` (`object_type`, `object_id`, `meta_key`, `meta_value`) (SELECT 'bb_topic', `topic_id`, 'deleted_posts', COUNT(`post_status`) as `meta_value` FROM `$bbdb->posts` WHERE `post_status` != '0' GROUP BY `topic_id`);";
	if ( is_wp_error( $bbdb->query( $sql ) ) ) {
		return sprintf( $statement, $result );
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}

function bb_recount_forum_topics()
{
	global $bbdb;

	$statement = __( 'Counting the number of topics in each forum&hellip; %s' );
	$result = __( 'Failed!' );

	$sql = "INSERT INTO `$bbdb->forums` (`forum_id`, `topics`) (SELECT `forum_id`, COUNT(`topic_status`) as `topics` FROM `$bbdb->topics` WHERE `topic_status` = '0' GROUP BY `forum_id`) ON DUPLICATE KEY UPDATE `topics` = VALUES(`topics`);";
	if ( is_wp_error( $bbdb->query( $sql ) ) ) {
		return sprintf( $statement, $result );
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}

function bb_recount_forum_posts()
{
	global $bbdb;

	$statement = __( 'Counting the number of posts in each forum&hellip; %s' );
	$result = __( 'Failed!' );

	$sql = "INSERT INTO `$bbdb->forums` (`forum_id`, `posts`) (SELECT `forum_id`, COUNT(`post_status`) as `posts` FROM `$bbdb->posts` WHERE `post_status` = '0' GROUP BY `forum_id`) ON DUPLICATE KEY UPDATE `posts` = VALUES(`posts`);";
	if ( is_wp_error( $bbdb->query( $sql ) ) ) {
		return sprintf( $statement, $result );
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}

function bb_recount_user_topics_replied()
{
	global $bbdb;

	$statement = __( 'Counting the number of topics to which each user has replied&hellip; %s' );
	$result = __( 'Failed!' );

	$sql_select = "SELECT `poster_id`, COUNT(DISTINCT `topic_id`) as `_count` FROM `$bbdb->posts` WHERE `post_status` = '0' GROUP BY `poster_id`;";
	$insert_rows = $bbdb->get_results( $sql_select );

	if ( is_wp_error( $insert_rows ) ) {
		return sprintf( $statement, $result );
	}

	$meta_key = $bbdb->prefix . 'topics_replied';

	$insert_values = array();
	foreach ( $insert_rows as $insert_row ) {
		$insert_values[] = "('$insert_row->poster_id', '$meta_key', '$insert_row->_count')";
	}

	if ( !count( $insert_values ) ) {
		return sprintf( $statement, $result );
	}

	$sql_delete = "DELETE FROM `$bbdb->usermeta` WHERE `meta_key` = '$meta_key';";
	if ( is_wp_error( $bbdb->query( $sql_delete ) ) ) {
		return sprintf( $statement, $result );
	}

	$insert_values = array_chunk( $insert_values, 10000 );
	foreach ( $insert_values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `$bbdb->usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";

		if ( is_wp_error( $bbdb->query( $sql_insert ) ) ) {
			return sprintf( $statement, $result );
		}
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}

// TODO - make fast - see #1146
function bb_recount_topic_tags()
{
	global $bbdb, $wp_taxonomy_object;

	// Reset tag count to zero
	$bbdb->query( "UPDATE $bbdb->topics SET tag_count = 0" );

	// Get all tags
	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag' );

	if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
		$message = __('Counted topic tags');
		foreach ( $terms as $term ) {
			$topic_ids = bb_get_tagged_topic_ids( $term->term_id );
			if ( !is_wp_error( $topic_ids ) && is_array( $topic_ids ) ) {
				$bbdb->query(
					"UPDATE $bbdb->topics SET tag_count = tag_count + 1 WHERE topic_id IN (" . join( ',', $topic_ids ) . ")"
				);
			}
			unset( $topic_ids );
		}
	}
	unset( $terms, $term );

	return $message;
}

// TODO - make fast - see #1146
function bb_recount_tag_topics()
{
	global $wp_taxonomy_object;

	// Get all tags
	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', array( 'hide_empty' => false ) );

	if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
		$message = __('Counted tagged topics');
		$_terms = array();
		foreach ( $terms as $term ) {
			$_terms[] = $term->term_id;
		}
		if ( count( $_terms ) ) {
			$wp_taxonomy_object->update_term_count( $_terms, 'bb_topic_tag' );
		}
	}
	unset( $term, $_terms );

	return $message;
}

// TODO - make fast - see #1146
function bb_recount_tag_delete_empty()
{
	global $wp_taxonomy_object;

	// Get all tags
	if ( !isset( $terms ) ) {
		$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', array( 'hide_empty' => false ) );
	}

	if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
		$message = __('Deleted tags with no topics');
		foreach ( $terms as $term ) {
			$topic_ids = bb_get_tagged_topic_ids( $term->term_id );
			if ( !is_wp_error( $topic_ids ) && is_array( $topic_ids ) ) {
				if ( false === $topic_ids || ( is_array( $topic_ids ) && !count( $topic_ids ) ) ) {
					bb_destroy_tag( $term->term_taxonomy_id );
				}
			}
			unset( $topic_ids );
		}
	}
	unset( $terms, $term );

	return $message;
}

function bb_recount_clean_favorites()
{
	global $bbdb;

	$statement = __( 'Removing deleted topics from user favorites&hellip; %s' );
	$result = __( 'Failed!' );

	$meta_key = $bbdb->prefix . 'favorites';

	$users = $bbdb->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `$bbdb->usermeta` WHERE `meta_key` = '$meta_key';" );
	if ( is_wp_error( $users ) ) {
		return sprintf( $statement, $result );
	}

	$topics = $bbdb->get_col( "SELECT `topic_id` FROM `$bbdb->topics` WHERE `topic_status` = '0';" );

	if ( is_wp_error( $topics ) ) {
		return sprintf( $statement, $result );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->favorites ) || !is_string( $user->favorites ) ) {
			continue;
		}
		$favorites = explode( ',', $user->favorites );
		if ( empty( $favorites ) || !is_array( $favorites ) ) {
			continue;
		}
		$favorites = join( ',', array_intersect( $topics, $favorites ) );
		$values[] = "('$user->user_id', '$meta_key', '$favorites')";
	}

	if ( !count( $values ) ) {
		return sprintf( $statement, $result );
	}

	$sql_delete = "DELETE FROM `$bbdb->usermeta` WHERE `meta_key` = '$meta_key';";
	if ( is_wp_error( $bbdb->query( $sql_delete ) ) ) {
		return sprintf( $statement, $result );
	}

	$values = array_chunk( $values, 10000 );
	foreach ( $values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `$bbdb->usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";
		if ( is_wp_error( $bbdb->query( $sql_insert ) ) ) {
			return sprintf( $statement, $result );
		}
	}

	$result = __( 'Complete!' );
	return sprintf( $statement, $result );
}
