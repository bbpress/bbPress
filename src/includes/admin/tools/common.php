<?php

/**
 * bbPress Admin Tools Common.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the current admin repair tool page.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page() {
	return sanitize_key( $_GET['page'] );
}

/**
 * Return the current admin repair tool page ID.
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
 * Return a URL to the repair tool page.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param array $args
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page_url( $args = array() ) {

	// Parse arguments
	$r = wp_parse_args(
		$args,
		array(
			'page' => bbp_get_admin_repair_tool_page()
		)
	);

	return add_query_arg( $r, admin_url( 'tools.php' ) );
}

/**
 * Output the URL to run a specific repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 */
function bbp_admin_repair_tool_run_url( $component = array() ) {
	echo esc_url( bbp_get_admin_repair_tool_run_url( $component ) );
}

/**
 * Return the URL to run a specific repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 */
function bbp_get_admin_repair_tool_run_url( $component = array() ) {
	return bbp_get_admin_repair_tool_action_url( $component, 'run' );
}

/**
 * Output the URL for a specific repair tool action.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array  $component Repair tool registration.
 * @param string $action    Repair tool action.
 */
function bbp_admin_repair_tool_action_url( $component = array(), $action = 'run' ) {
	echo esc_url( bbp_get_admin_repair_tool_action_url( $component, $action ) );
}

/**
 * Return the URL for a specific repair tool action.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array  $component Repair tool registration.
 * @param string $action    Repair tool action.
 * @return string
 */
function bbp_get_admin_repair_tool_action_url( $component = array(), $action = 'run' ) {

	// Page
	$page = ( 'repair' === $component['type'] )
		? 'bbp-repair'
		: 'bbp-upgrade';

	// Arguments
	$args = array(
		'page'    => $page,
		'action'  => sanitize_key( $action ),
		'checked' => array( $component['id'] )
	);

	// Url
	$nonced = wp_nonce_url( bbp_get_admin_repair_tool_page_url( $args ), 'bbpress-do-counts' );

	/**
	 * Filters an admin repair tool action URL.
	 *
	 * @since 2.7.0 bbPress (r8218)
	 *
	 * @param string $nonced   Nonced action URL.
	 * @param array  $component Repair tool registration.
	 * @param string $action    Repair tool action.
	 */
	$url = apply_filters( 'bbp_get_admin_repair_tool_action_url', $nonced, $component, $action );

	// Preserve the original filter for Run actions
	if ( 'run' === $action ) {
		$url = apply_filters( 'bbp_get_admin_repair_tool_run_url', $url, $component );
	}

	return $url;
}

/**
 * Return the action-keyed callbacks for an admin repair tool.
 *
 * The legacy callback value is treated as the run callback when an explicit
 * action-keyed run callback is not available.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tool Repair tool registration.
 * @return array
 */
function bbp_get_admin_repair_tool_callbacks( $tool = array() ) {
	$callbacks = ! empty( $tool['callbacks'] )
		? (array) $tool['callbacks']
		: array();
	$registered_callback = isset( $tool['_registered_callback'] )
		? $tool['_registered_callback']
		: false;
	$legacy_override = ( false !== $registered_callback )
		&& array_key_exists( 'callback', $tool )
		&& ( $registered_callback !== $tool['callback'] );
	$callbacks_override = ( false !== $registered_callback )
		&& array_key_exists( 'callbacks', $tool )
		&& ( ! array_key_exists( 'run', $callbacks ) || ( $registered_callback !== $callbacks['run'] ) );

	// A legacy override cannot safely inherit action-specific callbacks
	if ( $legacy_override && ! $callbacks_override ) {
		$callbacks = ! empty( $tool['callback'] )
			? array( 'run' => $tool['callback'] )
			: array();

	// Explicit action-keyed callbacks win when both public forms are changed
	} elseif ( empty( $callbacks['run'] ) && ! $callbacks_override && ! empty( $tool['callback'] ) ) {
		$callbacks['run'] = $tool['callback'];
	}

	$retval = array();
	foreach ( $callbacks as $action => $callback ) {
		$action = sanitize_key( $action );

		if ( ! empty( $action ) && ! empty( $callback ) ) {
			$retval[ $action ] = $callback;
		}
	}

	return $retval;
}

