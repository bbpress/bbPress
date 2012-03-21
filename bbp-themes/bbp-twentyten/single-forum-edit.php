<?php

/**
 * Edit handler for forums
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="bbp-edit-page" class="bbp-edit-page">
						<h1 class="entry-title"><?php the_title(); ?></h1>
						<div class="entry-content">

							<?php bbp_get_template_part( 'form', 'forum' ); ?>

						</div>
					</div><!-- #bbp-edit-page -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
