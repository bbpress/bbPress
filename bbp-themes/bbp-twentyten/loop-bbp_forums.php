<?php
/**
 * The loop that displays bbPress forums.
 *
 * @package bbPress
 * @subpackage Twenty Ten
 */
?>

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
			<tr><td colspan="4">&nbsp;<?php // @todo - Moderation links ?></td></tr>
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

			<?php endwhile; global $bbp_forums_template; unset( $bbp_forums_template ); ?>

	</table>

<?php endif; ?>
