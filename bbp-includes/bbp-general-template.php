<?php

/**
 * bbPress General Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

/** START - WordPress Add-on Actions ******************************************/

/**
 * Add our custom head action to wp_head
 *
 * @since bbPress (r2464)
 *
 * @uses do_action() Calls 'bbp_head'
*/
function bbp_head() {
	do_action( 'bbp_head' );
}

/**
 * Add our custom head action to wp_head
 *
 * @since bbPress (r2464)
 *
 * @uses do_action() Calls 'bbp_footer'
 */
function bbp_footer() {
	do_action( 'bbp_footer' );
}

/** END - WordPress Add-on Actions ********************************************/

/** START is_ Functions *******************************************************/

/**
 * Check if current page is a bbPress forum
 *
 * @since bbPress (r2549)
 *
 * @uses WP_Query
 * @return bool
 */
function bbp_is_forum() {
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
 * Check if current page is a bbPress topic
 *
 * @since bbPress (r2549)
 *
 * @uses WP_Query
 * @uses bbp_is_topic_edit() To check if it's a topic edit page
 * @return bool
 */
function bbp_is_topic() {
	global $wp_query, $bbp;

	// Return false if it's a edit topic page
	if ( bbp_is_topic_edit() )
		return false;

	if ( is_singular( $bbp->topic_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->topic_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->topic_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * Check if current page is a topic edit page
 *
 * @since bbPress (r2753)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_topic_edit is true
 * @return bool
 */
function bbp_is_topic_edit() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_topic_edit ) && $wp_query->bbp_is_topic_edit == true )
		return true;

	return false;
}

/**
 * Check if current page is a topic merge page
 *
 * @since bbPress (r2756)
 *
 * @uses bbp_is_topic_edit() To check if it's a topic edit page
 * @return bool
 */
function bbp_is_topic_merge() {

	if ( bbp_is_topic_edit() && !empty( $_GET['action'] ) && 'merge' == $_GET['action'] )
		return true;

	return false;
}

/**
 * Check if current page is a topic split page
 *
 * @since bbPress (r2756)
 *
 * @uses bbp_is_topic_edit() To check if it's a topic edit page
 * @return bool
 */
function bbp_is_topic_split() {

	if ( bbp_is_topic_edit() && !empty( $_GET['action'] ) && 'split' == $_GET['action'] )
		return true;

	return false;
}

/**
 * Check if current page is a bbPress reply
 *
 * @since bbPress (r2549)
 *
 * @uses WP_Query
 * @uses bbp_is_reply_edit() To check if it's a reply edit page
 * @return bool
 */
function bbp_is_reply() {
	global $wp_query, $bbp;

	// Return false if it's a edit reply page
	if ( bbp_is_reply_edit() )
		return false;

	if ( is_singular( $bbp->reply_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->reply_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->reply_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * Check if current page is a reply edit page
 *
 * @since bbPress (r2753)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_reply_edit is true
 * @return bool
 */
function bbp_is_reply_edit() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_reply_edit ) && $wp_query->bbp_is_reply_edit == true )
		return true;

	return false;
}

/**
 * Check if current page is a bbPress user's favorites page (profile page)
 *
 * @since bbPress (r2652)
 *
 * @param bool $query_name_check Optional. Check the query name
 *                                (_bbp_query_name query var), if it is
 *                                'bbp_user_profile_favorites' or not. Defaults
 *                                to true.
 * @uses bbp_is_user_profile_page() To check if it's the user profile page
 * @uses bbp_get_query_name() To get the query name
 * @return bool
 */
function bbp_is_favorites( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && 'bbp_user_profile_favorites' != bbp_get_query_name() )
		return false;

	return true;
}

/**
 * Check if current page is a bbPress user's subscriptions page (profile page)
 *
 * @since bbPress (r2652)
 *
 * @param bool $query_name_check Optional. Check the query name
 *                                (_bbp_query_name query var), if it is
 *                                'bbp_user_profile_favorites' or not. Defaults
 *                                to true.
 * @uses bbp_is_user_profile_page() To check if it's the user profile page
 * @uses bbp_get_query_name() To get the query name
 * @return bool
 */
function bbp_is_subscriptions( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && 'bbp_user_profile_subscriptions' != bbp_get_query_name() )
		return false;

	return true;
}

/**
 * Check if current page shows the topics created by a bbPress user (profile
 * page)
 *
 * @since bbPress (r2688)
 *
 * @param bool $query_name_check Optional. Check the query name
 *                                (_bbp_query_name query var), if it is
 *                                'bbp_user_profile_favorites' or not. Defaults
 *                                to true.
 * @uses bbp_is_user_profile_page() To check if it's the user profile page
 * @uses bbp_get_query_name() To get the query name
 * @return bool
 */
