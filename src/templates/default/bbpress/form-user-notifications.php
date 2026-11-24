<?php

/**
 * bbPress User Notification Settings
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<?php if ( bbp_current_user_can_edit_user_field( 'email', bbp_get_displayed_user_id() ) ) : ?>

	<h2 class="entry-title"><?php esc_html_e( 'Notifications', 'bbpress' ); ?></h2>

	<fieldset class="bbp-form" id="bbp-user-notifications">
		<legend><?php esc_html_e( 'Notifications', 'bbpress' ); ?></legend>

		<?php
		/**
		 * Fires before the notifications fields in the user edit form.
		 *
		 * @since 2.7.0 bbPress (r8264)
		 */
		do_action( 'bbp_user_edit_before_notifications' ); ?>

		<?php
		/**
		 * Fires in the notifications section of the user edit form.
		 *
		 * @since 2.7.0 bbPress (r8264)
		 */
		do_action( 'bbp_user_edit_notifications' ); ?>

		<?php
		/**
		 * Fires after the notifications fields in the user edit form.
		 *
		 * @since 2.7.0 bbPress (r8264)
		 */
		do_action( 'bbp_user_edit_after_notifications' ); ?>

	</fieldset>

<?php endif; ?>
