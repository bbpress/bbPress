<?php
/**
 * The loop that displays bbPress topics.
 *
 * @package bbPress
 * @subpackage Twenty Ten
 */

if ( bbp_get_query_name() || bbp_has_topics() ) : ?>

	<?php get_template_part( 'pagination', 'bbp_topics' ); ?>

	<table class="bbp-topics" id="bbp-forum-<?php bbp_topic_id(); ?>">
		<thead>
			<tr>
				<th class="bbp-topic-title"><?php _e( 'Topic', 'bbpress' ); ?></th>
				<th class="bbp-topic-replie-count"><?php _e( 'Replies', 'bbpress' ); ?></th>
				<th class="bbp-topic-voice-count"><?php _e( 'Voices', 'bbpress' ); ?></th>
				<th class="bbp-topic-freshness"><?php _e( 'Freshness', 'bbpress' ); ?></th>
				<?php if ( ( bbp_is_user_home() && ( bbp_is_favorites() || bbp_is_subscriptions() ) ) ) : ?><th class="bbp-topic-action"><?php _e( 'Remove', 'bbpress' ); ?></th><?php endif; ?>
			</tr>
		</thead>

		<tfoot>
			<tr><td colspan="<?php echo ( bbp_is_user_home() && ( bbp_is_favorites() || bbp_is_subscriptions() ) ) ? '5' : '4'; ?>">&nbsp</td></tr>
		</tfoot>

		<tbody>

			<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

				<tr id="topic-<?php bbp_topic_id(); ?>" <?php bbp_topic_class(); ?>>

					<td class="bbp-topic-title">
						<a href="<?php bbp_topic_permalink(); ?>" title="<?php bbp_topic_title(); ?>"><?php bbp_topic_title(); ?></a>

						<p class="bbp-topic-meta">

							<?php printf( 'Started by: <a href="%1$s">%2$s</a>', bbp_get_topic_author_url(), bbp_get_topic_author() ); ?>

							<?php if ( !bbp_is_forum() ) printf( 'in: <a href="%1$s">%2$s</a>', bbp_get_forum_permalink( bbp_get_topic_forum_id() ), bbp_get_forum_title( bbp_get_topic_forum_id() ) ); ?>

						</p>

					</td>

					<td class="bbp-topic-reply-count"><?php bbp_topic_reply_count(); ?></td>

					<td class="bbp-topic-voice-count"><?php bbp_topic_voice_count(); ?></td>

					<td class="bbp-topic-freshness"><?php bbp_topic_freshness_link(); ?></td>

					<?php if ( bbp_is_user_home() ) : ?>

						<?php if ( bbp_is_favorites() ) : ?>

							<td class="bbp-topic-action">

								<?php bbp_user_favorites_link( array( 'mid' => '+', 'post' => '' ), array( 'pre' => '', 'mid' => '&times;', 'post' => '' ) ); ?>

							</td>

						<?php elseif ( bbp_is_subscriptions() ) : ?>

							<td class="bbp-topic-action">

								<?php bbp_user_subscribe_link( array( 'before' => '', 'subscribe' => '+', 'unsubscribe' => '&times;' ) ); ?>

							</td>

						<?php endif; ?>

					<?php endif; ?>

				</tr><!-- #topic-<?php bbp_topic_id(); ?> -->

			<?php endwhile; ?>

		</tbody>

	</table><!-- #bbp-forum-<?php bbp_topic_id(); ?> -->

	<?php get_template_part( 'pagination', 'bbp_topics' ); ?>

<?php else : ?>

	<div id="topic-0" class="post">
		<div class="entry-content">
			<p><?php _e( 'Oh bother! No topics were found here! Perhaps searching will help.', 'bbpress' ); ?></p>

			<?php get_search_form(); ?>

		</div><!-- .entry-content -->
	</div><!-- #post-0 -->

<?php endif; ?>
