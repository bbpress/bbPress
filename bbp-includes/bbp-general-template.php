<?php

/**
 * bbPress General Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

/** Add-on Actions ************************************************************/

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

/** is_ ***********************************************************************/

/**
 * Check if current page is a bbPress forum
 *
 * @since bbPress (r2549)
 *
 * @param int $post_id Possible post_id to check
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses is_singular() To check if it's the single post page
 * @uses get_post_field() To get the post type of the post id
 * @return bool True if it's a forum page, false if not
 */
function bbp_is_forum( $post_id = 0 ) {
	global $bbp;

	if ( empty( $post_id ) ) {

		if ( is_singular( bbp_get_forum_post_type() ) )
			return true;

		if ( ( $post_type = get_query_var( 'post_type' ) ) && ( bbp_get_forum_post_type() === $post_type ) )
			return true;

		if ( isset( $bbp->forum_query->post->post_type ) && ( bbp_get_forum_post_type() === $bbp->forum_query->post->post_type ) )
			return true;

		if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && ( bbp_get_forum_post_type() === $_GET['post_type'] ) )
			return true;

	} elseif ( !empty( $post_id ) && ( bbp_get_forum_post_type() == get_post_field( 'post_type', $post_id ) ) )
		return true;

	return false;
}

/**
 * Check if current page is a bbPress topic
 *
 * @since bbPress (r2549)
 *
 * @param int $post_id Possible post_id to check
 * @uses bbp_is_topic_edit() To return false if it's a topic edit page
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses is_singular() To check if it's the single post page
 * @uses get_post_field() To get the post type of the post id
 * @return bool True if it's a topic page, false if not
 */
function bbp_is_topic( $post_id = 0 ) {
	global $bbp;

	// Return false if it's a edit topic page
	if ( bbp_is_topic_edit() )
		return false;

	if ( empty( $post_id ) ) {

		if ( is_singular( bbp_get_topic_post_type() ) )
			return true;

		if ( ( $post_type = get_query_var( 'post_type' ) ) && ( bbp_get_topic_post_type() === $post_type ) )
			return true;

		if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && ( bbp_get_topic_post_type() === $_GET['post_type'] ) )
			return true;

	} elseif ( !empty( $post_id ) && ( bbp_get_topic_post_type() == get_post_field( 'post_type', $post_id ) ) )
		return true;

	return false;
}

/**
 * Check if current page is a topic edit page
 *
 * @since bbPress (r2753)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_topic_edit is true
 * @return bool True if it's the topic edit page, false if not
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
 * @return bool True if it's the topic merge page, false if not
 */
function bbp_is_topic_merge() {

	if ( bbp_is_topic_edit() && !empty( $_GET['action'] ) && ( 'merge' == $_GET['action'] ) )
		return true;

	return false;
}

/**
 * Check if current page is a topic split page
 *
 * @since bbPress (r2756)
 *
 * @uses bbp_is_topic_edit() To check if it's a topic edit page
 * @return bool True if it's the topic split page, false if not
 */
function bbp_is_topic_split() {

	if ( bbp_is_topic_edit() && !empty( $_GET['action'] ) && ( 'split' == $_GET['action'] ) )
		return true;

	return false;
}

/**
 * Check if current page is a bbPress reply
 *
 * @since bbPress (r2549)
 *
 * @param int $post_id Possible post_id to check
 * @uses bbp_is_reply_edit() To return false if it's a reply edit page
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses is_singular() To check if it's the single post page
 * @uses get_post_field() To get the post type of the post id
 * @return bool True if it's a reply page, false if not
 */
function bbp_is_reply( $post_id = 0 ) {
	global $bbp;

	// Return false if it's a edit reply page
	if ( bbp_is_reply_edit() )
		return false;

	if ( empty( $post_id ) ) {

		if ( is_singular( bbp_get_reply_post_type() ) )
			return true;

		if ( ( $post_type = get_query_var( 'post_type' ) ) && ( bbp_get_reply_post_type() === $post_type ) )
			return true;

		if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && ( bbp_get_reply_post_type() === $_GET['post_type'] ) )
			return true;

	} elseif ( !empty( $post_id ) && ( bbp_get_reply_post_type() == get_post_field( 'post_type', $post_id ) ) )
		return true;

	return false;
}

