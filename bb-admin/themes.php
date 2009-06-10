<?php
require_once('admin.php');

$themes = bb_get_themes();

$activetheme = bb_get_option('bb_active_theme');
if (!$activetheme) {
	$activetheme = BB_DEFAULT_THEME;
}

if ( isset($_GET['theme']) ) {
	if ( !bb_current_user_can( 'manage_themes' ) ) {
		wp_redirect( bb_get_uri(null, null, BB_URI_CONTEXT_HEADER) );
		exit;
	}
	
	bb_check_admin_referer( 'switch-theme' );
	do_action( 'bb_deactivate_theme_' . $activetheme );
	
	$theme = stripslashes($_GET['theme']);
	$theme_data = bb_get_theme_data( $theme );
	if ($theme_data['Name']) {
		$name = $theme_data['Name'];
	} else {
		$name = preg_replace( '/^([a-z0-9_-]+#)/i', '', $theme);
	}
	if ($theme == BB_DEFAULT_THEME) {
		bb_delete_option( 'bb_active_theme' );
	} else {
		bb_update_option( 'bb_active_theme', $theme );
	}
	do_action( 'bb_activate_theme_' . $theme );
	wp_redirect( bb_get_uri('bb-admin/themes.php', array('activated' => 1, 'name' => urlencode( $name ) ), BB_URI_CONTEXT_HEADER + BB_URI_CONTEXT_BB_ADMIN ) );
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
	$activation_url = bb_get_uri('bb-admin/themes.php', array('theme' => urlencode($theme)), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$activation_url = clean_url( wp_nonce_url( $activation_url, 'switch-theme' ) );
?>
	<li<?php alt_class( 'theme' ); ?>>
		<div class="screen-shot"><?php if ( $screen_shot ) : ?><a href="<?php echo $activation_url; ?>" title="<?php echo attribute_escape( __('Click to activate') ); ?>"><img alt="<?php echo attribute_escape( $theme_data['Title'] ); ?>" src="<?php echo $screen_shot; ?>" /></a><?php endif; ?></div>
		<div class="description">
			<h3><a href="<?php echo $activation_url; ?>" title="<?php echo attribute_escape( __('Click to activate') ); ?>"><?php echo $theme_data['Title']; ?></a></h3>
			<small class="version"><?php echo $theme_data['Version']; ?></small>
			<small class="author"><?php printf(__('by <cite>%s</cite>'), $theme_data['Author']); if ( $theme_data['Porter'] ) printf(__(', ported by <cite>%s</cite>'), $theme_data['Porter']); ?></small>
			<?php echo $theme_data['Description']; // Description is autop'ed ?>
			<small class="location"><?php printf(__('All of this theme\'s files are located in the "%s" themes directory.'), $theme_data['Location']); ?></small>
		</div>
	</li>
<?php
}

if ( isset( $bb->safemode ) && $bb->safemode === true ) {
	bb_admin_notice( __('"Safe mode" is on, the default theme will be used instead of the active theme indicated below.'), 'error' );
}

$bb_admin_body_class = ' bb-admin-appearance';

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('Manage Themes'); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>

<h3><?php _e('Current Theme'); ?></h3>

<ul class="theme-list active">
<?php bb_admin_theme_row( $themes[$activetheme] ); unset($themes[$activetheme] ); ?>
</ul>
<?php if ( !empty($themes) ) : ?>

<h3><?php _e('Available Themes'); ?></h3>
<div class="theme-list">
<ul class="theme-list">
<?php foreach ( $themes as $theme ) bb_admin_theme_row( $theme ); ?>
</ul>
<div class="clear"></div>
</div>

</div>

<?php endif; bb_get_admin_footer(); ?>
