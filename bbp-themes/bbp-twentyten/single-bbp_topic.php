<?php
/**
 * bbPress Single Topic
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<div id="topic-<?php bbp_topic_id(); ?>" class="bbp-topic-info">
					<h1 class="entry-title"><?php bbp_topic_title(); ?></h1>
					<div class="entry-content">

						<?php the_content(); ?>

					</div>
				</div><!-- #topic-<?php bbp_topic_id(); ?> -->

				<?php get_template_part( 'loop', 'bbp_replies' ); ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>