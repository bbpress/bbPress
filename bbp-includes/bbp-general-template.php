<?php

/** START - WordPress Add-on Actions ******************************************/

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
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
 * @since bbPress (r2464)
 */
function bbp_footer () {
	do_action( 'bbp_footer' );
}
add_action( 'wp_footer', 'bbp_footer' );

/** END - WordPress Add-on Actions ********************************************/

/** START is_ Functions *******************************************************/

/**
 * bbp_is_forum ()
 *
 * Check if current page is a bbPress forum
 *
 * @since bbPress (r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_forum () {
	global $wp_query, $bbp;

	if ( is_singular( $bbp->forum_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->forum_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->forum_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_topic ()
 *
 * Check if current page is a bbPress topic
 *
 * @since bbPress (r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_topic () {
	global $wp_query, $bbp;

	if ( is_singular( $bbp->topic_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->topic_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->topic_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_reply ()
 *
 * Check if current page is a bbPress topic reply
 *
 * @since bbPress (r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_reply () {
	global $wp_query, $bbp;

	if ( is_singular( $bbp->reply_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->reply_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->reply_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_favorites ()
 *
 * Check if current page is a bbPress user's favorites page (profile page)
 *
 * @since bbPress (r2652)
 *
 * @param bool $query_name_check Optional. Check the query name (_bbp_query_name query var), if it is 'bbp_user_profile_favorites' or not. Defaults to true.
 * @return bool
 */
function bbp_is_favorites ( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && 'bbp_user_profile_favorites' != get_query_var( '_bbp_query_name' ) )
		return false;

	return true;
}

/**
 * bbp_is_subscriptions ()
 *
 * Check if current page is a bbPress user's subscriptions page (profile page)
 *
 * @since bbPress (r2652)
 *
 * @param bool $query_name_check Optional. Check the query name (_bbp_query_name query var), if it is 'bbp_user_profile_subscriptions' or not. Defaults to true.
 * @return bool
 */
function bbp_is_subscriptions ( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && 'bbp_user_profile_subscriptions' != get_query_var( '_bbp_query_name' ) )
		return false;

	return true;
}

/**
 * bbp_is_topics_created ()
 *
 * Check if current page shows the topics created by a bbPress user (profile page)
 *
 * @since bbPress (r2688)
 *
 * @param bool $query_name_check Optional. Check the query name (_bbp_query_name query var), if it is 'bbp_user_profile_subscriptions' or not. Defaults to true.
 * @return bool
 */
function bbp_is_topics_created ( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && 'bbp_user_profile_topics_created' != get_query_var( '_bbp_query_name' ) )
		return false;

	return true;
}

/**
 * bbp_is_user_home ()
 *
 * Check if current page is the currently logged in users author page
 *
 * @global object $current_user
 * @return bool
 */
function bbp_is_user_home () {
	global $bbp;

	if ( !isset( $bbp->displayed_user ) )
		$retval = false;
	else
		$retval = $bbp->current_user->ID == $bbp->displayed_user->ID ? true : false;

	return apply_filters( 'bbp_is_user_home', $retval );
}

/**
 * bbp_is_user_profile_page ()
 *
 * Check if current page is a user profile page
 *
 * @since bbPress (r2688)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_user_profile_page () {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_user_profile_page ) && $wp_query->bbp_is_user_profile_page == true )
		return true;
	else
		return false;

	return apply_filters( 'bbp_is_user_profile_page', $retval, $wp_query );
}

/**
 * bbp_is_user_profile_edit ()
 *
 * Check if current page is a user profile edit page
 *
 * @since bbPress (r2688)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_user_profile_edit () {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_user_profile_edit ) && $wp_query->bbp_is_user_profile_edit == true )
		return true;
	else
		return false;

	return apply_filters( 'bbp_is_user_profile_edit', $retval, $wp_query );
}

/** END is_ Functions *********************************************************/

/** START Form Functions ******************************************************/

/**
 * bbp_new_topic_form_fields ()
 *
 * Output the required hidden fields when creating a new topic
 *
 * @uses wp_nonce_field, bbp_forum_id
 */
function bbp_new_topic_form_fields () {

	if ( bbp_is_forum() ) : ?>

	<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />

	<?php endif; ?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-new-topic" />

	<?php wp_nonce_field( 'bbp-new-topic' );
}

/**
 * bbp_new_reply_form_fields ()
 *
 * Output the required hidden fields when creating a new reply
 *
 * @uses wp_nonce_field, bbp_forum_id, bbp_topic_id
 */
function bbp_new_reply_form_fields () { ?>

	<input type="hidden" name="bbp_reply_title" id="bbp_reply_title" value="<?php printf( __( 'Reply To: %s', 'bbpress' ), bbp_get_topic_title() ); ?>" />
	<input type="hidden" name="bbp_forum_id"    id="bbp_forum_id"    value="<?php bbp_forum_id(); ?>" />
	<input type="hidden" name="bbp_topic_id"    id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />
	<input type="hidden" name="action"          id="bbp_post_action" value="bbp-new-reply" />

	<?php wp_nonce_field( 'bbp-new-reply' );
}

