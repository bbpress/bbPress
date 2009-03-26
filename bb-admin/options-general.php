<?php

require_once('admin.php');

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && $_POST['action'] == 'update') {
	
	bb_check_admin_referer( 'options-general-update' );
	
	// Deal with xmlrpc checkbox when it isn't checked
	if (!isset($_POST['enable_xmlrpc'])) {
		$_POST['enable_xmlrpc'] = false;
	}
	
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
		'class' => 'long',
		'note' => __( 'The full URL of your bbPress install.' ),
	),
	'from_email' => array(
		'title' => __( 'E-mail address' ),
		'note' => __( 'Emails sent by the site will appear to come from this address.' ),
	),
	'mod_rewrite' => array(
		'title' => __( 'Pretty permalink type' ),
		'type' => 'select',
		'options' => array(
			'0' => __( 'None&nbsp;&nbsp;&nbsp;&hellip;/forums.php?id=1' ),
			'1' => __( 'Numeric&nbsp;&nbsp;&nbsp;.../forums/1' ),
			'slugs' => __( 'Name based&nbsp;&nbsp;&nbsp;.../forums/first-forum' ),
		),
		'note' => sprintf(
			__( 'If you activate "Numeric" or "Name based" permalinks, you will need to create a file at <code>%s</code> containing the url rewriting rules <a href="%s">provided here</a>.' ),
			BB_PATH . '.htaccess',
			bb_get_uri( 'bb-admin/rewrite-rules.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN )
		),
	),
	'page_topics' => array(
		'title' => __( 'Items per page' ),
		'class' => 'short',
		'note' => __( 'Number of topics, posts or tags to show per page.' ),
	),
	'edit_lock' => array(
		'title' => __( 'Lock post editing after' ),
		'class' => 'short',
		'after' => __( 'minutes' ),
		'note' => 'A user can edit a post for this many minutes after submitting.',
	),
);

$date_time_options = array(
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

$remote_options = array(
	'enable_xmlrpc' => array(
		'title' => __( 'Enable XML-RPC' ),
		'type' => 'checkbox',
		'after' => __( 'Allow remote publishing and management via the bbPress <a href="http://codex.wordpress.org/Glossary#XML-RPC">XML-RPC</a> publishing protocol?' ),
	),
	'enable_pingback' => array(
		'title' => __( 'Enable Pingbacks' ),
		'type' => 'checkbox',
		'after' => __( 'Allow sending and receiving of <a href="http://codex.wordpress.org/Glossary#PingBack">pingbacks</a>?' ),
	),
);

$bb_get_option_avatars_show = create_function( '$a', 'return 1;' );
add_filter( 'bb_get_option_avatars_show', $bb_get_option_avatars_show );
$avatar_options = array(
	'avatars_show' => array(
		'title' => __( 'Show avatars' ),
		'type' => 'checkbox',
		'after' => __( 'Display avatars on your site?' ),
	),
	'avatars_default' => array(
		'title' => __( 'Gravatar default image' ),
		'type' => 'select',
		'options' => array(
			'default' => __( 'Default' ),
			'logo' => __( 'Gravatar Logo' ),
			'monsterid' => __( 'MonsterID' ),
			'wavatar' => __( 'Wavatar' ),
			'identicon' => __( 'Identicon' ),
		),
		'note' => array(
			__( 'Select what style of avatar to display to users without a Gravatar:' ),
			bb_get_avatar( 'anotherexample', 30, 'default' ) . __( 'Default' ),
			bb_get_avatar( 'anotherexample', 30, 'logo' ) . __( 'Gravatar Logo' ),
			bb_get_avatar( 'anotherexample', 30, 'monsterid' ) . __( 'MonsterID' ),
			bb_get_avatar( 'anotherexample', 30, 'wavatar' ) . __( 'Wavatar' ),
			bb_get_avatar( 'anotherexample', 30, 'identicon' ) . __( 'Identicon' ),
		),
	),
	'avatars_rating' => array(
		'title' => __( 'Gravatar maximum rating' ),
		'type' => 'select',
		'options' => array(
			'0' => __( 'None' ),
			'x' => __( 'X' ),
			'r' => __( 'R' ),
			'pg' => __( 'PG' ),
			'g' => __( 'G' ),
		),
		'note' => array(
			'<img src="http://site.gravatar.com/images/gravatars/ratings/3.gif" alt="' . attribute_escape( __( 'Rated X' ) ) . '" /> ' . __( 'X rated gravatars may contain hardcore sexual imagery or extremely disturbing violence.' ),
			'<img src="http://site.gravatar.com/images/gravatars/ratings/2.gif" alt="' . attribute_escape( __( 'Rated R' ) ) . '" /> ' . __( 'R rated gravatars may contain such things as harsh profanity, intense violence, nudity, or hard drug use.' ),
			'<img src="http://site.gravatar.com/images/gravatars/ratings/1.gif" alt="' . attribute_escape( __( 'Rated PG' ) ). '" /> ' . __( 'PG rated gravatars may contain rude gestures, provocatively dressed individuals, the lesser swear words, or mild violence.' ),
			'<img src="http://site.gravatar.com/images/gravatars/ratings/0.gif" alt="' . attribute_escape( __( 'Rated G' ) ) . '" /> ' . __( 'A G rated gravatar is suitable for display on all websites with any audience type.' ),
		),
	),
);
remove_filter( 'bb_get_option_avatars_show', $bb_get_option_avatars_show );

bb_get_admin_header();

?>

<div class="wrap">

<h2><?php _e('General Settings'); ?></h2>

<form class="settings" method="post" action="<?php bb_uri('bb-admin/options-general.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset><?php foreach ( $general_options as $option => $args ) bb_option_form_element( $option, $args ); ?></fieldset>
	<fieldset>
		<legend><?php _e('Date and Time') ?></legend>
		<div>
			<label>
				<?php _e('<abbr title="Coordinated Universal Time">UTC</abbr> time is') ?>
			</label>
			<div>
				<p><?php echo gmdate(__('Y-m-d g:i:s a')); ?></p>
			</div>
		</div>
<?php		foreach ( $date_time_options as $option => $args ) bb_option_form_element( $option, $args ); ?>
	</fieldset>
	<fieldset>
		<legend><?php _e('Remote publishing and Pingbacks'); ?></legend>
		<p>
			<?php _e('How do we describe this?'); ?>
		</p>
<?php		foreach ( $remote_options as $option => $args ) bb_option_form_element( $option, $args ); ?>
	</fieldset>
	<fieldset>
		<legend><?php _e('Avatars'); ?></legend>
		<p>
			<?php _e('bbPress includes built-in support for <a href="http://gravatar.com/">Gravatars</a>, you can enable this feature here.'); ?>
		</p>
<?php		foreach ( $avatar_options as $option => $args ) bb_option_form_element( $option, $args ); ?>
	</fieldset>
	<fieldset class="submit">
		<?php bb_nonce_field( 'options-general-update' ); ?>
		<input type="hidden" name="action" value="update" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>

</div>

<?php

bb_get_admin_footer();
