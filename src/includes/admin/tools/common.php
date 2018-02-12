<?php

/**
 * bbPress Admin Tools Common
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Output the URL to run a specific repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 */
function bbp_admin_repair_tool_run_url( $component = array() ) {
	echo esc_url( bbp_get_admin_repair_tool_run_url( $component ) );
}

	/**
	 * Return the URL to run a specific repair tool
	 *
	 * @since 2.6.0 bbPress (r5885)
	 *
	 * @param string $component
	 */
	function bbp_get_admin_repair_tool_run_url( $component = array() ) {

		// Page
		$page = ( 'repair' === $component['type'] )
			? 'bbp-repair'
			: 'bbp-upgrade';

		// Arguments
		$args = array(
			'page'    => $page,
			'action'  => 'run',
			'checked' => array( $component['id'] )
		);

		// Url
		$nonced = wp_nonce_url( add_query_arg( $args, admin_url( 'tools.php' ) ), 'bbpress-do-counts' );

		// Filter & return
		return apply_filters( 'bbp_get_admin_repair_tool_run_url', $nonced, $component );
	}

/**
 * Assemble the admin notices
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @param string|WP_Error $message        A message to be displayed or {@link WP_Error}
 * @param string          $class          Optional. A class to be added to the message div
 * @param bool            $is_dismissible Optional. True to dismiss, false to persist
 *
 * @return string The message HTML
 */
function bbp_admin_tools_feedback( $message, $class = false, $is_dismissible = true ) {
	return bbp_admin()->add_notice( $message, $class, $is_dismissible );
}

/**
 * Handle the processing and feedback of the admin tools page
 *
 * @since 2.0.0 bbPress (r2613)
 *
 */
function bbp_admin_repair_handler() {

	// Bail if not an actionable request
	if ( ! bbp_is_get_request() ) {
		return;
	}

	// Get the current action or bail
	if ( ! empty( $_GET['action'] ) ) {
		$action = sanitize_key( $_GET['action'] );
	} elseif ( ! empty( $_GET['action2'] ) ) {
		$action = sanitize_key( $_GET['action2'] );
	} else {
		return;
	}

	// Bail if not running an action
	if ( 'run' !== $action ) {
		return;
	}

	check_admin_referer( 'bbpress-do-counts' );

	// Stores messages
	$messages = array();

	// Kill all the caches, because we don't know what's where anymore
	wp_cache_flush();

	// Get the list
	$list = bbp_get_admin_repair_tools();

	// Run through checked repair tools
	if ( ! empty( $_GET['checked'] ) ) {
		foreach ( $_GET['checked'] as $item_id ) {
			if ( isset( $list[ $item_id ] ) && is_callable( $list[ $item_id ]['callback'] ) ) {
				$messages[] = call_user_func( $list[ $item_id ]['callback'] );
			}
		}
	}

	// Feedback
	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			bbp_admin_tools_feedback( $message[1] );
		}
	}

	// @todo Redirect away from here
}

/**
 * Get the array of available repair tools
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $type repair|upgrade The type of tools to get. Default to 'repair'
 * @return array
 */
