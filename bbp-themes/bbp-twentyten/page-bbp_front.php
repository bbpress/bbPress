<?php
/**
 * Template Name: bbPress - Forum Index
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php get_template_part( 'loop', 'bbp_forums' ); ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>
