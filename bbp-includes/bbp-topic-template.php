<?php

/**
 * bbPress Topic Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

/** START - Topic Loop Functions **********************************************/

/**
 * The main topic loop. WordPress makes this easy for us
 *
 * @since bbPress (r2485)
 *
 * @param mixed $args All the arguments supported by {@link WP_Query}
 * @uses bbp_is_user_profile_page() To check if it's the profile page
 * @uses get_the_ID() To get the id
 * @uses WP_Query To make query and get the topics
 * @uses is_page() To check if it's a page
 * @uses bbp_is_forum() To check if it's a forum
 * @uses bbp_get_paged() To get the current page value
 * @uses bbp_get_super_stickies() To get the super stickies
 * @uses bbp_get_stickies() To get the forum stickies
 * @uses wpdb::get_results() To execute our query and get the results
 * @uses WP_Rewrite::using_permalinks() To check if the blog is using permalinks
 * @uses get_permalink() To get the permalink
 * @uses add_query_arg() To add custom args to the url
 * @uses apply_filters() Calls 'bbp_topics_pagination' with the pagination args
 * @uses paginate_links() To paginate the links
 * @uses apply_filters() Calls 'bbp_has_topics' with
 *                        bbPres::topic_query::have_posts()
 *                        and bbPres::topic_query
 * @return object Multidimensional array of topic information
 */
