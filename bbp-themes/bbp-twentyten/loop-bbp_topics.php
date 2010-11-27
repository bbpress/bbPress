<?php
/**
 * The loop that displays bbPress topics.
 *
 * @package bbPress
 * @subpackage Twenty Ten
 */
?>

<?php
if ( !bbp_is_favorites() ) :
	$_bbp_query = bbp_has_topics();
else :
	$_bbp_query = true;
endif;
?>

<?php if ( $_bbp_query ) : ?>

	<?php get_template_part( 'pagination', 'bbp_topics' ); ?>

	<table class="bbp-topics" id="bbp-forum-<?php bbp_topic_id(); ?>">
		<thead>
			<tr>
				<th class="bbp-topic-title"><?php _e( 'Topic', 'bbpress' ); ?></th>
				<th class="bbp-topic-replie-count"><?php _e( 'Replies', 'bbpress' ); ?></th>
				<th class="bbp-topic-voice-count"><?php _e( 'Voices', 'bbpress' ); ?></th>
				<th class="bbp-topic-freshness"><?php _e( 'Freshness', 'bbpress' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr><td colspan="4">&nbsp</td></tr>
		</tfoot>

		<tbody>

			<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

				<tr id="topic-<?php bbp_topic_id(); ?>" <?php post_class( 'bbp-topic' ); ?>>

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

				</tr><!-- #topic-<?php bbp_topic_id(); ?> -->

			<?php endwhile; ?>

		</tbody>

	</table><!-- #bbp-forum-<?php bbp_topic_id(); ?> -->

	<?php get_template_part( 'pagination', 'bbp_topics' ); ?>

<?php else : ?>

	<div id="topic-0" class="post">
		<div class="entry-content">
			<p><?php _e( 'Oh bother! This forum does not have any topics yet! Perhaps searching will help.', 'bbpress' ); ?></p>

			<?php get_search_form(); ?>

		</div><!-- .entry-content -->
	</div><!-- #post-0 -->

<?php endif; ?>
