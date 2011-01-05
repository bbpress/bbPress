<?php
/**
 * Edit topic/reply page
 *
 * @package bbPress
 * @subpackage Themes
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

								<?php get_template_part( 'form', 'bbp_reply' ); ?>

							<?php elseif ( bbp_is_topic_edit() ) : ?>

								<?php get_template_part( 'form', 'bbp_topic' ); ?>

							<?php endif; ?>

						</div>
					</div><!-- #bbp-edit-page -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