/**
 * bbp_edit_user_form_fields ()
 *
 * Output the required hidden fields when editing a user
 *
 * @uses wp_nonce_field
 * @uses wp_referer_field
 */
function bbp_edit_user_form_fields () { ?>

	<input type="hidden" name="action"  id="bbp_post_action" value="bbp-update-user" />
	<input type="hidden" name="user_id" id="user_id"         value="<?php bbp_displayed_user_id(); ?>" />

	<?php wp_referer_field(); ?>
	<?php wp_nonce_field( 'update-user_' . bbp_get_displayed_user_id() );
}

/**
 * bbp_forum_dropdown ()
 *
 * Output a select box allowing to pick which forum a new topic belongs in.
 *
 * @param array $args
 */
function bbp_forum_dropdown ( $args = '' ) {
	echo bbp_get_forum_dropdown( $args );
}
	/**
	 * bbp_get_forum_dropdown ()
	 *
	 * Return a select box allowing to pick which forum a new topic belongs in.
	 *
	 * @global object $bbp
	 * @param array $args
	 * @return string
	 */
	function bbp_get_forum_dropdown ( $args = '' ) {
		global $bbp;

		$defaults = array (
			'post_type'         => $bbp->forum_id,
			'selected'          => bbp_get_forum_id(),
			'sort_column'       => 'menu_order, post_title',
			'child_of'          => '0',
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		if ( $forums = get_posts( $r ) ) {
			$output = '<select name="bbp_forum_id" id="bbp_forum_id">';
			$output .= walk_page_dropdown_tree( $forums, 0, $r );
			$output .= '</select>';
		} else {
			$output = __( 'No forums to post to!', 'bbpress' );
		}

		return apply_filters( 'bbp_get_forums_dropdown', $output );
	}

/** END Form Functions ********************************************************/

/** Start General Functions ***************************************************/

/**
 * bbp_error_messages ()
 *
 * Display possible error messages inside a template file
 *
 * @global WP_Error $errors
 */
function bbp_error_messages () {
	global $errors;

	if ( isset( $errors ) && is_wp_error( $errors ) ) : ?>

		<div class="bp-messages error">
			<p>
				<?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?>
			</p>
		</div>

<?php endif;
}
add_action( 'bbp_template_notices', 'bbp_error_messages' );

/**
 * bbp_title_breadcrumb ( $sep )
 *
 * Output the page title as a breadcrumb
 *
 * @param string $sep
 */
function bbp_title_breadcrumb ( $sep = '&larr;' ) {
	echo bbp_get_breadcrumb( $sep );
}

/**
 * bbp_breadcrumb ( $sep )
 *
 * Output a breadcrumb
 *
 * @param string $sep
 */
function bbp_breadcrumb ( $sep = '&larr;' ) {
	echo bbp_get_breadcrumb( $sep );
}
	/**
	 * bbp_get_breadcrumb ( $sep )
	 *
	 * Return a breadcrumb ( forum < topic
	 *
	 * @global object $post
	 * @param string $sep
	 * @return string
	 */
	function bbp_get_breadcrumb( $sep = '&larr;' ) {
		global $post, $bbp;

		$trail       = '';
		$parent_id   = $post->post_parent;
		$breadcrumbs = array();

		// Loop through parents
		while ( $parent_id ) {
			// Parents
			$parent = get_post( $parent_id );

			// Switch through post_type to ensure correct filters are applied
			switch ( $parent->post_type ) {
				// Forum
				case $bbp->forum_id :
					$breadcrumbs[] = '<a href="' . bbp_get_forum_permalink( $parent->ID ) . '">' . bbp_get_forum_title( $parent->ID ) . '</a>';
					break;

				// Topic
				case $bbp->topic_id :
					$breadcrumbs[] = '<a href="' . bbp_get_topic_permalink( $parent->ID ) . '">' . bbp_get_topic_title( $parent->ID ) . '</a>';
					break;

				// Reply (Note: not in most themes)
				case $bbp->reply_id :
					$breadcrumbs[] = '<a href="' . bbp_get_reply_permalink( $parent->ID ) . '">' . bbp_get_reply_title( $parent->ID ) . '</a>';
					break;

				// WordPress Post/Page/Other
				default :
					$breadcrumbs[] = '<a href="' . get_permalink( $parent->ID ) . '">' . get_the_title( $parent->ID ) . '</a>';
					break;
			}

			// Walk backwards up the tree
			$parent_id = $parent->post_parent;
		}

		// Reverse the breadcrumb
		$breadcrumbs = array_reverse( $breadcrumbs );

		// Build the trail
		foreach ( $breadcrumbs as $crumb )
			$trail .= $crumb . ' ' . $sep . ' ';

		return apply_filters( 'bbp_get_breadcrumb', $trail . get_the_title() );
	}

?>
