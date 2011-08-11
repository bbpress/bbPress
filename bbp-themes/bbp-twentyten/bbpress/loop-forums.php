<?php

/**
 * Forums Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

	<?php do_action( 'bbp_template_before_forums_loop' ); ?>

	<table class="bbp-forums">

		<thead>
			<tr>
				<th class="bbp-forum-info"><?php _e( 'Forum', 'bbpress' ); ?></th>
				<th class="bbp-forum-topic-count"><?php _e( 'Topics', 'bbpress' ); ?></th>
				<th class="bbp-forum-reply-count"><?php bbp_show_lead_topic() ? _e( 'Replies', 'bbpress' ) : _e( 'Posts', 'bbpress' ); ?></th>
				<th class="bbp-forum-freshness"><?php _e( 'Freshness', 'bbpress' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr><td colspan="4">&nbsp;</td></tr>
		</tfoot>

		<tbody>

			<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

				<tr id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class(); ?>>

					<td class="bbp-forum-info">

						<?php do_action( 'bbp_theme_before_forum_title' ); ?>

						<a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>"><?php bbp_forum_title(); ?></a>

						<?php do_action( 'bbp_theme_after_forum_title' ); ?>

						<?php do_action( 'bbp_theme_before_forum_sub_forums' ); ?>

						<?php bbp_list_forums(); ?>

						<?php do_action( 'bbp_theme_after_forum_sub_forums' ); ?>

						<?php do_action( 'bbp_theme_before_forum_description' ); ?>

						<div class="bbp-forum-description"><?php the_content(); ?></div>

						<?php do_action( 'bbp_theme_after_forum_description' ); ?>

					</td>

					<td class="bbp-forum-topic-count"><?php bbp_forum_topic_count(); ?></td>

					<td class="bbp-forum-reply-count"><?php bbp_show_lead_topic() ? bbp_forum_reply_count() : bbp_forum_post_count(); ?></td>

					<td class="bbp-forum-freshness">

						<?php do_action( 'bbp_theme_before_forum_freshness_link' ); ?>

						<?php bbp_forum_freshness_link(); ?>

						<?php do_action( 'bbp_theme_after_forum_freshness_link' ); ?>

						<p class="bbp-topic-meta">

							<?php do_action( 'bbp_theme_before_topic_author' ); ?>

							<span class="bbp-topic-freshness-author"><?php bbp_author_link( array( 'post_id' => bbp_get_forum_last_active_id(), 'size' => 14 ) ); ?></span>

							<?php do_action( 'bbp_theme_after_topic_author' ); ?>

						</p>
					</td>

				</tr><!-- bbp-forum-<?php bbp_forum_id(); ?> -->

			<?php endwhile; ?>

		</tbody>

	</table>

	<?php do_action( 'bbp_template_after_forums_loop' ); ?>
