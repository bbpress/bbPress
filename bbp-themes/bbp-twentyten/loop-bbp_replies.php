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

	<?php while ( bbp_replies() ) : bbp_the_reply(); ?>

		<tr id="reply-<?php bbp_reply_id(); ?>" <?php post_class( 'topic_reply' ); ?>>

			<td class="bbp-reply-author">
				<?php
					// @todo - abstract
					printf (
						'<a href="%1$s" title="%2$s">%3$s</a>',
						get_author_posts_url( get_the_author_meta( 'ID' ) ),
						sprintf( __( 'Posts by %s' ), esc_attr( get_author_name() ) ),
						get_avatar( get_the_author_meta( 'ID' ), 40 )
					);
				?>
				<br />
				<?php
					// @todo - abstract
					printf(
						'<a href="%1$s" title="%2$s" class="url">%3$s</a>',
						get_author_posts_url( get_the_author_meta( 'ID' ) ),
						sprintf( __( 'Posts by %s' ), esc_attr( get_author_name() ) ),
						get_the_author()
					);
				?>
			</td>

			<td class="bbp-reply-content">

				<?php the_content(); // @todo - bbp_reply_content(); ?>

				<div class="entry-meta">
					<a href="#reply-<?php bbp_reply_id(); ?>" title="<?php bbp_topic_title(); ?>"><?php bbp_topic_title(); ?></a>

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

<?php endif; ?>
