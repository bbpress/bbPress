<?php
/**
 * bbPress Single Forum
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<div id="forum-<?php bbp_forum_id(); ?>" class="bbp-forum-info">
					<h1 class="entry-title"><?php bbp_forum_title(); ?></h1>
					<div class="entry-content">

						<?php the_content(); ?>

					</div>
				</div><!-- #forum-<?php bbp_forum_id(); ?> -->

				<?php get_template_part( 'loop', 'bbp_forums' ); ?>

				<?php get_template_part( 'loop', 'bbp_topics' ); ?>

				<?php get_template_part( 'form', 'bbp_topic' ); ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>