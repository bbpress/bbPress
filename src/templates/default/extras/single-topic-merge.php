<?php

/**
 * Merge topic page
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

	<?php
	/** This action is documented in src/templates/default/extras/archive-forum.php. */
	do_action( 'bbp_before_main_content' ); ?>

	<?php
	/** This action is documented in src/templates/default/bbpress/form-forum.php. */
	do_action( 'bbp_template_notices' ); ?>

	<?php while ( have_posts() ) : the_post(); ?>

		<div id="bbp-edit-page" class="bbp-edit-page">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="entry-content">

				<?php bbp_get_template_part( 'form', 'topic-merge' ); ?>

			</div>
		</div><!-- #bbp-edit-page -->

	<?php endwhile; ?>

	<?php
	/** This action is documented in src/templates/default/extras/archive-forum.php. */
	do_action( 'bbp_after_main_content' ); ?>

<?php get_sidebar(); ?>
<?php get_footer();
