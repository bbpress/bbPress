<?php

/**
 * User Registration Form
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

	<form method="post" action="<?php bbp_wp_login_action( array( 'action' => 'register', 'context' => 'login_post' ) ); ?>" class="bbp-login-form">
		<fieldset>
			<legend><?php _e( 'Register', 'bbpress' ); ?></legend>

			<?php do_action( 'bbp_template_notices' ); ?>

			<div class="bbp-username">
				<label for="user_login"><?php _e( 'Username', 'bbpress' ); ?>: </label>
				<input type="text" name="user_login" value="<?php bbp_sanitize_val( 'user_login' ); ?>" size="20" id="user_login" tabindex="<?php bbp_tab_index(); ?>" />
			</div>

			<div class="bbp-email">
				<label for="user_email"><?php _e( 'Email Address', 'bbpress' ); ?>: </label>
				<input type="text" name="user_email" value="<?php bbp_sanitize_val( 'user_email' ); ?>" size="20" id="user_email" tabindex="<?php bbp_tab_index(); ?>" />
			</div>

			<div class="bbp-submit-wrapper">

				<?php do_action( 'register_form' ); ?>

				<button type="submit" name="user-submit" tabindex="<?php bbp_tab_index(); ?>" class="user-submit"><?php _e( 'Register', 'bbpress' ); ?></button>

				<?php bbp_user_register_fields(); ?>

			</div>
		</fieldset>
	</form>
