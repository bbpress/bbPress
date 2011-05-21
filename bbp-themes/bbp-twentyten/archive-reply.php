<?php

/**
 * bbPress - Reply Archive
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="topics-front" class="bbp-topics-front">
						<h1 class="entry-title"><?php the_title(); ?></h1>
						<div class="entry-content">

							<?php bbp_get_template_part( 'bbpress/nav', 'breadcrumb' ); ?>

							<?php do_action( 'bbp_template_before_replies_archive' ); ?>

							<?php if ( bbp_has_topics() ) : ?>

								<?php bbp_get_template_part( 'bbpress/pagination', 'replies' ); ?>

								<?php bbp_get_template_part( 'bbpress/loop',       'replies' ); ?>

								<?php bbp_get_template_part( 'bbpress/pagination', 'replies' ); ?>

							<?php else : ?>

								<?php bbp_get_template_part( 'bbpress/no', 'topics' ); ?>

							<?php endif; ?>

							<?php do_action( 'bbp_template_after_replies_archive' ); ?>

						</div>
					</div><!-- #topics-front -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
