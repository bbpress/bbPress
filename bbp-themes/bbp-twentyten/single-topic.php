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

				<?php if ( bbp_is_forum_public( bbp_get_topic_forum_id(), false ) || current_user_can( 'read_private_forums' ) ) : ?>

					<?php while ( have_posts() ) : the_post(); ?>

						<div id="bbp-topic-wrapper-<?php bbp_topic_id(); ?>" class="bbp-topic-wrapper">
							<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
							<div class="entry-content">

								<?php bbp_topic_tag_list(); ?>

								<?php bbp_single_topic_description(); ?>

								<div id="ajax-response"></div>

								<?php get_template_part( 'bbpress/single', 'topic'   ); ?>

								<?php if ( bbp_get_query_name() || bbp_has_replies() ) : ?>

									<?php get_template_part( 'bbpress/pagination', 'replies' ); ?>

									<?php get_template_part( 'bbpress/loop',       'replies' ); ?>

									<?php get_template_part( 'bbpress/pagination', 'replies' ); ?>

									<?php get_template_part( 'bbpress/form',       'reply'   ); ?>

								<?php endif; ?>

							</div>
						</div><!-- #bbp-topic-wrapper-<?php bbp_topic_id(); ?> -->

					<?php endwhile; ?>

				<?php else : ?>

					<div id="forum-private" class="bbp-forum-info">
						<h1 class="entry-title"><?php _e( 'Private', 'bbpress' ); ?></h1>
						<div class="entry-content">

							<div class="bbp-template-notice info">
								<p><?php _e( 'You do not have permission to view this forum.', 'bbpress' ); ?></p>
							</div>

						</div>
					</div><!-- #forum-private -->

				<?php endif; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