function bbp_get_admin_repair_tools( $type = '' ) {

	// Get tools array
	$tools = ! empty( bbp_admin()->tools )
		? bbp_admin()->tools
		: array();

	// Maybe limit to type (otherwise return all tools)
	if ( ! empty( $type ) ) {
		$tools = wp_list_filter( bbp_admin()->tools, array( 'type' => $type ) );
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tools', $tools, $type );
}

/**
 * Return array of components from the array of registered tools
 *
 * @since 2.5.0 bbPress (r5885)
 *
 * @return array
 */
function bbp_get_admin_repair_tool_registered_components() {
	$tools   = bbp_get_admin_repair_tools( str_replace( 'bbp-', '', sanitize_key( $_GET['page'] ) ) );
	$plucked = wp_list_pluck( $tools, 'components' );
	$retval  = array();

	foreach ( $plucked as $components ) {
		foreach ( $components as $component ) {
			if ( in_array( $component, $retval, true ) ) {
				continue;
			}
			$retval[] = $component;
		}
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_registered_components', $retval );
}

/**
 * Output the repair list search form
 *
 * @since 2.6.0 bbPress (r5885)
 */
function bbp_admin_repair_list_search_form() {
	?>

	<p class="search-box">
		<label class="screen-reader-text" for="bbp-repair-search-input"><?php esc_html_e( 'Search Tools:', 'bbpress' ); ?></label>
		<input type="search" id="bbp-repair-search-input" name="s" value="<?php _admin_search_query(); ?>">
		<input type="submit" id="search-submit" class="button" value="<?php esc_html_e( 'Search Tools', 'bbpress' ); ?>">
	</p>

	<?php
}

/**
 * Output a select drop-down of components to filter by
 *
 * @since 2.5.0 bbPress (r5885)
 */
function bbp_admin_repair_list_components_filter() {

	// Sanitize component value, if exists
	$selected = ! empty( $_GET['components'] )
		? sanitize_key( $_GET['components'] )
		: '';

	// Get registered components
	$components = bbp_get_admin_repair_tool_registered_components(); ?>

	<label class="screen-reader-text" for="cat"><?php esc_html_e( 'Filter by Component', 'bbpress' ); ?></label>
	<select name="components" id="components" class="postform">
		<option value="" <?php selected( $selected, false ); ?>><?php esc_html_e( 'All Components', 'bbpress' ); ?></option>

		<?php foreach ( $components as $component ) : ?>

			<option class="level-0" value="<?php echo esc_attr( $component ); ?>" <?php selected( $selected, $component ); ?>><?php echo esc_html( bbp_admin_repair_tool_translate_component( $component ) ); ?></option>

		<?php endforeach; ?>

	</select>
	<input type="submit" name="filter_action" id="components-submit" class="button" value="<?php esc_html_e( 'Filter', 'bbpress' ); ?>">

	<?php
}

/**
 * Maybe translate a repair tool overhead name
 *
 * @since 2.6.0 bbPress (r6177)
 *
 * @param string $overhead
 * @return string
 */
function bbp_admin_repair_tool_translate_overhead( $overhead = '' ) {

	// Get the name of the component
	switch ( $overhead ) {
		case 'low' :
			$name = esc_html__( 'Low', 'bbpress' );
			break;
		case 'medium' :
			$name = esc_html__( 'Medium', 'bbpress' );
			break;
		case 'high' :
			$name = esc_html__( 'High', 'bbpress' );
			break;
		default :
			$name = ucwords( $overhead );
			break;
	}

	return $name;
}

/**
 * Maybe translate a repair tool component name
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 * @return string
 */
function bbp_admin_repair_tool_translate_component( $component = '' ) {

	// Get the name of the component
	switch ( $component ) {
		case 'bbp_user' :
			$name = esc_html__( 'Users', 'bbpress' );
			break;
		case bbp_get_forum_post_type() :
			$name = esc_html__( 'Forums', 'bbpress' );
			break;
		case bbp_get_topic_post_type() :
			$name = esc_html__( 'Topics', 'bbpress' );
			break;
		case bbp_get_reply_post_type() :
			$name = esc_html__( 'Replies', 'bbpress' );
			break;
		case bbp_get_topic_tag_tax_id() :
			$name = esc_html__( 'Topic Tags', 'bbpress' );
			break;
		case bbp_get_user_rewrite_id() :
			$name = esc_html__( 'Users', 'bbpress' );
			break;
		case bbp_get_user_favorites_rewrite_id() :
			$name = esc_html__( 'Favorites', 'bbpress' );
			break;
		case bbp_get_user_subscriptions_rewrite_id() :
			$name = esc_html__( 'Subscriptions', 'bbpress' );
			break;
		case bbp_get_user_engagements_rewrite_id() :
			$name = esc_html__( 'Engagements', 'bbpress' );
			break;
		default :
			$name = ucwords( $component );
			break;
	}

	return $name;
}

/**
 * Get the array of the repair list
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @return array Repair list of options
 */
function bbp_admin_repair_list( $type = 'repair' ) {

	// Define empty array
	$repair_list = array();

	// Get the available tools
	$list      = bbp_get_admin_repair_tools( $type );
	$search    = ! empty( $_GET['s']          ) ? stripslashes( $_GET['s']          ) : '';
	$overhead  = ! empty( $_GET['overhead']   ) ? sanitize_key( $_GET['overhead']   ) : '';
	$component = ! empty( $_GET['components'] ) ? sanitize_key( $_GET['components'] ) : '';

	// Overhead filter
	if ( ! empty( $overhead ) ) {
		$list = wp_list_filter( $list, array( 'overhead' => $overhead ) );
	}

	// Loop through and key by priority for sorting
	foreach ( $list as $id => $tool ) {

		// Component filter
		if ( ! empty( $component ) ) {
			if ( ! in_array( $component, $tool['components'], true ) ) {
				continue;
			}
		}

		// Search
		if ( ! empty( $search ) ) {
			if ( ! strstr( strtolower( $tool['title'] ), strtolower( $search ) ) ) {
				continue;
			}
		}

		// Add to repair list
		$repair_list[ $tool['priority'] ] = array(
			'id'          => sanitize_key( $id ),
			'type'        => $tool['type'],
			'title'       => $tool['title'],
			'description' => $tool['description'],
			'callback'    => $tool['callback'],
			'overhead'    => $tool['overhead'],
			'components'  => $tool['components'],
		);
	}

	// Sort
	ksort( $repair_list );

	// Filter & return
	return (array) apply_filters( 'bbp_repair_list', $repair_list );
}

/**
 * Get filter links for components for a specific admin repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_components( $item = array() ) {

	// Get the tools URL
	$tools_url = add_query_arg( array( 'page' => sanitize_key( $_GET['page'] ) ), admin_url( 'tools.php' ) );

	// Define links array
	$links = array();

	// Loop through tool components and build links
	foreach ( $item['components'] as $component ) {
		$args       = array( 'components' => $component );
		$filter_url = add_query_arg( $args, $tools_url );
		$name       = bbp_admin_repair_tool_translate_component( $component );
		$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_components', $links, $item );
}

/**
 * Output filter links for components for a specific admin repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_overhead_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_overhead_filters( $args );
}

/**
 * Get filter links for components for a specific admin repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_overhead_filters( $args = array() ) {

	// Parse args
	$r = bbp_parse_args( $args, array(
		'before'       => '<ul class="subsubsub">',
		'after'        => '</ul>',
		'link_before'  => '<li>',
		'link_after'   => '</li>',
		'count_before' => ' <span class="count">(',
		'count_after'  => ')</span>',
		'sep'          => ' | ',

		// Retired, use 'sep' instead
		'separator'    => false
	), 'get_admin_repair_tool_overhead_filters' );

	/**
	 * Necessary for backwards compatibility
	 * @see https://bbpress.trac.wordpress.org/ticket/2900
	 */
	if ( ! empty( $r['separator'] ) ) {
		$r['sep'] = $r['separator'];
	}

	// Get page
	$page = sanitize_key( $_GET['page'] );

	// Count the tools
	$tools = bbp_get_admin_repair_tools( str_replace( 'bbp-', '', $page ) );

	// Get the tools URL
	$tools_url = add_query_arg( array( 'page' => $page ), admin_url( 'tools.php' ) );

	// Define arrays
	$overheads = $links = array();

	// Loop through tools and count overheads
	foreach ( $tools as $tool ) {

		// Get the overhead level
		$overhead = $tool['overhead'];

		// Set an empty count
		if ( empty( $overheads[ $overhead ] ) ) {
			$overheads[ $overhead ] = 0;
		}

		// Bump the overhead count
		$overheads[ $overhead ]++;
	}

	// Create the "All" link
	$current = empty( $_GET['overhead'] ) ? 'current' : '';
	$links[] = $r['link_before'] . '<a href="' . esc_url( $tools_url ) . '" class="' . esc_attr( $current ) . '">' . sprintf( esc_html__( 'All %s', 'bbpress' ), $r['count_before'] . count( $tools ) . $r['count_after'] ) . '</a>' . $r['link_after'];

	// Sort
	ksort( $overheads );

	// Loop through overheads and build filter
	foreach ( $overheads as $overhead => $count ) {

		// Build the filter URL
		$key        = sanitize_key( $overhead );
		$args       = array( 'overhead' => $key );
		$filter_url = add_query_arg( $args, $tools_url );

		// Figure out separator and active class
		$current  = ! empty( $_GET['overhead'] ) && ( sanitize_key( $_GET['overhead'] ) === $key )
			? 'current'
			: '';

		// Counts to show
		if ( ! empty( $count ) ) {
			$overhead_count = $r['count_before'] . $count . $r['count_after'];
		}

		// Build the link
		$links[] = $r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $current ) . '">' . bbp_admin_repair_tool_translate_overhead( $overhead ) . $overhead_count . '</a>' . $r['link_after'];
	}

	// Surround output with before & after strings
	$output = $r['before'] . implode( $r['sep'], $links ) . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_components', $output, $r, $args );
}

/**
 * Get filter links for overhead for a specific admin repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_overhead( $item = array() ) {

	// Get the tools URL
	$tools_url = add_query_arg( array( 'page' => sanitize_key( $_GET['page'] ) ), admin_url( 'tools.php' ) );

	// Define links array
	$links     = array();
	$overheads = array( $item['overhead'] );

	// Loop through tool overhead and build links
	foreach ( $overheads as $overhead ) {
		$args       = array( 'overhead' => $overhead );
		$filter_url = add_query_arg( $args, $tools_url );
		$name       = bbp_admin_repair_tool_translate_overhead( $overhead );
		$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_overhead', $links, $item );
}
