<?php

/**
 * bbPress Admin Tools Page
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Admin tools page
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_recount_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses screen_icon() To display the screen icon
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_tools() {

	$recount_list = bbp_recount_list();

	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
		check_admin_referer( 'do-counts' );

		// Stores messages
		$messages = array();

		wp_cache_flush();

		foreach ( (array) $recount_list as $item )
			if ( isset( $item[2] ) && isset( $_POST[$item[0]] ) && 1 == $_POST[$item[0]] && is_callable( $item[2] ) )
				$messages[] = call_user_func( $item[2] );


		if ( count( $messages ) ) {
			foreach ( $messages as $message ) {
				bbp_admin_notices( $message[1] );
			}
		}
	} ?>

	<div class="wrap">

		<?php screen_icon( 'tools' ); ?>

		<h2><?php _e( 'bbPress Recount', 'bbpress' ) ?></h2>

		<?php do_action( 'admin_notices' ); ?>

		<p><?php _e( 'bbPress keeps a running count of things like replies to each topic and topics in each forum. In rare occasions these counts can fall out of sync. Using this form you can have bbPress manually recount these items.', 'bbpress' ); ?></p>
		<p><?php _e( 'You can also use this form to clean out stale items like empty tags.', 'bbpress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php _e( 'Things to recount:', 'bbpress' ) ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Recount', 'bbpress' ) ?></span></legend>

								<?php if ( !empty( $recount_list ) ) :

										foreach ( $recount_list as $item ) {
											echo '<label><input type="checkbox" class="checkbox" name="' . esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ) . '" value="1" /> ' . esc_html( $item[1] ) . '</label><br />' . "\n";
										}
								?>

								<?php else : ?>

									<p><?php _e( 'There are no recount tools available.', 'bbpress' ) ?></p>

								<?php endif; ?>

							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php _e( 'Recount Items', 'bbpress' ); ?>" />
				<?php wp_nonce_field( 'do-counts' ); ?>
			</fieldset>
		</form>
	</div>

<?php
}
?>
