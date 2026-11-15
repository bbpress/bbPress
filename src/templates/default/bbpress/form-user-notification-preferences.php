<?php

/**
 * User Notification Preferences
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<?php if ( bbp_current_user_can_edit_user_field( 'email', bbp_get_displayed_user_id() ) ) : ?>
	<?php bbp_user_notification_preference_fields(); ?>
	<?php bbp_user_notification_preferences_nonce(); ?>

<?php endif;
