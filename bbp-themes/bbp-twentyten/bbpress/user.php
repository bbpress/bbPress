<?php

/**
 * User Profile
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php
					// Profile details
					bbp_get_template_part( 'bbpress/user', 'details' );

					// Subsciptions
					bbp_get_template_part( 'bbpress/user', 'subscriptions' );

					// Favorite topics
					bbp_get_template_part( 'bbpress/user', 'favorites' );

					// Topics created
					bbp_get_template_part( 'bbpress/user', 'topics-created' );

				?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
