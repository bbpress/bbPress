<?php

/** START - WordPress Add-on Actions ******************************************/

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
*/
function bbp_head () {
	do_action( 'bbp_head' );
}
add_action( 'wp_head', 'bbp_head' );

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 */
function bbp_footer () {
	do_action( 'bbp_footer' );
}
add_action( 'wp_footer', 'bbp_footer' );

/** END - WordPress Add-on Actions ********************************************/

/** START - Forum Loop Functions **********************************************/

/**
 * bbp_has_forums()
 *
 * The main forum loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @param array $args Possible arguments to change returned forums
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums ( $args = '' ) {
	global $bbp_forums_template, $wp_query;

	if ( bbp_is_forum() )
		$post_parent = bbp_get_forum_id();
	else
		$post_parent = 0;

	$default = array (
		'post_type'     => BBP_FORUM_POST_TYPE_ID,
		'post_parent'   => $post_parent,
		'orderby'       => 'menu_order',
		'order'         => 'ASC'
	);

	$r = wp_parse_args( $args, $default );

	$bbp_forums_template = new WP_Query( $r );

	return apply_filters( 'bbp_has_forums', $bbp_forums_template->have_posts(), $bbp_forums_template );
}

/**
 * bbp_forums()
 *
 * Whether there are more forums available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @return object Forum information
 */
function bbp_forums () {
	global $bbp_forums_template;
	return $bbp_forums_template->have_posts();
}

/**
 * bbp_the_forum()
 *
 * Loads up the current forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @return object Forum information
 */
function bbp_the_forum () {
	global $bbp_forums_template;
	return $bbp_forums_template->the_post();
}

/**
 * bbp_forum_id()
 *
 * Output id from bbp_forum_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_id()
 */
function bbp_forum_id () {
	echo bbp_get_forum_id();
}
	/**
	 * bbp_get_forum_id()
	 *
	 * Return the forum ID
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @global object $forums_template
	 * @return string Forum id
	 */
	function bbp_get_forum_id () {
		global $bbp_forums_template, $wp_query;

		// Currently viewing a forum
		if ( bbp_is_forum() && isset( $wp_query->post->ID ) )
			$bbp_forum_id = $wp_query->post->ID;

		// Currently inside a forum loop
		elseif ( isset( $bbp_forums_template->post->ID ) )
			$bbp_forum_id = $bbp_forums_template->post->ID;

		// Fallback
		// @todo - experiment
		else
			$bbp_forum_id = get_the_ID();

		return apply_filters( 'bbp_get_forum_id', (int)$bbp_forum_id );
	}

/**
 * bbp_forum_permalink ()
 *
 * Output the link to the forum in the forum loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_permalink()
 */
function bbp_forum_permalink ( $forum_id = 0 ) {
	echo bbp_get_forum_permalink( $forum_id );
}
	/**
	 * bbp_get_forum_permalink()
	 *
	 * Return the link to the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to forum
	 */
	function bbp_get_forum_permalink ( $forum_id = 0 ) {
		return apply_filters( 'bbp_get_forum_permalink', get_permalink( $forum_id ) );
	}

/**
 * bbp_forum_title ()
 *
 * Output the title of the forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_title()
 */
function bbp_forum_title ( $forum_id = 0 ) {
	echo bbp_get_forum_title( $forum_id );
}
	/**
	 * bbp_get_forum_title ()
	 *
	 * Return the title of the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of forum
	 *
	 */
	function bbp_get_forum_title ( $forum_id = 0 ) {
		return apply_filters( 'bbp_get_forum_title', get_the_title( $forum_id ) );
	}

/**
 * bbp_forum_last_active ()
 *
 * Output the forums last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_last_active()
 * @param int $forum_id optional
 */
function bbp_forum_last_active ( $forum_id = 0 ) {
	echo bbp_get_forum_last_active( $forum_id );
}
	/**
	 * bbp_get_forum_last_active ()
	 *
	 * Return the forums last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @return string
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_last_active ( $forum_id = 0 ) {
		if ( empty( $forum_id ) )
			$forum_id = bbp_get_forum_id();

		return apply_filters( 'bbp_get_forum_last_active', bbp_get_time_since( bbp_get_modified_time( $forum_id ) ) );
	}

/**
 * bbp_forum_topic_count ()
 *
 * Output total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_topic_count()
 * @param int $forum_id optional Forum ID to check
 */
