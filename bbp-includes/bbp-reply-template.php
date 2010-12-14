<?php

/** START - Reply Loop Functions **********************************************/

/**
 * bbp_has_replies ( $args )
 *
 * The main reply loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @global WP_Query $bbp->reply_query
 * @param array $args Possible arguments to change returned replies
 * @return object Multidimensional array of reply information
 */
function bbp_has_replies ( $args = '' ) {
	global $wp_rewrite, $bbp;

	$default = array(
		// Narrow query down to bbPress topics
		'post_type'      => $bbp->reply_id,

		// Forum ID
		'post_parent'    => bbp_get_topic_id(),

		// 'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'        => 'date',

		// 'ASC', 'DESC'
		'order'          => 'ASC',

		// @todo replace 15 with setting
		'posts_per_page' => 15,

		// Page Number
		'paged'          => bbp_get_paged(),

		// Reply Search
		's'              => !empty( $_REQUEST['rs'] ) ? $_REQUEST['rs'] : '',
	);

	// Set up topic variables
	$bbp_r = wp_parse_args( $args, $default );
	$r     = extract( $bbp_r );

	// Call the query
	$bbp->reply_query = new WP_Query( $bbp_r );

	// Add pagination values to query object
	$bbp->reply_query->posts_per_page = $posts_per_page;
	$bbp->reply_query->paged          = $paged;

	// Only add pagination if query returned results
	if ( (int) $bbp->reply_query->found_posts && (int) $bbp->reply_query->posts_per_page ) {

		// If pretty permalinks are enabled, make our pagination pretty
		if ( $wp_rewrite->using_permalinks() )
			$base = user_trailingslashit( trailingslashit( get_permalink( $post_parent ) ) . 'page/%#%/' );
		else
			$base = add_query_arg( 'page', '%#%' );

		// Pagination settings with filter
		$bbp_replies_pagination = apply_filters( 'bbp_replies_pagination', array(
			'base'      => $base,
			'format'    => '',
			'total'     => ceil( (int) $bbp->reply_query->found_posts / (int) $posts_per_page ),
			'current'   => (int) $bbp->reply_query->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$bbp->reply_query->pagination_links = paginate_links( $bbp_replies_pagination );

		// Remove first page from pagination
		$bbp->reply_query->pagination_links = str_replace( 'page/1/\'', '\'', $bbp->reply_query->pagination_links );
	}

	// Return object
	return apply_filters( 'bbp_has_replies', $bbp->reply_query->have_posts(), $bbp->reply_query );
}

/**
 * bbp_replies ()
 *
 * Whether there are more replies available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @global WP_Query $bbp->reply_query
 * @return object Replies information
 */
function bbp_replies () {
	global $bbp;
	return $bbp->reply_query->have_posts();
}

/**
 * bbp_the_reply ()
 *
 * Loads up the current reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @global WP_Query $bbp->reply_query
 * @return object Reply information
 */
function bbp_the_reply () {
	global $bbp;
	return $bbp->reply_query->the_post();
}

/**
 * bbp_reply_id ()
 *
 * Output id from bbp_get_reply_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
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
	 * @since bbPress (r2553)
	 *
	 * @global object $bbp->reply_query
	 * @return int Reply id
	 */
	function bbp_get_reply_id ( $reply_id = 0 ) {
		global $bbp, $wp_query, $bbp;

		// Easy empty checking
		if ( !empty( $reply_id ) && is_numeric( $reply_id ) )
			$bbp_reply_id = $reply_id;

		// Currently viewing a reply
		elseif ( bbp_is_reply() && isset( $wp_query->post->ID ) )
			$bbp_reply_id = $wp_query->post->ID;

		// Currently inside a replies loop
		elseif ( isset( $bbp->reply_query->post->ID ) )
			$bbp_reply_id = $bbp->reply_query->post->ID;

		// Fallback
		else
			$bbp_reply_id = 0;

		$bbp->current_reply_id = $bbp_reply_id;

		return apply_filters( 'bbp_get_reply_id', (int)$bbp_reply_id );
	}

/**
 * bbp_reply_permalink ()
 *
 * Output the link to the reply in the reply loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
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
	 * @since bbPress (r2553)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @param int $reply_id optional
	 *
	 * @return string Permanent link to reply
	 */
	function bbp_get_reply_permalink ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_permalink', get_permalink( $reply_id ), $reply_id );
	}
/**
 * bbp_reply_url ()
 *
 * Output the paginated url to the reply in the reply loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2679)
 *
 * @uses bbp_get_reply_url()
 * @param int $reply_id optional
 */
function bbp_reply_url ( $reply_id = 0 ) {
	echo bbp_get_reply_url( $reply_id );
}
	/**
	 * bbp_get_reply_url()
	 *
	 * Return the paginated url to the reply in the reply loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2679)
	 *
	 * @uses apply_filters
	 * @uses bbp_get_reply_id
	 * @uses bbp_get_reply_topic_id
	 * @uses bbp_get_topic_permalink
	 * @param int $reply_id optional
	 *
	 * @return string Link to reply relative to paginated topic
	 */
	function bbp_get_reply_url ( $reply_id = 0 ) {
		global $bbp;

		// Set needed variables
		$reply_id      = bbp_get_reply_id( $reply_id );
		$topic_id      = bbp_get_reply_topic_id( $reply_id );
		$topic_url     = bbp_get_topic_permalink( $topic_id );
		$topic_replies = bbp_get_topic_reply_count( $topic_id );
		$reply_page    = ceil( $topic_replies / get_option( '_bbp_replies_per_page', 15 ) );

		// Don't include pagination if on first page
		if ( 1 >= $reply_page )
			$url = untrailingslashit( $topic_url ) . "/#reply-{$reply_id}";
		else
			$url = trailingslashit( $topic_url ) . "page/{$reply_page}/#reply-{$reply_id}";

		return apply_filters( 'bbp_get_reply_url', $url, $reply_id );
	}

/**
 * bbp_reply_title ()
 *
 * Output the title of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
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
	 * @since bbPress (r2553)
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
 * @since bbPress (r2553)
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
	 * @since bbPress (r2553)
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
 * bbp_reply_status ()
 *
 * Output the status of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_status()
 */
function bbp_reply_status ( $reply_id = 0 ) {
	echo bbp_get_reply_status( $reply_id );
}
	/**
	 * bbp_get_reply_status ()
	 *
	 * Return the status of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @todo custom topic ststuses
	 *
	 * @uses apply_filters
	 * @uses get_post_status()
	 * @param int $reply_id optional
	 *
	 * @return string Status of reply
	 */
	function bbp_get_reply_status ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_status', get_post_status( $reply_id ) );
	}

