<?php
require_once( 'admin.php' );

require_once( 'includes/functions.bb-plugin.php' );

// Get all autoloaded plugins
$autoload_plugins = bb_get_plugins( 'all', 'autoload' );

// Get all normal plugins
$normal_plugins = bb_get_plugins();

// Get currently active 
$active_plugins = (array) bb_get_option( 'active_plugins' );

// Check for missing plugin files and remove them from the active plugins array
$update = false;
foreach ( $active_plugins as $index => $plugin ) {
	if ( !file_exists( bb_get_plugin_path( $plugin ) ) ) {
		$update = true;
		unset( $active_plugins[$index] );
	}
}
if ( $update ) {
	bb_update_option( 'active_plugins', $active_plugins );
}
unset( $update, $index, $plugin );

// Set the action
$action = '';
if( isset( $_GET['action'] ) && !empty( $_GET['action'] ) ) {
	$action = trim( $_GET['action'] );
}

// Set the plugin
$plugin = isset( $_GET['plugin'] ) ? trim( stripslashes( $_GET['plugin'] ) ) : '';

// Deal with user actions
if ( !empty( $action ) ) {
	switch ( $action ) {
		case 'activate':
			// Activation
			bb_check_admin_referer( 'activate-plugin_' . $plugin );

			$result = bb_activate_plugin( $plugin, 'plugins.php?message=error&plugin=' . urlencode( $plugin ) );
			if ( is_wp_error( $result ) )
				bb_die( $result );

			// Overrides the ?message=error one above
			wp_redirect( 'plugins.php?message=activate&plugin=' . urlencode( $plugin ) );
			break;

		case 'deactivate':
			// Deactivation
			bb_check_admin_referer( 'deactivate-plugin_' . $plugin );

			// Remove the deactivated plugin
			bb_deactivate_plugins( $plugin );

			// Redirect
			wp_redirect( 'plugins.php?message=deactivate&plugin=' . urlencode( $plugin ) );
			break;

		case 'scrape':
			// Scrape php errors from the plugin
			bb_check_admin_referer('scrape-plugin_' . $plugin);

			$valid_path = bb_validate_plugin( $plugin );
			if ( is_wp_error( $valid_path ) )
				bb_die( $valid_path );

			// Pump up the errors and output them to screen
			error_reporting( E_ALL ^ E_NOTICE );
			@ini_set( 'display_errors', true );

			include( $valid_path );
			break;
	}

	// Stop processing
	exit;
}

// Display notices
if ( isset($_GET['message']) ) {
	switch ( $_GET['message'] ) {
		case 'error' :
			bb_admin_notice( __( '<strong>Plugin could not be activated, it produced a Fatal Error</strong>. The error is shown below.' ), 'error' );
			break;
		case 'activate' :
			$plugin_data = bb_get_plugin_data( $plugin );
			bb_admin_notice( sprintf( __( '<strong>"%s" plugin activated</strong>' ), esc_attr( $plugin_data['name'] ) ) );
			break;
		case 'deactivate' :
			$plugin_data = bb_get_plugin_data( $plugin );
			bb_admin_notice( sprintf( __( '<strong>"%s" plugin deactivated</strong>' ), esc_attr( $plugin_data['name'] ) ) );
			break;
	}
}

if ( isset( $bb->safemode ) && $bb->safemode === true ) {
	bb_admin_notice( __( '<strong>"Safe mode" is on, all plugins are disabled even if they are listed as active.</strong>' ), 'error' );
}

$bb_admin_body_class = ' bb-admin-plugins';

bb_get_admin_header();
?>

<div class="wrap">

<?php
if ( bb_verify_nonce( $_GET['_scrape_nonce'], 'scrape-plugin_' . $plugin ) ) {
	$scrape_src = esc_attr(
		wp_nonce_url(
			bb_get_uri(
				'bb-admin/plugins.php',
				array(
					'action' => 'scrape',
					'plugin' => urlencode( $plugin )
				),
				BB_URI_CONTEXT_IFRAME_SRC + BB_URI_CONTEXT_BB_ADMIN
			),
			'scrape-plugin_' . $plugin
		)
	);
?>

<iframe class="error" src="<?php echo $scrape_src; ?>"></iframe>

<?php
}
?>

	<h2><?php _e( 'Manage Plugins' ); ?></h2>
	<?php do_action( 'bb_admin_notices' ); ?>

	<p><?php _e( 'Plugins extend and expand the functionality of bbPress. Once a plugin is installed, you may activate it or deactivate it here.' ); ?></p>

<?php
if ( $normal_plugins ) :
?> 

	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e( 'Plugin' ); ?></th>
				<th class="vers"><?php _e( 'Version' ); ?></th>
				<th><?php _e( 'Description' ); ?></th>
				<th class="action"><?php _e( 'Action' ); ?></th>
			</tr>
		</thead>
		<tbody>

<?php
	foreach ( $normal_plugins as $plugin => $plugin_data ) :
		$class = '';
		$action = 'activate';
		$action_class = 'edit';
		$action_text = __( 'Activate' );
		if ( in_array( $plugin, $active_plugins ) ) {
			$class =  'active';
			$action = 'deactivate';
			$action_class = 'delete';
			$action_text = __( 'Deactivate' );
		}
		$href = esc_attr(
			wp_nonce_url(
				bb_get_uri(
					'bb-admin/plugins.php',
					array(
						'action' => $action,
						'plugin' => urlencode($plugin)
					),
					BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN
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
					<cite><?php printf( __( 'By %s.' ), $plugin_data['author_link'] ); ?></cite>
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

	<h3><?php _e( 'Automatically loaded plugins' ); ?></h3>

	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e( 'Plugin' ); ?></th>
				<th class="vers"><?php _e( 'Version' ); ?></th>
				<th><?php _e( 'Description' ); ?></th>
			</tr>
		</thead>
		<tbody>

<?php
	foreach ( $autoload_plugins as $plugin => $plugin_data ) :
?>

			<tr<?php alt_class( 'autoload_plugin', 'active' ); ?>>

<?php
		if ( is_array( $plugin_data ) ) :
?>

				<td><?php echo $plugin_data['plugin_link']; ?></td>
				<td class="vers"><?php echo $plugin_data['version']; ?></td>
				<td><?php echo $plugin_data['description']; ?>
					<cite><?php printf( __( 'By %s.' ), $plugin_data['author_link'] ); ?></cite>
				</td>

<?php
		else :
?>

				<td colspan="3"><?php echo esc_html( $plugin ); ?></td>

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
?>

	<p><?php _e( 'If something goes wrong with a plugin and you can\'t use bbPress, delete or rename that file in the <code>my-plugins</code> directory and it will be automatically deactivated.' ); ?></p>

<?php
if ( !$normal_plugins && !$autoload_plugins ) :
?>

	<p><?php _e( 'No Plugins Installed' ); ?></p>

<?php
endif;
?>

	<h3 class="after"><?php _e( 'Get More Plugins' ); ?></h3>

	<p><?php printf( __( 'You can find additional plugins for your site in the <a href="%s">bbPress plugin directory</a>.' ), 'http://bbpress.org/plugins/' ); ?></p>

	<p><?php _e( 'To install a plugin you generally just need to upload the plugin file into your <code>my-plugins</code> directory. Once a plugin is uploaded, you may activate it here.' ); ?></p>

</div>

<?php
bb_get_admin_footer();
?>