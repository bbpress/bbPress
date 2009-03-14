<?php

/* Tags */

/**
 * bb_add_topic_tag() - Adds a single tag to a topic.
 *
 * @param int $topic_id
 * @param string $tag The (unsanitized) full name of the tag to be added
 * @return int|bool The TT_ID of the new bb_topic_tag or false on failure
 */
function bb_add_topic_tag( $topic_id, $tag ) {
	$tt_ids = bb_add_topic_tags( $topic_id, $tag );
	if ( is_array( $tt_ids ) )
		return $tt_ids[0];
	return false;
}

/**
 * bb_add_topic_tag() - Adds a multiple tags to a topic.
 *
 * @param int $topic_id
 * @param array|string $tags The (unsanitized) full names of the tag to be added.  CSV or array.
 * @return array|bool The TT_IDs of the new bb_topic_tags or false on failure
 */
function bb_add_topic_tags( $topic_id, $tags ) {
	global $wp_taxonomy_object;
	$topic_id = (int) $topic_id;
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'add_tag_to', $topic_id ) )
		return false;

	$user_id = bb_get_current_user_info( 'id' );

	if ( !is_array( $tags ) )
		$tags = explode(',', (string) $tags);

	$tt_ids = $wp_taxonomy_object->set_object_terms( $topic->topic_id, $tags, 'bb_topic_tag', array( 'append' => true, 'user_id' => $user_id ) );

	if ( is_array($tt_ids) ) {
		global $bbdb;
		$bbdb->query( $bbdb->prepare(
			"UPDATE $bbdb->topics SET tag_count = tag_count + %d WHERE topic_id = %d", count( $tt_ids ), $topic->topic_id
		) );
		wp_cache_delete( $topic->topic_id, 'bb_topic' );
		foreach ( $tt_ids as $tt_id )
			do_action('bb_tag_added', $tt_id, $user_id, $topic_id);
		return $tt_ids;
	}
	return false;
}

/**
 * bb_create_tag() - Creates a single bb_topic_tag.
 *
 * @param string $tag The (unsanitized) full name of the tag to be created
 * @return int|bool The TT_ID of the new bb_topic_tags or false on failure
 */
function bb_create_tag( $tag ) {
	global $wp_taxonomy_object;

	if ( list($term_id, $tt_id) = $wp_taxonomy_object->is_term( $tag, 'bb_topic_tag' ) )
		return $tt_id;

	list($term_id, $tt_id) = $wp_taxonomy_object->insert_term( $tag, 'bb_topic_tag' );

	if ( is_wp_error($term_id) || is_wp_error($tt_id) || !$tt_id )
		return false;

	return $tt_id;
}

/**
 * bb_remove_topic_tag() - Removes a single bb_topic_tag by a user from a topic.
 *
 * @param int $tt_id The TT_ID of the bb_topic_tag to be removed
 * @param int $user_id
 * @param int $topic_id
 * @return array|false The TT_IDs of the users bb_topic_tags on that topic or false on failure
 */
function bb_remove_topic_tag( $tt_id, $user_id, $topic_id ) {
	global $wp_taxonomy_object;
	$tt_id   = (int) $tt_id;
	$user_id  = (int) $user_id;
	$topic_id = (int) $topic_id;
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'edit_tag_by_on', $user_id, $topic_id ) )
		return false;

	$_tag = bb_get_tag( $tt_id );

	do_action('bb_pre_tag_removed', $tt_id, $user_id, $topic_id);
	$current_tag_ids = $wp_taxonomy_object->get_object_terms( $topic_id, 'bb_topic_tag', array( 'user_id' => $user_id, 'fields' => 'tt_ids' ) );
	if ( !is_array($current_tag_ids) )
		return false;

	$current_tag_ids = array_map( 'intval', $current_tag_ids );

	if ( false === $pos = array_search( $tt_id, $current_tag_ids ) )
		return false;

	unset($current_tag_ids[$pos]);

	$tt_ids = $wp_taxonomy_object->set_object_terms( $topic_id, array_values($current_tag_ids), 'bb_topic_tag', array( 'user_id' => $user_id ) );
	if ( is_array( $tt_ids ) ) {
		global $bbdb;
		$bbdb->query( $bbdb->prepare(
			"UPDATE $bbdb->topics SET tag_count = %d WHERE topic_id = %d", count( $tt_ids ), $topic_id
		) );
		wp_cache_delete( $topic_id, 'bb_topic' );

		// Count is updated at set_object_terms()
		if ( $_tag && 2 > $_tag->tag_count ) {
			bb_destroy_tag( $_tag->term_taxonomy_id );
		}
	} elseif ( is_wp_error( $tt_ids ) ) {
		return false;
	}
	return $tt_ids;
}

