<?php

/**
 * bbPress Single Forum
 *
 * @package bbPress
 * @subpackage Themes
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<?php if ( !bbp_is_forum_private() || current_user_can( 'read_private_forums' ) ) : ?>

						<div id="forum-<?php bbp_forum_id(); ?>" class="bbp-forum-info">
							<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
							<div class="entry-content">

								<?php the_content(); ?>

								<?php get_template_part( 'loop', 'bbp_forums' ); ?>

								<?php if ( !bbp_is_forum_category() ) : ?>

									<?php get_template_part( 'loop', 'bbp_topics' ); ?>

									<?php get_template_part( 'form', 'bbp_topic' ); ?>

								<?php endif; ?>

							</div>
						</div><!-- #forum-<?php bbp_forum_id(); ?> -->

					<?php else : ?>

						<div id="forum-private" class="bbp-forum-info">
							<h1 class="entry-title"><?php _e( 'Private Forum!', 'bbpress' ); ?></h1>
							<div class="entry-content">

								<div class="bbp-template-notice">
									<p><?php _e( 'This forum is marked as private, and you do not have permission to view it.', 'bbpress' ); ?></p>
								</div>

							</div>
						</div><!-- #forum-private -->

					<?php endif; ?>

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