/**
 * Check if current page is a reply edit page
 *
 * @since bbPress (r2753)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_reply_edit is true
 * @return bool True if it's the reply edit page, false if not
 */
function bbp_is_reply_edit() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_reply_edit ) && ( true == $wp_query->bbp_is_reply_edit ) )
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
 * @return bool True if it's the favorites page, false if not
 */
function bbp_is_favorites( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && ( !bbp_is_query_name( 'bbp_user_profile_favorites' ) ) )
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
 * @return bool True if it's the subscriptions page, false if not
 */
function bbp_is_subscriptions( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && ( !bbp_is_query_name( 'bbp_user_profile_subscriptions' ) ) )
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
 * @return bool True if it's the topics created page, false if not
 */
function bbp_is_topics_created( $query_name_check = true ) {
	if ( !bbp_is_user_profile_page() )
		return false;

	if ( !empty( $query_name_check ) && ( !bbp_is_query_name( 'bbp_user_profile_topics_created' ) ) )
		return false;

	return true;
}

/**
 * Check if current page is the currently logged in users author page
 *
 * @since bbPress (r2688)
 *
 * @uses bbPres Checks if bbPress::displayed_user is set and if
 *               bbPress::displayed_user::ID equals bbPress::current_user::ID
 *               or not
 * @return bool True if it's the user's home, false if not
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
 * @return bool True if it's a user's profile page, false if not
 */
function bbp_is_user_profile_page() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_user_profile_page ) && ( true == $wp_query->bbp_is_user_profile_page ) )
		return true;

	return false;
}

/**
 * Check if current page is a user profile edit page
 *
 * @since bbPress (r2688)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_user_profile_edit is set to true
 * @return bool True if it's a user's profile edit page, false if not
 */
function bbp_is_user_profile_edit() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_user_profile_edit ) && ( true == $wp_query->bbp_is_user_profile_edit ) )
		return true;

	return false;
}

/**
 * Check if current page is a view page
 *
 * @since bbPress (r2789)
 *
 * @uses WP_Query Checks if WP_Query::bbp_is_view is true
 * @return bool Is it a view page?
 */
function bbp_is_view() {
	global $wp_query;

	if ( !empty( $wp_query->bbp_is_view ) && ( true == $wp_query->bbp_is_view ) )
		return true;

	return false;
}

/**
 * Use the above is_() functions to output a body class for each scenario
 *
 * @since bbPress (r2926)
 *
 * @param array $wp_classes
 * @param array $custom_classes
 * @uses bbp_is_forum()
 * @uses bbp_is_topic()
 * @uses bbp_is_topic_edit()
 * @uses bbp_is_topic_merge()
 * @uses bbp_is_topic_split()
 * @uses bbp_is_reply()
 * @uses bbp_is_reply_edit()
 * @uses bbp_is_reply_edit()
 * @uses bbp_is_view()
 * @uses bbp_is_user_profile_edit()
 * @uses bbp_is_user_profile_page()
 * @uses bbp_is_user_home()
 * @uses bbp_is_subscriptions()
 * @uses bbp_is_favorites()
 * @uses bbp_is_topics_created()
 * @return array Body Classes
 */
