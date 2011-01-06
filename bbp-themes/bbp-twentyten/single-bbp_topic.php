<?php

/**
 * bbPress Single Topic
 *
 * @package bbPress
 * @subpackage Themes
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="bbp-topic-wrapper-<?php bbp_topic_id(); ?>" class="bbp-topic-wrapper">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php bbp_topic_tag_list(); ?>

							<div id="ajax-response"></div>

							<table class="bbp-topic" id="bbp-topic-<?php bbp_topic_id(); ?>">
								<thead>
									<tr>
										<th class="bbp-topic-author"><?php _e( 'Creator', 'bbpress' ); ?></th>
										<th class="bbp-topic-content">

											<?php _e( 'Topic', 'bbpress' ); ?>

											<?php bbp_user_subscribe_link(); ?>

											<?php bbp_user_favorites_link(); ?>

										</th>
									</tr>
								</thead>

								<tfoot>
									<tr>
										<td colspan="2">
											<span class="bbp-topic_replies"><?php bbp_topic_replies_link(); ?></span>
											<span class="bbp-topic_voices"><?php printf( _n( '(%s voice)', '(%s voices)', bbp_get_topic_voice_count(), 'bbpress' ), bbp_get_topic_voice_count() ); ?></span>
											<span class="bbp-admin-links"><?php bbp_topic_admin_links(); ?><span>
										</td>
									</tr>
								</tfoot>

								<tbody>

									<tr class="bbp-topic-header">
										<td class="bbp-topic-author"><?php bbp_topic_author_display_name(); ?></td>

										<td class="bbp-topic-content">
											<a href="#bbp-topic-<?php bbp_topic_id(); ?>" title="<?php bbp_topic_title(); ?>">#</a>

											<?php printf( __( 'Posted on %1$s at %2$s', 'bbpress' ), get_the_date(), esc_attr( get_the_time() ) ); ?>

										</td>
									</tr>

									<tr id="reply-<?php bbp_topic_id(); ?>" <?php post_class( 'bbp-forum-topic' ); ?>>

										<td class="bbp-topic-author"><?php bbp_topic_author_link(); ?></td>

										<td class="bbp-topic-content">

											<?php the_content(); ?>

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
