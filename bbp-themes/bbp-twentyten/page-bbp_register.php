<?php

/**
 * Template Name: bbPress - User Register
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="bbp-register" class="bbp-register">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php the_content(); ?>

							<?php if ( !is_user_logged_in() ) : ?>

								<?php get_template_part( 'form', 'bbp_user_register' ); ?>

							<?php else : ?>

								<p><?php _e( 'You&#8217;re already logged in, why do you need to register?', 'bbpress' ); ?></p>

							<?php endif; ?>

						</div>
					</div><!-- #bbp-register -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
