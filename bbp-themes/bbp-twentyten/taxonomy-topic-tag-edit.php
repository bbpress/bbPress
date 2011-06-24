<?php

/**
 * Topic Tag Edit
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<div id="topic-tag" class="bbp-topic-tag">
					<h1 class="entry-title"><?php printf( __( 'Topic Tag: %s', 'bbpress' ), '<span>' . bbp_get_topic_tag_name() . '</span>' ); ?></h1>

					<div class="entry-content">

						<?php bbp_breadcrumb(); ?>

						<?php bbp_topic_tag_description(); ?>

						<?php do_action( 'bbp_template_before_topic_tag_edit' ); ?>

						<?php bbp_get_template_part( 'bbpress/form', 'topic-tag' ); ?>

						<?php do_action( 'bbp_template_after_topic_tag_edit' ); ?>

					</div>
				</div><!-- #topic-tag -->
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
