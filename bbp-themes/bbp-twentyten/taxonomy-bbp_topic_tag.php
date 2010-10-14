<?php
/**
 * bbPress Topic Tag
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<div id="topic-tag-<?php //bbp_topic_tag_id(); ?>" class="bbp-topic-tag-info">
					<h1 class="entry-title"><?php //bbp_topic_tag_title(); ?></h1>
					<div class="entry-content">

						<?php //bbp_topic_tag_description(); ?>

					</div>
				</div><!-- #topic-tag-<?php //bbp_topic_tag_id(); ?> -->

				<?php get_template_part( 'loop', 'bbp_topics' ); ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