function bbp_body_class( $wp_classes, $custom_classes = false ) {

	$bbp_classes = array();

	/** Components ********************************************************/

	if ( bbp_is_forum() )
		$bbp_classes[] = bbp_get_forum_post_type();

	if ( bbp_is_topic() )
		$bbp_classes[] = bbp_get_topic_post_type();

	if ( bbp_is_topic_edit() )
		$bbp_classes[] = bbp_get_topic_post_type() . '-edit';

	if ( bbp_is_topic_merge() )
		$bbp_classes[] = bbp_get_topic_post_type() . '-merege';

	if ( bbp_is_topic_split() )
		$bbp_classes[] = bbp_get_topic_post_type() . '-split';

	if ( bbp_is_reply() )
		$bbp_classes[] = bbp_get_reply_post_type();

	if ( bbp_is_reply_edit() )
		$bbp_classes[] = bbp_get_reply_post_type() . '-edit';

	if ( bbp_is_view() )
		$bbp_classes[] = 'bbp-view';

	/** User **************************************************************/

	if ( bbp_is_user_profile_edit() )
		$bbp_classes[] = 'bbp-user-edit';

	if ( bbp_is_user_profile_page() )
		$bbp_classes[] = 'bbp-user-page';

	if ( bbp_is_user_home() )
		$bbp_classes[] = 'bbp-user-home';

	if ( bbp_is_topics_created() )
		$bbp_classes[] = 'bbp-topics-created';

	if ( bbp_is_favorites() )
		$bbp_classes[] = 'bbp-favorites';

	if ( bbp_is_subscriptions() )
		$bbp_classes[] = 'bbp-subscriptions';

	/** Clean up **********************************************************/

	// Add bbPress class if we are within a bbPress page
	if ( !empty( $bbp_classes ) )
		$bbp_classes[] = 'bbPress';

	// Merge WP classes with bbPress classes
	$classes = array_merge( (array) $bbp_classes, (array) $wp_classes );

	// Remove any duplicates
	$classes = array_unique( $classes );

	return apply_filters( 'bbp_get_the_body_class', $classes, $bbp_classes, $wp_classes, $custom_classes );
}

/** Forms *********************************************************************/

/**
 * Output the login form action url
 *
 * @since bbPress (r2815)
 *
 * @param string $url Pass a URL to redirect to
 * @uses add_query_arg() To add a arg to the url
 * @uses site_url() Toget the site url
 * @uses apply_filters() Calls 'bbp_wp_login_action' with the url and args
 */
function bbp_wp_login_action( $args = '' ) {
	$defaults = array (
		'action'  => '',
		'context' => ''
	);
	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	if ( !empty( $action ) )
		$login_url = add_query_arg( array( 'action' => $action ), 'wp-login.php' );
	else
		$login_url = 'wp-login.php';

	$login_url = site_url( $login_url, $context );

	echo apply_filters( 'bbp_wp_login_action', $login_url, $args );
}

/**
 * Output hidden request URI field for user forms.
 *
 * The referer link is the current Request URI from the server super global. The
 * input name is '_wp_http_referer', in case you wanted to check manually.
 *
 * @since bbPress (r2815)
 *
 * @param string $url Pass a URL to redirect to
 * @uses wp_get_referer() To get the referer
 * @uses esc_attr() To escape the url
 * @uses apply_filters() Calls 'bbp_redirect_to_field' with the referer field
 *                        and url
 */
function bbp_redirect_to_field( $url = '' ) {
	// If no URL is passed, try to get the referer and then the request uri
	if ( empty( $url ) && ( !$url = wp_get_referer() ) && ( !empty( $_SERVER['REQUEST_URI'] ) ) )
		$url = $_SERVER['REQUEST_URI'];

	// Remove loggedout query arg if it's there
	$url = (string) esc_attr( remove_query_arg( 'loggedout', $url ) );

	$referer_field = '<input type="hidden" name="redirect_to" value="' . $url . '" />';

	echo apply_filters( 'bbp_redirect_to_field', $referer_field, $url );
}

/**
 * Echo sanitized $_REQUEST value.
 *
 * Use the $input_type parameter to properly process the value. This
 * ensures correct sanitization of the value for the receiving input.
 *
 * @since bbPress (r2815)
 *
 * @param string $request Name of $_REQUEST to look for
 * @param string $input_type Type of input the value is for
 * @uses bbp_get_sanitize_val() To sanitize the value
 */
