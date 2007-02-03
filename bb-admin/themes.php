<?php
require_once('admin.php');

if ( isset($_GET['theme']) ) {
	if ( !bb_current_user_can( 'use_keys' ) ) {
		wp_redirect( bb_get_option( 'uri' ) );
		exit;
	}
	bb_check_admin_referer( 'switch_theme' );
	$activetheme = stripslashes($_GET['theme']);
	bb_update_option( 'bb_active_theme', $activetheme );
	wp_redirect( bb_get_option( 'uri' ) . 'bb-admin/themes.php?activated' );
	exit;
} 

$themes = bb_get_themes();
$activetheme = bb_get_option('bb_active_theme');

if ( isset($_GET['activated']) )
	$theme_notice = bb_admin_notice( sprintf(__('Theme "%s" activated'), basename($activetheme)) );

if ( !in_array($activetheme, $themes) ) {
	$activetheme = BBPATH . 'bb-templates/kakumei';
	bb_update_option( 'bb_active_theme', $activetheme );
	remove_action( 'bb_admin_notices', $theme_notice );
	bb_admin_notice( __('Theme not found.  Default theme applied.'), 'error' );
}

function bb_admin_theme_row( $theme ) {
	$theme_data = file_exists( $theme . 'style.css' ) ? bb_get_theme_data( $theme . 'style.css' ) : false;
	$screen_shot = file_exists( $theme . 'screenshot.png' ) ? bb_path_to_url( $theme . 'screenshot.png' ) : false;
	$activation_url = bb_nonce_url( add_query_arg( 'theme', urlencode($theme), bb_get_option( 'uri' ) . 'bb-admin/themes.php' ), 'switch_theme' );
?>
	<li<?php alt_class( 'theme', $class ); ?>>
		<div class="screen-shot"><?php if ( $screen_shot ) : ?><a href="<?php echo $activation_url; ?>" title="<?php _e('Click to activate'); ?>"><img alt="<?php echo wp_specialchars( $theme_data['Title'], 1 ); ?>" src="<?php echo $screen_shot; ?>" /></a><?php endif; ?></div>
		<div class="description">
			<h3><a href="<?php echo $activation_url; ?>" title="<?php _e('Click to activate'); ?>"><?php echo wp_specialchars( $theme_data['Title'] ); ?></a></h3>
			<small class="version"><?php echo wp_specialchars( $theme_data['Version'] ); ?></small>
			<?php printf(__('by <cite>%s</cite>'), $theme_data['Author']); if ( $theme_data['Porter'] ) printf(__(', ported by <cite>%s</cite>'), $theme_data['Porter']); ?>
			<?php echo bb_autop( $theme_data['Description'] ); ?>
		</div>
		<br class="clear" />
	</li>
<?php
}

bb_get_admin_header();
?>

<h2><?php _e('Current Theme'); ?></h2>
<ul class="theme-list active">
<?php bb_admin_theme_row( $themes[basename($activetheme)] ); unset($themes[basename($activetheme)] ); ?>
</ul>
<?php if ( !empty($themes) ) : ?>

<h2><?php _e('Available Themes'); ?></h2>
<ul class="theme-list">
<?php foreach ( $themes as $theme ) bb_admin_theme_row( $theme ); ?>
</ul>

<?php endif; bb_get_admin_footer(); ?>
