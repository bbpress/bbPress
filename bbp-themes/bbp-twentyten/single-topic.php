<?php

/**
 * Single Topic
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php if ( bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) ) : ?>

					<?php while ( have_posts() ) : the_post(); ?>

						<div id="bbp-topic-wrapper-<?php bbp_topic_id(); ?>" class="bbp-topic-wrapper">
							<h1 class="entry-title"><?php bbp_topic_title(); ?></h1>
							<div class="entry-content">

								<?php bbp_get_template_part( 'bbpress/nav', 'breadcrumb' ); ?>

								<?php if ( post_password_required() ) : ?>

									<?php bbp_get_template_part( 'bbpress/form', 'protected' ); ?>

								<?php else : ?>

									<?php bbp_topic_tag_list(); ?>

									<?php bbp_single_topic_description(); ?>

									<div id="ajax-response"></div>

									<?php bbp_get_template_part( 'bbpress/single', 'topic' ); ?>

									<?php if ( bbp_get_query_name() || bbp_has_replies() ) : ?>

										<?php bbp_get_template_part( 'bbpress/pagination', 'replies' ); ?>

										<?php bbp_get_template_part( 'bbpress/loop',       'replies' ); ?>

										<?php bbp_get_template_part( 'bbpress/pagination', 'replies' ); ?>

									<?php endif; ?>

									<?php bbp_get_template_part( 'bbpress/form',       'reply'   ); ?>

								<?php endif; ?>

							</div>
						</div><!-- #bbp-topic-wrapper-<?php bbp_topic_id(); ?> -->

					<?php endwhile; ?>

				<?php elseif ( bbp_is_forum_private( bbp_get_topic_forum_id(), false ) ) : ?>

					<?php bbp_get_template_part( 'bbpress/no', 'access' ); ?>

				<?php endif; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