function bbp_sanitize_val( $request = '', $input_type = 'text' ) {
	echo bbp_get_sanitize_val( $request, $input_type );
}
	/**
	 * Return sanitized $_REQUEST value.
	 *
	 * Use the $input_type parameter to properly process the value. This
	 * ensures correct sanitization of the value for the receiving input.
	 *
	 * @since bbPress (r2815)
	 *
	 * @param string $request Name of $_REQUEST to look for
	 * @param string $input_type Type of input the value is for
	 * @uses esc_attr() To escape the string
	 * @uses apply_filters() Calls 'bbp_get_sanitize_val' with the sanitized
	 *                        value, request and input type
	 * @return string Sanitized value ready for screen display
	 */
	function bbp_get_sanitize_val( $request = '', $input_type = 'text' ) {

		// Check that requested
		if ( empty( $_REQUEST[$request] ) )
			return false;

		// Set request varaible
		$pre_ret_val = $_REQUEST[$request];

		// Treat different kinds of fields in different ways
		switch ( $input_type ) {
			case 'text'     :
			case 'textarea' :
				$retval = esc_attr( stripslashes( $pre_ret_val ) );
				break;

			case 'password' :
			case 'select'   :
			case 'radio'    :
			case 'checkbox' :
			default :
				$retval = esc_attr( $pre_ret_val );
				break;
		}

		return apply_filters( 'bbp_get_sanitize_val', $retval, $request, $input_type );
	}

/**
 * Output the current tab index of a given form
 *
 * Use this function to handle the tab indexing of user facing forms within a
 * template file. Calling this function will automatically increment the global
 * tab index by default.
 *
 * @since bbPress (r2810)
 *
 * @param int $auto_increment Optional. Default true. Set to false to prevent
 *                             increment
 */