/**
 * bbp_reply_author ()
 *
 * Output the author of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author ( $reply_id = 0 ) {
	echo bbp_get_reply_author( $reply_id );
}
	/**
	 * bbp_get_reply_author ()
	 *
	 * Return the author of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		if ( get_post_field( 'post_author', $reply_id ) )
			$author = get_the_author();
		else
			$author = get_post_meta( $reply_id, '_bbp_anonymous_name', true );

		return apply_filters( 'bbp_get_reply_author', $author );
	}

/**
 * bbp_reply_author_id ()
 *
 * Output the author ID of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author_id ( $reply_id = 0 ) {
	echo bbp_get_reply_author_id( $reply_id );
}
	/**
	 * bbp_get_reply_author_id ()
	 *
	 * Return the author ID of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author_id ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_author_id', get_post_field( 'post_author', $reply_id ) );
	}

/**
 * bbp_reply_author_display_name ()
 *
 * Output the author display_name of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author_display_name ( $reply_id = 0 ) {
	echo bbp_get_reply_author_display_name( $reply_id );
}
	/**
	 * bbp_get_reply_author_display_name ()
	 *
	 * Return the author display_name of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author_display_name ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Check for anonymous user
		if ( $author_id = get_post_field( 'post_author', $reply_id ) )
			$author_name = get_the_author_meta( 'display_name', $author_id );
		else
			$author_name = get_post_meta( $reply_id, '_bbp_anonymous_name', true );

		return apply_filters( 'bbp_get_reply_author_display_name', esc_attr( $author_name ) );
	}

/**
 * bbp_reply_author_avatar ()
 *
 * Output the author avatar of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author_avatar()
 */
function bbp_reply_author_avatar ( $reply_id = 0, $size = 40 ) {
	echo bbp_get_reply_author_avatar( $reply_id, $size );
}
	/**
	 * bbp_get_reply_author_avatar ()
	 *
	 * Return the author avatar of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Avatar of author of the reply
	 */
	function bbp_get_reply_author_avatar ( $reply_id = 0, $size = 40 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Check for anonymous user
		if ( $author_id = get_post_field( 'post_author', $reply_id ) )
			$author_avatar = get_avatar( $author_id );
		else
			$author_avatar = get_avatar( get_post_meta( $reply_id, '_bbp_anonymous_email', true ) );

		return apply_filters( 'bbp_get_reply_author_avatar', $author_avatar, $reply_id, $size );
	}

/**
 * bbp_reply_author_link ()
 *
 * Output the author link of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2717)
 *
 * @param mixed|int $args If it is an integer, it is used as reply_id. Optional.
 * @uses bbp_get_reply_author_link()
 */
