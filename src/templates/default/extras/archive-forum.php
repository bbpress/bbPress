<?php

/**
 * bbPress - Forum Archive
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

	<?php
	/**
	 * Fires before the main bbPress content.
	 *
	 * @since 2.1.0 bbPress (r3734)
	 */
	do_action( 'bbp_before_main_content' ); ?>

	<?php
	/** This action is documented in src/templates/default/bbpress/form-forum.php. */
	do_action( 'bbp_template_notices' ); ?>

	<div id="forum-front" class="bbp-forum-front">
		<h1 class="entry-title"><?php bbp_forum_archive_title(); ?></h1>
		<div class="entry-content">

			<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>

		</div>
	</div><!-- #forum-front -->

	<?php
	/**
	 * Fires after the main bbPress content.
	 *
	 * @since 2.1.0 bbPress (r3734)
	 */
	do_action( 'bbp_after_main_content' ); ?>

<?php get_sidebar(); ?>
<?php get_footer();
