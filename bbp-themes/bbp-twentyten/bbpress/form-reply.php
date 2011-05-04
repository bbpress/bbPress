<?php

/**
 * New/Edit Reply
 *
 * @package bbPress
 * @subpackage Theme
 */

// Make sure we're back where we started
wp_reset_postdata();

?>

<?php if ( bbp_is_reply_edit() || bbp_is_topic_open() || current_user_can( 'edit_topic', bbp_get_topic_id() ) ) : ?>

	<?php if ( ( bbp_is_reply_edit() && current_user_can( 'edit_reply', bbp_get_reply_id() ) ) || ( current_user_can( 'publish_topics' ) || bbp_allow_anonymous() ) ) : ?>

		<div id="new-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-form">

			<form id="new-post" name="new-post" method="post" action="">
				<fieldset>
					<legend><?php printf( __( 'Reply to: &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_topic_title() ); ?></legend>

					<?php if ( !bbp_is_topic_open() && !bbp_is_reply_edit() ) : ?>

						<div class="bbp-template-notice">
							<p><?php _e( 'This topic is marked as closed to new replies, however your posting capabilities still allow you to do so.', 'bbpress' ); ?></p>
						</div>

					<?php endif; ?>

					<?php do_action( 'bbp_template_notices' ); ?>

					<div>

						<div class="alignright avatar">

							<?php bbp_is_reply_edit() ? bbp_reply_author_avatar( bbp_get_reply_id(), 120 ) : bbp_current_user_avatar( 120 ); ?>

						</div>

						<?php bbp_get_template_part( 'bbpress/form', 'anonymous' ); ?>

						<p>
							<label for="bbp_reply_content"><?php _e( 'Reply:', 'bbpress' ); ?></label><br />
							<textarea id="bbp_reply_content" tabindex="<?php bbp_tab_index(); ?>" name="bbp_reply_content" cols="51" rows="6"><?php bbp_form_reply_content(); ?></textarea>
						</p>

						<p class="form-allowed-tags">
							<label><?php _e( 'You may use these <abbr title="HyperText Markup Language">HTML</abbr> tags and attributes:','bbpress' ); ?></label><br />
							<code><?php bbp_allowed_tags(); ?></code>
						</p>

						<p>
							<label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
							<input id="bbp_topic_tags" type="text" value="<?php bbp_form_topic_tags(); ?>" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_tags" />
						</p>

						<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() && ( !bbp_is_reply_edit() || ( bbp_is_reply_edit() && !bbp_is_reply_anonymous() ) ) ) : ?>

							<p>

								<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe"<?php bbp_form_topic_subscribed(); ?> tabindex="<?php bbp_tab_index(); ?>" />

								<?php if ( bbp_is_reply_edit() && $post->post_author != bbp_get_current_user_id() ) : ?>

									<label for="bbp_topic_subscription"><?php _e( 'Notify the author of follow-up replies via email', 'bbpress' ); ?></label>

								<?php else : ?>

									<label for="bbp_topic_subscription"><?php _e( 'Notify me of follow-up replies via email', 'bbpress' ); ?></label>

								<?php endif; ?>

							</p>

						<?php endif; ?>

						<?php if ( bbp_is_reply_edit() ) : ?>

							<fieldset>
								<legend><?php _e( 'Revision', 'bbpress' ); ?></legend>
								<div>
									<input name="bbp_log_reply_edit" id="bbp_log_reply_edit" type="checkbox" value="1" <?php bbp_form_reply_log_edit(); ?> tabindex="<?php bbp_tab_index(); ?>" />
									<label for="bbp_log_reply_edit"><?php _e( 'Keep a log of this edit:', 'bbpress' ); ?></label><br />
								</div>

								<div>
									<label for="bbp_reply_edit_reason"><?php printf( __( 'Optional reason for editing:', 'bbpress' ), bbp_get_current_user_name() ); ?></label><br />
									<input type="text" value="<?php bbp_form_reply_edit_reason(); ?>" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_reply_edit_reason" id="bbp_reply_edit_reason" />
								</div>
							</fieldset>

						<?php else : ?>

							<?php bbp_topic_admin_links(); ?>

						<?php endif; ?>

						<div class="bbp-submit-wrapper">
							<button type="submit" tabindex="<?php bbp_tab_index(); ?>" id="bbp_reply_submit" name="bbp_reply_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
						</div>
					</div>

					<?php bbp_reply_form_fields(); ?>

				</fieldset>
			</form>
		</div>

	<?php else : ?>

		<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
			<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
			<div class="bbp-template-notice">
				<p><?php is_user_logged_in() ? _e( 'You cannot reply to this topic.', 'bbpress' ) : _e( 'You must be logged in to reply to this topic.', 'bbpress' ); ?></p>
			</div>
		</div>

	<?php endif; ?>

<?php else : ?>

	<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
		<h2 class="entry-title"><?php _e( 'Topic Closed', 'bbpress' ); ?></h2>
		<div class="bbp-template-notice">
			<p><?php _e( 'This topic has been closed to new replies.', 'bbpress' ); ?></p>
		</div>
	</div>

<?php endif; ?>
