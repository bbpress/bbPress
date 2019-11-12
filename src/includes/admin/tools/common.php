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
 * Return the current admin repair tool page
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page() {
	return sanitize_key( $_GET['page'] );
}

/**
 * Return the current admin repair tool page ID
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page_id() {

	// Get the page
	$page = bbp_get_admin_repair_tool_page();

	// Maybe trim prefix off of page
	if ( ! empty( $page ) && ( 0 === strpos( $page, 'bbp-' ) ) ) {
		$page = str_replace( 'bbp-', '', $page );
	} else {
		$page = '';
	}

	return $page;
}

/**
 * Return a URL to the repair tool page
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param array $args
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page_url( $args = array() ) {

	// Parse arguments
	$r = wp_parse_args( $args, array(
		'page' => bbp_get_admin_repair_tool_page()
	) );

	return add_query_arg( $r, admin_url( 'tools.php' ) );
}

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
		$nonced = wp_nonce_url( bbp_get_admin_repair_tool_page_url( $args ), 'bbpress-do-counts' );

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

	// Parse list of checked repairs
	$checked = ! empty( $_GET['checked'] )
		? array_map( 'sanitize_key', $_GET['checked'] )
		: array();

	// Flush all caches before running tools
	wp_cache_flush();

	// Get the list
	$list = bbp_get_admin_repair_tools();

	// Stores messages
	$messages = array();

	// Run through checked repair tools
	if ( count( $checked ) ) {
		foreach ( $checked as $item_id ) {
			if ( isset( $list[ $item_id ] ) && is_callable( $list[ $item_id ]['callback'] ) ) {
				$messages[] = call_user_func( $list[ $item_id ]['callback'] );

				// Remove from pending
				bbp_remove_pending_upgrade( $item_id );
			}
		}
	}

	// Feedback
	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			bbp_admin_tools_feedback( $message[1] );
		}
	}

	// Flush all caches after running tools
	wp_cache_flush();
}

/**
 * Get the array of available repair tools
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $type repair|upgrade The type of tools to get. Default empty for all tools.
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

	// Default return value
	$retval = array();

	// Get tools
	$tools  = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

	// Loop through tools
	if ( ! empty( $tools ) ) {
		$plucked = wp_list_pluck( $tools, 'components' );

		// Loop through components
		if ( count( $plucked ) ) {
			foreach ( $plucked as $components ) {
				foreach ( $components as $component ) {

					// Skip if already in array
					if ( in_array( $component, $retval, true ) ) {
						continue;
					}

					// Add component to the array
					$retval[] = $component;
				}
			}
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
	$components = bbp_get_admin_repair_tool_registered_components();

	// Bail if no components
	if ( empty( $components ) ) {
		return;
	} ?>

	<label class="screen-reader-text" for="components"><?php esc_html_e( 'Filter by Component', 'bbpress' ); ?></label>
	<select name="components" id="components" class="postform">
		<option value="" <?php selected( $selected, false ); ?>><?php esc_html_e( 'All Components', 'bbpress' ); ?></option>

		<?php foreach ( $components as $component ) : ?>

			<option class="level-0" value="<?php echo esc_attr( $component ); ?>" <?php selected( $selected, $component ); ?>><?php echo esc_html( bbp_admin_repair_tool_translate_component( $component ) ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php
}

/**
 * Return array of versions from the array of registered tools
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return array
 */
