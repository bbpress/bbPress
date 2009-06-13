<?php

require_once('admin.php');

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && $_POST['action'] == 'update') {
	
	bb_check_admin_referer( 'options-discussion-update' );
	
	// Deal with pingbacks checkbox when it isn't checked
	if (!isset($_POST['enable_pingback'])) {
		$_POST['enable_pingback'] = false;
	}
	
	// Deal with avatars checkbox when it isn't checked
	if (!isset($_POST['avatars_show'])) {
		$_POST['avatars_show'] = false;
	}
	
	foreach ( (array) $_POST as $option => $value ) {
		if ( !in_array( $option, array('_wpnonce', '_wp_http_referer', 'action', 'submit') ) ) {
			$option = trim( $option );
			$value = is_array( $value ) ? $value : trim( $value );
			$value = stripslashes_deep( $value );
			if ($option == 'uri' && !empty($value)) {
				$value = rtrim( $value, " \t\n\r\0\x0B/" ) . '/';
			}
			if ( $value ) {
				bb_update_option( $option, $value );
			} else {
				bb_delete_option( $option );
			}
		}
	}
	
	$goback = add_query_arg('updated', 'true', wp_get_referer());
	bb_safe_redirect($goback);
	exit;
}

if ( !empty($_GET['updated']) ) {
	bb_admin_notice( __( '<strong>Settings saved.</strong>' ) );
}

$remote_options = array(
	'enable_pingback' => array(
		'title' => __( 'Enable Pingbacks' ),
		'type' => 'checkbox',
		'after' => __( 'Allow link notifications from other blogs.' ),
	),
);

$bb_get_option_avatars_show = create_function( '$a', 'return 1;' );
add_filter( 'bb_get_option_avatars_show', $bb_get_option_avatars_show );
$avatar_options = array(
	'avatars_show' => array(
		'title' => __( 'Avatar display' ),
		'type' => 'checkbox',
		'after' => __( 'Show avatars' ),
	),
	'avatars_rating' => array(
		'title' => __( 'Maximum rating' ),
		'type' => 'select',
		'options' => array(
			'g' => __( 'G &#8212; Suitable for all audiences' ),
			'pg' => __( 'PG &#8212; Possibly offensive, usually for audiences 13 and above' ),
			'r' => __( 'R &#8212; Intended for adult audiences above 17' ),
			'x' => __( 'X &#8212; Even more mature than above' )
		)
	),
	'avatars_default' => array(
		'title' => __( 'Gravatar default image' ),
		'type' => 'select',
		'options' => array(
			'default' => __( 'Mystery Man' ),
			'logo' => __( 'Gravatar Logo' ),
			'identicon' => __( 'Identicon (Generated)' ),
			'wavatar' => __( 'Wavatar (Generated)' ),
			'monsterid' => __( 'MonsterID  (Generated)' )
		),
		'note' => array(
			__( 'For users without a custom avatar of their own, you can either display a generic logo or a generated one based on their e-mail address.' ),
			bb_get_avatar( 'anotherexample', 30, 'default' ) . __( 'Mystery Man' ),
			bb_get_avatar( 'anotherexample', 30, 'logo' ) . __( 'Gravatar Logo' ),
			bb_get_avatar( 'anotherexample', 30, 'identicon' ) . __( 'Identicon (Generated)' ),
			bb_get_avatar( 'anotherexample', 30, 'wavatar' ) . __( 'Wavatar (Generated)' ),
			bb_get_avatar( 'anotherexample', 30, 'monsterid' ) . __( 'MonsterID (Generated)' )
		),
	)
);
remove_filter( 'bb_get_option_avatars_show', $bb_get_option_avatars_show );

$bb_admin_body_class = ' bb-admin-settings';

bb_get_admin_header();

?>

<div class="wrap">

<h2><?php _e('Discussion Settings'); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>

<form class="settings" method="post" action="<?php bb_uri( 'bb-admin/options-discussion.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN ); ?>">
	<fieldset>
<?php
foreach ( $remote_options as $option => $args ) {
	bb_option_form_element( $option, $args );
}
?>
	</fieldset>
	<fieldset>
		<legend><?php _e('Avatars'); ?></legend>
		<p>
			<?php _e('bbPress includes built-in support for <a href="http://gravatar.com/">Gravatars</a>. A Gravatar is an image that follows you from site to site, appearing beside your name when you comment on Gravatar enabled sites. Here you can enable the display of Gravatars on your site.'); ?>
		</p>
<?php
foreach ( $avatar_options as $option => $args ) {
	bb_option_form_element( $option, $args );
}
?>
	</fieldset>
	<fieldset class="submit">
		<?php wp_nonce_field( 'options-discussion-update' ); ?>
		<input type="hidden" name="action" value="update" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>

</div>

<?php

bb_get_admin_footer();