function bbp_has_topics( $args = '' ) {
	global $wp_rewrite, $bbp, $wpdb;

	$default = array (
		// Narrow query down to bbPress topics
		'post_type'            => $bbp->topic_id,

		// Forum ID
		'post_parent'          => bbp_get_forum_id(),

		// Make sure topic has some last activity time
		'meta_key'             => '_bbp_topic_last_active',

		// 'meta_value', 'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'              => 'meta_value',

		// 'ASC', 'DESC'
		'order'                => 'DESC',

		// @todo replace 15 with setting
		'posts_per_page'       => 15,

		// Page Number
		'paged'                => bbp_get_paged(),

		// Topic Search
		's'                    => !empty( $_REQUEST['ts'] ) ? $_REQUEST['ts'] : '',

		// Ignore sticky topics?
		'ignore_sticky_topics' => false,
	);

	// Don't pass post_parent if forum_id is empty or 0
	if ( empty( $default['post_parent'] ) ) {
		unset( $default['post_parent'] );
		if ( !bbp_is_user_profile_page() )
			$post_parent = get_the_ID();
	}

	// Set up topic variables
	$bbp_t = wp_parse_args( $args, $default );
	extract( $bbp_t );

	// If we're viewing a tax/term, use the existing query; if not, run our own
	if ( !is_tax() ) {
		$bbp->topic_query = new WP_Query( $bbp_t );
	} else {
		global $wp_query;
		$bbp->topic_query = $wp_query;
	}

	// Put sticky posts at the top of the posts array, much part of code taken from query.php in wp-includes
	if ( empty( $ignore_sticky_topics ) && ( is_page() || bbp_is_forum() ) && bbp_get_paged() <= 1 ) {
		$stickies = bbp_get_super_stickies();
		$stickies = !empty( $post_parent ) ? array_merge( $stickies, bbp_get_stickies( $post_parent ) ) : $stickies;
		$stickies = array_unique( $stickies );

		if ( is_array( $stickies ) && !empty( $stickies ) ) {

			$num_topics    = count( $bbp->topic_query->posts );
			$sticky_offset = 0;

			// Loop over topics and relocate stickies to the front.
			for ( $i = 0; $i < $num_topics; $i++ ) {

				if ( in_array( $bbp->topic_query->posts[$i]->ID, $stickies ) ) {
					$sticky = $bbp->topic_query->posts[$i];

					// Remove sticky from current position
					array_splice( $bbp->topic_query->posts, $i, 1 );

					// Move to front, after other stickies
					array_splice( $bbp->topic_query->posts, $sticky_offset, 0, array( $sticky ) );

					// Increment the sticky offset.  The next sticky will be placed at this offset.
					$sticky_offset++;

					// Remove post from sticky posts array
					$offset = array_search( $sticky->ID, $stickies );

					unset( $stickies[$offset] );
				}

			}

			// If any posts have been excluded specifically, Ignore those that are sticky.
			if ( !empty( $stickies ) && !empty( $post__not_in ) )
				$stickies = array_diff( $stickies, $post__not_in );

			// Fetch sticky posts that weren't in the query results
			if ( !empty( $stickies ) ) {
				global $wpdb;

				$stickies__in   = implode( ',', array_map( 'absint', $stickies ) );
				$stickies_where = "AND $wpdb->posts.post_type = '$bbp->topic_id'";
				$stickies       = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE $wpdb->posts.ID IN ($stickies__in) $stickies_where" );

				foreach ( $stickies as $sticky ) {

					// Ignore sticky posts the current user cannot read or are not published.
					if ( 'publish' != $sticky->post_status )
						continue;

					array_splice( $bbp->topic_query->posts, $sticky_offset, 0, array( $sticky ) );
					$sticky_offset++;
				}
			}
		}
	}

	if ( -1 == $posts_per_page )
		$posts_per_page = $bbp->topic_query->post_count;

	// Add pagination values to query object
	$bbp->topic_query->posts_per_page = $posts_per_page;
	$bbp->topic_query->paged          = $paged;

	// Only add pagination if query returned results
	if ( ( (int) $bbp->topic_query->post_count || (int) $bbp->topic_query->found_posts ) && (int) $bbp->topic_query->posts_per_page ) {

		// If pretty permalinks are enabled, make our pagination pretty
		if ( $wp_rewrite->using_permalinks() && bbp_is_user_profile_page() )
			$base = $base = user_trailingslashit( trailingslashit( bbp_get_user_profile_url( bbp_get_displayed_user_id() ) ) . 'page/%#%/' );
		elseif ( $wp_rewrite->using_permalinks() )
			$base = user_trailingslashit( trailingslashit( get_permalink( $post_parent ) ) . 'page/%#%/' );
		else
			$base = add_query_arg( 'paged', '%#%' );

		// Pagination settings with filter
		$bbp_topic_pagination = apply_filters( 'bbp_topic_pagination', array (
			'base'      => $base,
			'format'    => '',
			'total'     => $posts_per_page == $bbp->topic_query->found_posts ? 1 : ceil( (int) $bbp->topic_query->found_posts / (int) $posts_per_page ),
			'current'   => (int) $bbp->topic_query->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$bbp->topic_query->pagination_links = paginate_links ( $bbp_topic_pagination );

		// Remove first page from pagination
		$bbp->topic_query->pagination_links = str_replace( 'page/1/\'', '\'', $bbp->topic_query->pagination_links );
	}

	// Return object
	return apply_filters( 'bbp_has_topics', $bbp->topic_query->have_posts(), $bbp );
}

/**
 * Whether there are more topics available in the loop
 *
 * @since bbPress (r2485)
 *
 * @uses WP_Query bbPress::topic_query::have_posts()
 * @return object Topic information
 */
function bbp_topics() {
	global $bbp;
	return $bbp->topic_query->have_posts();
}

/**
 * Loads up the current topic in the loop
 *
 * @since bbPress (r2485)
 *
 * @uses WP_Query bbPress::topic_query::the_post()
 * @return object Topic information
 */
function bbp_the_topic() {
	global $bbp;
	return $bbp->topic_query->the_post();
}

/**
 * Output the topic id
 *
 * @since bbPress (r2485)
 *
 * @uses bbp_get_topic_id() To get the topic id
 */
function bbp_topic_id( $topic_id = 0) {
	echo bbp_get_topic_id( $topic_id );
}
	/**
	 * Return the topic id
	 *
	 * @since bbPress (r2485)
	 *
	 * @param $topic_id Optional. Used to check emptiness
	 * @uses bbPress::topic_query::post::ID To get the topic id
	 * @uses bbp_is_topic() To check if it's a topic page
	 * @uses bbp_is_topic_edit() To check if it's a topic edit page
	 * @uses bbp_is_reply() To check if it it's a reply page
	 * @uses bbp_is_reply_edit() To check if it's a reply edit page
	 * @uses bbp_get_reply_topic_edit() To get the reply topic id
	 * @uses WP_Query::post::ID To get the topic id
	 * @uses apply_filters() Calls 'bbp_get_topic_id' with the topic id
	 */
	function bbp_get_topic_id( $topic_id = 0 ) {
		global $bbp, $wp_query, $bbp;

		// Easy empty checking
		if ( !empty( $topic_id ) && is_numeric( $topic_id ) )
			$bbp_topic_id = $topic_id;

		// Currently inside a topic loop
		elseif ( !empty( $bbp->topic_query->in_the_loop ) && isset( $bbp->topic_query->post->ID ) )
			$bbp_topic_id = $bbp->topic_query->post->ID;

		// Currently viewing a topic
		elseif ( ( bbp_is_topic() || bbp_is_topic_edit() ) && isset( $wp_query->post->ID ) )
			$bbp_topic_id = $wp_query->post->ID;

		// Currently viewing a singular reply
		elseif ( ( bbp_is_reply() || bbp_is_reply_edit() ) )
			$bbp_topic_id = bbp_get_reply_topic_id();

		// Fallback
		else
			$bbp_topic_id = 0;

		$bbp->current_topic_id = $bbp_topic_id;

		return apply_filters( 'bbp_get_topic_id', (int) $bbp_topic_id );
	}

/**
 * Output the link to the topic in the topic loop
 *
 * @since bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_permalink() To get the topic permalink
 */
function bbp_topic_permalink( $topic_id = 0 ) {
	echo bbp_get_topic_permalink( $topic_id );
}
	/**
	 * Return the link to the topic in the loop
	 *
	 * @since bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_permalink() To get the topic permalink
	 * @uses apply_filters() Calls 'bbp_get_topic_permalink' with the link
	 *                        and topic id
	 * @return string Permanent link to topic
	 */
	function bbp_get_topic_permalink( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_permalink', get_permalink( $topic_id ), $topic_id );
	}

/**
 * Output the title of the topic in the loop
 *
 * @since bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_title() To get the topic title
 */
function bbp_topic_title( $topic_id = 0 ) {
	echo bbp_get_topic_title( $topic_id );
}
	/**
	 * Return the title of the topic in the loop
	 *
	 * @since bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_the_title() To get the title
	 * @uses apply_filters() Calls 'bbp_get_topic_title' with the title and
	 *                        topic id
	 * @return string Title of topic
	 */
	function bbp_get_topic_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_title', get_the_title( $topic_id ), $topic_id );
	}

/**
 * Output the status of the topic in the loop
 *
 * @since bbPress (r2667)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_status() To get the topic status
 */
function bbp_topic_status( $topic_id = 0 ) {
	echo bbp_get_topic_status( $topic_id );
}
	/**
	 * Return the status of the topic in the loop
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post_status() To get the topic status
	 * @uses apply_filters() Calls 'bbp_get_topic_status' with the
	 * @return string Status of topic
	 */
	function bbp_get_topic_status( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_status', get_post_status( $topic_id ), $topic_id );
	}

/**
 * Is the topic open to new replies?
 *
 * @since bbPress (r2727)
 *
 * @uses bbp_get_topic_status()
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_is_topic_closed() To check if the topic is closed
 * @return bool True if open, false if closed.
 */
function bbp_is_topic_open( $topic_id = 0 ) {
	return !bbp_is_topic_closed( $topic_id );
}

	/**
	 * Is the topic closed to new replies?
	 *
	 * @since bbPress (r2746)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_status() To get the topic status
	 * @return bool True if closed, false if not.
	 */
	function bbp_is_topic_closed( $topic_id = 0 ) {
		global $bbp;

		if ( $bbp->closed_status_id == bbp_get_topic_status( $topic_id ) )
			return true;

		return false;
	}

/**
 * Is the topic a sticky or super sticky?
 *
 * @since bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $check_super Optional. If set to true and if the topic is not a
 *                           normal sticky, it is checked if it is a super
 *                           sticky or not. Defaults to true.
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_get_stickies() To get the stickies
 * @uses bbp_is_topic_super_sticky() To check if the topic is a super sticky
 * @return bool True if sticky or super sticky, false if not.
 */
function bbp_is_topic_sticky( $topic_id = 0, $check_super = true ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_topic_forum_id( $topic_id );
	$stickies = bbp_get_stickies( $forum_id );

	if ( in_array( $topic_id, $stickies ) || ( !empty( $check_super ) && bbp_is_topic_super_sticky( $topic_id ) ) )
		return true;

	return false;
}

/**
 * Is the topic a super sticky?
 *
 * @since bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_super_stickies() To get the super stickies
 * @return bool True if super sticky, false if not.
 */
function bbp_is_topic_super_sticky( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$stickies = bbp_get_super_stickies( $topic_id );

	return in_array( $topic_id, $stickies );
}

/**
 * Is the topic marked as spam?
 *
 * @since bbPress (r2727)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_topic_status() To get the topic status
 * @return bool True if spam, false if not.
 */
function bbp_is_topic_spam( $topic_id = 0 ) {
	global $bbp;

	$topic_status = bbp_get_topic_status( bbp_get_topic_id( $topic_id ) );
	return $bbp->spam_status_id == $topic_status;
}

/**
 * Is the posted by an anonymous user?
 *
 * @since bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_topic_author_id() To get the topic author id
 * @uses get_post_meta() To get the anonymous user name and email meta
 * @return bool True if the post is by an anonymous user, false if not.
 */
function bbp_is_topic_anonymous( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( 0 != bbp_get_topic_author_id( $topic_id ) )
		return false;

	if ( false == get_post_meta( $topic_id, '_bbp_anonymous_name', true ) )
		return false;

	if ( false == get_post_meta( $topic_id, '_bbp_anonymous_email', true ) )
		return false;

	// The topic is by an anonymous user

	return true;
}

/**
 * Output the author of the topic in the loop
 *
 * @since bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_author() To get the topic author
 */
function bbp_topic_author( $topic_id = 0 ) {
	echo bbp_get_topic_author( $topic_id );
}
	/**
	 * Return the author of the topic in the loop
	 *
	 * @since bbPress (r2590)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_is_topic_anonymous() To check if the topic is by an
	 *                                 anonymous user
	 * @uses apply_filters() Calls 'bbp_get_topic_author' with the author
	 *                        and topic id
	 * @return string Author of topic
	 */
	function bbp_get_topic_author( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		if ( !bbp_is_topic_anonymous( $topic_id ) )
			$author = get_the_author();
		else
			$author = get_post_meta( $topic_id, '_bbp_anonymous_name', true );

		return apply_filters( 'bbp_get_topic_author', $author, $topic_id );
	}

/**
 * Output the author ID of the topic in the loop
 *
 * @since bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_author_id() To get the topic author id
 */
function bbp_topic_author_id( $topic_id = 0 ) {
	echo bbp_get_topic_author_id( $topic_id );
}
	/**
	 * Return the author ID of the topic in the loop
	 *
	 * @since bbPress (r2590)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post_field() To get the topic author id
	 * @uses apply_filters() Calls 'bbp_get_topic_author_id' with the author
	 *                        id and topic id
	 * @return string Author of topic
	 */
	function bbp_get_topic_author_id( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_author_id', get_post_field( 'post_author', $topic_id ), $topic_id );
	}

/**
 * Output the author display_name of the topic in the loop
 *
 * @since bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_author_display_name() To get the topic author's display
 *                                            name
 */
function bbp_topic_author_display_name( $topic_id = 0 ) {
	echo bbp_get_topic_author_display_name( $topic_id );
}
	/**
	 * Return the author display_name of the topic in the loop
	 *
	 * @since bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_is_topic_anonymous() To check if the topic is by an
	 *                                 anonymous user
	 * @uses bbp_get_topic_author_id() To get the topic author id
	 * @uses get_the_author_meta() To get the author meta
	 * @uses get_post_meta() To get the anonymous user name
	 * @uses apply_filters() Calls 'bbp_get_topic_author_id' with the
	 *                        display name and topic id
	 * @return string Topic's author's display name
	 */
	function bbp_get_topic_author_display_name( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Check for anonymous user
		if ( !bbp_is_topic_anonymous( $topic_id ) )
			$author_name = get_the_author_meta( 'display_name', bbp_get_topic_author_id( $topic_id ) );
		else
			$author_name = get_post_meta( $topic_id, '_bbp_anonymous_name', true );

		return apply_filters( 'bbp_get_topic_author_id', esc_attr( $author_name ), $topic_id );
	}

/**
 * Output the author avatar of the topic in the loop
 *
 * @since bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $size Optional. Avatar size. Defaults to 40
 * @uses bbp_get_topic_author_avatar() To get the topic author avatar
 */
function bbp_topic_author_avatar( $topic_id = 0, $size = 40 ) {
	echo bbp_get_topic_author_avatar( $topic_id, $size );
}
	/**
	 * Return the author avatar of the topic in the loop
	 *
	 * @since bbPress (r2590)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param int $size Optional. Avatar size. Defaults to 40
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_is_topic_anonymous() To check if the topic is by an
	 *                                 anonymous user
	 * @uses bbp_get_topic_author_id() To get the topic author id
	 * @uses get_post_meta() To get the anonymous user's email
	 * @uses get_avatar() To get the avatar
	 * @uses apply_filters() Calls 'bbp_get_topic_author_avatar' with the
	 *                        avatar, topic id and size
	 * @return string Avatar of the author of the topic
	 */
	function bbp_get_topic_author_avatar( $topic_id = 0, $size = 40 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Check for anonymous user
		if ( !bbp_is_topic_anonymous( $topic_id ) )
			$author_avatar = get_avatar( bbp_get_topic_author_id( $topic_id ), $size );
		else
			$author_avatar = get_avatar( get_post_meta( $topic_id, '_bbp_anonymous_email', true ), $size );

		return apply_filters( 'bbp_get_topic_author_avatar', $author_avatar, $topic_id, $size );
	}

/**
 * Output the author link of the topic in the loop
 *
 * @since bbPress (r2717)
 *
 * @param mixed|int $args If it is an integer, it is used as topic_id. Optional.
 * @uses bbp_get_topic_author_link() To get the topic author link
 */
function bbp_topic_author_link( $args = '' ) {
	echo bbp_get_topic_author_link( $args );
}
	/**
	 * Return the author link of the topic in the loop
	 *
	 * @since bbPress (r2717)
	 *
	 * @param mixed|int $args If it is an integer, it is used as topic id.
	 *                         Optional.
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_is_topic() To check if it's the topic page
	 * @uses bbp_get_topic_author() To get the topic author
	 * @uses bbp_is_topic_anonymous() To check if the topic is by an
	 *                                 anonymous user
	 * @uses bbp_get_topic_author_avatar() To get the topic author avatar
	 * @uses bbp_get_topic_author() To get the topic author
	 * @uses bbp_get_topic_author_url() To get the topic author url
	 * @uses apply_filters() Calls 'bbp_get_topic_author_link' with the link
	 *                        and args
	 * @return string Author link of topic
	 */
	function bbp_get_topic_author_link( $args = '' ) {
		// Used as topic_id
		if ( is_int( $args ) ) {
			$topic_id = bbp_get_topic_id( $args );
		} else {
			$defaults = array (
				'topic_id'   => 0,
				'link_title' => '',
				'link_text'  => ''
			);

			$r = wp_parse_args( $args, $defaults );
			extract( $r );
		}

		if ( empty( $topic_id ) )
			$topic_id = bbp_get_topic_id( $topic_id );

		if ( empty( $link_title ) && ( bbp_is_topic() || bbp_is_topic() ) )
			$link_title = sprintf( !bbp_is_topic_anonymous( $topic_id ) ? __( 'View %s\'s profile', 'bbpress' ) : __( 'Visit %s\'s website', 'bbpress' ), bbp_get_topic_author( $topic_id ) );

		if ( empty( $link_text ) && ( bbp_is_topic() || bbp_is_topic() ) )
			$link_text = bbp_get_topic_author_avatar( $topic_id, 80 );
		else
			$link_text = bbp_get_topic_author( $topic_id );

		$link_title = !empty( $link_title ) ? ' title="' . $link_title . '"' : '';

		// Check for anonymous user
		if ( $author_url = bbp_get_topic_author_url( $topic_id ) )
			$author_link = sprintf( '<a href="%1$s"%2$s>%3$s</a>', $author_url, $link_title, $link_text );
		else
			$author_link = $link_text;

		return apply_filters( 'bbp_get_topic_author_link', $author_link, $args );
	}

		/**
		 * Output the author url of the topic in the loop
		 *
		 * @since bbPress (r2590)
		 *
		 * @param int $topic_id Optional. Topic id
		 * @uses bbp_get_topic_author_url() To get the topic author url
		 */
		function bbp_topic_author_url( $topic_id = 0 ) {
			echo bbp_get_topic_author_url( $topic_id );
		}

			/**
			 * Return the author url of the topic in the loop
			 *
			 * @since bbPress (r2590)
			 *
			 * @param int $topic_id Optional. Topic id
			 * @uses bbp_get_topic_id() To get the topic id
			 * @uses bbp_is_topic_anonymous() To check if the topic
			 *                                 is by an anonymous
			 *                                 user or not
			 * @uses bbp_get_topic_author_id() To get topic author
			 *                                  id
			 * @uses bbp_get_user_profile_url() To get profile url
			 * @uses get_post_meta() To get anonmous user's website
			 * @uses apply_filters() Calls
			 *                        'bbp_get_topic_author_url'
			 *                        with the link & topic id
			 * @return string Author URL of topic
			 */
			function bbp_get_topic_author_url( $topic_id = 0 ) {
				$topic_id = bbp_get_topic_id( $topic_id );

				// Check for anonymous user
				if ( !bbp_is_topic_anonymous( $topic_id ) )
					$author_url = bbp_get_user_profile_url( bbp_get_topic_author_id( $topic_id ) );
				else
					if ( !$author_url = get_post_meta( $topic_id, '_bbp_anonymous_website', true ) )
						$author_url = '';

				return apply_filters( 'bbp_get_topic_author_url', $author_url, $topic_id );
			}

/**
 * Output the title of the forum a topic belongs to
 *
 * @since bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_forum_title() To get the topic's forum title
 */
function bbp_topic_forum_title( $topic_id = 0 ) {
	echo bbp_get_topic_forum_title( $topic_id );
}
	/**
	 * Return the title of the forum a topic belongs to
	 *
	 * @since bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get topic id
	 * @uses bbp_get_topic_forum_id() To get topic's forum id
	 * @uses apply_filters() Calls 'bbp_get_topic_forum' with the forum
	 *                        title and topic id
	 * @return string Topic forum title
	 */
	function bbp_get_topic_forum_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		return apply_filters( 'bbp_get_topic_forum', bbp_get_forum_title( $forum_id ), $topic_id );
	}

