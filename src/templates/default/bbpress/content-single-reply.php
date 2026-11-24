<?php

/**
 * Single Reply Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php bbp_breadcrumb(); ?>

	<?php
	/**
	 * Fires before the single reply template section.
	 *
	 * @since 2.1.0 bbPress (r3634)
	 */
	do_action( 'bbp_template_before_single_reply' ); ?>

	<?php if ( post_password_required( bbp_get_reply_id() ) ) : ?>

		<?php bbp_get_template_part( 'form', 'protected' ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'loop', 'single-reply' ); ?>

	<?php endif; ?>

	<?php
	/**
	 * Fires after the single reply template section.
	 *
	 * @since 2.1.0 bbPress (r3634)
	 */
	do_action( 'bbp_template_after_single_reply' ); ?>

</div>
