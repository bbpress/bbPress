<?php
require_once('admin.php');

// Get all autoloaded plugins
$autoload_plugins = bb_get_plugins( 'all', 'autoload' );

// Get all normal plugins
$normal_plugins = bb_get_plugins();

// Get currently active 
$active_plugins = (array) bb_get_option( 'active_plugins' );

// Check for missing plugin files and remove them from the active plugins array
$update = false;
foreach ( $active_plugins as $index => $filename ) {
	$filename = str_replace(
		array('core#', 'user#'),
		array(BB_CORE_PLUGIN_DIR, BB_PLUGIN_DIR),
		$filename
	);
	if ( !file_exists($filename) ) {
		$update = true;
		unset($active_plugins[$index]);
	}
}
if ( $update ) {
	bb_update_option( 'active_plugins', $active_plugins );
}
unset($update, $index, $filename);

// Deal with user actions
if ( isset($_GET['action']) ) {
	// Get the arguments
	$plugin = stripslashes(trim($_GET['plugin']));
	// Remove the core# or user# appendage for the filter name
	// (otherwise the plugin would need to add a filter for each location)
	$plugin_filter = basename(str_replace(array('core#', 'user#'), '', $plugin));
	
	if ('activate' == $_GET['action']) {
		// Activation
		bb_check_admin_referer( 'activate-plugin_' . $plugin );
		
		// Check if the plugin exists in the normal plugins array
		if ( !in_array($plugin, array_keys($normal_plugins)) ) {
			wp_redirect( 'plugins.php?message=invalid' );
		} elseif ( !in_array($plugin, $active_plugins) ) {
			// If the plugin isn't active already then activate it
			
			// We'll override this later if the plugin can be included without fatal error
			wp_redirect( 'plugins.php?message=error' );
			
			// Get the right path and include the plugin
			$filename = str_replace(
				array('core#', 'user#'),
				array(BB_CORE_PLUGIN_DIR, BB_PLUGIN_DIR),
				$plugin
			);
			@include( $filename );
			
			// Add to the active plugins array
			$active_plugins[] = $plugin;
			ksort($active_plugins);
			bb_update_option( 'active_plugins', $active_plugins );
			do_action( 'bb_activate_plugin_' . $plugin_filter );
			
			// Overrides the ?error=true one above
			wp_redirect( 'plugins.php?message=activate' );
		}
	} elseif ('deactivate' == $_GET['action']) {
		// Deactivation
		bb_check_admin_referer( 'deactivate-plugin_' . $plugin );
		
		// Remove the deactivated plugin
		array_splice($active_plugins, array_search($plugin, $active_plugins), 1 );
		bb_update_option( 'active_plugins', $active_plugins );
		do_action( 'bb_deactivate_plugin_' . $plugin_filter );
		
		// Redirect
		wp_redirect('plugins.php?message=deactivate');
	}
	
	// Stop processing
	exit;
}

// Display notices
if ( isset($_GET['message']) ) {
	switch ( $_GET['message'] ) {
		case 'error' :
			bb_admin_notice( __('Plugin could not be activated; it produced a <strong>Fatal Error</strong>.'), 'error' );
			break;
		case 'invalid' :
			bb_admin_notice( __('File is not a valid plugin.'), 'error' );
			break;
		case 'activate' :
			bb_admin_notice( __('Plugin <strong>activated</strong>') );
			break;
		case 'deactivate' :
			bb_admin_notice( __('Plugin <strong>deactivated</strong>') );
			break;
	}
}

bb_get_admin_header();
?>

<h2><?php _e('Plugins'); ?></h2>

<?php
if ( $normal_plugins ) :
?> 

<table class="widefat">
	<thead>
		<tr>
			<th><?php _e('Plugin'); ?></th>
			<th class="vers"><?php _e('Version'); ?></th>
			<th><?php _e('Description'); ?></th>
			<th class="action"><?php _e('Action'); ?></th>
		</tr>
	</thead>
	<tbody>

<?php
	foreach ( $normal_plugins as $plugin => $plugin_data ) :
		$class = '';
		$action = 'activate';
		$action_class = 'edit';
		$action_text = __('Activate');
		if ( in_array($plugin, $active_plugins) ) {
			$class =  'active';
			$action = 'deactivate';
			$action_class = 'delete';
			$action_text = __('Deactivate');
		}
		$href = attribute_escape(
			bb_nonce_url(
				add_query_arg(
					array(
						'action' => $action,
						'plugin' => urlencode($plugin)
					),
					bb_get_option( 'uri' ) . 'bb-admin/plugins.php'
				),
				$action . '-plugin_' . $plugin
			)
		);
?>

		<tr<?php alt_class( 'normal_plugin', $class ); ?>>
			<td><?php echo $plugin_data['plugin_link']; ?></td>
			<td class="vers"><?php echo $plugin_data['version']; ?></td>
			<td>
				<?php echo $plugin_data['description']; ?>
				<cite><?php printf( __('By %s.'), $plugin_data['author_link'] ); ?></cite>
			</td>
			<td class="action">
				<a class="<?php echo $action_class; ?>" href="<?php echo $href; ?>"><?php echo $action_text; ?></a>
			</td>
		</tr>

<?php
	endforeach;
?>

	</tbody>
</table>

<?php
endif;

if ( $autoload_plugins ) :
?>

<h3><?php _e('Automatically loaded plugins'); ?></h3>

<table class="widefat">
	<thead>
		<tr>
			<th><?php _e('Plugin'); ?></th>
			<th class="vers"><?php _e('Version'); ?></th>
			<th><?php _e('Description'); ?></th>
		</tr>
	</thead>
	<tbody>

<?php
	foreach ( $autoload_plugins as $plugin => $plugin_data ) :
?>

		<tr<?php alt_class( 'autoload_plugin' ); ?>>

<?php
		if ( is_array($plugin_data) ) :
?>

			<td><?php echo $plugin_data['plugin_link']; ?></td>
			<td class="vers"><?php echo $plugin_data['version']; ?></td>
			<td><?php echo $plugin_data['description']; ?>
				<cite><?php printf( __('By %s.'), $plugin_data['author_link'] ); ?></cite>
			</td>

<?php
		else :
?>

			<td colspan="3"><?php echo wp_specialchars( $plugin ); ?></td>

<?php
		endif;
?>

		</tr>

<?php
	endforeach;
?>

	</tbody>
</table>

<?php
endif;

if ( !$normal_plugins && !$autoload_plugins ) :
?>

<p><?php _e('No Plugins Installed'); ?></p>

<?php
endif;

bb_get_admin_footer();
?>