function bbp_tab_index( $auto_increment = true ) {
	echo bbp_get_tab_index( $auto_increment );
}

	/**
	 * Output the current tab index of a given form
	 *
	 * Use this function to handle the tab indexing of user facing forms
	 * within a template file. Calling this function will automatically
	 * increment the global tab index by default.
	 *
	 * @since bbPress (r2810)
	 *
	 * @uses apply_filters Allows return value to be filtered
	 * @param int $auto_increment Optional. Default true. Set to false to
	 *                             prevent the increment
	 * @return int $bbp->tab_index The global tab index
	 */
	function bbp_get_tab_index( $auto_increment = true ) {
		global $bbp;

		if ( true === $auto_increment )
			++$bbp->tab_index;

		return apply_filters( 'bbp_get_tab_index', (int) $bbp->tab_index );
	}

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
	 *  - post_type: Post type, defaults to bbp_get_forum_post_type() (bbp_forum)
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
	 *  - show_none: False or something like __( '(No Forum)', 'bbpress' ),
	 *                will have value=""
	 *  - none_found: False or something like
	 *                 __( 'No forums to post to!', 'bbpress' )
	 *  - disable_categories: Disable forum categories and closed forums?
	 *                         Defaults to true. Only for forums and when
	 *                         the category option is displayed.
	 * @uses BBP_Walker_Dropdown() As the default walker to generate the
	 *                              dropdown
	 * @uses current_user_can() To check if the current user can read
	 *                           private forums
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses walk_page_dropdown_tree() To generate the dropdown using the
	 *                                  walker
	 * @uses apply_filters() Calls 'bbp_get_dropdown' with the dropdown
	 *                        and args
	 * @return string The dropdown
	 */
	function bbp_get_dropdown( $args = '' ) {
		global $bbp;

		$defaults = array (
			'post_type'          => bbp_get_forum_post_type(),
			'selected'           => 0,
			'sort_column'        => 'menu_order',
			'child_of'           => '0',
			'post_status'        => 'publish',
			'numberposts'        => -1,
			'orderby'            => 'menu_order',
			'walker'             => '',

			// Output-related
			'select_id'          => 'bbp_forum_id',
			'tab'                => bbp_get_tab_index(),
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

		// Force 0
		if ( is_numeric( $r['selected'] ) && $r['selected'] < 0 )
			$r['selected'] = 0;

		// Don't show private forums to normal users
		if ( !current_user_can( 'read_private_forums' ) && empty( $r['meta_query'] ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) && empty( $r['meta_compare'] ) ) {
			$r['meta_query'] = array(
				array(
					'key'     => '_bbp_visibility',
					'value'   => 'public',
					'compare' => '='
				)
			);
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
		$posts     = get_posts( $r );

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
			// @todo - No nested ternaries
			$retval .= !empty( $none_found ) ? $none_found : ( $post_type == bbp_get_topic_post_type() ? __( 'No topics to post to!', 'bbpress' ) : ( $post_type == bbp_get_forum_post_type() ? __( 'No forums to post to!', 'bbpress' ) : __( 'No posts found!', 'bbpress' ) ) );
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

		<?php

		if ( current_user_can( 'unfiltered_html' ) )
			wp_nonce_field( 'bbp-unfiltered-html-topic_' . bbp_get_topic_id(), '_bbp_unfiltered_html_topic', false );

		?>

		<?php wp_nonce_field( 'bbp-edit-topic_' . bbp_get_topic_id() );

	else :

		if ( bbp_is_forum() ) : ?>

			<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />

		<?php endif; ?>

		<input type="hidden" name="action" id="bbp_post_action" value="bbp-new-topic" />

		<?php

		if ( current_user_can( 'unfiltered_html' ) )
			wp_nonce_field( 'bbp-unfiltered-html-topic_new', '_bbp_unfiltered_html_topic', false );

		?>

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

		<?php

		if ( current_user_can( 'unfiltered_html' ) )
			wp_nonce_field( 'bbp-unfiltered-html-reply_' . bbp_get_reply_id(), '_bbp_unfiltered_html_reply', false );

		?>

		<?php wp_nonce_field( 'bbp-edit-reply_' . bbp_get_reply_id() );

	} else {

	?>

		<input type="hidden" name="bbp_reply_title" id="bbp_reply_title" value="<?php printf( __( 'Reply To: %s', 'bbpress' ), bbp_get_topic_title() ); ?>" />
		<input type="hidden" name="bbp_forum_id"    id="bbp_forum_id"    value="<?php bbp_forum_id(); ?>" />
		<input type="hidden" name="bbp_topic_id"    id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />
		<input type="hidden" name="action"          id="bbp_post_action" value="bbp-new-reply" />

		<?php

		if ( current_user_can( 'unfiltered_html' ) )
			wp_nonce_field( 'bbp-unfiltered-html-reply_' . bbp_get_topic_id(), '_bbp_unfiltered_html_reply', false );

		?>

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
function bbp_edit_user_form_fields() {
?>

	<input type="hidden" name="action"  id="bbp_post_action" value="bbp-update-user" />
	<input type="hidden" name="user_id" id="user_id"         value="<?php bbp_displayed_user_id(); ?>" />

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

/** Views *********************************************************************/

/**
 * Output the view id
 *
 * @since bbPress (r2789)
 *
 * @param string $view Optional. View id
 * @uses bbp_get_view_id() To get the view id
 */
function bbp_view_id( $view = '' ) {
	echo bbp_get_view_id( $view );
}

	/**
	 * Get the view id
	 *
	 * If a view id is supplied, that is used. Otherwise the 'bbp_view'
	 * query var is checked for.
	 *
	 * @since bbPress (r2789)
	 *
	 * @param string $view Optional. View id.
	 * @uses sanitize_title() To sanitize the view id
	 * @uses get_query_var() To get the view id from query var 'bbp_view'
	 * @return bool|string ID on success, false on failure
	 */
	function bbp_get_view_id( $view = '' ) {
		global $bbp;

		$view = !empty( $view ) ? sanitize_title( $view ) : get_query_var( 'bbp_view' );

		if ( array_key_exists( $view, $bbp->views ) )
			return $view;

		return false;
	}

/**
 * Output the view name aka title
 *
 * @since bbPress (r2789)
 *
 * @param string $view Optional. View id
 * @uses bbp_get_view_title() To get the view title
 */
function bbp_view_title( $view = '' ) {
	echo bbp_get_view_title( $view );
}

	/**
	 * Get the view name aka title
	 *
	 * If a view id is supplied, that is used. Otherwise the bbp_view
	 * query var is checked for.
	 *
	 * @since bbPress (r2789)
	 *
	 * @param string $view Optional. View id
	 * @uses bbp_get_view_id() To get the view id
	 * @return bool|string Title on success, false on failure
	 */
	function bbp_get_view_title( $view = '' ) {
		global $bbp;

		if ( !$view = bbp_get_view_id( $view ) )
			return false;

		return $bbp->views[$view]['title'];
	}

/**
 * Output the view url
 *
 * @since bbPress (r2789)
 *
 * @param string $view Optional. View id
 * @uses bbp_get_view_url() To get the view url
 */
function bbp_view_url( $view = false ) {
	echo bbp_get_view_url( $view );
}
	/**
	 * Return the view url
	 *
	 * @since bbPress (r2789)
	 *
	 * @param string $view Optional. View id
	 * @uses sanitize_title() To sanitize the view id
	 * @uses home_url() To get blog home url
	 * @uses add_query_arg() To add custom args to the url
	 * @uses apply_filters() Calls 'bbp_get_view_url' with the view url,
	 *                        used view id
	 * @return string View url (or home url if the view was not found)
	 */
	function bbp_get_view_url( $view = false ) {
		global $bbp, $wp_rewrite;

		if ( !$view = bbp_get_view_id( $view ) )
			return home_url();

		// Pretty permalinks
		if ( $wp_rewrite->using_permalinks() ) {
			$url = $wp_rewrite->root . $bbp->view_slug . '/' . $view;
			$url = home_url( user_trailingslashit( $url ) );

		// Unpretty permalinks
		} else {
			$url = add_query_arg( array( 'bbp_view' => $view ), home_url( '/' ) );
		}

		return apply_filters( 'bbp_get_view_link', $url, $view );
	}

/** Query *********************************************************************/

/**
 * Check the passed parameter against the current _bbp_query_name
 *
 * @since bbPress (r2980)
 *
 * @uses bbp_get_query_name() Get the query var '_bbp_query_name'
 * @return bool True if match, false if not
 */
function bbp_is_query_name( $query_name )  {

	// No empties
	if ( empty( $query_name ) )
		return false;

	// Check if query var matches
	if ( bbp_get_query_name() == $query_name )
		return true;

	// No match
	return false;
}

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
 * @uses bbp_set_query_name() To set the query var '_bbp_query_name' value to ''
 */
function bbp_reset_query_name() {
	bbp_set_query_name();
}

/** Breadcrumbs ***************************************************************/

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
function bbp_breadcrumb( $sep = '&larr;', $current_page = true ) {
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
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses bbp_get_forum_title() To get the forum title
	 * @uses bbp_get_topic_title() To get the topic title
	 * @uses bbp_get_reply_title() To get the reply title
	 * @uses get_the_title() To get the title
	 * @uses apply_filters() Calls 'bbp_get_breadcrumb' with the crumbs
	 * @return string Breadcrumbs
	 */
	function bbp_get_breadcrumb( $sep = '&larr;', $current_page = true ) {
		global $post, $bbp;

		// No post, no breadcrumb
		if ( empty( $post ) )
			return;

		// Get post ancestors
		$ancestors   = array_reverse( get_post_ancestors( $post->ID ) );

		// Loop through parents
		foreach( $ancestors as $parent_id ) {

			// Parents
			$parent = get_post( $parent_id );

			// Switch through post_type to ensure correct filters are applied
			switch ( $parent->post_type ) {
				// Forum
				case bbp_get_forum_post_type() :
					$breadcrumbs[] = '<a href="' . bbp_get_forum_permalink( $parent->ID ) . '">' . bbp_get_forum_title( $parent->ID ) . '</a>';
					break;

				// Topic
				case bbp_get_topic_post_type() :
					$breadcrumbs[] = '<a href="' . bbp_get_topic_permalink( $parent->ID ) . '">' . bbp_get_topic_title( $parent->ID ) . '</a>';
					break;

				// Reply (Note: not in most themes)
				case bbp_get_reply_post_type() :
					$breadcrumbs[] = '<a href="' . bbp_get_reply_permalink( $parent->ID ) . '">' . bbp_get_reply_title( $parent->ID ) . '</a>';
					break;

				// WordPress Post/Page/Other
				default :
					$breadcrumbs[] = '<a href="' . get_permalink( $parent->ID ) . '">' . get_the_title( $parent->ID ) . '</a>';
					break;
			}
		}

		// Add current page to breadcrumb
		if ( true == $current_page )
			$breadcrumbs[] = get_the_title();

		// Build the trail
		if ( !empty( $breadcrumbs ) )
			$trail = implode( ' ' . $sep . ' ', $breadcrumbs );
		else
			$trail = '';

		return apply_filters( 'bbp_get_breadcrumb', $trail );
	}

/** Topic Tags ***************************************************************/

/**
 * Output all of the allowed tags in HTML format with attributes.
 *
 * This is useful for displaying in the post area, which elements and
 * attributes are supported. As well as any plugins which want to display it.
 *
 * @since bbPress (r2780)
 *
 * @uses bbp_get_allowed_tags()
 */
function bbp_allowed_tags() {
	echo bbp_get_allowed_tags();
}
	/**
	 * Display all of the allowed tags in HTML format with attributes.
	 *
	 * This is useful for displaying in the post area, which elements and
	 * attributes are supported. As well as any plugins which want to display it.
	 *
	 * @since bbPress (r2780)
	 *
	 * @uses allowed_tags() To get the allowed tags
	 * @uses apply_filters() Calls 'bbp_allowed_tags' with the tags
	 * @return string HTML allowed tags entity encoded.
	 */
	function bbp_get_allowed_tags() {
		return apply_filters( 'bbp_get_allowed_tags', allowed_tags() );
	}

/** Errors & Messages *********************************************************/

/**
 * Display possible errors & messages inside a template file
 *
 * @since bbPress (r2688)
 *
 * @uses WP_Error bbPress::errors::get_error_codes() To get the error codes
 * @uses WP_Error bbPress::errors::get_error_data() To get the error data
 * @uses WP_Error bbPress::errors::get_error_messages() To get the error
 *                                                       messages
 * @uses is_wp_error() To check if it's a {@link WP_Error}
 */
function bbp_template_notices() {
	global $bbp;

	// Bail if no notices or errors
	if ( !isset( $bbp->errors ) || !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() )
		return;

	// Prevent debug notices
	$errors = $messages = array();

	// Loop through notices
	foreach ( $bbp->errors->get_error_codes() as $code ) {

		// Get notice severity
		$severity = $bbp->errors->get_error_data( $code );

		// Loop through notices and separate errors from messages
		foreach ( $bbp->errors->get_error_messages( $code ) as $error ) {
			if ( 'message' == $severity ) {
				$messages[] = $error;
			} else {
				$errors[]   = $error;
			}
		}
	}

	// Display errors first...
	if ( !empty( $errors ) ) : ?>

		<div class="bbp-template-notice error">
			<p>
				<?php echo implode( "</p>\n<p>", $errors ); ?>
			</p>
		</div>

	<?php endif;

	// ...and messages last
	if ( !empty( $messages ) ) : ?>

		<div class="bbp-template-notice">
			<p>
				<?php echo implode( "</p>\n<p>", $messages ); ?>
			</p>
		</div>

	<?php endif;
}

/** Login/logout/register/lost pass *******************************************/

/**
 * Output the logout link
 *
 * @since bbPress (r2827)
 *
 * @param string $redirect_to Redirect to url
 * @uses bbp_get_logout_link() To get the logout link
 */
function bbp_logout_link( $redirect_to = '' ) {
	echo bbp_get_logout_link( $redirect_to );
}
	/**
	 * Return the logout link
	 *
	 * @since bbPress (r2827)
	 *
	 * @param string $redirect_to Redirect to url
	 * @uses wp_logout_url() To get the logout url
	 * @uses apply_filters() Calls 'bbp_get_logout_link' with the logout link and
	 *                        redirect to url
	 * @return string The logout link
	 */
	function bbp_get_logout_link( $redirect_to = '' ) {
		return apply_filters( 'bbp_get_logout_link', '<a href="' . wp_logout_url( $redirect_to ) . '" class="button logout-link">' . __( 'Log Out', 'bbpress' ) . '</a>', $redirect_to );
	}

/** Theme compat **************************************************************/

/**
 * Gets the bbPress compatable theme used in the event the currently active
 * WordPress theme does not explicitly support bbPress. This can be filtered,
 * or set manually. Tricky theme authors can override the default and include
 * their own bbPress compatability layers for their themes.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @uses apply_filters()
 * @return string
 */
function bbp_get_theme_compat() {
	global $bbp;

	return apply_filters( 'bbp_get_theme_compat', $bbp->theme_compat );
}

/**
 * Sets the bbPress compatable theme used in the event the currently active
 * WordPress theme does not explicitly support bbPress. This can be filtered,
 * or set manually. Tricky theme authors can override the default and include
 * their own bbPress compatability layers for their themes.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @param string $theme Optional. Must be full absolute path to theme
 * @uses apply_filters()
 * @return string
 */
function bbp_set_theme_compat( $theme = '' ) {
	global $bbp;

	// Set theme to bundled bbp-twentyten if nothing is passed
	if ( empty( $theme ) && !empty( $bbp->themes_dir ) )
		$bbp->theme_compat = $bbp->themes_dir . '/bbp-twentyten';

	// Set to what is passed
	else
		$bbp->theme_compat = $theme;

	return apply_filters( 'bbp_get_theme_compat', $bbp->theme_compat );
}

/**
 * Possibly intercept the template being loaded
 *
 * Listens to the 'template_include' filter and waits for a bbPress post_type
 * to appear. If the current theme does not explicitly support bbPress, it
 * intercepts the page template and uses one served from the bbPress compatable
 * theme, set as the $bbp->theme_compat global.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @global WP_Query $post
 * @param string $template
 * @return string
 */
function bbp_template_include( $template ) {

	// Current theme does not support bbPress, so we need to do some heavy
	// lifting to see if a bbPress template is needed in the current context
	if ( !current_theme_supports( 'bbpress' ) ) {

		// Are we looking at a forum/topic/reply?
		switch ( get_post_type() ) {

			// Single Forum
			case bbp_get_forum_post_type() :
				$forum_id = bbp_get_forum_id( get_the_ID() );

			// Single Topic
			case bbp_get_topic_post_type() :
				$forum_id = bbp_get_topic_forum_id( get_the_ID() );

			// Single Reply
			case bbp_get_reply_post_type() :
				$forum_id = bbp_get_reply_forum_id( get_the_ID() );

				// Display template
				if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) || bbp_is_forum_private( $forum_id ) ) {
					global $wp_query;

					// Prevent comments form from appearing
					$wp_query->is_page   = false;
					$wp_query->is_single = false;

					// Add a filter on the_content late, which we will later remove
					add_filter( 'the_content', 'bbp_replace_the_content', 99999 );

					// Default to the page template
					$template = locate_template( 'page.php', false, false );

				// Display 404 page
				} elseif ( bbp_is_forum_hidden( $forum_id ) ) {
					bbp_set_404();
				}

				break;
		}
	}

	// Return $template
	return $template;
}

/**
 * Replaces the_content() if the post_type being displayed is one that would
 * normally be handled by bbPress, but proper single page templates do not
 * exist in the currently active theme.
 *
 * @since bbPress (r3034)
 *
 * @global bbPress $bbp
 * @global WP_Query $post
 * @param string $content
 * @return type
 */
function bbp_replace_the_content( $content = '' ) {

	// Current theme does not support bbPress, so we need to do some heavy
	// lifting to see if a bbPress template is needed in the current context
	if ( !current_theme_supports( 'bbpress' ) ) {

		// Use the $post global to check it's post_type
		global $bbp;

		// Remove the filter that was added in bbp_template_include()
		remove_filter( 'the_content', 'bbp_replace_the_content', 99999 );

		// Bail if shortcodes are unset somehow
		if ( empty( $bbp->shortcodes ) )
			return content;

		// Use shortcode API to display forums/topics/replies because they are
		// already output buffered and
		// Check the post_type
		switch ( get_post_type() ) {

			// Single Forum
			case bbp_get_forum_post_type() :
				$content = $bbp->shortcodes->display_forum( array( 'id' => get_the_ID() ) );
				break;

			// Single Topic
			case bbp_get_topic_post_type() :
				$content = $bbp->shortcodes->display_topic( array( 'id' => get_the_ID() ) );
				break;

			// Single Reply
			case bbp_get_reply_post_type() :

				break;
		}
	}

	// Return possibly filtered content
	return $content;
}

?>
