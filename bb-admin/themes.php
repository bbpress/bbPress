<?php
require_once('admin.php');

$themes = bb_get_themes();

$activetheme = bb_get_option('bb_active_theme');
if (!$activetheme) {
	$activetheme = BB_DEFAULT_THEME;
}

if ( isset($_GET['theme']) ) {
	if ( !bb_current_user_can( 'manage_themes' ) ) {
		wp_redirect( bb_get_option( 'uri' ) );
		exit;
	}
	
	bb_check_admin_referer( 'switch-theme' );
	do_action( 'bb_deactivate_theme_' . $activetheme );
	
	$theme = stripslashes($_GET['theme']);
	$theme_data = bb_get_theme_data( $theme );
	if ($theme_data['Name']) {
		$name = $theme_data['Name'];
	} else {
		$name = str_replace(array('core#', 'user#'), '', $theme);
	}
	if ($theme == BB_DEFAULT_THEME) {
		bb_delete_option( 'bb_active_theme' );
	} else {
		bb_update_option( 'bb_active_theme', $theme );
	}
	do_action( 'bb_activate_theme_' . $theme );
	wp_redirect( bb_get_option( 'uri' ) . 'bb-admin/themes.php?activated&name=' . urlencode($name) );
	exit;
}

if ( isset($_GET['activated']) )
	$theme_notice = bb_admin_notice( sprintf(__('Theme "%s" <strong>activated</strong>'), attribute_escape($_GET['name'])) );

if ( !in_array($activetheme, $themes) ) {
	if ($activetheme == BB_DEFAULT_THEME) {
		remove_action( 'bb_admin_notices', $theme_notice );
		bb_admin_notice( __('Default theme is missing.'), 'error' );
	} else {
		bb_delete_option( 'bb_active_theme' );
		remove_action( 'bb_admin_notices', $theme_notice );
		bb_admin_notice( __('Theme not found.  Default theme applied.'), 'error' );
	}
}

function bb_admin_theme_row( $theme ) {
	$theme_directory = bb_get_theme_directory( $theme );
	$theme_data = file_exists( $theme_directory . 'style.css' ) ? bb_get_theme_data( $theme ) : false;
	$screen_shot = file_exists( $theme_directory . 'screenshot.png' ) ? clean_url( bb_get_theme_uri( $theme ) . 'screenshot.png' ) : false;
	$activation_url = clean_url( bb_nonce_url( add_query_arg( 'theme', urlencode($theme), bb_get_option( 'uri' ) . 'bb-admin/themes.php' ), 'switch-theme' ) );
?>
	<li<?php alt_class( 'theme', $class ); ?>>
		<div class="screen-shot"><?php if ( $screen_shot ) : ?><a href="<?php echo $activation_url; ?>" title="<?php echo attribute_escape( __('Click to activate') ); ?>"><img alt="<?php echo attribute_escape( $theme_data['Title'] ); ?>" src="<?php echo $screen_shot; ?>" /></a><?php endif; ?></div>
		<div class="description">
			<h3><a href="<?php echo $activation_url; ?>" title="<?php echo attribute_escape( __('Click to activate') ); ?>"><?php echo $theme_data['Title']; ?></a></h3>
			<small class="version"><?php echo $theme_data['Version']; ?></small>
			<?php printf(__('by <cite>%s</cite>'), $theme_data['Author']); if ( $theme_data['Porter'] ) printf(__(', ported by <cite>%s</cite>'), $theme_data['Porter']); ?>
			<?php echo $theme_data['Description']; // Description is autop'ed ?>
			<small><?php printf(__('Installed in: %s'), str_replace(array('core#', 'user#'), array(__('Core themes -&gt; '), __('User installed themes -&gt; ')), $theme)); ?></small>
		</div>
		<br class="clear" />
	</li>
<?php
}

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('Current Theme'); ?></h2>
<ul class="theme-list active">
<?php bb_admin_theme_row( $themes[$activetheme] ); unset($themes[$activetheme] ); ?>
</ul>
<?php if ( !empty($themes) ) : ?>

<h2><?php _e('Available Themes'); ?></h2>
<ul class="theme-list">
<?php foreach ( $themes as $theme ) bb_admin_theme_row( $theme ); ?>
</ul>

</div>

<?php endif; bb_get_admin_footer(); ?>
