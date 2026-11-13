<?php

/**
 * Subscription Unsubscribe Form
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<form class="bbp-subscription-unsubscribe-form" method="post">
	<fieldset class="bbp-form">
		<legend><?php esc_html_e( 'Unsubscribe', 'bbpress' ); ?></legend>

		<p><?php esc_html_e( 'Do you want to unsubscribe from these emails?', 'bbpress' ); ?></p>

		<input type="hidden" name="List-Unsubscribe" value="One-Click" />

		<div class="bbp-submit-wrapper">
			<button type="submit" class="button submit"><?php esc_html_e( 'Yes, unsubscribe me', 'bbpress' ); ?></button>
		</div>
	</fieldset>
</form>
