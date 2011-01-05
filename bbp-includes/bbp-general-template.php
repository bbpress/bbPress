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
 * Output a select box allowing to pick which forum/topic a new topic/reply
 * belongs in.
 *
 * Can be used for any post type, but is mostly used for topics and forums.
 *
 * @since bbPress (r2744)
 *
 * @param mixed $args See {@link bbp_get_dropdown()} for arguments
 */
function bbp_dropdown( $args = '' ) {
	echo bbp_get_dropdown( $args );
}
	/**
	 * Output a select box allowing to pick which forum/topic a new
	 * topic/reply belongs in.
	 *
	 * @since bbPress (r2744)
	 *
	 * @param mixed $args The function supports these args:
	 *  - post_type: Post type, defaults to $bbp->forum_id (bbp_forum)
	 *  - selected: Selected ID, to not have any value as selected, pass
	 *               anything smaller than 0 (due to the nature of select
	 *               box, the first value would of course be selected -
	 *               though you can have that as none (pass 'show_none' arg))
	 *  - sort_column: Sort by? Defaults to 'menu_order, post_title'
	 *  - child_of: Child of. Defaults to 0
	 *  - post_status: Which all post_statuses to find in? Can be an array
	 *                  or CSV of publish, category, closed, private, spam,
	 *                  trash (based on post type) - if not set, these are
	 *                  automatically determined based on the post_type
	 *  - posts_per_page: Retrieve all forums/topics. Defaults to -1 to get
	 *                     all posts
	 *  - walker: Which walker to use? Defaults to
	 *             {@link BBP_Walker_Dropdown}
	 *  - select_id: ID of the select box. Defaults to 'bbp_forum_id'
	 *  - tab: Tabindex value. False or integer
	 *  - options_only: Show only <options>? No <select>?
	 *  - show_none: False or something like __( '(No Forum)', 'bbpress' ), will have value=""
	 *  - none_found: False or something like __( 'No forums to post to!', 'bbpress' )
	 *  - disable_categories: Disable forum categories? Defaults to true. Only for forums and when the category option is displayed.
	 * @return string
	 */
	function bbp_get_dropdown( $args = '' ) {
		global $bbp;

		$defaults = array (
			'post_type'          => $bbp->forum_id,
			'selected'           => 0,
			'sort_column'        => 'post_title',
			'child_of'           => '0',
			'post_status'        => 'publish',
			'numberposts'        => -1,
			'orderby'            => 'menu_order',
			'walker'             => '',

			// Output-related
			'select_id'          => 'bbp_forum_id',
			'tab'                => false,
			'options_only'       => false,
			'show_none'          => false,
			'none_found'         => false,
			'disable_categories' => true
		);

		$r = wp_parse_args( $args, $defaults );

		if ( empty( $r['walker'] ) ) {
			$r['walker']            = new BBP_Walker_Dropdown();
			$r['walker']->tree_type = $r['post_type'];
		}

		// Determine a selected value
		if ( empty( $r['selected'] ) ) {

			// We're getting forums
			if ( $r['post_type'] == $bbp->forum_id ) {
				$r['selected'] = bbp_get_forum_id();

			// We're getting topics
			} elseif ( $r['post_type'] == $bbp->topic_id ) {
				$r['selected'] = bbp_get_topic_id();
			}
		}

		// Force 0
		if ( is_numeric( $r['selected'] ) && $r['selected'] < 0 )
			$r['selected'] = 0;

		// Don't show private forums to normal users
		if ( !current_user_can( 'edit_others_forums' ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) && empty( $r['meta_compare'] ) ) {
			$r['meta_key']     = '_bbp_forum_visibility';
			$r['meta_value']   = 'public';
			$r['meta_compare'] = '==';
		}

		extract( $r );

		// Unset the args not needed for WP_Query to avoid any possible conflicts.
		// Note: walker and disable_categories are not unset
		unset( $r['select_id'], $r['tab'], $r['options_only'], $r['show_none'], $r['none_found'] );

		// Setup variables
		$name      = esc_attr( $select_id );
		$select_id = $name;
		$tab       = (int) $tab;
		$retval    = '';

		// @todo - write a better get_ function
		if ( $r['post_type'] == $bbp->forum_id )
			$posts = get_pages( $r );
		elseif ( $r['post_type'] == $bbp->topic_id )
			$posts = get_posts( $r );

		// Make a drop down if we found posts
		if ( !empty( $posts ) ) {
			if ( empty( $options_only ) ) {
				$tab     = !empty( $tab ) ? ' tabindex="' . $tab . '"' : '';
				$retval .= '<select name="' . $name . '" id="' . $select_id . '"' . $tab . '>' . "\n";
			}

			$retval .= !empty( $show_none ) ? "\t<option value=\"\" class=\"level-0\">" . $show_none . '</option>' : '';
			$retval .= walk_page_dropdown_tree( $posts, 0, $r );

			if ( empty( $options_only ) )
				$retval .= '</select>';

		// Display feedback
		} else {
			// Long short hand
			$retval .= !empty( $none_found ) ? $none_found : $post_type == $bbp->topic_id ? __( 'No topics to post to!', 'bbpress' ) : $post_type == $bbp->forum_id ? __( 'No forums to post to!', 'bbpress' ) : __( 'No posts found!', 'bbpress' );
		}

		return apply_filters( 'bbp_get_dropdown', $retval, $args );
	}

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

/** END Form Functions ********************************************************/

/** Start General Functions ***************************************************/

/**
 * bbp_error_messages ()
 *
 * Display possible error messages inside a template file
 *
 * @global WP_Error $bbp->errors
 */
function bbp_error_messages () {
	global $bbp;

	if ( isset( $bbp->errors ) && is_wp_error( $bbp->errors ) && $bbp->errors->get_error_codes() ) : ?>

		<div class="bbp-template-notice error">
			<p>
				<?php echo implode( "</p>\n<p>", $bbp->errors->get_error_messages() ); ?>
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
	 * Return a breadcrumb ( forum < topic )
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

/** Start Query Functions *****************************************************/

/**
 * bbp_get_query_name ()
 *
 * Get the '_bbp_query_name' setting to $name
 */
function bbp_get_query_name ()  {
	return get_query_var( '_bbp_query_name' );
}

/**
 * bbp_set_query_name ()
 *
 * Set the '_bbp_query_name' setting to $name
 *
 * @param str $name
 */
function bbp_set_query_name ( $name )  {
	set_query_var( '_bbp_query_name', $name );
}

/**
 * bbp_reset_query_name ()
 *
 * Used to clear the '_bbp_query_name' setting
 */
function bbp_reset_query_name () {
	set_query_var( '_bbp_query_name', '' );
}

?>
