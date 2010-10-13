<?php
/**
 * bbPress Single Forum
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<div id="forum-<?php bbp_forum_id(); ?>" class="bbp-forum-info">
					<h1 class="entry-title"><?php bbp_forum_title(); ?></h1>
					<div class="entry-content">

						<?php the_content(); ?>

					</div>
				</div><!-- #topic-<?php bbp_forum_id(); ?> -->

				<?php if ( bbp_has_topics() ) : ?>

					<table class="bbp-forum-topics">
						<thead>
							<tr>
								<th><?php _e( 'Topic', 'bbpress' ); ?></th>
								<th><?php _e( 'Posts', 'bbpress' ); ?></th>
								<th><?php _e( 'Voices', 'bbpress' ); ?></th>
								<th><?php _e( 'Freshness', 'bbpress' ); ?></th>
							</tr>
						</thead>

						<tfoot>

							<td colspan="4">&nbsp;<?php // @todo - Moderation links ?></td>

						</tfoot>

						<tbody>

							<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

								<tr id="topic-<?php bbp_topic_id(); ?>" <?php post_class( 'forum_topic' ); ?>>

									<td class="bbp-topic-title">
										<a href="<?php bbp_topic_permalink(); ?>" title="<?php bbp_topic_title(); ?>"><?php bbp_topic_title(); ?></a>
									</td>

									<td class="bbp-topic-replies"><?php bbp_topic_reply_count(); ?></td>

									<td class="bbp-topic-voices"><?php //bbp_topic_voice_count(); ?></td>

									<td class="bbp-topic-freshness">
										<a href="<?php bbp_topic_permalink(); ?>"><?php bbp_topic_last_active(); ?></a>

										<?php //bbp_topic_author_permalink(); ?>

									</td>

								</tr><!-- #topic-<?php bbp_topic_id(); ?> -->

							<?php endwhile; ?>

						</tbody>

					</table><!-- .bbp-forum-topics -->

				<?php else : ?>

					<div id="topic-0" class="post">
						<div class="entry-content">
							<p><?php _e( 'Oh bother! This forum does not have any topics yet! Perhaps searching will help.', 'bbpress' ); ?></p>

							<?php get_search_form(); ?>

						</div><!-- .entry-content -->
					</div><!-- #post-0 -->

				<?php endif; ?>


			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>