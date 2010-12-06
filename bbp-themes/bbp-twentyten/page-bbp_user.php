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
					get_template_part( 'profile', 'bbp_details' );

					// Subsciptions
					get_template_part( 'profile', 'bbp_subscriptions' );

					// Favorite topics
					get_template_part( 'profile', 'bbp_favorites' );

					// Topics created
					get_template_part( 'profile', 'bbp_topics_created' );

					// Blog posts
					get_template_part( 'profile', 'posts' );
				?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