/**
 * Output the forum id a topic belongs to
 *
 * @since bbPress (r2491)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_forum_id()
 */
function bbp_topic_forum_id( $topic_id = 0 ) {
	echo bbp_get_topic_forum_id( $topic_id );
}
	/**
	 * Return the forum id a topic belongs to
	 *
	 * @since bbPress (r2491)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get topic id
	 * @uses get_post_field() To get get topic's parent
	 * @uses apply_filters() Calls 'bbp_get_topic_forum_id' with the forum
	 *  id and topic id
	 * @return int Topic forum id
	 */
	function bbp_get_topic_forum_id( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = get_post_field( 'post_parent', $topic_id );

		return apply_filters( 'bbp_get_topic_forum_id', $forum_id, $topic_id );
	}

/**
 * Output the topics last update date/time (aka freshness)
 *
 * @since bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_last_active() To get topic freshness
 */
function bbp_topic_last_active( $topic_id = 0 ) {
	echo bbp_get_topic_last_active( $topic_id );
}
	/**
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get topic id
	 * @uses get_post_meta() To get the topic lst active meta
	 * @uses bbp_get_topic_last_reply_id() To get topic last reply id
	 * @uses get_post_field() To get the post date of topic/reply
	 * @uses bbp_convert_date() To convert date
	 * @uses bbp_get_time_since() To get time in since format
	 * @uses apply_filters() Calls 'bbp_get_topic_last_active' with topic
	 *                        freshness and topic id
	 * @return string Topic freshness
	 */
	function bbp_get_topic_last_active( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Try to get the most accurate freshness time possible
		if ( !$last_active = get_post_meta( $topic_id, '_bbp_topic_last_active', true ) ) {
			if ( $reply_id = bbp_get_topic_last_reply_id( $topic_id ) ) {
				$last_active = get_post_field( 'post_date', $reply_id );
			} else {
				$last_active = get_post_field( 'post_date', $topic_id );
			}
		}

		$last_active = !empty( $last_active ) ? bbp_get_time_since( bbp_convert_date( $last_active ) ) : '';

		// Return the time since
		return apply_filters( 'bbp_get_topic_last_active', $last_active, $topic_id );
	}