/**
 * bb_remove_topic_tag() - Removes all bb_topic_tags from a topic.
 *
 * @param int $topic_id
 * @return bool
 */
function bb_remove_topic_tags( $topic_id ) {
	global $wp_taxonomy_object;
	$topic_id = (int) $topic_id;
	if ( !$topic_id || !get_topic( $topic_id ) )
		return false;

	$_tags = bb_get_topic_tags( $topic_id );

	do_action( 'bb_pre_remove_topic_tags', $topic_id );

	$wp_taxonomy_object->delete_object_term_relationships( $topic_id, 'bb_topic_tag' );

	global $bbdb;
	$bbdb->query( $bbdb->prepare(
		"UPDATE $bbdb->topics SET tag_count = 0 WHERE topic_id = %d", $topic_id
	) );
	wp_cache_delete( $topic_id, 'bb_topic' );

	if ( $_tags ) {
		foreach ( $_tags as $_tag ) {
			// Count is updated at delete_object_term_relationships()
			if ( 2 > $_tag->tag_count ) {
				bb_destroy_tag( $_tag->term_taxonomy_id );
			}
		}
	}

	return true;
}

/**
 * bb_destroy_tag() - Completely removes a bb_topic_tag.
 *
 * @param int $tt_id The TT_ID of the tag to destroy
 * @return bool
 */
function bb_destroy_tag( $tt_id, $recount_topics = true ) {
	global $wp_taxonomy_object;

	$tt_id = (int) $tt_id;

	if ( !$tag = bb_get_tag( $tt_id ) )
		return false;

	$topic_ids = bb_get_tagged_topic_ids( $tag->term_id );

	$return = $wp_taxonomy_object->delete_term( $tag->term_id, 'bb_topic_tag' );

	if ( is_wp_error($return) )
		return false;

	if ( !is_wp_error( $topic_ids ) && is_array( $topic_ids ) ) {
		global $bbdb;
		$bbdb->query(
			"UPDATE $bbdb->topics SET tag_count = tag_count - 1 WHERE topic_id IN (" . join( ',', $topic_ids ) . ")"
		);
		foreach ( $topic_ids as $topic_id ) {
			wp_cache_delete( $topic_id, 'bb_topic' );
		}
	}

	return $return;
}

/**
 * bb_get_tag_id() - Returns the id of the specified or global tag.
 *
 * @param mixed $id The TT_ID, tag name of the desired tag, or 0 for the global tag
 * @return int 
 */
function bb_get_tag_id( $id = 0 ) {
	global $tag;
	if ( $id ) {
		$_tag = bb_get_tag( $id );
	} else {
		$_tag =& $tag;
	}
	return (int) $_tag->tag_id;
}

/**
 * bb_get_tag() - Returns the specified tag.  If $user_id and $topic_id are passed, will check to see if that tag exists on that topic by that user.
 *
 * @param mixed $id The TT_ID or tag name of the desired tag
 * @param int $user_id (optional)
 * @param int $topic_id (optional)
 * @return object Term object (back-compat)
 */
