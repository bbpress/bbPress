<?php

/**
 * Forums Loop - Single Forum
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<ul id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class(); ?>>
	<li class="bbp-forum-info">

		<?php if ( bbp_is_subscriptions() && bbp_current_user_can_manage_engagements( 'subscription' ) ) : ?>

			<span class="bbp-row-actions">

				<?php
				/**
				 * Fires before the forum subscription action in the bbPress theme.
				 *
				 * @since 2.5.0 bbPress (r5156)
				 */
				do_action( 'bbp_theme_before_forum_subscription_action' ); ?>

				<?php bbp_forum_subscription_link(
					array(
						'before'      => '',
						'subscribe'   => '+',
						'unsubscribe' => '&times;'
					)
				); ?>

				<?php
				/**
				 * Fires after the forum subscription action in the bbPress theme.
				 *
				 * @since 2.5.0 bbPress (r5156)
				 */
				do_action( 'bbp_theme_after_forum_subscription_action' ); ?>

			</span>

		<?php endif; ?>

		<?php
		/** This action is documented in src/templates/default/bbpress/loop-search-forum.php. */
		do_action( 'bbp_theme_before_forum_title' ); ?>

		<a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>"><?php bbp_forum_title(); ?></a>

		<?php
		/** This action is documented in src/templates/default/bbpress/loop-search-forum.php. */
		do_action( 'bbp_theme_after_forum_title' ); ?>

		<?php
		/**
		 * Fires before the forum description in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_before_forum_description' ); ?>

		<div class="bbp-forum-content"><?php bbp_forum_content(); ?></div>

		<?php
		/**
		 * Fires after the forum description in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_after_forum_description' ); ?>

		<?php
		/**
		 * Fires before the forum sub forums in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_before_forum_sub_forums' ); ?>

		<?php bbp_list_forums(); ?>

		<?php
		/**
		 * Fires after the forum sub forums in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_after_forum_sub_forums' ); ?>

		<?php bbp_forum_row_actions(); ?>

	</li>

	<li class="bbp-forum-topic-count"><?php bbp_forum_topic_count(); ?></li>

	<li class="bbp-forum-reply-count"><?php bbp_show_lead_topic() ? bbp_forum_reply_count() : bbp_forum_post_count(); ?></li>

	<li class="bbp-forum-freshness">

		<?php
		/**
		 * Fires before the forum freshness link in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_before_forum_freshness_link' ); ?>

		<?php bbp_forum_freshness_link(); ?>

		<?php
		/**
		 * Fires after the forum freshness link in the bbPress theme.
		 *
		 * @since 2.0.0 bbPress (r3417)
		 */
		do_action( 'bbp_theme_after_forum_freshness_link' ); ?>

		<p class="bbp-topic-meta">

			<?php
			/**
			 * Fires before the topic author in the bbPress theme.
			 *
			 * @since 2.0.0 bbPress (r3417)
			 */
			do_action( 'bbp_theme_before_topic_author' ); ?>

			<span class="bbp-topic-freshness-author"><?php bbp_author_link(
				array(
					'post_id' => bbp_get_forum_last_active_id(),
					'size'    => 14
				)
			); ?></span>

			<?php
			/**
			 * Fires after the topic author in the bbPress theme.
			 *
			 * @since 2.0.0 bbPress (r3417)
			 */
			do_action( 'bbp_theme_after_topic_author' ); ?>

		</p>
	</li>
</ul><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->
