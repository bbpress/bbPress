<?php

/**
 * Template Name: bbPress - User Login
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

					<div id="bbp-login" class="bbp-login">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php the_content(); ?>

							<?php if ( !is_user_logged_in() ) : ?>

								<fieldset>
									<legend><?php _e( 'Login', 'bbpress' ); ?></legend>

									<?php do_action( 'bbp_template_notices' ); ?>

									<?php wp_login_form( array( 'redirect' => $_SERVER['HTTP_REFERER'] ) ); ?>

								</fieldset>
							
							<?php else : ?>



							<?php endif; ?>

						</div>
					</div><!-- #bbp-login -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
