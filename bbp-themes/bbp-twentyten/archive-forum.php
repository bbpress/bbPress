<?php

/**
 * bbPress - Forum Archive
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<div id="forum-front" class="bbp-forum-front">
					<h1 class="entry-title"><?php bbp_forum_archive_title(); ?></h1>
					<div class="entry-content">

						<?php bbp_breadcrumb(); ?>

						<?php do_action( 'bbp_template_before_forums_index' ); ?>

						<?php if ( bbp_has_forums() ) : ?>

							<?php bbp_get_template_part( 'bbpress/loop', 'forums' ); ?>

						<?php else : ?>

							<?php bbp_get_template_part( 'bbpress/no',   'forums' ); ?>

						<?php endif; ?>

						<?php do_action( 'bbp_template_after_forums_index' ); ?>

					</div>
				</div><!-- #forum-front -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
