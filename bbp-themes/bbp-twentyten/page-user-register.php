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

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="bbp-register" class="bbp-register">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php the_content(); ?>

							<?php get_template_part( 'bbpress/form', 'user-register' ); ?>

						</div>
					</div><!-- #bbp-register -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
