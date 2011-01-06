<?php
/**
 * Split topic form
 *
 * @package bbPress
 * @subpackage Themes
 */
?>
<?php if ( is_user_logged_in() && current_user_can( 'edit_topic', bbp_get_topic_id() ) ) : ?>

	<div id="merge-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-merge">

		<form id="merge_topic" name="merge_topic" method="post" action="">

			<fieldset>

				<legend><?php printf( __( 'Split topic "%s"', 'bbpress' ), bbp_get_topic_title() ); ?></legend>

				<div class="alignleft">

					<p><?php _e( 'When you split a topic, you are slicing it in half starting with the reply you just selected. Choose to use that reply as a new topic with a new title, or merge those replies into an existing topic.', 'bbpress' ); ?></p>

					<div class="bbp-template-notice">
						<p><?php _e( '<strong>NOTE:</strong> If you use the existing topic option, replies within both topics will be merged chronologically. The order of the merged replies is based on the time and date they were posted.', 'bbpress' ); ?></p>
					</div>

					<div class="bbp-template-notice error">
						<p><?php _e( '<strong>WARNING:</strong> This process cannot undone, so double-check everything before you .', 'bbpress' ); ?></p>
					</div>

					<?php // @todo Make a codex and add the merge topic docs. ?>
					<?php // printf( __( 'For more information, check <a href="%s">this documentation.', 'bbpress' ), 'http://codex.bbpress.org/Merge_Topics' ); ?>

					<fieldset>
						<legend><?php _e( 'Split the topic by:', 'bbpress' ); ?></legend>

						<div>
							<input name="bbp_topic_split_option" id="bbp_topic_split_option_reply" type="radio" checked="checked" value="reply" tabindex="10" />
							<label for="bbp_topic_split_option_reply"><?php _e( 'Creating a new topic in this forum:', 'bbpress' ); ?></label>
							<input type="text" id="bbp_topic_split_destination_title" value="<?php bbp_topic_title(); ?>" tabindex="12" size="40" name="bbp_topic_split_destination_title" /><br />

							<input name="bbp_topic_split_option" id="bbp_topic_split_option_existing" type="radio" value="existing" tabindex="14" />
							<label for="bbp_topic_split_option_existing"><?php _e( 'Use an existing topic in this forum:', 'bbpress' ); ?></label>

							<?php
								global $bbp;
								bbp_dropdown( array( 'post_type' => $bbp->topic_id, 'post_parent' => bbp_get_topic_forum_id( bbp_get_topic_id() ), 'selected' => -1, 'exclude' => bbp_get_topic_id(), 'select_id' => 'bbp_destination_topic', 'tab' => 16, 'none_found' => __( 'No topics were found to which the topic could be split to!', 'bbpress' ) ) );
							?>

						</div>
					</fieldset>

					<fieldset>
						<legend><?php _e( 'Topic Extras', 'bbpress' ); ?></legend>

						<div>

							<?php if ( bbp_is_subscriptions_active() ) : ?>

								<input name="bbp_topic_subscribers" id="bbp_topic_subscribers" type="checkbox" value="1" checked="checked" tabindex="4" />
								<label for="bbp_topic_subscribers"><?php _e( 'Copy subscribers to the new topic', 'bbpress' ); ?></label><br />

							<?php endif; ?>

							<input name="bbp_topic_favoriters" id="bbp_topic_favoriters" type="checkbox" value="1" checked="checked" tabindex="6" />
							<label for="bbp_topic_favoriters"><?php _e( 'Copy favoriters to the new topic', 'bbpress' ); ?></label><br />

							<input name="bbp_topic_tags" id="bbp_topic_tags" type="checkbox" value="1" checked="checked" tabindex="8" />
							<label for="bbp_topic_tags"><?php _e( 'Copy topic tags to the new topic', 'bbpress' ); ?></label><br />

						</div>
					</fieldset>

					<p id="bbp_topic_submit_container">
						<button type="submit" tabindex="18" id="bbp_merge_topic_submit" name="bbp_merge_topic_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
					</p>
				</div>

				<?php bbp_split_topic_form_fields(); ?>

			</fieldset>
		</form>
	</div>

<?php else : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
		<div class="entry-content"><?php is_user_logged_in() ? _e( 'You do not have the permissions to edit this topic!', 'bbpress' ) : _e( 'You cannot edit this topic.', 'bbpress' ); ?></div>
	</div>


<?php endif; ?>
