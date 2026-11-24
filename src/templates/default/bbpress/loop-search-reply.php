<?php

/**
 * Search Loop - Single Reply
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div class="bbp-reply-header">
	<div class="bbp-meta">
		<span class="bbp-reply-post-date"><?php bbp_reply_post_date(); ?></span>
		<a href="<?php bbp_reply_url(); ?>" class="bbp-reply-permalink">#<?php bbp_reply_id(); ?></a>
	</div><!-- .bbp-meta -->

	<div class="bbp-reply-title">
		<h3><?php esc_html_e( 'In reply to: ', 'bbpress' ); ?>
		<a class="bbp-topic-permalink" href="<?php bbp_topic_permalink( bbp_get_reply_topic_id() ); ?>"><?php bbp_topic_title( bbp_get_reply_topic_id() ); ?></a></h3>
	</div><!-- .bbp-reply-title -->
</div><!-- .bbp-reply-header -->

<div id="post-<?php bbp_reply_id(); ?>" <?php bbp_reply_class(); ?>>
	<div class="bbp-reply-author">

		<?php
		/**
		 * Fires before the reply author details in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_before_reply_author_details' ); ?>

		<?php bbp_reply_author_link( array( 'show_role' => true ) ); ?>

		<?php if ( bbp_is_user_keymaster() ) : ?>

			<?php
			/**
			 * Fires before the reply author admin details in the bbPress theme.
			 *
			 * @since 2.0.0 bbPress (r3417)
			 */
			do_action( 'bbp_theme_before_reply_author_admin_details' ); ?>

			<div class="bbp-reply-ip"><?php bbp_author_ip( bbp_get_reply_id() ); ?></div>

			<?php
			/**
			 * Fires after the reply author admin details in the bbPress theme.
			 *
			 * @since 2.0.0 bbPress (r3417)
			 */
			do_action( 'bbp_theme_after_reply_author_admin_details' ); ?>

		<?php endif; ?>

		<?php
		/**
		 * Fires after the reply author details in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_after_reply_author_details' ); ?>

	</div><!-- .bbp-reply-author -->

	<div class="bbp-reply-content">

		<?php
		/**
		 * Fires before the reply content in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_before_reply_content' ); ?>

		<?php bbp_reply_content(); ?>

		<?php
		/**
		 * Fires after the reply content in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_after_reply_content' ); ?>

	</div><!-- .bbp-reply-content -->
</div><!-- #post-<?php bbp_reply_id(); ?> -->

