<?php

/**
 * Password Protected
 *
 * @package bbPress
 * @subpackage Theme
 */

// Make sure we're back where we started
wp_reset_postdata();

?>

	<fieldset class="bbp-form" id="bbp-protected">
		<Legend><?php _e( 'Protected', 'bbpress' ); ?></legend>

		<?php echo get_the_password_form(); ?>

	</fieldset>