function bb_get_tag( $id, $user_id = 0, $topic_id = 0 ) {
	global $wp_taxonomy_object;
	$user_id  = (int) $user_id;
	$topic_id = (int) $topic_id;

	$term = false;
	if ( is_integer( $id ) ) {
		$tt_id = (int) $id;
	} else {
		if ( !$term = $wp_taxonomy_object->get_term_by( 'slug', $id, 'bb_topic_tag' ) )
			return false;
		$tt_id = (int) $term->term_taxonomy_id;
	}

	if ( $user_id && $topic_id ) {
		$args = array( 'user_id' => $user_id, 'fields' => 'tt_ids' );
		$cache_id = $topic_id . serialize( $args );

		$tt_ids = wp_cache_get( $cache_id, 'bb_topic_tag_terms' );
		if ( empty( $tt_ids ) ) {
			$tt_ids = $wp_taxonomy_object->get_object_terms( $topic_id, 'bb_topic_tag', $args );
			wp_cache_set( $cache_id, $tt_ids, 'bb_topic_tag_terms' );
		}
		if ( !in_array( $tt_id, $tt_ids ) )
			return false;
	}

	if ( !$term )
		$term = $wp_taxonomy_object->get_term_by( 'tt_id', $tt_id, 'bb_topic_tag' );

	_bb_make_tag_compat( $term );

	return $term;
}

/**
 * bb_get_topic_tags() - Returns all of the bb_topic_tags associated with the specified topic.
 *
 * @param int $topic_id
 * @param mixed $args
 * @return array|false Term objects (back-compat), false on failure
 */
function bb_get_topic_tags( $topic_id = 0, $args = null ) {
	global $wp_taxonomy_object;

	if ( !$topic = get_topic( get_topic_id( $topic_id ) ) )
		return false;

	$topic_id = (int) $topic->topic_id;

	$cache_id = $topic_id . serialize( $args );

	$terms = wp_cache_get( $cache_id, 'bb_topic_tag_terms' );
	if ( empty( $terms ) ) {
		$terms = $wp_taxonomy_object->get_object_terms( (int) $topic->topic_id, 'bb_topic_tag', $args );
		wp_cache_set( $cache_id, $terms, 'bb_topic_tag_terms' );
	}

	if ( is_wp_error( $terms ) )
		return false;

	for ( $i = 0; isset($terms[$i]); $i++ )
		_bb_make_tag_compat( $terms[$i] );

	return $terms;
}

