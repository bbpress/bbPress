<?php

function bb_get_plugins_callback( $type = 'normal', $path, $filename )
{
	if ( '.php' != substr($filename, -4) )
		return false;
	
	switch ($type) {
		case 'all':
			// Catch, but do nothing
			break;
		case 'autoload':
			if ( '_' != substr($filename, 0, 1) )
				return false;
			break;
		case 'normal':
		default:
			if ( '_' == substr($filename, 0, 1) )
				return false;
			break;
	}
	
	return bb_get_plugin_data( $path );
}

function bb_get_plugins( $location = 'all', $type = 'normal' )
{
	static $plugin_cache = array();

	if ( !in_array( $type, array( 'all', 'autoload', 'normal' ) ) ) {
		$type = 'normal';
	}

	if ( isset( $plugin_cache[$location][$type] ) ) {
		return $plugin_cache[$location][$type];
	}

	global $bb;
	$directories = array();
	if ( 'all' === $location ) {
		foreach ( $bb->plugin_locations as $_name => $_data ) {
			$directories[] = $_data['dir'];
		}
	} elseif ( isset( $bb->plugin_locations[$location]['dir'] ) ) {
		$directories[] = $bb->plugin_locations[$location]['dir'];
	}

	require_once( BB_PATH . BB_INC . 'class.bb-dir-map.php' );

	$plugin_arrays = array();
	foreach ( $directories as $directory ) {
		$dir_map = new BB_Dir_Map(
			$directory,
			array(
				'callback' => 'bb_get_plugins_callback',
				'callback_args' => array( $type ),
				'recurse' => 1
			)
		);
		$dir_plugins = $dir_map->get_results();
		$dir_plugins = is_wp_error( $dir_plugins ) ? array() : $dir_plugins;
		$plugin_arrays[] = $dir_plugins;
		unset($dir_map, $dir_plugins);
	}
	
	$plugins = array();
	foreach ($plugin_arrays as $plugin_array) {
		$plugins = array_merge($plugins, $plugin_array);
	}
	
	$adjusted_plugins = array();
	foreach ($plugins as $plugin => $plugin_data) {
		$adjusted_plugins[$plugin_data['location'] . '#' . $plugin] = $plugin_data;
	}

	uasort( $adjusted_plugins, 'bb_plugins_sort' );

	$plugin_cache[$location][$type] = $adjusted_plugins;

	return $adjusted_plugins;
}

function bb_plugins_sort( $a, $b )
{
	return strnatcasecmp( $a['name'], $b['name'] );
}

// Output sanitized for display
function bb_get_plugin_data( $plugin_file )
{
	global $bb;

	if ( preg_match( '/^([a-z0-9_-]+)#((?:[a-z0-9\/\\_-]+.)+)(php)$/i', $plugin_file, $_matches ) ) {
		$plugin_file = $bb->plugin_locations[$_matches[1]]['dir'] . $_matches[2] . $_matches[3];
		
		$_directory = $bb->plugin_locations[$_matches[1]]['dir'];
		$_plugin = $_matches[2] . $_matches[3];

		if ( !$_plugin ) {
			// Not likely
			return false;
		}

		if ( validate_file( $_plugin ) ) {
			// $plugin has .., :, etc.
			return false;
		}

		$plugin_file = $_directory . $_plugin;
		unset( $_matches, $_directory, $_plugin );
	}

	if ( !file_exists( $plugin_file ) ) {
		// The plugin isn't there
		return false;
	}

	$plugin_code = implode( '', file( $plugin_file ) );

	// Grab just the first commented area from the file
	if ( !preg_match( '|/\*(.*)\*/|msU', $plugin_code, $plugin_block ) ) {
		return false;
	}

	$plugin_data = trim( $plugin_block[1] );

	if ( !preg_match( '/Plugin Name:(.*)/i', $plugin_data, $plugin_name ) ) {
		return false;
	}

	preg_match( '/Plugin URI:(.*)/i', $plugin_data, $plugin_uri );
	preg_match( '/Description:(.*)/i', $plugin_data, $description );
	preg_match( '/Author:(.*)/i', $plugin_data, $author_name );
	preg_match( '/Author URI:(.*)/i', $plugin_data, $author_uri );

	if ( preg_match( '/Requires at least:(.*)/i', $plugin_data, $requires ) ) {
		$requires = wp_specialchars( trim( $requires[1] ) );
	} else {
		$requires = '';
	}
	if ( preg_match( '/Tested up to:(.*)/i', $plugin_data, $tested ) ) {
		$tested = wp_specialchars( trim( $tested[1] ) );
	} else {
		$tested = '';
	}
	if ( preg_match( '/Version:(.*)/i', $plugin_data, $version ) ) {
		$version = wp_specialchars( trim( $version[1] ) );
	} else {
		$version = '';
	}

	$plugin_name = wp_specialchars( trim( $plugin_name[1] ) );

	if ( $plugin_uri ) {
		$plugin_uri = clean_url( trim( $plugin_uri[1] ) );
	} else {
		$plugin_uri = '';
	}
	if ( $author_name ) {
		$author_name = wp_specialchars( trim( $author_name[1] ) );
	} else {
		$author_name = '';
	}
	if ( $author_uri ) {
		$author_uri = clean_url( trim( $author_uri[1] ) );
	} else {
		$author_uri = '';
	}

	if ( $description ) {
		$description = trim( $description[1] );
		$description = bb_encode_bad( $description );
		$description = bb_code_trick( $description );
		$description = force_balance_tags( $description );
		$description = bb_filter_kses( $description );
		$description = bb_autop( $description );
	} else {
		$description = '';
	}

	// Normalise the path to the plugin
	$plugin_file = str_replace( '\\', '/', $plugin_file );

	foreach ( $bb->plugin_locations as $_name => $_data ) {
		$_directory = str_replace( '\\', '/', $_data['dir'] );
		if ( 0 === strpos( $plugin_file, $_directory ) ) {
			$location = $_name;
			break;
		}
	}

	$r = array(
		'location' => $location,
		'name' => $plugin_name,
		'uri' => $plugin_uri,
		'description' => $description,
		'author' => $author_name,
		'author_uri' => $author_uri,
		'requires' => $requires,
		'tested' => $tested,
		'version' => $version
	);

	$r['plugin_link'] = ( $plugin_uri ) ?
		"<a href='$plugin_uri' title='" . attribute_escape( __('Visit plugin homepage') ) . "'>$plugin_name</a>" :
		$plugin_name;
	$r['author_link'] = ( $author_name && $author_uri ) ?
		"<a href='$author_uri' title='" . attribute_escape( __('Visit author homepage') ) . "'>$author_name</a>" :
		$author_name;

	return $r;
}

