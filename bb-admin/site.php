<?php
require_once('admin.php');

$bb_admin_body_class = ' bb-admin-tools';

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('Tools') ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>

<form class="settings" method="post" action="<?php bb_uri('bb-admin/bb-do-counts.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset>
		<legend><?php _e( 'Re-count' ) ?></legend>
		<p><?php _e( 'To minimize database queries, bbPress keeps it\'s own count of various items like posts in each topic and topics in each forum. Occasionally these internal counters may become incorrect, you can manually re-count these items using this form.' ) ?></p>
		<p><?php _e( 'You can also clean out some stale items here, like empty tags.' ) ?></p>
<?php
bb_recount_list();
if ( $recount_list ) {
?>
		<div id="option-counts">
			<div class="label">
				<?php _e( 'Items to re-count' ); ?>
			</div>
			<div class="inputs">
<?php
	foreach ( $recount_list as $item ) {
		echo '<label class="checkboxs"><input type="checkbox" class="checkbox" name="' . esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ) . '" value="1" /> ' . esc_html( $item[1] ) . '</label>' . "\n";
	}
?>
			</div>
		</div>
<?php
} else {
?>
		<p><?php _e( 'There are no re-count tools available.' ) ?></p>
<?php
}
?>
	</fieldset>
	<fieldset class="submit">
		<?php bb_nonce_field( 'do-counts' ); ?>
		<input class="submit" type="submit" name="submit" value="<?php _e('Recount Items') ?>" />
	</fieldset>
</form>

</div>

<?php bb_get_admin_footer(); ?>
