<?php

/**
 * Split/merge topic page
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) the_post(); ?>

					<div id="bbp-edit-page" class="bbp-edit-page">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php if ( bbp_is_topic_merge() ) : ?>

								<?php get_template_part( 'form', 'bbp_merge' ); ?>

							<?php elseif ( bbp_is_topic_split() ) : ?>

								<?php get_template_part( 'form', 'bbp_split' ); ?>

							<?php endif; ?>

						</div>
					</div><!-- #bbp-edit-page -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
