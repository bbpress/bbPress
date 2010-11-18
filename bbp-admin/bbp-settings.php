<?php

function bbp_admin_settings () {

	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
		check_admin_referer( 'bbpress' );
	} ?>

	<div class="wrap">

		<?php do_action( 'admin_notices' ); ?>
		<?php screen_icon(); ?>

		<h2><?php _e( 'bbPress Settings', 'bbpress' ) ?></h2>

		<form name="form1" method="post" action="options.php">

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="_bbp_edit_lock"><?php _e( 'Lock post editing after', 'bbpress' ); ?></label></th>
					<td>
						<input name="_bbp_edit_lock" type="text" id="posts_per_page" value="<?php form_option( '_bbp_edit_lock' ); ?>" class="small-text" /> <?php _e( 'minutes', 'bbpress' ); ?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><label for="_bbp_throttle_time"><?php _e( 'Throttle time' ); ?></label></th>
					<td><input name="_bbp_throttle_time" type="text" id="posts_per_rss" value="<?php form_option( '_bbp_throttle_time' ); ?>" class="small-text" /> <?php _e( 'seconds', 'bbpress' ); ?></td>
				</tr>

			</table>

			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
			</p>
		</form>
	</div>

<?php
}
?>
