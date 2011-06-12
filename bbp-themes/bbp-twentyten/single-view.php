<?php

/**
 * Single View
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<div id="bbp-view-<?php bbp_view_id(); ?>" class="bbp-view">
					<h1 class="entry-title"><?php bbp_view_title(); ?></h1>
					<div class="entry-content">

						<?php bbp_get_template_part( 'bbpress/content', 'single-view' ); ?>

					</div>
				</div><!-- #bbp-view-<?php bbp_view_id(); ?> -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