function bbp_is_topics_created( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && 'bbp_user_profile_topics_created' != bbp_get_query_name() )
		return false;

	return true;
}

/**
 * Check if current page is the currently logged in users author page
 *
 * @uses bbPres Checks if bbPress::displayed_user is set and if
 *               bbPress::displayed_user::ID equals bbPress::current_user::ID
 *               or not
 * @return bool
 */
function bbp_is_user_home() {
	global $bbp;

	if ( !isset( $bbp->displayed_user ) )
		return false;

	return $bbp->current_user->ID == $bbp->displayed_user->ID;
}

/**
 * Check if current page is a user profile page
 *
 * @since bbPress (r2688)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_user_profile_page is set to true
 * @return bool
 */
function bbp_is_user_profile_page() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_user_profile_page ) && $wp_query->bbp_is_user_profile_page == true )
		return true;

	return false;
}

/**
 * Check if current page is a user profile edit page
 *
 * @since bbPress (r2688)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_user_profile_edit is set to true
 * @return bool
 */
function bbp_is_user_profile_edit() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_user_profile_edit ) && $wp_query->bbp_is_user_profile_edit == true )
		return true;

	return false;
}

/** END is_ Functions *********************************************************/

/** START Form Functions ******************************************************/

/**
 * Output a select box allowing to pick which forum/topic a new topic/reply
 * belongs in.
 *
 * Can be used for any post type, but is mostly used for topics and forums.
 *
 * @since bbPress (r2746)
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
	 * @since bbPress (r2746)
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
		if ( !current_user_can( 'read_private_forums' ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) && empty( $r['meta_compare'] ) ) {
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
 * Output the required hidden fields when creating/editing a topic
 *
 * @since bbPress (r2753)
 *
 * @uses bbp_is_topic_edit() To check if it's the topic edit page
 * @uses wp_nonce_field() To generate hidden nonce fields
 * @uses bbp_topic_id() To output the topic id
 * @uses bbp_is_forum() To check if it's a forum page
 * @uses bbp_forum_id() To output the forum id
 */
function bbp_topic_form_fields() {

	if ( bbp_is_topic_edit() ) : ?>

		<input type="hidden" name="action"       id="bbp_post_action" value="bbp-edit-topic" />
		<input type="hidden" name="bbp_topic_id" id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />

		<?php wp_nonce_field( 'bbp-edit-topic_' . bbp_get_topic_id() );

	else :

		if ( bbp_is_forum() ) : ?>

			<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />

		<?php endif; ?>

		<input type="hidden" name="action" id="bbp_post_action" value="bbp-new-topic" />

		<?php wp_nonce_field( 'bbp-new-topic' );

	endif;
}

/**
 * Output the required hidden fields when creating/editing a reply
 *
 * @since bbPress (r2753)
 *
 * @uses bbp_is_reply_edit() To check if it's the reply edit page
 * @uses wp_nonce_field() To generate hidden nonce fields
 * @uses bbp_reply_id() To output the reply id
 * @uses bbp_topic_id() To output the topic id
 * @uses bbp_forum_id() To output the forum id
 */
function bbp_reply_form_fields() {

	if ( bbp_is_reply_edit() ) { ?>

		<input type="hidden" name="action"       id="bbp_post_action" value="bbp-edit-reply" />
		<input type="hidden" name="bbp_reply_id" id="bbp_reply_id"    value="<?php bbp_reply_id(); ?>" />

		<?php wp_nonce_field( 'bbp-edit-reply_' . bbp_get_reply_id() );

	} else {

	?>

		<input type="hidden" name="bbp_reply_title" id="bbp_reply_title" value="<?php printf( __( 'Reply To: %s', 'bbpress' ), bbp_get_topic_title() ); ?>" />
		<input type="hidden" name="bbp_forum_id"    id="bbp_forum_id"    value="<?php bbp_forum_id(); ?>" />
		<input type="hidden" name="bbp_topic_id"    id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />
		<input type="hidden" name="action"          id="bbp_post_action" value="bbp-new-reply" />

		<?php wp_nonce_field( 'bbp-new-reply' );
	}
}

/**
 * Output the required hidden fields when editing a user
 *
 * @since bbPress (r2690)
 *
 * @uses bbp_displayed_user_id() To output the displayed user id
 * @uses wp_nonce_field() To generate a hidden nonce field
 * @uses wp_referer_field() To generate a hidden referer field
 */