/** TOPIC LAST REPLY **********************************************************/

/**
 * Output the id of the topics last reply
 *
 * @since bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_last_reply_id() To get the topic last reply id
 */
function bbp_topic_last_reply_id( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_id( $topic_id );
}
	/**
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post_meta() To get the last reply id meta
	 * @uses apply_filters() Calls 'bbp_get_topic_last_reply_id' with the
	 *                        last reply id and topic id
	 * @return int Topic last reply id
	 */
	function bbp_get_topic_last_reply_id( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = get_post_meta( $topic_id, '_bbp_topic_last_reply_id', true );

		return apply_filters( 'bbp_get_topic_last_reply_id', $reply_id, $topic_id );
	}

/**
 * Output the title of the last reply inside a topic
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_last_reply_title() To get the topic last reply title
 */
function bbp_topic_last_reply_title( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_title( $topic_id );
}
	/**
	 * Return the title of the last reply inside a topic
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_topic_last_reply_id() To get the topic last reply id
	 * @uses bbp_get_reply_title() To get the reply title
	 * @uses apply_filters() Calls 'bbp_get_topic_last_topic_title' with
	 *                        the reply title and topic id
	 * @return string Topic last reply title
	 */
	function bbp_get_topic_last_reply_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		return apply_filters( 'bbp_get_topic_last_topic_title', bbp_get_reply_title( bbp_get_topic_last_reply_id( $topic_id ) ), $topic_id );
	}

/**
 * Output the link to the last reply in a topic
 *
 * @since bbPress (r2464)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_last_reply_permalink() To get the topic's last reply link
 */
function bbp_topic_last_reply_permalink( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_permalink( $topic_id );
}
	/**
	 * Return the link to the last reply in a topic
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_topic_last_reply_id() To get the topic last reply id
	 * @uses bbp_get_reply_permalink() To get the reply permalink
	 * @uses apply_filters() Calls 'bbp_get_topic_last_topic_permalink' with
	 *                        the reply permalink and topic id
	 * @return string Permanent link to the reply
	 */
	function bbp_get_topic_last_reply_permalink( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		return apply_filters( 'bbp_get_topic_last_reply_permalink', bbp_get_reply_permalink( bbp_get_topic_last_reply_id( $topic_id ) ) );
	}

/**
 * Output the link to the last reply in a topic
 *
 * @since bbPress (r2683)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_last_reply_url() To get the topic last reply url
 */
function bbp_topic_last_reply_url( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_url( $topic_id );
}
	/**
	 * Return the link to the last reply in a topic
	 *
	 * @since bbPress (r2683)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_topic_last_reply_id() To get the topic last reply id
	 * @uses bbp_get_reply_url() To get the reply url
	 * @uses bbp_get_reply_permalink() To get the reply permalink
	 * @uses apply_filters() Calls 'bbp_get_topic_last_topic_url' with
	 *                        the reply url and topic id
	 * @return string Topic last reply url
	 */
	function bbp_get_topic_last_reply_url( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = bbp_get_topic_last_reply_id( $topic_id );

		if ( !empty( $reply_id ) )
			$reply_url = bbp_get_reply_url( $reply_id );
		else
			$reply_url = bbp_get_topic_permalink( $topic_id );

		return apply_filters( 'bbp_get_topic_last_reply_url', $reply_url );
	}

/**
 * Output link to the most recent activity inside a topic, complete with link
 * attributes and content.
 *
 * @since bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_freshness_link() To get the topic freshness link
 */
function bbp_topic_freshness_link( $topic_id = 0) {
	echo bbp_get_topic_freshness_link( $topic_id );
}
	/**
	 * Returns link to the most recent activity inside a topic, complete
	 * with link attributes and content.
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_topic_last_reply_url() To get the topic last reply url
	 * @uses bbp_get_topic_last_reply_title() To get the reply title
	 * @uses bbp_get_topic_last_active() To get the topic freshness
	 * @uses apply_filters() Calls 'bbp_get_topic_freshness_link' with the
	 *                        link and topic id
	 * @return string Topic freshness link
	 */
	function bbp_get_topic_freshness_link( $topic_id = 0 ) {
		$topic_id   = bbp_get_topic_id( $topic_id );
		$link_url   = bbp_get_topic_last_reply_url( $topic_id );
		$title      = bbp_get_topic_last_reply_title( $topic_id );
		$time_since = bbp_get_topic_last_active( $topic_id );

		if ( !empty( $time_since ) )
			$anchor = '<a href="' . $link_url . '" title="' . esc_attr( $title ) . '">' . $time_since . '</a>';
		else
			$anchor = __( 'No Replies', 'bbpress' );

		return apply_filters( 'bbp_get_topic_freshness_link', $anchor, $topic_id );
	}

/**
 * Output the replies link of the topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_replies_link() To get the topic replies link
 */
function bbp_topic_replies_link( $topic_id = 0 ) {
	echo bbp_get_topic_replies_link( $topic_id );
}

	/**
	 * Return the replies link of the topic
	 *
	 * @since bbPress (r2740)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses bbp_get_topic_reply_count() To get the topic reply count
	 * @uses bbp_get_topic_permalink() To get the topic permalink
	 * @uses remove_query_arg() To remove args from the url
	 * @uses bbp_get_topic_hidden_reply_count() To get the topic hidden
	 *                                           reply count
	 * @uses current_user_can() To check if the current user can edit others
	 *                           replies
	 * @uses add_query_arg() To add custom args to the url
	 * @uses apply_filters() Calls 'bbp_get_topic_replies_link' with the
	 *                        replies link and topic id
	 */
	function bbp_get_topic_replies_link( $topic_id = 0 ) {
		global $bbp;

		$topic    = get_post( bbp_get_topic_id( (int) $topic_id ) );
		$topic_id = $topic->ID;
		$replies  = bbp_get_topic_reply_count( $topic_id );
		$replies  = sprintf( _n( '%s reply', '%s replies', $replies, 'bbpress' ), $replies );
		$retval   = '';

		if ( !empty( $_GET['view'] ) && 'all' == $_GET['view'] && current_user_can( 'edit_others_replies' ) )
			$retval .= "<a href='" . esc_url( remove_query_arg( array( 'view' => 'all' ),  bbp_get_topic_permalink( $topic_id ) ) ) . "'>$replies</a>";
		else
			$retval .= $replies;

		if ( current_user_can( 'edit_others_replies' ) && $deleted = bbp_get_topic_hidden_reply_count( $topic_id ) ) {
			$extra = sprintf( __( ' + %d more', 'bbpress' ), $deleted );
			if ( !empty( $_GET['view'] ) && 'all' == $_GET['view'] )
				$retval .= " $extra";
			else
				$retval .= " <a href='" . esc_url( add_query_arg( array( 'view' => 'all' ) ) ) . "'>$extra</a>";
		}

		return apply_filters( 'bbp_get_topic_replies_link', $retval, $topic_id );
	}

/**
 * Output total reply count of a topic
 *
 * @since bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_reply_count() To get the topic reply count
 */
function bbp_topic_reply_count( $topic_id = 0 ) {
	echo bbp_get_topic_reply_count( $topic_id );
}
	/**
	 * Return total reply count of a topic
	 *
	 * @since bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post_meta() To get the topic reply count meta
	 * @uses bbp_update_topic_reply_count() To update the topic reply count
	 * @uses apply_filters() Calls 'bbp_get_topic_reply_count' with the
	 *                        reply count and topic id
	 * @return int Reply count
	 */
	function bbp_get_topic_reply_count( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$replies  = get_post_meta( $topic_id, '_bbp_topic_reply_count', true );

		if ( '' === $replies )
			$replies = bbp_update_topic_reply_count( $topic_id );

		return apply_filters( 'bbp_get_topic_reply_count', (int) $replies, $topic_id );
	}