function bb_get_user_tags( $topic_id, $user_id ) {
	$tags = bb_get_topic_tags( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$user_tags = array();

	foreach ( $tags as $tag ) :
		if ( $tag->user_id == $user_id )
			$user_tags[] = $tag;
	endforeach;
	return $user_tags;
}

function bb_get_other_tags( $topic_id, $user_id ) {
	$tags = bb_get_topic_tags( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$other_tags = array();

	foreach ( $tags as $tag ) :
		if ( $tag->user_id != $user_id )
			$other_tags[] = $tag;
	endforeach;
	return $other_tags;
}

function bb_get_public_tags( $topic_id ) {
	$tags = bb_get_topic_tags( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$used_tags   = array();
	$public_tags = array();

	foreach ( $tags as $tag ) :
		if ( !in_array($tag->tag_id, $used_tags) ) :
			$public_tags[] = $tag;
			$used_tags[]   = $tag->tag_id;
		endif;
	endforeach;
	return $public_tags;
}

function bb_get_tagged_topic_ids( $tag_id ) {
	global $wp_taxonomy_object, $tagged_topic_count;
	
	if ( $topic_ids = (array) $wp_taxonomy_object->get_objects_in_term( $tag_id, 'bb_topic_tag', array( 'field' => 'tt_id' ) ) ) {
		$tagged_topic_count = count($topic_ids);
		return apply_filters('get_tagged_topic_ids', $topic_ids);
	} else {
		$tagged_topic_count = 0;
		return false;
	}
}

function get_tagged_topics( $args ) {
	$defaults = array( 'tag_id' => false, 'page' => 1, 'number' => false );
	if ( is_numeric( $args ) )
		$args = array( 'tag_id' => $args );
	else
		$args = wp_parse_args( $args ); // Make sure it's an array
	if ( 1 < func_num_args() )
		$args['page'] = func_get_arg(1);
	if ( 2 < func_num_args() )
		$args['number'] = func_get_arg(2);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	$q = array('tag_id' => (int) $tag_id, 'page' => (int) $page, 'per_page' => (int) $number);

	$query = new BB_Query( 'topic', $q, 'get_tagged_topics' );
	return $query->results;
}

function get_tagged_topic_posts( $args ) {
	$defaults = array( 'tag_id' => false, 'page' => 1, 'number' => false );
	if ( is_numeric( $args ) )
		$args = array( 'tag_id' => $args );
	else
		$args = wp_parse_args( $args ); // Make sure it's an array
	if ( 1 < func_num_args() )
		$args['page'] = func_get_arg(1);
	if ( 2 < func_num_args() )
		$args['number'] = func_get_arg(2);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	$q = array('tag_id' => (int) $tag_id, 'page' => (int) $page, 'per_page' => (int) $number);

	$query = new BB_Query( 'post', $q, 'get_tagged_topic_posts' );
	return $query->results;
}

/**
 * bb_get_top_tags() - Returns most popular tags.
 *
 * @param mixed $args
 * @return array|false Term objects (back-compat), false on failure
 */
function bb_get_top_tags( $args = null ) {
	global $wp_taxonomy_object;

	$args = wp_parse_args( $args, array( 'number' => 40 ) );
	$args['order'] = 'DESC';
	$args['orderby'] = 'count';

	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', $args );
	if ( is_wp_error( $terms ) )
		return false;

	for ( $i = 0; isset($terms[$i]); $i++ )
		_bb_make_tag_compat( $terms[$i] );

	return $terms;
}

function _bb_make_tag_compat( &$tag ) {
	if ( is_object($tag) && isset($tag->term_id) ) {
		$tag->tag_id    =& $tag->term_taxonomy_id;
		$tag->tag       =& $tag->slug;
		$tag->raw_tag   =& $tag->name;
		$tag->tag_count =& $tag->count;
	} elseif ( is_array($tag) && isset($tag['term_id']) ) {
		$tag->tag_id    =& $tag['term_taxonomy_id'];
		$tag->tag       =& $tag['slug'];
		$tag->raw_tag   =& $tag['name'];
		$tag->tag_count =& $tag['count'];
	}
}






// TODO
function bb_related_tags( $_tag = false, $number = 40 ) {
	return array();

	global $bbdb, $tag;
	if ( false === $_tag )
		$_tag = $tag;
	else
		$_tag = bb_get_tag( $_tag );

	if ( !$_tag )
		return false;

	$number = (int) $number;

	$sql = $bbdb->prepare(
		"SELECT tag.tag_id, tag.tag, tag.raw_tag, COUNT(DISTINCT t.topic_id) AS tag_count
	           FROM $bbdb->tagged AS t
	           JOIN $bbdb->tagged AS tt  ON (t.topic_id = tt.topic_id)
	           JOIN $bbdb->tags   AS tag ON (t.tag_id = tag.tag_id)
	        WHERE tt.tag_id = %d AND t.tag_id != %d GROUP BY t.tag_id ORDER BY tag_count DESC LIMIT %d",
		$_tag->tag_id, $_tag->tag_id, $number
	);

	foreach ( (array) $tags = $bbdb->get_results( $sql ) as $_tag ) {
		wp_cache_add( $tag->tag, $tag, 'bb_tag' );
		wp_cache_add( $tag->tag_id, $tag->tag, 'bb_tag_id' );
	}

	return $tags;
}

