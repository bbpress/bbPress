<?php

/**
 * bbPress User Profile Edit
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php bbp_get_template_part( 'bbpress/user', 'details' ); ?>

				<div class="entry-content bbp-edit-user">

					<?php bbp_get_template_part( 'bbpress/form', 'user-edit' ); ?>

				</div>
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
