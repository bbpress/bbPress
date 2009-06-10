<?php

require_once('admin.php');

$file_source = BB_PATH . 'bb-admin/includes/defaults.bb-htaccess.php';
$file_target = BB_PATH . '.htaccess';
include( $file_source );
$file_source_rules = $_rules; // This is a string

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && $_POST['action'] == 'update') {
	
	bb_check_admin_referer( 'options-permalinks-update' );
	
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

	$file_target_rules = array();

	$file_target_exists = false;
	$file_target_writable = true;
	if ( file_exists( $file_target ) ) {
		if ( is_readable( $file_target ) ) {
			$file_target_rules = explode( "\n", implode( '', file(  $file_target ) ) );
		}
		$file_target_exists = true;
		if ( !is_writable( $file_target ) ) {
			$file_target_writable = false;
		}
	} else {
		$file_target_dir = dirname( $file_target );
		if ( file_exists( $file_target_dir ) ) {
			if ( !is_writable( $file_target_dir ) || !is_dir( $file_target_dir ) ) {
				$file_target_writable = false;
			}
		} else {
			$file_target_writable = false;
		}
	}

	// Strip out existing bbPress rules
	$_keep_rule = true;
	$_kept_rules = array();
	foreach ( $file_target_rules as $_rule ) {
		if ( false !== strpos( $_rule, '# BEGIN bbPress' ) ) {
			$_keep_rule = false;
			continue;
		} elseif ( false !== strpos( $_rule, '# END bbPress' ) ) {
			$_keep_rule = true;
			continue;
		}
		if ( $_keep_rule ) {
			$_kept_rules[] = $_rule;
		}
	}

	$file_target_rules = join( "\n", $_kept_rules ) . "\n" . $file_source_rules;
	
	$file_target_written = 0;
	if ( $file_target_writable ) {
		// Open the file for writing - rewrites the whole file
		if ( $file_target_handle = fopen( $file_target, 'w' ) ) {
			if ( fwrite( $file_target_handle, $file_target_rules ) ) {
				$file_target_written = 1;
			}
			// Close the file
			fclose( $file_target_handle );
			chmod( $file_target, 0666 );
		}
	}

	bb_update_option( 'mod_rewrite_writable', $file_target_writable );

	$goback = add_query_arg( 'updated', 'true', wp_get_referer() );
	bb_safe_redirect( $goback );
	exit;
}

if ( bb_get_option( 'mod_rewrite' ) && !bb_get_option( 'mod_rewrite_writable' ) ) {
	$manual_instructions = true;
}

if ( !empty( $_GET['updated'] ) ) {
	if ( $manual_instructions ) {
		bb_admin_notice( __('You should update your .htaccess now.') );
	} else {
		bb_admin_notice( __('Permalink structure updated.') );
	}
}

$permalink_options = array(
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
	)
);

$bb_admin_body_class = ' bb-admin-settings';

bb_get_admin_header();

?>

<div class="wrap">

<h2><?php _e( 'Permalink Settings' ); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>

<form class="settings" method="post" action="<?php bb_uri( 'bb-admin/options-permalinks.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN ); ?>">
	<fieldset>
		<p>
			<?php _e( 'By default bbPress uses web URLs which have question marks and lots of numbers in them, however bbPress offers you the ability to choose an alternative URL structure for your permalinks. This can improve the aesthetics, usability, and forward-compatibility of your links.' ); ?>
		</p>
<?php
foreach ( $permalink_options as $option => $args ) { 
	bb_option_form_element( $option, $args );
}
?>
	</fieldset>
	<fieldset class="submit">
		<?php wp_nonce_field( 'options-permalinks-update' ); ?>
		<input type="hidden" name="action" value="update" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>

<?php
if ( $manual_instructions ) {
?>
<form class="settings" method="post" action="<?php bb_uri( 'bb-admin/options-permalinks.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN ); ?>">
	<fieldset>
		<p>
			<?php _e( 'If your <code>.htaccess</code> file were <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>, we could do this automatically, but it isn&#8217;t so these are the mod_rewrite rules you should have in your <code>.htaccess</code> file. Click in the field and press <kbd>CTRL + a</kbd> to select all.' ); ?>
		</p>
		<textarea><?php echo trim( $file_source_rules ); ?></textarea>
	</fieldset>
</form>

<?php
}
?>

</div>

<?php

bb_get_admin_footer();
