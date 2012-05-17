<?php

/**
 * Single Topic Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php do_action( 'bbp_template_before_lead_topic' ); ?>

<ul id="bbp-topic-<?php bbp_topic_id(); ?>-lead" class="bbp-lead-topic">

	<li class="bbp-header">

		<div class="bbp-topic-author"><?php  _e( 'Creator',  'bbpress' ); ?></div><!-- .bbp-topic-author -->

		<div class="bbp-topic-content">

			<?php _e( 'Topic', 'bbpress' ); ?>

			<?php bbp_user_subscribe_link(); ?>

			<?php bbp_user_favorites_link(); ?>

		</div><!-- .bbp-topic-content -->

	</li><!-- .bbp-header -->

	<li class="bbp-body">

		<div class="bbp-topic-header">

			<div class="bbp-meta">

				<?php printf( __( '%1$s at %2$s', 'bbpress' ), get_the_date(), esc_attr( get_the_time() ) ); ?>

				<a href="<?php bbp_topic_permalink(); ?>" title="<?php bbp_topic_title(); ?>" class="bbp-topic-permalink">#<?php bbp_topic_id(); ?></a>

				<?php do_action( 'bbp_theme_before_topic_admin_links' ); ?>

				<?php bbp_topic_admin_links(); ?>

				<?php do_action( 'bbp_theme_after_topic_admin_links' ); ?>

			</div><!-- .bbp-meta -->

		</div><!-- .bbp-topic-header -->

		<div id="post-<?php bbp_topic_id(); ?>" <?php bbp_topic_class(); ?>>

			<div class="bbp-topic-author">

				<?php do_action( 'bbp_theme_before_topic_author_details' ); ?>

				<?php bbp_topic_author_link( array( 'sep' => '<br />', 'show_role' => true ) ); ?>

				<?php if ( is_super_admin() ) : ?>

					<?php do_action( 'bbp_theme_before_topic_author_admin_details' ); ?>

					<div class="bbp-topic-ip"><?php bbp_author_ip( bbp_get_topic_id() ); ?></div>

					<?php do_action( 'bbp_theme_after_topic_author_admin_details' ); ?>

				<?php endif; ?>

				<?php do_action( 'bbp_theme_after_topic_author_details' ); ?>

			</div><!-- .bbp-topic-author -->

			<div class="bbp-topic-content">

				<?php do_action( 'bbp_theme_before_topic_content' ); ?>

				<?php bbp_topic_content(); ?>

				<?php do_action( 'bbp_theme_after_topic_content' ); ?>

			</div><!-- .bbp-topic-content -->

		</div><!-- #post-<?php bbp_topic_id(); ?> -->

	</li><!-- .bbp-body -->

	<li class="bbp-footer">

		<div class="bbp-topic-author"><?php  _e( 'Creator',  'bbpress' ); ?></div>

		<div class="bbp-topic-content">

			<?php if ( !bbp_show_lead_topic() ) : ?>

				<?php _e( 'Posts', 'bbpress' ); ?>

			<?php else : ?>

				<?php _e( 'Replies', 'bbpress' ); ?>

			<?php endif; ?>

		</div><!-- .bbp-topic-content -->

	</li>

</ul><!-- #topic-<?php bbp_topic_id(); ?>-replies -->

<?php do_action( 'bbp_template_after_lead_topic' ); ?>
