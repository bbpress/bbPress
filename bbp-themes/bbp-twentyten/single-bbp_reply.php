<?php
/**
 * bbPress Single Reply
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php get_template_part( 'loop', 'bbp_replies' ); ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>