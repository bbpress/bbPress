<?php

/**
 * Single Reply Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

	<?php bbp_breadcrumb(); ?>

	<?php do_action( 'bbp_template_before_single_reply' ); ?>

	<?php if ( post_password_required() ) : ?>

		<?php bbp_get_template_part( 'bbpress/form', 'protected' ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'bbpress/loop', 'single-reply' ); ?>

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_single_reply' ); ?>
