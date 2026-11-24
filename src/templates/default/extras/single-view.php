<?php

/**
 * Single View
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

	<div id="bbp-view-<?php bbp_view_id(); ?>" class="bbp-view">
		<h1 class="entry-title"><?php bbp_view_title(); ?></h1>
		<div class="entry-content">

			<?php bbp_get_template_part( 'content', 'single-view' ); ?>

		</div>
	</div><!-- #bbp-view-<?php bbp_view_id(); ?> -->

	<?php
	/** This action is documented in src/templates/default/extras/archive-forum.php. */
	do_action( 'bbp_after_main_content' ); ?>

<?php get_sidebar(); ?>
<?php get_footer();
