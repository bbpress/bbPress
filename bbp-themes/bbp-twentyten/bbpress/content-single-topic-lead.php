<?php

/**
 * Single Topic Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

	<?php do_action( 'bbp_template_before_lead_topic' ); ?>

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

					<?php bbp_topic_admin_links(); ?>

				</td>
			</tr>
		</tfoot>

		<tbody>

			<tr class="bbp-topic-header">
				<td colspan="2">

					<span class="bbp-topic-post-date"><?php bbp_topic_post_date(); ?></span>

					<a href="#bbp-topic-<?php bbp_topic_id(); ?>" title="<?php bbp_topic_title(); ?>" class="bbp-topic-permalink">#<?php bbp_topic_id(); ?></a>

				</td>
			</tr>

			<tr id="post-<?php bbp_topic_id(); ?>" <?php post_class( 'bbp-forum-topic' ); ?>>

				<td class="bbp-topic-author">

					<?php bbp_topic_author_link( array( 'sep' => '<br />', 'show_role' => true ) ); ?>

					<?php if ( is_super_admin() ) : ?>

						<div class="bbp-topic-ip"><?php bbp_author_ip( bbp_get_topic_id() ); ?></div>

					<?php endif; ?>

				</td>

				<td class="bbp-topic-content">

					<?php bbp_topic_content(); ?>

				</td>

			</tr><!-- #post-<?php bbp_topic_id(); ?> -->

		</tbody>
	</table><!-- #bbp-topic-<?php bbp_topic_id(); ?> -->

	<?php do_action( 'bbp_template_after_lead_topic' ); ?>