/**
 * Return the callback for a specific admin repair tool action.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array  $tool   Repair tool registration.
 * @param string $action Repair tool action.
 * @return callable|false
 */
function bbp_get_admin_repair_tool_callback( $tool = array(), $action = 'run' ) {
	$callbacks = bbp_get_admin_repair_tool_callbacks( $tool );
	$action    = sanitize_key( $action );

	return isset( $callbacks[ $action ] )
		? $callbacks[ $action ]
		: false;
}

/**
 * Return the actions registered for one or more admin repair tools.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tools Repair tool registrations.
 * @return array
 */
function bbp_get_admin_repair_tool_actions( $tools = array() ) {
	$actions = array();

	foreach ( $tools as $tool ) {
		$actions = array_merge( $actions, array_keys( bbp_get_admin_repair_tool_callbacks( $tool ) ) );
	}

	return array_values( array_unique( $actions ) );
}

/**
 * Return a human-readable admin repair tool action label.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param string $action Repair tool action.
 * @return string
 */
function bbp_admin_repair_tool_action_label( $action = '' ) {
	switch ( $action ) {
		case 'run':
			$label = esc_html__( 'Run', 'bbpress' );
			break;

		case 'dry-run':
			$label = esc_html__( 'Dry Run', 'bbpress' );
			break;

		default:
			$label = ucwords( str_replace( '-', ' ', sanitize_key( $action ) ) );
			break;
	}

	/**
	 * Filters a repair tool action label.
	 *
	 * @since 2.7.0 bbPress (r8218)
	 *
	 * @param string $label  Human-readable action label.
	 * @param string $action Repair tool action.
	 */
	return apply_filters( 'bbp_admin_repair_tool_action_label', $label, $action );
}

/**
 * Output bulk actions registered by the available admin repair tools.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tools Repair tool registrations.
 */
function bbp_admin_repair_tool_bulk_actions( $tools = array() ) {
	foreach ( bbp_get_admin_repair_tool_actions( $tools ) as $action ) {
		printf(
			'<option value="%1$s" class="hide-if-no-js">%2$s</option>',
			esc_attr( $action ),
			esc_html( bbp_admin_repair_tool_action_label( $action ) )
		);
	}
}

/**
 * Output the actions registered by an admin repair tool.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tool Repair tool registration.
 */
function bbp_admin_repair_tool_row_actions( $tool = array() ) {
	$links = array();

	foreach ( array_keys( bbp_get_admin_repair_tool_callbacks( $tool ) ) as $action ) {
		$label = bbp_admin_repair_tool_action_label( $action );

		switch ( $action ) {
			case 'run':
				/* translators: %s: Repair tool title */
				$aria_label = sprintf( esc_attr__( 'Run %s', 'bbpress' ), $tool['title'] );
				$link_id    = $tool['id'];
				break;

			case 'dry-run':
				/* translators: %s: Repair tool title */
				$aria_label = sprintf( esc_attr__( 'Dry Run %s', 'bbpress' ), $tool['title'] );
				$link_id    = $tool['id'] . '-dry-run';
				break;

			default:
				/* translators: %1$s: Action label; %2$s: Repair tool title */
				$aria_label = sprintf( esc_attr__( '%1$s %2$s', 'bbpress' ), $label, $tool['title'] );
				$link_id    = $tool['id'] . '-' . $action;
				break;
		}

		$links[] = sprintf(
			'<span class="%1$s"><a href="%2$s" id="%3$s" aria-label="%4$s">%5$s</a></span>',
			esc_attr( sanitize_html_class( $action ) ),
			esc_url( bbp_get_admin_repair_tool_action_url( $tool, $action ) ),
			esc_attr( $link_id ),
			esc_attr( $aria_label ),
			esc_html( $label )
		);
	}

	echo implode( ' | ', $links );
}

/**
 * Assemble the admin notices.
 *
 * @since 2.1.0 bbPress (r3884)
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
 * Handle the processing and feedback of the admin tools page.
 *
 * @since 2.1.0 bbPress (r3884)
 *
 */
