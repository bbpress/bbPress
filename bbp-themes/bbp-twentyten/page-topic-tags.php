<?php

/**
 * Template Name: bbPress - Topic Tags
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

					<div id="bbp-topic-tags" class="bbp-topic-tags">
						<h1 class="entry-title"><?php the_title(); ?></h1>
						<div class="entry-content">

							<?php get_the_content() ? the_content() : _e( '<p>This is a collection of tags that are currently popular on our forums.</p>', 'bbpress' ); ?>

							<?php bbp_get_template_part( 'bbpress/nav', 'breadcrumb' ); ?>

							<div id="bbp-topic-hot-tags">

								<?php wp_tag_cloud( array( 'smallest' => 9, 'largest' => 38, 'number' => 80, 'taxonomy' => $bbp->topic_tag_id ) ); ?>

							</div>

						</div>
					</div><!-- #bbp-topic-tags -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
