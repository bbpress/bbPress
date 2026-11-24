<?php

/**
 * Template Name: bbPress - User Register
 *
 * @package bbPress
 * @subpackage Theme
 */

// No logged in users
bbp_logged_in_redirect();

// Begin Template
get_header(); ?>

	<?php
	/** This action is documented in src/templates/default/extras/archive-forum.php. */
	do_action( 'bbp_before_main_content' ); ?>

	<?php
	/** This action is documented in src/templates/default/bbpress/form-forum.php. */
	do_action( 'bbp_template_notices' ); ?>

	<?php while ( have_posts() ) : the_post(); ?>

		<div id="bbp-register" class="bbp-register">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="entry-content">

				<?php the_content(); ?>

				<div id="bbpress-forums" class="bbpress-wrapper">

					<?php bbp_breadcrumb(); ?>

					<?php bbp_get_template_part( 'form', 'user-register' ); ?>

				</div>
			</div>
		</div><!-- #bbp-register -->

	<?php endwhile; ?>

	<?php
	/** This action is documented in src/templates/default/extras/archive-forum.php. */
	do_action( 'bbp_after_main_content' ); ?>

<?php get_sidebar(); ?>
<?php get_footer();
