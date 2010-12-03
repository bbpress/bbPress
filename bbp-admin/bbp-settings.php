<?php

function bbp_admin_settings () {

	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['action'] ) && $_POST['action'] == '_bbp_update_settings' ) {
		check_admin_referer( '_bbp_settings' );

		$options = array( '_bbp_edit_lock' => 'int', '_bbp_throttle_time' => 'int', '_bbp_enable_subscriptions' => 'bool' );
		foreach ( array_keys( $options ) as $option ) {
			$$option = trim( @$_POST[$option] );
			switch ( $options[$option] ) {
				case 'int':
					$$option = intval( $$option );
					break;
				case 'bool':
					$$option = intval( $$option ) == 0 ? false : true;
					break;
				case 'text':
				case 'default':
					$$option = esc_attr( $$option );
					break;
			}
			update_option( $option, $$option );
		}

		bbp_admin_notices( __( 'Options successfully saved!' ) );
	} ?>

	<div class="wrap">

		<?php do_action( 'admin_notices' ); ?>
		<?php screen_icon(); ?>

		<h2><?php _e( 'bbPress Settings', 'bbpress' ) ?></h2>

		<form name="form1" method="post">

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="_bbp_edit_lock"><?php _e( 'Lock post editing after', 'bbpress' ); ?></label></th>
					<td><input name="_bbp_edit_lock" type="text" id="posts_per_page" value="<?php form_option( '_bbp_edit_lock' ); ?>" class="small-text" /> <?php _e( 'minutes', 'bbpress' ); ?></td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="_bbp_throttle_time"><?php _e( 'Throttle time', 'bbpress' ); ?></label></th>
					<td><input name="_bbp_throttle_time" type="text" id="posts_per_rss" value="<?php form_option( '_bbp_throttle_time' ); ?>" class="small-text" /> <?php _e( 'seconds', 'bbpress' ); ?></td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="_bbp_enable_subscriptions"><?php _e( 'Enable subscriptions', 'bbpress' ); ?></label></th>
					<td><input id="_bbp_enable_subscriptions" name="_bbp_enable_subscriptions" type="checkbox" id="posts_per_rss" value="1" <?php checked( true, bbp_is_subscriptions_active() ); ?> class="small-text" /><label for="_bbp_enable_subscriptions"><?php _e( 'Allow users to subscribe to topics', 'bbpress' ); ?></label></td>
				</tr>

			</table>

			<p class="submit">
				<?php wp_nonce_field( '_bbp_settings' ); ?>
				<input type="hidden" name="action" value="_bbp_update_settings" />
				<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'bbpress' ); ?>" />
			</p>
		</form>
	</div>

<?php
}
?>