function bbp_edit_user_form_fields() { ?>

	<input type="hidden" name="action"  id="bbp_post_action" value="bbp-update-user" />
	<input type="hidden" name="user_id" id="user_id"         value="<?php bbp_displayed_user_id(); ?>" />

	<?php wp_referer_field(); ?>
	<?php wp_nonce_field( 'update-user_' . bbp_get_displayed_user_id() );
}

/**
 * Merge topic form fields
 *
 * Output the required hidden fields when merging a topic
 *
 * @since bbPress (r2756)
 *
 * @uses wp_nonce_field() To generate a hidden nonce field
 * @uses bbp_topic_id() To output the topic id
 */
function bbp_merge_topic_form_fields() {

	?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-merge-topic" />
	<input type="hidden" name="bbp_topic_id" id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />

	<?php wp_nonce_field( 'bbp-merge-topic_' . bbp_get_topic_id() );
}

/**
 * Split topic form fields
 *
 * Output the required hidden fields when splitting a topic
 *
 * @since bbPress (r2756)
 *
 * @uses wp_nonce_field() To generete a hidden nonce field
 */
function bbp_split_topic_form_fields() {

	?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-split-topic" />
	<input type="hidden" name="bbp_reply_id" id="bbp_reply_id"    value="<?php echo absint( $_GET['reply_id'] ); ?>" />

	<?php wp_nonce_field( 'bbp-split-topic_' . bbp_get_topic_id() );
}

/** END Form Functions ********************************************************/

/** Start General Functions ***************************************************/

/**
 * Display possible error messages inside a template file
 *
 * @since bbPress (r2688)
 *
 * @uses WP_Error bbPress::errors::get_error_codes() To get the error codes
 * @uses WP_Error bbPress::errors::get_error_messages() To get the error
 *                                                       messages
 * @uses is_wp_error() To check if it's a {@link WP_Error}
 */
function bbp_error_messages() {
	global $bbp;

	if ( isset( $bbp->errors ) && is_wp_error( $bbp->errors ) && $bbp->errors->get_error_codes() ) : ?>

		<div class="bbp-template-notice error">
			<p>
				<?php echo implode( "</p>\n<p>", $bbp->errors->get_error_messages() ); ?>
			</p>
		</div>

<?php endif;
}

/**
 * Output the page title as a breadcrumb
 *
 * @since bbPress (r2589)
 *
 * @param string $sep Separator. Defaults to '&larr;'
 * @uses bbp_get_breadcrumb() To get the breadcrumb
 */
function bbp_title_breadcrumb( $sep = '&larr;' ) {
	echo bbp_get_breadcrumb( $sep );
}

/**
 * Output a breadcrumb
 *
 * @since bbPress (r2589)
 *
 * @param string $sep Separator. Defaults to '&larr;'
 * @uses bbp_get_breadcrumb() To get the breadcrumb
 */
function bbp_breadcrumb( $sep = '&larr;' ) {
	echo bbp_get_breadcrumb( $sep );
}
	/**
	 * Return a breadcrumb ( forum -> topic -> reply )
	 *
	 * @since bbPress (r2589)
	 *
	 * @param string $sep Separator. Defaults to '&larr;'
	 * @uses get_post() To get the post
	 * @uses bbp_get_forum_permalink() To get the forum link
	 * @uses bbp_get_topic_permalink() To get the topic link
	 * @uses bbp_get_reply_permalink() To get the reply link
	 * @uses get_permalink() To get the permalink
	 * @uses bbp_get_forum_title() To get the forum title
	 * @uses bbp_get_topic_title() To get the topic title
	 * @uses bbp_get_reply_title() To get the reply title
	 * @uses get_the_title() To get the title
	 * @uses apply_filters() Calls 'bbp_get_breadcrumb' with the crumbs
	 * @return string Breadcrumbs
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
 * Get the '_bbp_query_name' setting
 *
 * @since bbPress (r2695)
 *
 * @uses get_query_var() To get the query var '_bbp_query_name'
 * @return string To return the query var value
 */
function bbp_get_query_name()  {
	return get_query_var( '_bbp_query_name' );
}

/**
 * Set the '_bbp_query_name' setting to $name
 *
 * @since bbPress (r2692)
 *
 * @param string $name What to set the query var to
 * @uses set_query_var() To set the query var '_bbp_query_name'
 */
function bbp_set_query_name( $name = '' )  {
	set_query_var( '_bbp_query_name', $name );
}

/**
 * Used to clear the '_bbp_query_name' setting
 *
 * @since bbPress (r2692)
 *
 * @uses bbp_set_query_name() To set the query var '_bbp_query_name' to ''
 */
function bbp_reset_query_name() {
	bbp_set_query_name();
}

?>