function bbp_forum_topic_count ( $forum_id = 0 ) {
	echo bbp_get_forum_topic_count( $forum_id );
}
	/**
	 * bbp_get_forum_topic_count ()
	 *
	 * Return total topic count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional Forum ID to check
	 */
	function bbp_get_forum_topic_count ( $forum_id = 0 ) {
		if ( empty( $forum_id ) )
			$forum_id = bbp_get_forum_id();

		$forum_topics = 0; //get_pages( array( 'post_parent' => $forum_id, 'post_type' => BBP_TOPIC_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_forum_topic_count', $forum_topics );

		//return apply_filters( 'bbp_get_forum_topic_count', (int)get_post_meta( $forum_id, 'bbp_forum_topic_count', true ) );
	}

/**
 * bbp_update_forum_topic_count ()
 *
 * Adjust the total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @todo make this not suck
 *
 * @param int $new_topic_count
 * @param int $forum_id optional
 * @return int
 */
function bbp_update_forum_topic_count ( $new_topic_count, $forum_id = 0 ) {
	if ( empty( $forum_id ) )
		$forum_id = bbp_get_forum_id();

	return apply_filters( 'bbp_update_forum_topic_count', (int)update_post_meta( $forum_id, 'bbp_forum_topic_count', $new_topic_count ) );
}

/**
 * bbp_forum_topic_reply_count ()
 *
 * Output total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_topic_reply_count()
 * @param int $forum_id optional
 */
function bbp_forum_topic_reply_count ( $forum_id = 0 ) {
	echo bbp_get_forum_topic_reply_count( $forum_id );
}
	/**
	 * bbp_forum_topic_reply_count ()
	 *
	 * Return total post count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id()
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_topic_reply_count ( $forum_id = 0 ) {
		if ( empty( $forum_id ) )
			$forum_id = bbp_get_forum_id();

		$forum_topic_replies = 0; //get_pages( array( 'post_parent' => $forum_id, 'post_type' => BBP_REPLY_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_forum_topic_reply_count', $forum_topic_replies );

		//return apply_filters( 'bbp_get_forum_topic_reply_count', (int)get_post_meta( $forum_id, 'bbp_forum_topic_reply_count', true ) );
	}

/**
 * bbp_update_forum_topic_reply_count ()
 *
 * Adjust the total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @todo make this not suck
 *
 * @uses bbp_get_forum_id(0
 * @uses apply_filters
 *
 * @param int $new_topic_reply_count New post count
 * @param int $forum_id optional
 *
 * @return int
 */
function bbp_update_forum_topic_reply_count ( $new_topic_reply_count, $forum_id = 0 ) {
	if ( empty( $forum_id ) )
		$forum_id = bbp_get_forum_id();

	return apply_filters( 'bbp_update_forum_topic_reply_count', (int)update_post_meta( $forum_id, 'bbp_forum_topic_reply_count', $new_topic_reply_count ) );
}

/** END - Forum Loop Functions ************************************************/

/** START - Topic Loop Functions **********************************************/

/**
 * bbp_has_topics()
 *
 * The main topic loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @param array $args Possible arguments to change returned topics
 * @return object Multidimensional array of topic information
 */
