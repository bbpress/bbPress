<?php
/**
 * Template Name: bbPress - Topics (No Replies)
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="topics-front" class="bbp-topics-front">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php the_content(); ?>

							<?php bbp_set_query_name( 'bbp_no_replies' ); ?>

							<?php bbp_has_topics( array( 'meta_key' => '_bbp_topic_reply_count', 'meta_value' => 1, 'meta_compare' => '<', 'orderby' => '' ) ); ?>

							<?php get_template_part( 'loop', 'bbp_topics' ); ?>

							<?php bbp_reset_query_name(); ?>

						</div>
					</div><!-- #topics-front -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
