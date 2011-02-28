<?php

/**
 * Edit handler for topics and replies
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

							<?php if ( bbp_is_reply_edit() ) : ?>

								<?php get_template_part( 'bbpress/form', 'reply' ); ?>

							<?php elseif ( bbp_is_topic_edit() ) : ?>

								<?php get_template_part( 'bbpress/form', 'topic' ); ?>

							<?php endif; ?>

						</div>
					</div><!-- #bbp-edit-page -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
