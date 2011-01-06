<?php
/**
 * Merge topic form
 *
 * @package bbPress
 * @subpackage Themes
 */
?>

<?php if ( is_user_logged_in() && current_user_can( 'edit_topic', bbp_get_topic_id() ) ) : ?>

	<div id="merge-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-merge">

		<form id="merge_topic" name="merge_topic" method="post" action="">

			<fieldset>

				<legend><?php printf( __( 'Merge topic "%s"', 'bbpress' ), bbp_get_topic_title() ); ?></legend>

				<div class="alignleft">

					<p><?php _e( 'Select the topic to merge this one into. The destination topic will remain the lead topic, and this one will change into a reply.', 'bbpress' ); ?></p>

					<p><?php _e( 'To keep this topic as the lead, go to the other topic and use the merge tool from there instead.', 'bbpress' ); ?></p>

					<div class="bbp-template-notice">
						<p><?php _e( '<strong>NOTE:</strong> All replies within both topics will be merged chronologically. The order of the merged replies is based on the time and date they were posted.', 'bbpress' ); ?></p>
					</div>

					<div class="bbp-template-notice error">
						<p><?php _e( '<strong>WARNING:</strong> This process cannot undone, so double-check everything before you .', 'bbpress' ); ?></p>
					</div>

					<?php // @todo Make a codex and add the merge topic docs. ?>
					<?php // printf( __( 'For more information, check <a href="%s">this documentation</a>.', 'bbpress' ), 'http://codex.bbpress.org/Merge_Topics' ); ?>

					<fieldset>
						<legend><?php _e( 'Destination', 'bbpress' ); ?></legend>
						<div>
							<label for="bbp_destination_topic"><?php _e( 'Merge with this topic:', 'bbpress' ); ?></label>
							<?php
								global $bbp;
								bbp_dropdown( array( 'post_type' => $bbp->topic_id, 'post_parent' => bbp_get_topic_forum_id( bbp_get_topic_id() ), 'selected' => -1, 'exclude' => bbp_get_topic_id(), 'select_id' => 'bbp_destination_topic', 'tab' => 4, 'none_found' => __( 'No topics were found to which the topic could be merged to!', 'bbpress' ) ) );
							?>
						</div>
					</fieldset>

					<fieldset>
						<legend><?php _e( 'Topic Extras', 'bbpress' ); ?></legend>

						<div>

							<?php if ( bbp_is_subscriptions_active() ) : ?>

								<input name="bbp_topic_subscribers" id="bbp_topic_subscribers" type="checkbox" value="1" checked="checked" tabindex="6" />
								<label for="bbp_topic_subscribers"><?php _e( 'Merge topic subscribers', 'bbpress' ); ?></label><br />

							<?php endif; ?>

							<input name="bbp_topic_favoriters" id="bbp_topic_favoriters" type="checkbox" value="1" checked="checked" tabindex="8" />
							<label for="bbp_topic_favoriters"><?php _e( 'Merge topic favoriters', 'bbpress' ); ?></label><br />

							<input name="bbp_topic_tags" id="bbp_topic_tags" type="checkbox" value="1" checked="checked" tabindex="10" />
							<label for="bbp_topic_tags"><?php _e( 'Merge topic tags', 'bbpress' ); ?></label><br />

						</div>
					</fieldset>

					<p id="bbp_topic_submit_container">
						<button type="submit" tabindex="12" id="bbp_merge_topic_submit" name="bbp_merge_topic_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
					</p>
				</div>

				<?php bbp_merge_topic_form_fields(); ?>

			</fieldset>
		</form>
	</div>

<?php else : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
		<div class="entry-content"><?php is_user_logged_in() ? _e( 'You do not have the permissions to edit this topic!', 'bbpress' ) : _e( 'You cannot edit this topic.', 'bbpress' ); ?></div>
	</div>


<?php endif; ?>