function bbp_reply_author_link ( $args = '' ) {
	echo bbp_get_reply_author_link( $args );
}
	/**
	 * bbp_get_reply_author_link ()
	 *
	 * Return the author link of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2717)
	 *
	 * @uses bbp_get_reply_author_url()
	 * @uses bbp_get_reply_author()
	 *
	 * @param mixed|int $args If it is an integer, it is used as reply_id. Optional.
	 * @return string Author link of reply
	 */
	function bbp_get_reply_author_link ( $args = '' ) {
		// Used as reply_id
		if ( is_int( $args ) ) {
			$reply_id = bbp_get_reply_id( $args );
		} else {
			$defaults = array (
				'reply_id'   => 0,
				'link_title' => '',
				'link_text'  => ''
			);

			$r = wp_parse_args( $args, $defaults );
			extract( $r );
		}

		if ( empty( $reply_id ) )
			$reply_id   = bbp_get_reply_id( $reply_id );

		if ( empty( $link_title ) && ( bbp_is_topic() || bbp_is_reply() ) )
			$link_title = sprintf( get_the_author_meta( 'ID' ) ? __( 'View %s\'s profile', 'bbpress' ) : __( 'Visit %s\'s website', 'bbpress' ), bbp_get_reply_author( $reply_id ) );

		if ( empty( $link_text ) && ( bbp_is_topic() || bbp_is_reply() ) )
			$link_text  = bbp_get_reply_author_avatar( $reply_id, 80 );
		else
			$link_text  = bbp_get_reply_author( $reply_id );

		$link_title = !empty( $link_title ) ? ' title="' . $link_title . '"' : '';

		// Check for anonymous user
		if ( $author_url = bbp_get_reply_author_url( $reply_id ) )
			$author_link = sprintf( '<a href="%1$s"%2$s>%3$s</a>', $author_url, $link_title, $link_text );
		else
			$author_link = $link_text; // Still return $link_text

		return apply_filters( 'bbp_get_reply_author_link', $author_link, $args );
	}

		/**
		 * bbp_reply_author_url ()
		 *
		 * Output the author url of the reply in the loop
		 *
		 * @package bbPress
		 * @subpackage Template Tags
		 * @since bbPress (r2667)
		 * @param int $reply_id optional
		 *
		 * @uses bbp_get_reply_author_url()
		 */
		function bbp_reply_author_url ( $reply_id = 0 ) {
			echo bbp_get_reply_author_url( $reply_id );
		}
			/**
			 * bbp_get_reply_author_url ()
			 *
			 * Return the author url of the reply in the loop
			 *
			 * @package bbPress
			 * @subpackage Template Tags
			 * @since bbPress (r22667)
			 *
			 * @uses bbp_get_user_profile_url()
			 * @uses get_post_meta()
			 *
			 * @param int $reply_id optional
			 * @return string Author URL of reply
			 */
			function bbp_get_reply_author_url ( $reply_id = 0 ) {
				$reply_id = bbp_get_reply_id( $reply_id );

				// Check for anonymous user
				if ( $author_id = get_post_field( 'post_author', $reply_id ) )
					$author_url = bbp_get_user_profile_url( $author_id );
				else
					if ( !$author_url = get_post_meta( $reply_id, '_bbp_anonymous_website', true ) )
						$author_url = '';

				return apply_filters( 'bbp_get_reply_author_url', $author_url, $reply_id );
			}

/**
 * bbp_reply_topic_title ()
 *
 * Output the topic title a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_topic()
 */
function bbp_reply_topic_title ( $reply_id = 0 ) {
	echo bbp_get_reply_topic_title( $reply_id );
}
	/**
	 * bbp_get_reply_topic_title ()
	 *
	 * Return the topic title a reply belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id optional
	 *
	 * @uses bbp_get_reply_topic_id ()
	 * @uses bbp_topic_title ()
	 *
	 * @return string
	 */
	function bbp_get_reply_topic_title ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		return apply_filters( 'bbp_get_reply_topic_title', bbp_get_topic_title( $topic_id ), $reply_id, $topic_id );
	}

/**
 * bbp_reply_topic_id ()
 *
 * Output the topic ID a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
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
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id optional
	 *
	 * @todo - Walk ancestors and look for topic post_type (for threaded replies)
	 *
	 * @return string
	 */
	function bbp_get_reply_topic_id ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = get_post_field( 'post_parent', $reply_id );

		return apply_filters( 'bbp_get_reply_topic_id', $topic_id, $reply_id );
	}

/**
 * bbp_reply_forum_id ()
 *
 * Output the forum ID a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2679)
 *
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_topic_id ()
 */
