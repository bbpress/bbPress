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
				<div id="bbp-reply-wrapper-<?php bbp_reply_id(); ?>" class="bbp-reply-wrapper">
					<div class="entry-content">

						<?php get_template_part( 'loop', 'bbp_replies' ); ?>

					</div><!-- .entry-content -->
				</div><!-- #bbp-reply-wrapper-<?php bbp_reply_id(); ?> -->
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>