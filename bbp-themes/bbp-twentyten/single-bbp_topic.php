<?php
/**
 * bbPress Single Topic
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="bbp-topic-wrapper-<?php bbp_topic_id(); ?>" class="bbp-topic-wrapper">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php bbp_topic_tag_list(); ?>

							<table class="bbp-topic" id="bbp-topic-<?php bbp_topic_id(); ?>">
								<thead>
									<tr>
										<th><?php _e( 'Creator', 'bbpress' ); ?></th>
										<th><?php _e( 'Topic', 'bbpress' ); ?></th>
									</tr>
								</thead>

								<tfoot>
									<tr>
										<td colspan="2"><?php bbp_topic_admin_links(); ?></td>
									</tr>
								</tfoot>

								<tbody>

									<tr id="reply-<?php bbp_topic_id(); ?>" <?php post_class( 'bbp-forum-topic' ); ?>>

										<td class="bbp-topic-author">

											<?php bbp_topic_author_box(); ?>

										</td>

										<td class="bbp-topic-content">

											<?php the_content(); ?>

											<div class="entry-meta">

												<?php
													// @todo - abstract
													printf( __( 'Posted at %1$s on %2$s', 'bbpress' ),
														esc_attr( get_the_time() ),
														get_the_date()
													);
												?>

											</div>
										</td>

									</tr><!-- #bbp-topic-<?php bbp_topic_id(); ?> -->

								</tbody>
							</table><!-- #bbp-topic-<?php bbp_topic_id(); ?> -->

						<?php endwhile; ?>

						<?php get_template_part( 'loop', 'bbp_replies' ); ?>

						<?php get_template_part( 'form', 'bbp_reply' ); ?>

					</div>
				</div><!-- #bbp-topic-wrapper-<?php bbp_topic_id(); ?> -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>