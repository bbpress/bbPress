<?php
/**
 * The loop that displays bbPress replies.
 *
 * @package bbPress
 * @subpackage Twenty Ten
 *
 * @todo - Not use table rows
 */
?>

<?php if ( bbp_has_replies() ) : ?>

	<?php get_template_part( 'pagination', 'bbp_replies' ); ?>

	<table class="bbp-replies" id="topic-<?php bbp_topic_id(); ?>-replies">
		<thead>
			<tr>
				<th class="bbp-reply-author"><?php  _e( 'Author',  'bbpress' ); ?></th>
				<th class="bbp-reply-content"><?php _e( 'Replies', 'bbpress' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="2"><?php bbp_topic_admin_links(); ?></td>
			</tr>
		</tfoot>

		<tbody>

			<?php while ( bbp_replies() ) : bbp_the_reply(); ?>

				<tr class="bbp-reply-header">
					<td class="bbp-reply-author">

						<?php bbp_reply_author_display_name(); ?>

					</td>
					<td class="bbp-reply-content">
						<a href="<?php bbp_reply_url(); ?>" title="<?php bbp_reply_title(); ?>">#</a>

						<?php printf( __( 'Posted on %1$s at %2$s', 'bbpress' ), get_the_date(), esc_attr( get_the_time() ) ); ?>

						<span><?php bbp_reply_admin_links(); ?></span>
					</td>
				</tr>

				<tr id="reply-<?php bbp_reply_id(); ?>" <?php bbp_reply_class(); ?>>

					<td class="bbp-reply-author"><?php bbp_reply_author_link(); ?></td>

					<td class="bbp-reply-content">

						<?php the_content(); ?>

					</td>

				</tr><!-- #topic-<?php bbp_topic_id(); ?>-replies -->

			<?php endwhile; ?>

		</tbody>

	</table>

	<?php get_template_part( 'pagination', 'bbp_replies' ); ?>

<?php endif; ?>