function bbp_get_admin_repair_tool_registered_versions() {

	// Default return value
	$retval = array();

	// Get tools
	$tools  = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

	// Loop through tools
	if ( ! empty( $tools ) ) {
		$plucked = wp_list_pluck( $tools, 'version' );

		// Loop through components
		if ( count( $plucked ) ) {
			foreach ( $plucked as $versions ) {

				// Skip if empty
				if ( empty( $versions ) ) {
					continue;

				// Cast to array if string
				} elseif ( is_string( $versions ) ) {
					$versions = (array) $versions;
				}

				// Loop through versions
				foreach ( $versions as $version ) {

					// Skip if already in array
					if ( in_array( $version, $retval, true ) ) {
						continue;
					}

					// Add component to the array
					$retval[] = $version;
				}
			}
		}
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_registered_versions', $retval );
}

/**
 * Output a select drop-down of versions to filter by
 *
 * @since 2.5.0 bbPress (r6894)
 */
function bbp_admin_repair_list_versions_filter() {

	// Sanitize component value, if exists
	$selected = ! empty( $_GET['version'] )
		? sanitize_text_field( $_GET['version'] )
		: '';

	// Get registered components
	$versions = bbp_get_admin_repair_tool_registered_versions();

	// Bail if no components
	if ( empty( $versions ) ) {
		return;
	} ?>

	<label class="screen-reader-text" for="version"><?php esc_html_e( 'Filter by Version', 'bbpress' ); ?></label>
	<select name="version" id="version" class="postform">
		<option value="" <?php selected( $selected, false ); ?>><?php esc_html_e( 'All Versions', 'bbpress' ); ?></option>

		<?php foreach ( $versions as $version ) : ?>

			<option class="level-0" value="<?php echo esc_attr( $version ); ?>" <?php selected( $selected, $version ); ?>><?php echo esc_html( bbp_admin_repair_tool_translate_version( $version ) ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php
}

/** Translations **************************************************************/

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
 * Maybe translate a repair tool overhead name
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param string $version
 * @return string
 */
function bbp_admin_repair_tool_translate_version( $version = '' ) {

	// Get the version
	switch ( $version ) {
		case '2.5' :
		case '2.5.0' :
			$name = esc_html__( '2.5.0', 'bbpress' );
			break;
		case '2.6' :
		case '2.6.0' :
			$name = esc_html__( '2.6.0', 'bbpress' );
			break;
		default :
			$name = sanitize_text_field( $version );
			break;
	}

	return $name;
}

/** Lists *********************************************************************/

/**
 * Get the array of the repairs to show in a list table.
 *
 * Uses known filters to reduce the registered results down to the most finite
 * set of tools.
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @return array Repair list of options
 */
function bbp_admin_repair_list( $type = 'repair' ) {

	// Define empty array
	$repair_list = array();

	// Get the available tools
	$list = bbp_get_admin_repair_tools( $type );

	// Get pending upgrades
	$pending = bbp_get_pending_upgrades();

	// Search
	$search = ! empty( $_GET['s'] )
		? stripslashes( $_GET['s'] )
		: '';

	// Status
	$status = ! empty( $_GET['status'] )
		? sanitize_key( $_GET['status'] )
		: '';

	// Overhead
	$overhead  = ! empty( $_GET['overhead'] )
		? sanitize_key( $_GET['overhead'] )
		: '';

	// Component
	$component = ! empty( $_GET['components'] )
		? sanitize_key( $_GET['components'] )
		: '';

	// Version
	$version = ! empty( $_GET['version'] )
		? sanitize_text_field( $_GET['version'] )
		: '';

	// Orderby
	$orderby = ! empty( $_GET['orderby'] )
		? sanitize_key( $_GET['orderby'] )
		: 'priority';

	// Order
	$order = ! empty( $_GET['order'] ) && in_array( strtolower( $_GET['order'] ), array( 'asc', 'desc' ), true )
		? strtolower( $_GET['order'] )
		: 'asc';

	// Overhead filter
	if ( ! empty( $overhead ) ) {
		$list = wp_list_filter( $list, array( 'overhead' => $overhead ) );
	}

	if ( count( $list ) ) {

		// Loop through and key by priority for sorting
		foreach ( $list as $id => $tool ) {

			// Status filter
			if ( ! empty( $status ) && ( 'pending' === $status ) ) {
				if ( ! in_array( $id, (array) $pending, true ) ) {
					continue;
				}
			}

			// Component filter
			if ( ! empty( $component ) ) {
				if ( ! in_array( $component, (array) $tool['components'], true ) ) {
					continue;
				}
			}

			// Version filter
			if ( ! empty( $version ) ) {
				if ( ! in_array( $version, (array) $tool['version'], true ) ) {
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
				'priority'    => $tool['priority'],
				'description' => $tool['description'],
				'callback'    => $tool['callback'],
				'overhead'    => $tool['overhead'],
				'version'     => $tool['version'],
				'components'  => $tool['components']
			);
		}
	}

	// Sort
	$retval = wp_list_sort( $repair_list, $orderby, $order, true );

	// Filter & return
	return (array) apply_filters( 'bbp_repair_list', $retval );
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
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define links array
	$links      = array();
	$components = ! empty( $item['components'] )
		? (array) $item['components']
		: array();

	// Loop through tool components and build links
	if ( count( $components ) ) {
		foreach ( $components as $component ) {
			$args       = array( 'components' => $component );
			$filter_url = add_query_arg( $args, $tools_url );
			$name       = bbp_admin_repair_tool_translate_component( $component );
			$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
		}

	// No components, so return a dash
	} else {
		$links[] = '&mdash;';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_components', $links, $item );
}

/**
 * Get filter links for versions for a specific admin repair tool
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_version( $item = array() ) {

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define links array
	$links    = array();
	$versions = ! empty( $item['version'] )
		? (array) $item['version']
		: array();

	// Loop through tool versions and build links
	if ( count( $versions ) ) {
		foreach ( $versions as $version ) {
			$args       = array( 'version' => $version );
			$filter_url = add_query_arg( $args, $tools_url );
			$name       = bbp_admin_repair_tool_translate_version( $version );
			$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
		}

	// No versions, so return a dash
	} else {
		$links[] = '&mdash;';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_version', $links, $item );
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
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define links array
	$links     = array();
	$overheads = ! empty( $item['overhead'] )
		? (array) $item['overhead']
		: array();

	// Loop through tool overhead and build links
	if ( count( $overheads ) ) {
		foreach ( $overheads as $overhead ) {
			$args       = array( 'overhead' => $overhead );
			$filter_url = add_query_arg( $args, $tools_url );
			$name       = bbp_admin_repair_tool_translate_overhead( $overhead );
			$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
		}

	// No overhead, so return a single dash
	} else {
		$links[] = '&mdash;';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_overhead', $links, $item );
}

/** Overhead ******************************************************************/

/**
 * Output filter links for overheads for a specific admin repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_overhead_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_overhead_filters( $args );
}

/**
 * Get filter links for overheads for a specific admin repair tool
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

	// Count the tools
	$tools = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define arrays
	$overheads = $links = array();

	// Loop through tools and count overheads
	if ( count( $tools ) ) {
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
	}

	// Get the current overhead, if any
	$selected = ! empty( $_GET['overhead'] )
		? sanitize_key( $_GET['overhead'] )
		: '';

	// Create the "All" link
	$current = empty( $selected ) ? 'current' : '';
	$links[] = $r['link_before'] . '<a href="' . esc_url( $tools_url ) . '" class="' . esc_attr( $current ) . '">' . sprintf( esc_html__( 'All %s', 'bbpress' ), $r['count_before'] . count( $tools ) . $r['count_after'] ) . '</a>' . $r['link_after'];

	// Loop through overheads and created links
	if ( count( $overheads ) ) {

		// Sort
		ksort( $overheads );

		// Loop through overheads and build filter
		foreach ( $overheads as $overhead => $count ) {

			// Build the filter URL
			$key        = sanitize_key( $overhead );
			$args       = array( 'overhead' => $key );
			$filter_url = add_query_arg( $args, $tools_url );

			// Figure out separator and active class
			$current  = ( $selected === $key )
				? 'current'
				: '';

			// Counts to show
			if ( ! empty( $count ) ) {
				$overhead_count = $r['count_before'] . $count . $r['count_after'];
			}

			// Build the link
			$links[] = $r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $current ) . '">' . bbp_admin_repair_tool_translate_overhead( $overhead ) . $overhead_count . '</a>' . $r['link_after'];
		}
	}

	// Surround output with before & after strings
	$output = $r['before'] . implode( $r['sep'], $links ) . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_overhead_filters', $output, $r, $args );
}

/** Pending ******************************************************************/

/**
 * Output filter links for statuses
 *
 * @since 2.6.0 bbPress (r6925)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_status_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_status_filters( $args );
}

/**
 * Get filter links for statuses
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_status_filters( $args = array() ) {

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
	), 'get_admin_repair_tool_status_filters' );

	/**
	 * Necessary for backwards compatibility
	 * @see https://bbpress.trac.wordpress.org/ticket/2900
	 */
	if ( ! empty( $r['separator'] ) ) {
		$r['sep'] = $r['separator'];
	}

	// Get the type of tool
	$type = bbp_get_admin_repair_tool_page_id();

	// Count the tools
	$tools = bbp_get_admin_repair_tools( $type );

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Get pending upgrades
	$pending = bbp_get_pending_upgrades( $type );

	// Get the current status, if any
	$selected = ! empty( $_GET['status'] )
		? sanitize_key( $_GET['status'] )
		: '';

	// Nothing is current?
	$all_current = empty( $selected )
		? 'current'
		: '';

	// Pending is current?
	$pending_current = ( 'pending' === $selected )
		? 'current'
		: '';

	// Sort
	ksort( $pending );

	// Build the filter URL
	$filter_url = add_query_arg( array(
		'status' => 'pending'
	), $tools_url );

	// Count HTML
	$all_count     = $r['count_before'] . count( $tools   ) . $r['count_after'];
	$pending_count = $r['count_before'] . count( $pending ) . $r['count_after'];

	// Define links
	$links = array(
		$r['link_before'] . '<a href="' . esc_url( $tools_url  ) . '" class="' . esc_attr( $all_current     ) . '">' . sprintf( esc_html__( 'All %s',     'bbpress' ), $all_count     ) . '</a>' . $r['link_after'],
		$r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $pending_current ) . '">' . sprintf( esc_html__( 'Pending %s', 'bbpress' ), $pending_count ) . '</a>' . $r['link_after']
	);

	// Surround output with before & after strings
	$output = $r['before'] . implode( $r['sep'], $links ) . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_status_filters', $output, $r, $args );
}