function bbp_reply_forum_id ( $reply_id = 0 ) {
	echo bbp_get_reply_forum_id( $reply_id );
}
	/**
	 * bbp_get_reply_forum_id ()
	 *
	 * Return the forum ID a reply belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2679)
	 *
	 * @param int $reply_id optional
	 *
	 * @todo - Walk ancestors and look for forum post_type
	 *
	 * @return string
	 */
	function bbp_get_reply_forum_id ( $reply_id = 0 ) {
		$reply_id = bbp_get_forum_id( $reply_id );
		$topic_id = get_post_field( 'post_parent', $reply_id );
		$forum_id = get_post_field( 'post_parent', $topic_id );

		return apply_filters( 'bbp_get_reply_topic_id', $forum_id, $reply_id );
	}

/**
 * bbp_reply_admin_links()
 *
 * Output admin links for reply
 *
 * @param array $args
 */
function bbp_reply_admin_links( $args = '' ) {
	echo bbp_get_reply_admin_links( $args );
}
	/**
	 * bbp_get_reply_admin_links()
	 *
	 * Return admin links for reply
	 *
	 * @param array $args
	 * @return string
	 */
	function bbp_get_reply_admin_links( $args = '' ) {
		if ( !current_user_can( 'edit_others_replies' ) )
			return;

		$defaults = array (
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array (
				'edit'  => __( 'Edit', 'bbpress' ),  // bbp_get_reply_close_link( $args ),
				'trash' => __( 'Trash', 'bbpress' ), // bbp_get_reply_delete_link( $args ),
			),
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		// Process the admin links
		$links = implode( $sep, $links );

		return apply_filters( 'bbp_get_reply_admin_links', $before . $links . $after, $args );
	}

/**
 * bbp_reply_class ()
 *
 * Output the row class of a reply
 */
function bbp_reply_class ( $reply_id = 0 ) {
	echo bbp_get_reply_class( $reply_id );
}
	/**
	 * bbp_get_reply_class ()
	 *
	 * Return the row class of a reply
	 *
	 * @global WP_Query $bbp->reply_query
	 * @param int $reply_id
	 * @return string
	 */
	function bbp_get_reply_class ( $reply_id = 0 ) {
		global $bbp;

		$count     = isset( $bbp->reply_query->current_post ) ? $bbp->reply_query->current_post : 1;
		$alternate = (int) $count % 2 ? 'even' : 'odd';
		$status    = 'status-'  . bbp_get_reply_status();
		$post      = post_class( array( $alternate, $status ) );

		return apply_filters( 'bbp_reply_class', $post );
	}

/**
 * bbp_topic_pagination_count ()
 *
 * Output the pagination count
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2519)
 *
 * @global WP_Query $bbp->topic_query
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
	 * @since bbPress (r2519)
	 *
	 * @global WP_Query $bbp->reply_query
	 * @return string
	 */
	function bbp_get_topic_pagination_count () {
		global $bbp;

		// Set pagination values
		$start_num = intval( ( $bbp->reply_query->paged - 1 ) * $bbp->reply_query->posts_per_page ) + 1;
		$from_num  = bbp_number_format( $start_num );
		$to_num    = bbp_number_format( ( $start_num + ( $bbp->reply_query->posts_per_page - 1 ) > $bbp->reply_query->found_posts ) ? $bbp->reply_query->found_posts : $start_num + ( $bbp->reply_query->posts_per_page - 1 ) );
		$total     = bbp_number_format( $bbp->reply_query->found_posts );

		// Set return string
		if ( $total > 1 && (int)$from_num == (int)$to_num )
			$retstr = sprintf( __( 'Viewing reply %1$s (of %2$s total)', 'bbpress' ), $from_num, $total );
		elseif ( $total > 1 && empty( $to_num ) )
			$retstr = sprintf( __( 'Viewing %1$s replies', 'bbpress' ), $total );
		if ( $total > 1 && (int)$from_num != (int)$to_num )
			$retstr = sprintf( __( 'Viewing %1$s replies - %2$s through %3$s (of %4$s total)', 'bbpress' ), $bbp->reply_query->post_count, $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s reply', 'bbpress' ), $total );

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
 * @since bbPress (r2519)
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
	 * @since bbPress (r2519)
	 *
	 * @global WP_Query $bbp->reply_query
	 * @return string
	 */
	function bbp_get_topic_pagination_links () {
		global $bbp;

		if ( !isset( $bbp->reply_query->pagination_links ) || empty( $bbp->reply_query->pagination_links ) )
			return false;
		else
			return apply_filters( 'bbp_get_topic_pagination_links', $bbp->reply_query->pagination_links );
	}

/** END reply Loop Functions **************************************************/

?>
