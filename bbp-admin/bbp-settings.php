<?php

/**
 * bbPress Admin Settings
 *
 * @package bbPress
 * @subpackage Administration
 */

/**
 * Main settings section description for the settings page
 *
 * @since bbPress (r2737)
 */
function bbp_admin_setting_callback_section() {
?>

			<p><?php _e( 'Main settings for the bbPress plugin', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit lock setting field
 *
 * @since bbPress (r2737)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_editlock () {
?>

			<input name="_bbp_edit_lock" type="text" id="_bbp_edit_lock" value="<?php form_option( '_bbp_edit_lock' ); ?>" class="small-text" />
			<label for="_bbp_edit_lock"><?php _e( 'minutes', 'bbpress' ); ?></label>

<?php
}

/**
 * Throttle setting field
 *
 * @since bbPress (r2737)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_throttle() {
?>

			<input name="_bbp_throttle_time" type="text" id="_bbp_throttle_time" value="<?php form_option( '_bbp_throttle_time' ); ?>" class="small-text" />
			<label for="_bbp_throttle_time"><?php _e( 'seconds', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow subscriptions setting field
 *
 * @since bbPress (r2737)
 *
 * @uses checked() To display the checked attribute
 */
function bbp_admin_setting_callback_subscriptions() {
?>

			<input id="_bbp_enable_subscriptions" name="_bbp_enable_subscriptions" type="checkbox" id="_bbp_enable_subscriptions" value="1" <?php checked( true, bbp_is_subscriptions_active() ); ?> />
			<label for="_bbp_enable_subscriptions"><?php _e( 'Allow users to subscribe to topics', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow anonymous posting setting field
 *
 * @since bbPress (r2737)
 *
 * @uses checked() To display the checked attribute
 */
function bbp_admin_setting_callback_anonymous() {
?>

			<input id="_bbp_allow_anonymous" name="_bbp_allow_anonymous" type="checkbox" id="_bbp_allow_anonymous" value="1" <?php checked( true, bbp_allow_anonymous() ); ?> />
			<label for="_bbp_allow_anonymous"><?php _e( 'Allow guest users without accounts to create topics and replies', 'bbpress' ); ?></label>

<?php
}

/**
 * The main settings page
 *
 * @since bbPress (r2643)
 *
 * @uses screen_icon() To display the screen icon
 * @uses settings_fields() To output the hidden fields for the form
 * @uses do_settings_sections() To output the settings sections
 */
function bbp_admin_settings() {
?>
	<div class="wrap">

		<?php screen_icon(); ?>

		<h2><?php _e( 'bbPress Settings', 'bbpress' ) ?></h2>

		<form action="options.php" method="post">

			<?php settings_fields( 'bbpress' ); ?>

			<?php do_settings_sections( 'bbpress' ); ?>

			<p class="submit">
				<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save Changes', 'bbpress' ); ?>" />
			</p>
		</form>
	</div>

<?php
}
?>
