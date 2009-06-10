<?php

require_once('admin.php');

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && $_POST['action'] == 'update') {
	
	bb_check_admin_referer( 'options-general-update' );
	
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
	bb_admin_notice( __('Settings saved.') );
}

$general_options = array(
	'name' => array(
		'title' => __( 'Site title' ),
		'class' => 'long',
	),
	'description' => array(
		'title' => __( 'Site description' ),
		'class' => 'long',
	),
	'uri' => array(
		'title' => __( 'bbPress address (URL)' ),
		'class' => array('long', 'code'),
		'note' => __( 'The full URL of your bbPress install.' ),
	),
	'from_email' => array(
		'title' => __( 'E-mail address' ),
		'note' => __( 'Emails sent by the site will appear to come from this address.' ),
	)
);

$time_options = array(
	'gmt_offset' => array(
		'title' => __( 'Times should differ<br />from UTC by' ),
		'class' => 'short',
		'after' => __( 'hours' ),
		'note' => __( 'Example: -7 for Pacific Daylight Time.' ),
	),
	'datetime_format' => array(
		'title' => __( 'Date and time format' ),
		'value' => bb_get_datetime_formatstring_i18n(),
		'note' => sprintf( __( 'Output: <strong>%s</strong>' ), bb_datetime_format_i18n( bb_current_time() ) ),
	),
	'date_format' => array(
		'title' => __( 'Date format' ),
		'value' => bb_get_datetime_formatstring_i18n( 'date' ),
		'note' => array(
			sprintf( __( 'Output: <strong>%s</strong>' ), bb_datetime_format_i18n( bb_current_time(), 'date' ) ),
			__( 'Click "Update settings" to update sample output.' ),
			__( '<a href="http://codex.wordpress.org/Formatting_Date_and_Time">Documentation on date formatting</a>.' ),
		),
	),
);

$bb_admin_body_class = ' bb-admin-settings';

bb_get_admin_header();

?>

<div class="wrap">

<h2><?php _e('General Settings'); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>

<form class="settings" method="post" action="<?php bb_uri('bb-admin/options-general.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset>
<?php
foreach ( $general_options as $option => $args ) {
	bb_option_form_element( $option, $args );
}
?>
		<div>
			<label>
				<?php _e('<abbr title="Coordinated Universal Time">UTC</abbr> time is') ?>
			</label>
			<div>
				<p><?php echo gmdate(__('Y-m-d g:i:s a')); ?></p>
			</div>
		</div>
<?php		foreach ( $time_options as $option => $args ) bb_option_form_element( $option, $args ); ?>
	</fieldset>
	<fieldset class="submit">
		<?php wp_nonce_field( 'options-general-update' ); ?>
		<input type="hidden" name="action" value="update" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>

</div>

<?php

bb_get_admin_footer();
