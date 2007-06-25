<?php
require_once('admin.php');

$plugins = bb_get_plugins();
$_plugins = array();
if ( is_callable( 'glob' ) ) {
	foreach ( glob(BBPLUGINDIR . '_*.php') as $_plugin ) {
		$_data = bb_get_plugin_data( $_plugin );
		$_plugins[$_plugin] = $_data ? $_data : true;
	}
}

$current = (array) bb_get_option( 'active_plugins' );

$update = false;
foreach ( $current as $c => $cur )
	if ( !file_exists(BBPLUGINDIR . $cur) ) {
		$update = true;
		unset($current[$c]);
		do_action( 'bb_deactivate_plugin' . $c );
	}

if ( isset($_GET['action']) ) {
	$plugin = stripslashes(trim($_GET['plugin']));
	if ('activate' == $_GET['action']) {
		bb_check_admin_referer( 'activate-plugin_' . $plugin );
		if ( !in_array($plugin, array_keys($plugins)) )
			wp_redirect( 'plugins.php?message=invalid' );
		elseif ( !in_array($plugin, $current) ) {
			wp_redirect( 'plugins.php?message=error' ); // we'll override this later if the plugin can be included without fatal error
			@include( BBPLUGINDIR . $plugin );
			$current[] = $plugin;
			ksort($current);
			bb_update_option( 'active_plugins', $current );
			do_action( 'bb_activate_plugin_' . $plugin );
			wp_redirect( 'plugins.php?message=activate' ); // overrides the ?error=true one above
		}
	} else if ('deactivate' == $_GET['action']) {
		bb_check_admin_referer( 'deactivate-plugin_' . $plugin );
		array_splice($current, array_search($plugin, $current), 1 );
		bb_update_option( 'active_plugins', $current );
		do_action( 'bb_deactivate_plugin' . $plugin );
		wp_redirect('plugins.php?message=deactivate');
	}
	exit;
}

if ( $update )
	bb_update_option( 'active_plugins', $current );

if ( isset($_GET['message']) ) : switch ( $_GET['message'] ) :
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
endswitch; endif;

bb_get_admin_header();
?>

<h2><?php _e('Plugins'); ?></h2>

<?php if( $plugins ) : ?> 

<table class="widefat">
<thead>
	<tr>
		<th>Plugin</th>
		<th class="vers">Version</th>
		<th>Description</th>
		<th class="action">Action</th>
	</tr>
</thead>
<tbody>

<?php foreach ( $plugins as $p => $plugin ) : $class = in_array($p, $current) ? 'active' : ''; ?>
	<tr<?php alt_class( 'plugin', $class ); ?>>
		<td><?php echo $plugin['plugin_link']; ?></td>
		<td class="vers"><?php echo $plugin['version']; ?></td>
		<td><?php echo $plugin['description']; ?>
			<cite><?php printf( __('By %s.'), $plugin['author_link'] ); ?></cite>
		</td>
<?php if ( $class ) : ?>
		<td class="action"><a class="delete" href="<?php echo attribute_escape( bb_nonce_url( add_query_arg( array('action' => 'deactivate', 'plugin' => urlencode($p)), bb_get_option( 'uri' ) . 'bb-admin/plugins.php' ), 'deactivate-plugin_' . $p ) ); ?>">Deactivate</a></td>
<?php else : ?>
		<td class="action"><a class="edit" href="<?php echo attribute_escape( bb_nonce_url( add_query_arg( array('action' => 'activate', 'plugin' => urlencode($p)), bb_get_option( 'uri' ) . 'bb-admin/plugins.php' ), 'activate-plugin_' . $p ) ); ?>">Activate</a></td>
<?php endif; ?>
	</tr>
<?php endforeach; ?>

</tbody>
</table>

<?php endif; if ( $_plugins ) : ?>

<br />
<h3><?php _e('Automatically loaded plugins'); ?></h3>

<table class="widefat">
<thead>
	<tr>
		<th>Plugin</th>
		<th class="vers">Version</th>
		<th>Description</th>
	</tr>
</thead>
<tbody>

<?php foreach ( $_plugins as $p => $plugin ) : ?>
	<tr<?php alt_class( '_plugin' ); ?>>
<?php if ( is_array($plugin) ) : ?>
		<td><?php echo $plugin['plugin_link']; ?></td>
		<td class="vers"><?php echo $plugin['version']; ?></td>
		<td><?php echo $plugin['description']; ?>
			<cite><?php printf( __('By %s.'), $plugin['author_link'] ); ?></cite>
		</td>
<?php else : ?>
		<td colspan="3"><?php echo wp_specialchars( $p ); ?></td>
<?php endif; ?>
	</tr>
<?php endforeach; ?>

</tbody>
</table>

<?php endif; if ( !$plugins && !$_plugins ) :?>
<p>No Plugins Installed</p>

<?php endif; ?>

<?php bb_get_admin_footer(); ?>