function bbp_has_topics ( $args = '' ) {
	global $bbp_topics_template;

	$default = array (
		// Narrow query down to bbPress topics
		'post_type'        => BBP_TOPIC_POST_TYPE_ID,

		// Forum ID
		'post_parent'      => isset( $_REQUEST['forum_id'] ) ? $_REQUEST['forum_id'] : bbp_get_forum_id(),

		//'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'date',

		// 'ASC', 'DESC'
		'order'            => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'DESC',

		// @todo replace 15 with setting
		'posts_per_page'   => isset( $_REQUEST['posts'] ) ? $_REQUEST['posts'] : 15,

		// Page Number
		'paged'            => isset( $_REQUEST['tpage'] ) ? $_REQUEST['tpage'] : 1,

		// Topic Search
		's'                => empty( $_REQUEST['ts'] ) ? '' : $_REQUEST['ts'],
	);

	// Set up topic variables
	$bbp_t = wp_parse_args( $args, $default );
	$r     = extract( $bbp_t );

	// Call the query
	$bbp_topics_template = new WP_Query( $bbp_t );

	// Add pagination values to query object
	$bbp_topics_template->posts_per_page = $posts_per_page;
	$bbp_topics_template->paged          = $paged;

	// Only add pagination if query returned results
	if ( (int)$bbp_topics_template->found_posts && (int)$bbp_topics_template->posts_per_page ) {

		// Pagination settings with filter
		$bbp_topic_pagination = apply_filters( 'bbp_topic_pagination', array (
			'base'      => add_query_arg( 'tpage', '%#%' ),
			'format'    => '',
			'total'     => ceil( (int)$bbp_topics_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$bbp_topics_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$bbp_topics_template->pagination_links = paginate_links ( $bbp_topic_pagination );
	}

	// Return object
	return apply_filters( 'bbp_has_topics', $bbp_topics_template->have_posts(), $bbp_topics_template );
}

/**
 * bbp_topics()
 *
 * Whether there are more topics available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @return object Forum information
 */
function bbp_topics () {
	global $bbp_topics_template;
	return $bbp_topics_template->have_posts();
}

/**
 * bbp_the_topic()
 *
 * Loads up the current topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @return object Forum information
 */
function bbp_the_topic () {
	global $bbp_topics_template;
	return $bbp_topics_template->the_post();
}

/**
 * bbp_topic_id()
 *
 * Output id from bbp_topic_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @uses bbp_get_topic_id()
 */
function bbp_topic_id () {
	echo bbp_get_topic_id();
}
	/**
	 * bbp_get_topic_id()
	 *
	 * Return the topic ID
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @global object $topics_template
	 * @return string Forum id
	 */
	function bbp_get_topic_id () {
		global $bbp_topics_template, $wp_query;

		// Currently viewing a topic
		if ( bbp_is_topic() && isset( $wp_query->post->ID ) )
			$bbp_topic_id = $wp_query->post->ID;

		// Currently inside a topic loop
		elseif ( isset( $bbp_topics_template->post->ID ) )
			$bbp_topic_id = $bbp_topics_template->post->ID;

		// Fallback
		// @todo - experiment
		else
			$bbp_topic_id = get_the_ID();

		return apply_filters( 'bbp_get_topic_id', (int)$bbp_topic_id );
	}

/**
 * bbp_topic_permalink ()
 *
 * Output the link to the topic in the topic loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @uses bbp_get_topic_permalink()
 * @param int $topic_id optional
 */
function bbp_topic_permalink ( $topic_id = 0 ) {
	echo bbp_get_topic_permalink( $topic_id );
}
	/**
	 * bbp_get_topic_permalink()
	 *
	 * Return the link to the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @param int $topic_id optional
	 *
	 * @return string Permanent link to topic
	 */
	function bbp_get_topic_permalink ( $topic_id = 0 ) {
		return apply_filters( 'bbp_get_topic_permalink', get_permalink( $topic_id ) );
	}

/**
 * bbp_topic_title ()
 *
 * Output the title of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_title()
 */
function bbp_topic_title ( $topic_id = 0 ) {
	echo bbp_get_topic_title( $topic_id );
}
	/**
	 * bbp_get_topic_title ()
	 *
	 * Return the title of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @param int $topic_id optional
	 *
	 * @return string Title of topic
	 */
	function bbp_get_topic_title ( $topic_id = 0 ) {
		return apply_filters( 'bbp_get_topic_title', get_the_title( $topic_id ) );
	}

/**
 * bbp_topic_forum ()
 *
 * Output the forum a topic belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_forum()
 */
function bbp_topic_forum ( $topic_id = 0 ) {
	echo bbp_get_topic_forum( $topic_id );
}
	/**
	 * bbp_get_topic_forum ()
	 *
	 * Return the forum a topic belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_forum ( $topic_id = 0 ) {
		$forum_id = bbp_get_topic_forum_id( $topic_id );
		return apply_filters( 'bbp_get_topic_forum', bbp_get_forum_title( $forum_id ) );
	}

	/**
	 * bbp_topic_forum_id ()
	 *
	 * Output the forum ID a topic belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2491)
	 *
	 * @param int $topic_id optional
	 *
	 * @uses bbp_get_topic_forum_id()
	 */
	function bbp_topic_forum_id ( $topic_id = 0 ) {
		echo bbp_get_topic_forum_id( $topic_id );
	}
		/**
		 * bbp_get_topic_forum_id ()
		 *
		 * Return the forum ID a topic belongs to
		 *
		 * @package bbPress
		 * @subpackage Template Tags
		 * @since bbPress (1.2-r2491)
		 *
		 * @param int $topic_id optional
		 *
		 * @return string
		 */
		function bbp_get_topic_forum_id ( $topic_id = 0 ) {
			global $bbp_topics_template;

			if ( empty( $topic_id ) )
				$topic_id = bbp_get_topic_id();

			$forum_id = get_post_field( 'post_parent', $bbp_topics_template );

			return apply_filters( 'bbp_get_topic_forum_id', $forum_id );
		}

/**
 * bbp_topic_last_active ()
 *
 * Output the topics last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_last_active()
 */
function bbp_topic_last_active ( $topic_id = 0 ) {
	echo bbp_get_topic_last_active( $topic_id );
}
	/**
	 * bbp_get_topic_last_active ()
	 *
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_last_active ( $topic_id = 0 ) {
		if ( empty( $topic_id ) )
			$topic_id = bbp_get_topic_id();

		return apply_filters( 'bbp_get_topic_last_active', bbp_get_time_since( bbp_get_modified_time( $topic_id ) ) );
	}

/**
 * bbp_topic_reply_count ()
 *
 * Output total post count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @uses bbp_get_topic_reply_count()
 * @param int $topic_id
 */
function bbp_topic_reply_count ( $topic_id = 0 ) {
	echo bbp_get_topic_reply_count( $topic_id );
}
	/**
	 * bbp_get_topic_reply_count ()
	 *
	 * Return total post count of a topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_topic_id()
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $topic_id
	 */
	function bbp_get_topic_reply_count ( $topic_id = 0 ) {
		if ( empty( $topic_id ) )
			$topic_id = bbp_get_topic_id();

		$topic_replies = 0; //get_pages( array( 'post_parent' => $topic_id, 'post_type' => BBP_REPLY_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_topic_reply_count', $topic_replies );

		//return apply_filters( 'bbp_get_topic_topic_reply_count', (int)get_post_meta( $topic_id, 'bbp_topic_topic_reply_count', true ) );
	}

/**
 * bbp_update_topic_reply_count ()
 *
 * Adjust the total post count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2467)
 *
 * @todo make this not suck
 *
 * @uses bbp_get_topic_id()
 * @uses apply_filters
 *
 * @param int $new_topic_reply_count New post count
 * @param int $topic_id optional Forum ID to update
 *
 * @return int
 */
function bbp_update_topic_reply_count ( $new_topic_reply_count, $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		$topic_id = bbp_get_topic_id();

	return apply_filters( 'bbp_update_topic_reply_count', (int)update_post_meta( $topic_id, 'bbp_topic_reply_count', $new_topic_reply_count ) );
}

/**
 * bbp_topic_voice_count ()
 *
 * Output total voice count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2567)
 *
 * @uses bbp_get_topic_voice_count()
 * @uses apply_filters
 *
 * @param int $topic_id
 */
function bbp_topic_voice_count ( $topic_id = 0 ) {
	echo bbp_get_topic_voice_count( $topic_id );
}
	/**
	 * bbp_get_topic_voice_count ()
	 *
	 * Return total voice count of a topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2567)
	 *
	 * @uses bbp_get_topic_id()
	 * @uses apply_filters
	 *
	 * @param int $topic_id
	 *
	 * @return int Voice count of the topic
	 */
	function bbp_get_topic_voice_count ( $topic_id = 0 ) {
		if ( empty( $topic_id ) )
			$topic_id = bbp_get_topic_id();

		// Look for existing count, and populate if does not exist
		if ( !$voices = get_post_meta( $topic_id, 'bbp_topic_voice_count', true ) )
			$voices = bbp_update_topic_voice_count( $topic_id );

		return apply_filters( 'bbp_get_topic_voice_count', (int)$voices, $topic_id );
	}

/**
 * bbp_update_topic_voice_count ()
 *
 * Adjust the total voice count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2567)
 *
 * @uses bbp_get_topic_id()
 * @uses wpdb
 * @uses apply_filters
 *
 * @todo cache
 *
 * @param int $topic_id optional Topic ID to update
 *
 * @return bool false on failure, voice count on success
 */
function bbp_update_topic_voice_count ( $topic_id = 0 ) {
	global $wpdb;

	if ( empty( $topic_id ) )
		$topic_id = bbp_get_topic_id();

	// If it is not a topic or reply, then we don't need it
	if ( !in_array( get_post_field( 'post_type', $topic_id ), array( BBP_TOPIC_POST_TYPE_ID, BBP_REPLY_POST_TYPE_ID ) ) )
		return false;

	// If it's a reply, then get the parent (topic id)
	if ( BBP_REPLY_POST_TYPE_ID == get_post_field( 'post_type', $topic_id ) )
		$topic_id = get_post_field( 'post_parent', $topic_id );

	// There should always be at least 1 voice
	if ( !$voices = count( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '" . BBP_REPLY_POST_TYPE_ID . "' ) OR ( ID = %d AND post_type = '" . BBP_TOPIC_POST_TYPE_ID . "' );", $topic_id, $topic_id ) ) ) )
		$voices = 1;

	// Update the count
	update_post_meta( $topic_id, 'bbp_topic_voice_count', $voices );

	return apply_filters( 'bbp_update_topic_voice_count', (int)$voices );
}

/**
 * bbp_topic_pagination_count ()
 *
 * Output the pagination count
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2519)
 *
 * @global WP_Query $bbp_topics_template
 */
function bbp_topic_pagination_count () {
	echo bbp_get_topic_pagination_count();
}
	/**
	 * bbp_get_topic_pagination_count ()
	 *
	 * Return the pagination count
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2519)
	 *
	 * @global WP_Query $bbp_topics_template
	 * @return string
	 */
	function bbp_get_topic_pagination_count () {
		global $bbp_topics_template;

		// Set pagination values
		$start_num = intval( ( $bbp_topics_template->paged - 1 ) * $bbp_topics_template->posts_per_page ) + 1;
		$from_num  = bbp_number_format( $start_num );
		$to_num    = bbp_number_format( ( $start_num + ( $bbp_topics_template->posts_per_page - 1 ) > $bbp_topics_template->found_posts ) ? $bbp_topics_template->found_posts : $start_num + ( $bbp_topics_template->posts_per_page - 1 ) );
		$total     = bbp_number_format( $bbp_topics_template->found_posts );

		// Set return string
		if ( $total > 1 )
			$retstr = sprintf( __( 'Viewing %1$s to %2$s (of %3$s)', 'bbpress' ), $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s topic', 'bbpress' ), $total );

		// Filter and return
		return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
	}

/**
 * bbp_topic_pagination_links ()
 *
 * Output pagination links
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2519)
 */
function bbp_topic_pagination_links () {
	echo bbp_get_topic_pagination_links();
}
	/**
	 * bbp_get_topic_pagination_links ()
	 *
	 * Return pagination links
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2519)
	 *
	 * @global WP_Query $bbp_topics_template
	 * @return string
	 */
	function bbp_get_topic_pagination_links () {
		global $bbp_topics_template;

		return apply_filters( 'bbp_get_topic_pagination_links', $bbp_topics_template->pagination_links );
	}

/** END - Topic Loop Functions ************************************************/

/** START - Reply Loop Functions **********************************************/

/**
 * bbp_has_replies ( $args )
 *
 * The main reply loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @global WP_Query $bbp_replies_template
 * @param array $args Possible arguments to change returned replies
 * @return object Multidimensional array of reply information
 */
function bbp_has_replies ( $args = '' ) {
	global $bbp_replies_template;

	$default = array(
		// Narrow query down to bbPress topics
		'post_type'        => BBP_REPLY_POST_TYPE_ID,

		// Forum ID
		'post_parent'      => isset( $_REQUEST['topic_id'] ) ? $_REQUEST['topic_id'] : bbp_get_topic_id(),

		//'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'date',

		// 'ASC', 'DESC'
		'order'            => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC',

		// @todo replace 15 with setting
		'posts_per_page'   => isset( $_REQUEST['posts'] ) ? $_REQUEST['posts'] : 15,

		// Page Number
		'paged'            => isset( $_REQUEST['rpage'] ) ? $_REQUEST['rpage'] : 1,

		// Reply Search
		's'                => empty( $_REQUEST['rs'] ) ? '' : $_REQUEST['rs'],
	);

	// Set up topic variables
	$bbp_r = wp_parse_args( $args, $default );
	$r     = extract( $bbp_r );

	// Call the query
	$bbp_replies_template = new WP_Query( $bbp_r );

	// Add pagination values to query object
	$bbp_replies_template->posts_per_page = $posts_per_page;
	$bbp_replies_template->paged = $paged;

	// Only add pagination if query returned results
	if ( (int)$bbp_replies_template->found_posts && (int)$bbp_replies_template->posts_per_page ) {

		// Pagination settings with filter
		$bbp_replies_pagination = apply_filters( 'bbp_replies_pagination', array(
			'base'      => add_query_arg( 'tpage', '%#%' ),
			'format'    => '',
			'total'     => ceil( (int)$bbp_replies_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$bbp_replies_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$bbp_replies_template->pagination_links = paginate_links( $bbp_replies_pagination );
	}

	// Return object
	return apply_filters( 'bbp_has_replies', $bbp_replies_template->have_posts(), $bbp_replies_template );
}

/**
 * bbp_replies ()
 *
 * Whether there are more replies available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @global WP_Query $bbp_replies_template
 * @return object Replies information
 */
function bbp_replies () {
	global $bbp_replies_template;
	return $bbp_replies_template->have_posts();
}

/**
 * bbp_the_reply ()
 *
 * Loads up the current reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @global WP_Query $bbp_replies_template
 * @return object Reply information
 */
function bbp_the_reply () {
	global $bbp_replies_template;
	return $bbp_replies_template->the_post();
}

/**
 * bbp_reply_id ()
 *
 * Output id from bbp_get_reply_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @uses bbp_get_reply_id()
 */
function bbp_reply_id () {
	echo bbp_get_reply_id();
}
	/**
	 * bbp_get_reply_id ()
	 *
	 * Return the id of the reply in a replies loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2553)
	 *
	 * @global object $bbp_replies_template
	 * @return int Reply id
	 */
	function bbp_get_reply_id () {
		global $bbp_replies_template, $wp_query;

		// Currently viewing a reply
		if ( bbp_is_reply() && isset( $wp_query->post->ID ) )
			$bbp_reply_id = $wp_query->post->ID;

		// Currently inside a replies loop
		elseif ( isset( $bbp_replies_template->post->ID ) )
			$bbp_reply_id = $bbp_replies_template->post->ID;

		// Fallback
		// @todo - experiment
		else
			$bbp_reply_id = get_the_ID();

		return apply_filters( 'bbp_get_reply_id', (int)$bbp_reply_id );
	}

/**
 * bbp_reply_permalink ()
 *
 * Output the link to the reply in the reply loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @uses bbp_get_reply_permalink()
 * @param int $reply_id optional
 */
function bbp_reply_permalink ( $reply_id = 0 ) {
	echo bbp_get_reply_permalink( $reply_id );
}
	/**
	 * bbp_get_reply_permalink()
	 *
	 * Return the link to the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2553)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @param int $reply_id optional
	 *
	 * @return string Permanent link to reply
	 */
	function bbp_get_reply_permalink ( $reply_id = 0 ) {
		return apply_filters( 'bbp_get_reply_permalink', get_permalink( $reply_id ), $reply_id );
	}

/**
 * bbp_reply_title ()
 *
 * Output the title of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_title()
 */
function bbp_reply_title ( $reply_id = 0 ) {
	echo bbp_get_reply_title( $reply_id );
}

	/**
	 * bbp_get_reply_title ()
	 *
	 * Return the title of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2553)
	 *
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @param int $reply_id optional
	 *
	 * @return string Title of reply
	 */
	function bbp_get_reply_title ( $reply_id = 0 ) {
		return apply_filters( 'bbp_get_reply_title', get_the_title( $reply_id ), $reply_id );
	}

/**
 * bbp_reply_content ()
 *
 * Output the content of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @todo Have a parameter reply_id
 *
 * @uses bbp_get_reply_content()
 */
function bbp_reply_content () {
	echo bbp_get_reply_content();
}
	/**
	 * bbp_get_reply_content ()
	 *
	 * Return the content of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2553)
	 *
	 * @uses apply_filters
	 * @uses get_the_content()
	 *
	 * @return string Content of the reply
	 */
	function bbp_get_reply_content () {
		return apply_filters( 'bbp_get_reply_content', get_the_content() );
	}

/**
 * bbp_reply_topic ()
 *
 * Output the topic title a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_topic()
 */
function bbp_reply_topic ( $reply_id = 0 ) {
	echo bbp_get_reply_topic( $reply_id );
}
	/**
	 * bbp_get_reply_topic ()
	 *
	 * Return the topic title a reply belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2553)
	 *
	 * @param int $reply_id optional
	 *
	 * @uses bbp_get_reply_topic_id ()
	 * @uses bbp_topic_title ()
	 *
	 * @return string
	 */
	function bbp_get_reply_topic ( $reply_id = 0 ) {
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		return apply_filters( 'bbp_get_reply_topic', bbp_get_topic_title( $topic_id ), $reply_id, $topic_id );
	}

/**
 * bbp_reply_topic_id ()
 *
 * Output the topic ID a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2553)
 *
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_topic_id ()
 */
function bbp_reply_topic_id ( $reply_id = 0 ) {
	echo bbp_get_reply_topic_id( $reply_id );
}
	/**
	 * bbp_get_reply_topic_id ()
	 *
	 * Return the topic ID a reply belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2553)
	 *
	 * @param int $reply_id optional
	 *
	 * @todo - Walk ancestors and look for topic post_type (for threaded replies)
	 *
	 * @return string
	 */
	function bbp_get_reply_topic_id ( $reply_id = 0 ) {
		if ( empty( $reply_id ) )
			$reply_id = bbp_get_reply_id();

		$topic_id = get_post_field( 'post_parent', $bbp_replies_template );

		return apply_filters( 'bbp_get_reply_topic_id', $topic_id, $reply_id );
	}

/** END reply Loop Functions **************************************************/

/** START is_ Functions *******************************************************/

/**
 * bbp_is_forum ()
 *
 * Check if current page is a bbPress forum
 *
 * @since bbPress (1.2-r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_forum () {
	global $wp_query;

	if ( isset( $wp_query->query_vars['post_type'] ) && BBP_FORUM_POST_TYPE_ID === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && BBP_FORUM_POST_TYPE_ID === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_topic ()
 *
 * Check if current page is a bbPress topic
 *
 * @since bbPress (1.2-r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_topic () {
	global $wp_query;

	if ( isset( $wp_query->query_vars['post_type'] ) && BBP_TOPIC_POST_TYPE_ID === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && BBP_TOPIC_POST_TYPE_ID === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_reply ()
 *
 * Check if current page is a bbPress topic reply
 *
 * @since bbPress (1.2-r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_reply () {
	global $wp_query;

	if ( isset( $wp_query->query_vars['post_type'] ) && BBP_REPLY_POST_TYPE_ID === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && BBP_REPLY_POST_TYPE_ID === $_GET['post_type'] )
		return true;

	return false;
}

/** END is_ Functions *********************************************************/

?>
