<?php
/**
 * Template Name: bbPress Front Page
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php if ( bbp_has_forums() ) : ?>

					<table class="forums">

						<thead>
							<tr>
								<th><?php _e( 'Forums', 'bbpress' ); ?></th>
								<th><?php _e( 'Topics', 'bbpress' ); ?></th>
								<th><?php _e( 'Posts', 'bbpress' ); ?></th>
								<th><?php _e( 'Freshness', 'bbpress' ); ?></th>
							</tr>
						</thead>

						<tfoot>

							<?php // @todo - Moderation links ?>

						</tfoot>

						<tbody>

							<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

								<tr id="forum-<?php bbp_forum_id(); ?>" <?php post_class(); ?>>

									<td class="bbp-forum-info">
										<a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>"><?php bbp_forum_title(); ?></a>
										<div class="bbp-forum-description"><?php the_content(); ?></div>
									</td>

									<td class="bbp-forum-topic-count"><?php bbp_forum_topic_count(); ?></td>

									<td class="bbp-forum-topic-replies"><?php bbp_forum_topic_reply_count(); ?></td>

									<td class="bbp-forum-freshness"><?php bbp_forum_last_active(); ?></td>

								</tr><!-- bbp-forum-<?php bbp_forum_id(); ?> -->

						<?php endwhile; ?>

					</table>

				<?php else : ?>

					<div id="forum-0" class="post error404 not-found">
						<h1 class="entry-title"><?php _e( 'Not Found', 'bbpress' ); ?></h1>
						<div class="entry-content">
							<p><?php _e( 'Apologies, but the page you requested could not be found. Perhaps searching will help.', 'bbpress' ); ?></p>

							<?php get_search_form(); ?>

						</div><!-- .entry-content -->
					</div><!-- #post-0 -->
				
				<?php endif; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>