function bbp_admin_repair_handler() {

	// Bail if not an actionable request
	if ( ! bbp_is_get_request() ) {
		return;
	}

	// Filtering and searching must never run a selected tool action
	if ( ! empty( $_GET['filter_action'] ) || ! empty( $_GET['search_action'] ) ) {
		return;
	}

	// Bail on an ambiguous bulk-action request
	if ( ! empty( $_GET['doaction'] ) && ! empty( $_GET['doaction2'] ) ) {
		return;
	}

	// Get the action belonging to the clicked button, or a row-action URL
	if ( ! empty( $_GET['doaction2'] ) && ! empty( $_GET['action2'] ) ) {
		$action = sanitize_key( $_GET['action2'] );
	} elseif ( ! empty( $_GET['doaction'] ) && ! empty( $_GET['action'] ) ) {
		$action = sanitize_key( $_GET['action'] );
	} elseif ( empty( $_GET['doaction'] ) && empty( $_GET['doaction2'] ) && ! empty( $_GET['action'] ) ) {
		$action = sanitize_key( $_GET['action'] );
	} elseif ( empty( $_GET['doaction'] ) && empty( $_GET['doaction2'] ) && ! empty( $_GET['action2'] ) ) {
		$action = sanitize_key( $_GET['action2'] );
	} else {
		return;
	}

	// Bail if no tools were selected
	if ( empty( $_GET['checked'] ) ) {
		return;
	}

	check_admin_referer( 'bbpress-do-counts' );

	// Parse list of checked repairs
	$checked = array_map( 'sanitize_key', (array) $_GET['checked'] );

	// Flush all caches before running tools
	if ( 'run' === $action ) {
		wp_cache_flush();
	}

	// Get the list
	$list = bbp_get_admin_repair_tools();

	// Stores messages
	$messages = array();

	// Run through checked repair tools
	if ( count( $checked ) ) {
		foreach ( $checked as $item_id ) {
			if ( ! isset( $list[ $item_id ] ) ) {
				continue;
			}

			$tool              = $list[ $item_id ];
			$callbacks         = bbp_get_admin_repair_tool_callbacks( $tool );
			$tool['id']        = $item_id;
			$tool['callback']  = isset( $callbacks['run'] ) ? $callbacks['run'] : false;
			$tool['callbacks'] = $callbacks;
			$callback          = isset( $callbacks[ $action ] ) ? $callbacks[ $action ] : false;
			unset( $tool['_registered_callback'] );

			if ( ! is_callable( $callback ) ) {
				/* translators: %1$s: Repair tool title; %2$s: Action label */
				$message    = esc_html__( '%1$s does not support the %2$s action.', 'bbpress' );
				$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
				continue;
			}

			if ( 'run' === $action ) {
				$result = call_user_func( $callback );

				// Remove from pending
				bbp_remove_pending_upgrade( $item_id );
			} else {
				$result = call_user_func(
					$callback,
					array(
						'action' => $action,
						'tool'   => $tool
					)
				);
			}

			if ( is_array( $result ) && isset( $result[1] ) ) {
				$messages[] = $result;
			} else {
				/* translators: %1$s: Repair tool title; %2$s: Action label */
				$message    = esc_html__( '%1$s did not return a valid result for the %2$s action.', 'bbpress' );
				$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
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
	if ( 'run' === $action ) {
		wp_cache_flush();
	}
}

/**
 * Get the array of available repair tools.
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
 * Return array of components from the array of registered tools.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @return array
 */
function bbp_get_admin_repair_tool_registered_components() {

	// Default return value
	$retval = array();

	// Get tools
	$tools = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

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
 * Output the repair list search form.
 *
 * @since 2.6.0 bbPress (r5885)
 */
function bbp_admin_repair_list_search_form() {
?>

	<p class="search-box">
		<label class="screen-reader-text" for="bbp-repair-search-input"><?php esc_html_e( 'Search Tools:', 'bbpress' ); ?></label>
		<input type="search" id="bbp-repair-search-input" name="s" value="<?php _admin_search_query(); ?>">
		<input type="submit" name="search_action" id="search-submit" class="button" value="<?php esc_html_e( 'Search Tools', 'bbpress' ); ?>">
	</p>

	<?php
}

/**
 * Output a select drop-down of components to filter by.
 *
 * @since 2.6.0 bbPress (r5885)
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
 * Return array of versions from the array of registered tools.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return array
 */
function bbp_get_admin_repair_tool_registered_versions() {

	// Default return value
	$retval = array();

	// Get tools
	$tools = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

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
 * Output a select drop-down of versions to filter by.
 *
 * @since 2.6.0 bbPress (r6894)
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
 * Maybe translate a repair tool overhead name.
 *
 * @since 2.6.0 bbPress (r6176)
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
 * Maybe translate a repair tool component name.
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
 * Maybe translate a repair tool overhead name.
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
 * @since 2.1.0 bbPress (r3884)
 *
 * @return array Repair list of options.
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
	$overhead = ! empty( $_GET['overhead'] )
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
			$callbacks = bbp_get_admin_repair_tool_callbacks( $tool );
			$repair_list[ $tool['priority'] ] = array(
				'id'          => sanitize_key( $id ),
				'type'        => $tool['type'],
				'title'       => $tool['title'],
				'priority'    => $tool['priority'],
				'description' => $tool['description'],
				'callback'    => isset( $callbacks['run'] ) ? $callbacks['run'] : '',
				'callbacks'   => $callbacks,
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
 * Get filter links for components for a specific admin repair tool.
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
 * Get filter links for versions for a specific admin repair tool.
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
 * Get filter links for overhead for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r6176)
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
 * Output filter links for overheads for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_overhead_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_overhead_filters( $args );
}

/**
 * Get filter links for overheads for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_overhead_filters( $args = array() ) {

	// Parse args
	$r = bbp_parse_args(
		$args,
		array(
			'before'       => '<ul class="subsubsub">',
			'after'        => '</ul>',
			'link_before'  => '<li>',
			'link_after'   => '</li>',
			'count_before' => ' <span class="count">(',
			'count_after'  => ')</span>',
			'sep'          => ' | ',

			// Retired, use 'sep' instead
			'separator'    => false
		),
		'get_admin_repair_tool_overhead_filters'
	);

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
			++$overheads[ $overhead ];
		}
	}

	// Get the current overhead, if any
	$selected = ! empty( $_GET['overhead'] )
		? sanitize_key( $_GET['overhead'] )
		: '';

	// Create the "All" link
	$current = empty( $selected ) ? 'current' : '';

	$all_text = sprintf(
		/* translators: %s: Number of items */
		esc_html__( 'All %s', 'bbpress' ),
		$r['count_before'] . count( $tools ) . $r['count_after']
	);

	$links[] = sprintf(
		'%1$s<a href="%2$s" class="%3$s">%4$s</a>%5$s',
		$r['link_before'],
		esc_url( $tools_url ),
		esc_attr( $current ),
		$all_text,
		$r['link_after']
	);

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
 * Output filter links for statuses.
 *
 * @since 2.6.0 bbPress (r6926)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_status_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_status_filters( $args );
}

/**
 * Get filter links for statuses.
 *
 * @since 2.6.0 bbPress (r6926)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_status_filters( $args = array() ) {

	// Parse args
	$r = bbp_parse_args(
		$args,
		array(
			'before'       => '<ul class="subsubsub">',
			'after'        => '</ul>',
			'link_before'  => '<li>',
			'link_after'   => '</li>',
			'count_before' => ' <span class="count">(',
			'count_after'  => ')</span>',
			'sep'          => ' | ',

			// Retired, use 'sep' instead
			'separator'    => false
		),
		'get_admin_repair_tool_status_filters'
	);

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
	$filter_url = add_query_arg(
		array(
			'status' => 'pending'
		),
		$tools_url
	);

	// Count HTML
	$all_count     = $r['count_before'] . count( $tools   ) . $r['count_after'];
	$pending_count = $r['count_before'] . count( $pending ) . $r['count_after'];

	// Define links
	$links = array(
		$r['link_before'] . '<a href="' . esc_url( $tools_url  ) . '" class="' . esc_attr( $all_current     ) . '">' .
			sprintf(
				/* translators: %s: Number of items */
				esc_html__( 'All %s', 'bbpress' ),
				$all_count
			) .
		'</a>' . $r['link_after'],

		$r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $pending_current ) . '">' .
			sprintf(
				/* translators: %s: Number of pending items */
				esc_html__( 'Pending %s', 'bbpress' ),
				$pending_count
			) .
		'</a>' . $r['link_after']
	);

	// Surround output with before & after strings
	$output = $r['before'] . implode( $r['sep'], $links ) . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_status_filters', $output, $r, $args );
}
