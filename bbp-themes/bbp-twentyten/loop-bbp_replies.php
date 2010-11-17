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

	<table class="bbp-replies" id="topic-<?php bbp_topic_id(); ?>">
		<thead>
			<tr>
				<th class="bbp-reply-author"><?php _e( 'Author', 'bbpress' ); ?></th>
				<th class="bbp-reply-content"><?php _e( 'Replies', 'bbpress' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="2">&nbsp;</td>
			</tr>
		</tfoot>

		<tbody>

			<?php while ( bbp_replies() ) : bbp_the_reply(); ?>

				<tr id="reply-<?php bbp_reply_id(); ?>" <?php post_class( 'bbp-reply' ); ?>>

					<td class="bbp-reply-author">
						<?php
							// @todo - abstract
							printf (
								'<a href="%1$s" title="%2$s">%3$s<br />%4$s</a>',
								get_author_posts_url( get_the_author_meta( 'ID' ) ),
								sprintf( __( 'View %s\'s profile' ), bbp_get_topic_author_display_name() ),
								bbp_get_topic_author_avatar(),
								bbp_get_topic_author_display_name()
							);
						?>
					</td>

					<td class="bbp-reply-content">

						<?php the_content(); // @todo - bbp_reply_content(); ?>

						<div class="entry-meta">
							<a href="#reply-<?php bbp_reply_id(); ?>" title="<?php bbp_reply_title(); ?>"><?php bbp_reply_title(); ?></a>

							<?php
								// @todo - abstract
								printf( __( 'Posted at %2$s on %3$s', 'bbpress' ),
									'meta-prep meta-prep-author',
									esc_attr( get_the_time() ),
									get_the_date()
								);
							?>

						</div><!-- .entry-meta -->
					</td>

				</tr><!-- #topic-<?php bbp_topic_id(); ?> -->

			<?php endwhile; ?>

		</tbody>

	</table>

	<?php get_template_part( 'pagination', 'bbp_replies' ); ?>

<?php endif; ?>