/**
 * Attempts activation of plugin in a "sandbox" and redirects on success.
 *
 * A plugin that is already activated will not attempt to be activated again.
 *
 * The way it works is by setting the redirection to the error before trying to
 * include the plugin file. If the plugin fails, then the redirection will not
 * be overwritten with the success message. Also, the options will not be
 * updated and the activation hook will not be called on plugin error.
 *
 * It should be noted that in no way the below code will actually prevent errors
 * within the file. The code should not be used elsewhere to replicate the
 * "sandbox", which uses redirection to work.
 *
 * If any errors are found or text is outputted, then it will be captured to
 * ensure that the success redirection will update the error redirection.
 *
 * @since 1.0
 *
 * @param string $plugin Plugin path to main plugin file with plugin data.
 * @param string $redirect Optional. URL to redirect to.
 * @return WP_Error|null WP_Error on invalid file or null on success.
 */
function bb_activate_plugin( $plugin, $redirect = '' ) {
	$active_plugins = (array) bb_get_option( 'active_plugins' );
	$plugin = bb_plugin_basename( trim( $plugin ) );

	$valid_path = bb_validate_plugin( $plugin );
	if ( is_wp_error( $valid_path ) )
		return $valid_path;

	if ( in_array( $plugin, $active_plugins ) ) {
		return false;
	}

	if ( !empty( $redirect ) ) {
		// We'll override this later if the plugin can be included without fatal error
		wp_redirect( add_query_arg( '_scrape_nonce', bb_create_nonce( 'scrape-plugin_' . $plugin ), $redirect ) ); 
	}

	ob_start();
	@include( $valid_path );
	// Add to the active plugins array
	$active_plugins[] = $plugin;
	ksort( $active_plugins );
	bb_update_option( 'active_plugins', $active_plugins );
	do_action( 'bb_activate_plugin_' . $plugin );
	ob_end_clean();

	return $valid_path;
}

/**
 * Deactivate a single plugin or multiple plugins.
 *
 * The deactivation hook is disabled by the plugin upgrader by using the $silent
 * parameter.
 *
 * @since unknown
 *
 * @param string|array $plugins Single plugin or list of plugins to deactivate.
 * @param bool $silent Optional, default is false. Prevent calling deactivate hook.
 */
function bb_deactivate_plugins( $plugins, $silent = false ) {
	$active_plugins = (array) bb_get_option( 'active_plugins' );

	if ( !is_array( $plugins ) ) {
		$plugins = array( $plugins );
	}

	foreach ( $plugins as $plugin ) {
		$plugin = bb_plugin_basename( trim( $plugin ) );
		if ( !in_array( $plugin, $active_plugins ) ) {
			continue;
		}
		// Remove the deactivated plugin
		array_splice( $active_plugins, array_search( $plugin, $active_plugins ), 1 );
		if ( !$silent ) {
			do_action( 'bb_deactivate_plugin_' . $plugin );
		}
	}

	bb_update_option( 'active_plugins', $active_plugins );
}

/**
 * Validate the plugin path.
 *
 * Checks that the file exists and is valid file.
 *
 * @since 1.0
 * @uses validate_file() to check the passed plugin identifier isn't malformed
 * @uses bb_get_plugin_path() to get the full path of the plugin
 * @uses bb_get_plugins() to get the plugins that actually exist
 *
 * @param string $plugin Plugin Path
 * @param string $location The location of plugin, one of 'user', 'core' or 'all'
 * @param string $type The type of plugin, one of 'all', 'autoload' or 'normal'
 * @return WP_Error|int 0 on success, WP_Error on failure.
 */
function bb_validate_plugin( $plugin, $location = 'all', $type = 'all' ) {
	if ( validate_file( trim( $plugin ) ) ) {
		return new WP_Error( 'plugin_invalid', __( 'Invalid plugin path.' ) );
	}
	$path = bb_get_plugin_path( trim( $plugin ) );
	if ( !file_exists( $path ) ) {
		return new WP_Error( 'plugin_not_found', __( 'Plugin file does not exist.' ) );
	}
	if ( !in_array( trim( $plugin ), array_keys( bb_get_plugins( $location, $type ) ) ) ) {
		return new WP_Error( 'plugin_not_available', __( 'That type of plugin is not available in the specified location.' ) );
	}

	return $path;
}