/**
 * Output total hidden reply count of a topic (hidden includes trashed and
 * spammed replies)
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_hidden_reply_count() To get the topic hidden reply count
 */
function bbp_topic_hidden_reply_count( $topic_id = 0 ) {
	echo bbp_get_topic_hidden_reply_count( $topic_id );
}
	/**
	 * Return total hidden reply count of a topic (hidden includes trashed
	 * and spammed replies)
	 *
	 * @since bbPress (r2740)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post_meta() To get the hidden reply count
	 * @uses bbp_update_topic_hidden_reply_count() To update the topic
	 *                                              hidden reply count
	 * @uses apply_filters() Calls 'bbp_get_topic_hidden_reply_count' with
	 *                        the hidden reply count and topic id
	 * @return int Topic hidden reply count
	 */
	function bbp_get_topic_hidden_reply_count( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$replies  = get_post_meta( $topic_id, '_bbp_topic_hidden_reply_count', true );

		if ( '' === $replies )
			$replies = bbp_update_topic_hidden_reply_count( $topic_id );

		return apply_filters( 'bbp_get_topic_hidden_reply_count', (int) $replies, $topic_id );
	}

/**
 * Output total voice count of a topic
 *
 * @since bbPress (r2567)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_voice_count() To get the topic voice count
 */
function bbp_topic_voice_count( $topic_id = 0 ) {
	echo bbp_get_topic_voice_count( $topic_id );
}
	/**
	 * Return total voice count of a topic
	 *
	 * @since bbPress (r2567)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post_meta() To get the voice count meta
	 * @uses bbp_update_topic_voice_count() To update the topic voice count
	 * @uses apply_filters() Calls 'bbp_get_topic_voice_count' with the
	 *                        voice count and topic id
	 * @return int Voice count of the topic
	 */
	function bbp_get_topic_voice_count( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Look for existing count, and populate if does not exist
		if ( !$voices   = get_post_meta( $topic_id, '_bbp_topic_voice_count', true ) )
			$voices = bbp_update_topic_voice_count( $topic_id );

		return apply_filters( 'bbp_get_topic_voice_count', (int) $voices, $topic_id );
	}

/**
 * Output a the tags of a topic
 *
 * @param int $topic_id Optional. Topic id
 * @param mixed $args See {@link bbp_get_topic_tag_list()}
 * @uses bbp_get_topic_tag_list() To get the topic tag list
 */
