<?php
/**
 * Template Name: bbPress - User Profile
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php
					// Profile details
					get_template_part( 'user', 'bbp_details' );

					// Subsciptions
					get_template_part( 'user', 'bbp_subscriptions' );

					// Favorite topics
					get_template_part( 'user', 'bbp_favorites' );

					// Topics created
					get_template_part( 'user', 'bbp_topics_created' );

				?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