function bbp_topic_tag_list( $topic_id = 0, $args = '' ) {
	echo bbp_get_topic_tag_list( $topic_id, $args );
}
	/**
	 * Return the tags of a topic
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param array $args This function supports these arguments:
	 *  - before: Before the tag list
	 *  - sep: Tag separator
	 *  - after: After the tag list
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_the_term_list() To get the tags list
	 * @return string Tag list of the topic
	 */
	function bbp_get_topic_tag_list( $topic_id = 0, $args = '' ) {
		global $bbp;

		$defaults = array(
			'before' => '<p>' . __( 'Tagged:', 'bbpress' ) . '&nbsp;',
			'sep'    => ', ',
			'after'  => '</p>'
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic_id = bbp_get_topic_id( $topic_id );

		return get_the_term_list( $topic_id, $bbp->topic_tag_id, $before, $sep, $after );
	}

/**
 * Output the row class of a topic
 *
 * @since bbPress (r2667)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_class() To get the topic class
 */
function bbp_topic_class( $topic_id = 0 ) {
	echo bbp_get_topic_class( $topic_id );
}
	/**
	 * Return the row class of a topic
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_is_topic_sticky() To check if the topic is a sticky
	 * @uses bbp_is_topic_super_sticky() To check if the topic is a super
	 *                                    sticky
	 * @uses post_class() To get the topic classes
	 * @uses apply_filters() Calls 'bbp_get_topic_class' with the classes
	 *                        and topic id
	 * @return string Row class of a topic
	 */
	function bbp_get_topic_class( $topic_id = 0 ) {
		global $bbp;

		$classes   = array();
		$classes[] = $bbp->topic_query->current_post % 2     ? 'even'         : 'odd';
		$classes[] = bbp_is_topic_sticky( $topic_id, false ) ? 'sticky'       : '';
		$classes[] = bbp_is_topic_super_sticky( $topic_id  ) ? 'super-sticky' : '';
		$classes   = array_filter( $classes );
		$post      = post_class( $classes, $topic_id );

		return apply_filters( 'bbp_get_topic_class', $post, $topic_id );
	}

/** Topic Admin Links *********************************************************/

/**
 * Output admin links for topic
 *
 * @param mixed $args See {@link bbp_get_topic_admin_links()}
 * @uses bbp_get_topic_admin_links() To get the topic admin links
 */
function bbp_topic_admin_links( $args = '' ) {
	echo bbp_get_topic_admin_links( $args );
}
	/**
	 * Return admin links for topic.
	 *
	 * Move topic functionality is handled by the edit topic page.
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - id: Optional. Topic id
	 *  - before: Before the links
	 *  - after: After the links
	 *  - sep: Links separator
	 *  - links: Topic admin links array
	 * @uses bbp_is_topic() To check if it is a topic page
	 * @uses current_user_can() To check if the current user can edit/delete
	 *                           the topic
	 * @uses bbp_get_topic_edit_link() To get the topic edit link
	 * @uses bbp_get_topic_trash_link() To get the topic trash link
	 * @uses bbp_get_topic_close_link() To get the topic close link
	 * @uses bbp_get_topic_spam_link() To get the topic spam link
	 * @uses bbp_get_topic_stick_link() To get the topic stick link
	 * @uses bbp_get_topic_merge_link() To get the topic merge link
	 * @uses bbp_get_topic_status() To get the topic status
	 * @uses apply_filters() Calls 'bbp_get_topic_admin_links' with the
	 *                        topic admin links and args
	 * @return string Topic admin links
	 */
	function bbp_get_topic_admin_links( $args = '' ) {
		global $bbp;

		if ( !bbp_is_topic() )
			return '&nbsp;';

		$defaults = array (
			'id'     => bbp_get_topic_id(),
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array()
		);

		$r = wp_parse_args( $args, $defaults );

		if ( !current_user_can( 'edit_topic', $r['id'] ) )
			return '&nbsp;';

		if ( empty( $r['links'] ) ) {
			$r['links'] = array(
				'edit'  => bbp_get_topic_edit_link ( $r ),
				'trash' => bbp_get_topic_trash_link( $r ),
				'close' => bbp_get_topic_close_link( $r ),
				'stick' => bbp_get_topic_stick_link( $r ),
				'merge' => bbp_get_topic_merge_link( $r ),
				'spam'  => bbp_get_topic_spam_link ( $r ),
			);
		}

		// Check caps for trashing the topic
		if ( !current_user_can( 'delete_topic', $r['id'] ) && !empty( $r['links']['trash'] ) )
			unset( $r['links']['trash'] );

		// See if links need to be unset
		$topic_status = bbp_get_topic_status( $r['id'] );
		if ( in_array( $topic_status, array( $bbp->spam_status_id, $bbp->trash_status_id ) ) ) {

			// Close link shouldn't be visible on trashed/spammed topics
			unset( $r['links']['close'] );

			// Spam link shouldn't be visible on trashed topics
			if ( $topic_status == $bbp->trash_status_id )
				unset( $r['links']['spam'] );

			// Trash link shouldn't be visible on spam topics
			elseif ( $topic_status == $bbp->spam_status_id )
				unset( $r['links']['trash'] );
		}

		// Process the admin links
		$links = implode( $r['sep'], array_filter( $r['links'] ) );

		return apply_filters( 'bbp_get_topic_admin_links', $r['before'] . $links . $r['after'], $args );
	}

/**
 * Output the edit link of the topic
 *
 * @since bbPress (r2727)
 *
 * @param mixed $args See {@link bbp_get_topic_edit_link()}
 * @uses bbp_get_topic_edit_link() To get the topic edit link
 */
function bbp_topic_edit_link( $args = '' ) {
	echo bbp_get_topic_edit_link( $args );
}

	/**
	 * Return the edit link of the topic
	 *
	 * @since bbPress (r2727)
	 *
	 * @param mixed $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - edit_text: Edit text
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic
	 * @uses bbp_get_topic_edit_url() To get the topic edit url
	 * @uses apply_filters() Calls 'bbp_get_topic_edit_link' with the link
	 *                        and args
	 * @return string Topic edit link
	 */
	function bbp_get_topic_edit_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'edit_text'    => __( 'Edit', 'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic = get_post( bbp_get_topic_id( (int) $id ) );

		if ( empty( $topic ) || !current_user_can( 'edit_topic', $topic->ID ) )
			return;

		if ( !$uri = bbp_get_topic_edit_url( $id ) )
			return;

		return apply_filters( 'bbp_get_topic_edit_link', $link_before . '<a href="' . $uri . '">' . $edit_text . '</a>' . $link_after, $args );
	}

/**
 * Output URL to the topic edit page
 *
 * @since bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_edit_url() To get the topic edit url
 */
function bbp_topic_edit_url( $topic_id = 0 ) {
	echo bbp_get_topic_edit_url( $topic_id );
}
	/**
	 * Return URL to the topic edit page
	 *
	 * @since bbPress (r2753)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses add_query_arg() To add custom args to the url
	 * @uses home_url() To get the home url
	 * @uses apply_filters() Calls 'bbp_get_topic_edit_url' with the edit
	 *                        url and topic id
	 * @return string Topic edit url
	 */
	function bbp_get_topic_edit_url( $topic_id = 0 ) {
		global $wp_rewrite, $bbp;

		if ( !$topic = get_post( bbp_get_topic_id( $topic_id ) ) )
			return;

		if ( empty( $wp_rewrite->permalink_structure ) ) {
			$url = add_query_arg( array( $bbp->topic_id => $topic->post_name, 'edit' => '1' ), home_url( '/' ) );
		} else {
			$url = $wp_rewrite->front . $bbp->topic_slug . '/' . $topic->post_name . '/edit';
			$url = home_url( user_trailingslashit( $url ) );
		}

		return apply_filters( 'bbp_get_topic_edit_url', $url, $topic_id );
	}

/**
 * Output the trash link of the topic
 *
 * @since bbPress (r2727)
 *
 * @param mixed $args See {@link bbp_get_topic_trash_link()}
 * @uses bbp_get_topic_trash_link() To get the topic trash link
 */
function bbp_topic_trash_link( $args = '' ) {
	echo bbp_get_topic_trash_link( $args );
}

	/**
	 * Return the trash link of the topic
	 *
	 * @since bbPress (r2727)
	 *
	 * @param mixed $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - sep: Links separator
	 *  - trash_text: Trash text
	 *  - restore_text: Restore text
	 *  - delete_text: Delete text
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses current_user_can() To check if the current user can delete the
	 *                           topic
	 * @uses bbp_get_topic_status() To get the topic status
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses apply_filters() Calls 'bbp_get_topic_trash_link' with the link
	 *                        and args
	 * @return string Topic trash link
	 */
	function bbp_get_topic_trash_link( $args = '' ) {
		global $bbp;

		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'trash_text'   => __( 'Trash',   'bbpress' ),
			'restore_text' => __( 'Restore', 'bbpress' ),
			'delete_text'  => __( 'Delete',  'bbpress' )
		);
		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$actions = array();
		$topic   = get_post( bbp_get_topic_id( (int) $id ) );

		if ( empty( $topic ) || !current_user_can( 'delete_topic', $topic->ID ) )
			return;

		$topic_status = bbp_get_topic_status( $topic->ID );

		if ( $bbp->trash_status_id == $topic_status )
			$actions['untrash'] = '<a title="' . esc_attr( __( 'Restore this item from the Trash', 'bbpress' ) ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_topic_trash', 'sub_action' => 'untrash', 'topic_id' => $topic->ID ) ), 'untrash-' . $topic->post_type . '_' . $topic->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( "Are you sure you want to restore that?", "bbpress" ) ) . '\');">' . esc_html( $restore_text ) . '</a>';
		elseif ( EMPTY_TRASH_DAYS )
			$actions['trash']   = '<a title="' . esc_attr( __( 'Move this item to the Trash' ) ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_topic_trash', 'sub_action' => 'trash', 'topic_id' => $topic->ID ) ), 'trash-' . $topic->post_type . '_' . $topic->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( "Are you sure you want to trash that?", "bbpress" ) ) . '\' );">' . esc_html( $trash_text ) . '</a>';

		if ( $bbp->trash_status_id == $topic->post_status || !EMPTY_TRASH_DAYS )
			$actions['delete']  = '<a title="' . esc_attr( __( 'Delete this item permanently' ) ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_topic_trash', 'sub_action' => 'delete', 'topic_id' => $topic->ID ) ), 'delete-' . $topic->post_type . '_' . $topic->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( "Are you sure you want to delete that permanentaly?", "bbpress" ) ) . '\' );">' . esc_html( $delete_text ) . '</a>';

		// Process the admin links
		$actions = implode( $sep, $actions );

		return apply_filters( 'bbp_get_topic_trash_link', $link_before . $actions . $link_after, $args );
	}

/**
 * Output the close link of the topic
 *
 * @since bbPress (r2727)
 *
 * @param mixed $args See {@link bbp_get_topic_close_link()}
 * @uses bbp_get_topic_close_link() To get the topic close link
 */
function bbp_topic_close_link( $args = '' ) {
	echo bbp_get_topic_close_link( $args );
}

	/**
	 * Return the close link of the topic
	 *
	 * @since bbPress (r2727)
	 *
	 * @param mixed $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - close_text: Close text
	 *  - open_text: Open text
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic
	 * @uses bbp_is_topic_open() To check if the topic is open
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses apply_filters() Calls 'bbp_get_topic_close_link' with the link
	 *                        and args
	 * @return string Topic close link
	 */
	function bbp_get_topic_close_link( $args = '' ) {
		$defaults = array (
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'sep'         => ' | ',
			'close_text'  => __( 'Close', 'bbpress' ),
			'open_text'   => __( 'Open',  'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic = get_post( bbp_get_topic_id( (int) $id ) );

		if ( empty( $topic ) || !current_user_can( 'edit_topic', $topic->ID ) )
			return;

		$display = bbp_is_topic_open( $topic->ID ) ? $close_text : $open_text;

		$uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_close', 'topic_id' => $topic->ID ) );
		$uri = esc_url( wp_nonce_url( $uri, 'close-topic_' . $topic->ID ) );

		return apply_filters( 'bbp_get_topic_close_link', $link_before . '<a href="' . $uri . '">' . $display . '</a>' . $link_after, $args );
	}

/**
 * Output the stick link of the topic
 *
 * @since bbPress (r2754)
 *
 * @param mixed $args See {@link bbp_get_topic_stick_link()}
 * @uses bbp_get_topic_stick_link() To get the topic stick link
 */
function bbp_topic_stick_link( $args = '' ) {
	echo bbp_get_topic_stick_link( $args );
}

	/**
	 * Return the stick link of the topic
	 *
	 * @since bbPress (r2754)
	 *
	 * @param mixed $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - stick_text: Stick text
	 *  - unstick_text: Unstick text
	 *  - super_text: Stick to front text
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic
	 * @uses bbp_is_topic_sticky() To check if the topic is a sticky
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses apply_filters() Calls 'bbp_get_topic_stick_link' with the link
	 *                        and args
	 * @return string Topic stick link
	 */
	function bbp_get_topic_stick_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'stick_text'   => __( 'Stick',    'bbpress' ),
			'unstick_text' => __( 'Unstick',  'bbpress' ),
			'super_text'   => __( 'to front', 'bbpress' ),
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic = get_post( bbp_get_topic_id( (int) $id ) );

		if ( empty( $topic ) || !current_user_can( 'moderate', $topic->ID ) )
			return;

		$is_sticky = bbp_is_topic_sticky( $topic->ID );

		$stick_uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_stick', 'topic_id' => $topic->ID ) );
		$stick_uri = esc_url( wp_nonce_url( $stick_uri, 'stick-topic_' . $topic->ID ) );

		$stick_display = true == $is_sticky ? $unstick_text : $stick_text;
		$stick_display = '<a href="' . $stick_uri . '">' . $stick_display . '</a>';

		if ( empty( $is_sticky ) ) {
			$super_uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_stick', 'topic_id' => $topic->ID, 'super' => 1 ) );
			$super_uri = esc_url( wp_nonce_url( $super_uri, 'stick-topic_' . $topic->ID ) );

			$super_display = ' (<a href="' . $super_uri . '">' . $super_text . '</a>)';
		} else {
			$super_display = '';
		}

		return apply_filters( 'bbp_get_topic_stick_link', $link_before . $stick_display . $super_display . $link_after, $args );
	}

/**
 * Output the merge link of the topic
 *
 * @since bbPress (r2756)
 *
 * @param mixed $args
 * @uses bbp_get_topic_merge_link() To get the topic merge link
 */
function bbp_topic_merge_link( $args = '' ) {
	echo bbp_get_topic_merge_link( $args );
}

	/**
	 * Return the merge link of the topic
	 *
	 * @since bbPress (r2756)
	 *
	 * @param mixed $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - merge_text: Merge text
	 * @uses bbp_get_topic_edit_url() To get the topic edit url
	 * @uses add_query_arg() To add custom args to the url
	 * @uses esc_url() To escape the url
	 * @uses apply_filters() Calls 'bbp_get_topic_merge_link' with the link
	 *                        and args
	 * @return string Topic merge link
	 */
	function bbp_get_topic_merge_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'merge_text'    => __( 'Merge', 'bbpress' ),
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic = get_post( bbp_get_topic_id( (int) $id ) );

		if ( empty( $topic ) || !current_user_can( 'moderate', $topic->ID ) )
			return;

		$uri = esc_url( add_query_arg( array( 'action' => 'merge' ), bbp_get_topic_edit_url( $topic->id ) ) );

		return apply_filters( 'bbp_get_topic_merge_link', $link_before . '<a href="' . $uri . '">' . $merge_text . '</a>' . $link_after, $args );
	}

/**
 * Output the spam link of the topic
 *
 * @since bbPress (r2727)
 *
 * @param mixed $args See {@link bbp_get_topic_spam_link()}
 * @uses bbp_get_topic_spam_link() Topic spam link
 */
function bbp_topic_spam_link( $args = '' ) {
	echo bbp_get_topic_spam_link( $args );
}

	/**
	 * Return the spam link of the topic
	 *
	 * @since bbPress (r2727)
	 *
	 * @param mixed $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - spam_text: Spam text
	 *  - unspam_text: Unspam text
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses get_post() To get the topic
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic
	 * @uses bbp_is_topic_spam() To check if the topic is marked as spam
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses apply_filters() Calls 'bbp_get_topic_spam_link' with the link
	 *                        and args
	 * @return string Topic spam link
	 */
	function bbp_get_topic_spam_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'spam_text'    => __( 'Spam',   'bbpress' ),
			'unspam_text'  => __( 'Unspam', 'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic = get_post( bbp_get_topic_id( (int) $id ) );

		if ( empty( $topic ) || !current_user_can( 'moderate', $topic->ID ) )
			return;

		$display = bbp_is_topic_spam( $topic->ID ) ? $unspam_text : $spam_text;

		$uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_spam', 'topic_id' => $topic->ID ) );
		$uri = esc_url( wp_nonce_url( $uri, 'spam-topic_' . $topic->ID ) );

		return apply_filters( 'bbp_get_topic_spam_link', $link_before . '<a href="' . $uri . '">' . $display . '</a>' . $link_after, $args );
	}

/** Topic Updaters ************************************************************/

/**
 * Adjust the total reply count of a topic
 *
 * @since bbPress (r2467)
 *
 * @param int $topic_id Optional. Topic id to update
 * @uses bbp_get_topic_id() To get the topic id
 * @uses get_post_field() To get the post type of the supplied id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the topic reply count meta
 * @uses apply_filters() Calls 'bbp_update_topic_reply_count' with the reply
 *                        count and topic id
 * @return int Topic reply count
 */
function bbp_update_topic_reply_count( $topic_id = 0 ) {
	global $wpdb, $bbp;

	$topic_id = bbp_get_topic_id( $topic_id );

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );

	// Get replies of topic
	$replies = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "';", $topic_id ) ) );

	// Update the count
	update_post_meta( $topic_id, '_bbp_topic_reply_count', (int) $replies );

	return apply_filters( 'bbp_update_topic_reply_count', (int) $replies, $topic_id );
}

/**
 * Adjust the total hidden reply count of a topic (hidden includes trashed and spammed replies)
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id to update
 * @uses bbp_get_topic_id() To get the topic id
 * @uses get_post_field() To get the post type of the supplied id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the topic hidden reply count meta
 * @uses apply_filters() Calls 'bbp_update_topic_hidden_reply_count' with the
 *                        hidden reply count and topic id
 * @return int Topic hidden reply count
 */
function bbp_update_topic_hidden_reply_count( $topic_id = 0 ) {
	global $wpdb, $bbp;

	$topic_id = bbp_get_topic_id( $topic_id );

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );

	// Get replies of topic
	$replies = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( '" . join( '\',\'', array( $bbp->trash_status_id, $bbp->spam_status_id ) ) . "') AND post_type = '" . $bbp->reply_id . "';", $topic_id ) ) );

	// Update the count
	update_post_meta( $topic_id, '_bbp_topic_hidden_reply_count', (int) $replies );

	return apply_filters( 'bbp_update_topic_hidden_reply_count', (int) $replies, $topic_id );
}

/**
 * Update the topics last active date/time (aka freshness)
 *
 * @since bbPress (r2680)
 *
 * @param int $topic_id Optional. Topic id
 * @param string $new_time Optional. New time in mysql format
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses current_time() To get the current time
 * @uses update_post_meta() To update the topic last active meta
 * @return bool True on success, false on failure
 */
function bbp_update_topic_last_active( $topic_id = 0, $new_time = '' ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	// Check time and use current if empty
	if ( empty( $new_time ) )
		$new_time = current_time( 'mysql' );

	// Update the last reply ID
	if ( !empty( $topic_id ) )
		return update_post_meta( $topic_id, '_bbp_topic_last_active', $new_time );

	return false;
}

/**
 * Update the topic with the most recent reply ID
 *
 * @since bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses update_post_meta() To update the topic last reply id meta
 * @return bool True on success, false on failure
 */
function bbp_update_topic_last_reply_id( $topic_id = 0, $reply_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$reply_id = bbp_get_reply_id( $reply_id );

	// Update the last reply ID
	if ( !empty( $topic_id ) )
		return update_post_meta( $topic_id, '_bbp_topic_last_reply_id', $reply_id );

	return false;
}

/**
 * Adjust the total voice count of a topic
 *
 * @since bbPress (r2567)
 *
 * @param int $topic_id Optional. Topic id to update
 * @uses bbp_get_topic_id() To get the topic id
 * @uses get_post_field() To get the post type of the supplied id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the topic voice count meta
 * @uses apply_filters() Calls 'bbp_update_topic_voice_count' with the voice
 *                        count and topic id
 * @return bool False on failure, voice count on success
 */
function bbp_update_topic_voice_count( $topic_id = 0 ) {
	global $wpdb, $bbp;

	$topic_id = bbp_get_topic_id( $topic_id );

	// If it is not a topic or reply, then we don't need it
	if ( !in_array( get_post_field( 'post_type', $topic_id ), array( $bbp->topic_id, $bbp->reply_id ) ) )
		return false;

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );

	// There should always be at least 1 voice
	if ( !$voices = count( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "' ) OR ( ID = %d AND post_type = '" . $bbp->topic_id . "' );", $topic_id, $topic_id ) ) ) )
		$voices = 1;

	// Update the count
	update_post_meta( $topic_id, '_bbp_topic_voice_count', (int) $voices );

	return apply_filters( 'bbp_update_topic_voice_count', (int) $voices, $topic_id );
}

/** Topic Pagination **********************************************************/

/**
 * Output the pagination count
 *
 * @since bbPress (r2519)
 *
 * @uses bbp_get_forum_pagination_count() To get the forum pagination count
 */
function bbp_forum_pagination_count() {
	echo bbp_get_forum_pagination_count();
}
	/**
	 * Return the pagination count
	 *
	 * @since bbPress (r2519)
	 *
	 * @uses bbp_number_format() To format the number value
	 * @uses apply_filters() Calls 'bbp_get_forum_pagination_count' with the
	 *                        pagination count
	 * @return string Forum Pagintion count
	 */
	function bbp_get_forum_pagination_count() {
		global $bbp;

		if ( !isset( $bbp->topic_query ) )
			return false;

		// Set pagination values
		$start_num = intval( ( $bbp->topic_query->paged - 1 ) * $bbp->topic_query->posts_per_page ) + 1;
		$from_num  = bbp_number_format( $start_num );
		$to_num    = bbp_number_format( ( $start_num + ( $bbp->topic_query->posts_per_page - 1 ) > $bbp->topic_query->found_posts ) ? $bbp->topic_query->found_posts : $start_num + ( $bbp->topic_query->posts_per_page - 1 ) );
		$total     = bbp_number_format( !empty( $bbp->topic_query->found_posts ) ? $bbp->topic_query->found_posts : $bbp->topic_query->post_count );

		// Set return string
		if ( $total > 1 && (int) $from_num == (int) $to_num )
			$retstr = sprintf( __( 'Viewing topic %1$s (of %2$s total)', 'bbpress' ), $from_num, $total );
		elseif ( $total > 1 && empty( $to_num ) )
			$retstr = sprintf( __( 'Viewing %1$s topics', 'bbpress' ), $total );
		elseif ( $total > 1 && (int) $from_num != (int) $to_num )
			$retstr = sprintf( __( 'Viewing %1$s topics - %2$s through %3$s (of %4$s total)', 'bbpress' ), $bbp->topic_query->post_count, $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s topic', 'bbpress' ), $total );

		// Filter and return
		return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
	}

/**
 * Output pagination links
 *
 * @since bbPress (r2519)
 *
 * @uses bbp_get_forum_pagination_links() To get the pagination links
 */
function bbp_forum_pagination_links() {
	echo bbp_get_forum_pagination_links();
}
	/**
	 * Return pagination links
	 *
	 * @since bbPress (r2519)
	 *
	 * @uses bbPress::topic_query::pagination_links To get the links
	 * @return string Pagination links
	 */
	function bbp_get_forum_pagination_links() {
		global $bbp;

		if ( !isset( $bbp->topic_query ) )
			return false;

		return apply_filters( 'bbp_get_forum_pagination_links', $bbp->topic_query->pagination_links );
	}

/** END - Topic Loop Functions ************************************************/

/** Topic Actions *************************************************************/

/**
 * Closes a topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_close_topic' with the topic id
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_opened_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_close_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] == $bbp->closed_status_id )
		return false;

	do_action( 'bbp_close_topic', $topic_id );

	add_post_meta( $topic_id, '_bbp_topic_status', $topic['post_status'] );

	$topic['post_status'] = $bbp->closed_status_id;

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_closed_topic', $topic_id );

	return $topic_id;
}

/**
 * Opens a topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_open_topic' with the topic id
 * @uses get_post_meta() To get the previous status
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_opened_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_open_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] != $bbp->closed_status_id )
		return false;

	do_action( 'bbp_open_topic', $topic_id );

	$topic_status         = get_post_meta( $topic_id, '_bbp_topic_status', true );
	$topic['post_status'] = $topic_status;

	delete_post_meta( $topic_id, '_bbp_topic_status' );

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_opened_topic', $topic_id );

	return $topic_id;
}

/**
 * Marks a topic as spam
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_spam_topic' with the topic id
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_spammed_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_spam_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] == $bbp->spam_status_id )
		return false;

	do_action( 'bbp_spam_topic', $topic_id );

	add_post_meta( $topic_id, '_bbp_spam_meta_status', $topic['post_status'] );

	$topic['post_status'] = $bbp->spam_status_id;

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_spammed_topic', $topic_id );

	return $topic_id;
}

/**
 * Unspams a topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_unspam_topic' with the topic id
 * @uses get_post_meta() To get the previous status
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_unspammed_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_unspam_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] != $bbp->spam_status_id )
		return false;

	do_action( 'bbp_unspam_topic', $topic_id );

	$topic_status         = get_post_meta( $topic_id, '_bbp_spam_meta_status', true );
	$topic['post_status'] = $topic_status;

	delete_post_meta( $topic_id, '_bbp_spam_meta_status' );

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_unspammed_topic', $topic_id );

	return $topic_id;
}

/**
 * Sticks a topic to a forum or front
 *
 * @since bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $super Should we make the topic a super sticky?
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_get_stickies() To get the stickies
 * @uses do_action() 'bbp_stick_topic' with topic id and bool super
 * @uses update_option() To update the super stickies option
 * @uses update_post_meta() To update the forum stickies meta
 * @uses do_action() Calls 'bbp_sticked_topic' with the topic id, bool super
 *                    and success
 * @return bool True on success, false on failure
 */
function bbp_stick_topic( $topic_id = 0, $super = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = empty( $super ) ? bbp_get_topic_forum_id( $topic_id ) : 0;
	$stickies = bbp_get_stickies( $forum_id );

	do_action( 'bbp_stick_topic', $topic_id, $super );

	if ( !is_array( $stickies ) )
		$stickies   = array( $topic_id );
	else
		$stickies[] = $topic_id;

	$stickies = array_unique( array_filter( $stickies ) );

	$success = !empty( $super ) ? update_option( '_bbp_super_sticky_topics', $stickies ) : update_post_meta( $forum_id, '_bbp_sticky_topics', $stickies );

	do_action( 'bbp_sticked_topic', $topic_id, $super, $success );

	return $success;
}

/**
 * Unsticks a topic both from front and it's forum
 *
 * @since bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic_super_sticky() To check if the topic is a super sticky
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_get_stickies() To get the forum stickies
 * @uses do_action() Calls 'bbp_unstick_topic' with the topic id
 * @uses delete_option() To delete the super stickies option
 * @uses update_option() To update the super stickies option
 * @uses delete_post_meta() To delete the forum stickies meta
 * @uses update_post_meta() To update the forum stickies meta
 * @uses do_action() Calls 'bbp_unsticked_topic' with the topic id and success
 * @return bool Always true.
 */
function bbp_unstick_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$super    = bbp_is_topic_super_sticky( $topic_id );
	$forum_id = empty( $super ) ? bbp_get_topic_forum_id( $topic_id ) : 0;
	$stickies = bbp_get_stickies( $forum_id );
	$offset   = array_search( $topic_id, $stickies );

	do_action( 'bbp_unstick_topic', $topic_id );

	if ( empty( $stickies ) ) {
		$success = true;
	} elseif ( !in_array( $topic_id, $stickies ) ) {
		$success = true;
	} elseif ( false === $offset ) {
		$success = true;
	} else {
		array_splice( $stickies, $offset, 1 );
		if ( empty( $stickies ) )
			$success = !empty( $super ) ? delete_option( '_bbp_super_sticky_topics'            ) : delete_post_meta( $forum_id, '_bbp_sticky_topics'            );
		else
			$success = !empty( $super ) ? update_option( '_bbp_super_sticky_topics', $stickies ) : update_post_meta( $forum_id, '_bbp_sticky_topics', $stickies );
	}

	do_action( 'bbp_unsticked_topic', $topic_id, $success );

	return true;
}
/**
 * Displays topic notices
 *
 * @since bbPress (r2744)
 *
 * @uses bbp_is_topic() To check if it's a topic page
 * @uses bbp_get_topic_status() To get the topic status
 */
function bbp_topic_notices() {
	global $bbp;

	if ( !bbp_is_topic() )
		return;

	$topic_status = bbp_get_topic_status();

	if ( !in_array( $topic_status, array( $bbp->spam_status_id, $bbp->trash_status_id ) ) )
		return;

	$notice_text = $bbp->spam_status_id == $topic_status ? __( 'This topic is marked as spam.', 'bbpress' ) : __( 'This topic is currently trashed.', 'bbpress' ); ?>

	<div class="bbp-template-notice error">
		<p><?php echo $notice_text; ?></p>
	</div>

	<?php
}

?>
