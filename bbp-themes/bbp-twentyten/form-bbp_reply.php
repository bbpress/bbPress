<?php
/**
 * New/edit bbPress reply form
 *
 * @package bbPress
 * @subpackage Themes
 */
?>
<?php if ( bbp_is_reply_edit() || bbp_is_topic_open() || current_user_can( 'edit_topic', bbp_get_topic_id() ) ) : ?>

	<?php if ( ( bbp_is_reply_edit() && current_user_can( 'edit_reply', bbp_get_reply_id() ) ) || ( current_user_can( 'publish_topics' ) || bbp_allow_anonymous() ) ) : ?>

		<div id="new-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-form">

			<form id="new_post" name="new_post" method="post" action="">
				<fieldset>
					<legend><?php printf( __( 'Reply to: &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_topic_title() ); ?></legend>

					<?php if ( !bbp_is_topic_open() && !bbp_is_reply_edit() ) : ?>

						<div class="bbp-template-notice">
							<p><?php _e( 'This topic is marked as closed to new replies, however your posting capabilities still allow you to do so.', 'bbpress' ); ?></p>
						</div>

					<?php endif; ?>

					<?php do_action( 'bbp_template_notices' ); ?>

					<div class="alignleft">

						<?php bbp_is_reply_edit() ? bbp_reply_author_avatar( bbp_get_reply_id(), 80 ) : bbp_current_user_avatar( 80 ); ?>
					</div>

					<div class="alignleft">

						<?php get_template_part( 'form', 'bbp_anonymous' ); ?>

						<p>
							<label for="bbp_reply_content"><?php _e( 'Reply:', 'bbpress' ); ?></label><br />
							<textarea id="bbp_reply_content" tabindex="8" name="bbp_reply_content" cols="52" rows="6"><?php echo ( bbp_is_reply_edit() && !empty( $post->post_content ) ) ? $post->post_content : ''; ?></textarea>
						</p>

						<?php if ( !bbp_is_reply_edit() ) : ?>
							<p>
								<label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
								<input id="bbp_topic_tags" type="text" value="" tabindex="10" size="40" name="bbp_topic_tags" />
							</p>
						<?php endif; ?>

						<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() && ( !bbp_is_reply_edit() || ( bbp_is_reply_edit() && !bbp_is_reply_anonymous() ) ) ) : ?>

							<p>
								<?php if ( bbp_is_reply_edit() && $post->post_author != bbp_get_current_user_id() ) : ?>
									<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe"<?php checked( true, bbp_is_user_subscribed( $post->post_author, bbp_get_reply_topic_id() ) ); ?> tabindex="12" />
									<label for="bbp_topic_subscription"><?php _e( 'Notify the author of follow-up replies via email', 'bbpress' ); ?></label>
								<?php else : ?>
									<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe"<?php checked( true, bbp_is_user_subscribed( bbp_get_user_id( 0, false, true ), bbp_get_reply_topic_id() ) ); ?> tabindex="12" />
									<label for="bbp_topic_subscription"><?php _e( 'Notify me of follow-up replies via email', 'bbpress' ); ?></label>
								<?php endif; ?>
							</p>

						<?php endif; ?>

						<p id="bbp_reply_submit_container">
							<button type="submit" tabindex="14" id="bbp_reply_submit" name="bbp_reply_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
						</p>
					</div>

					<?php bbp_reply_form_fields(); ?>

				</fieldset>
			</form>
		</div>

	<?php else : ?>

		<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
			<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
			<div class="entry-content"><?php is_user_logged_in() ? _e( 'You cannot reply to this topic.', 'bbpress' ) : _e( 'You must be logged in to reply to this topic.', 'bbpress' ); ?></div>
		</div>

	<?php endif; ?>

<?php else : ?>

	<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
		<h2 class="entry-title"><?php _e( 'Topic Closed', 'bbpress' ); ?></h2>
		<div class="entry-content"><?php _e( 'This topic has been closed to new replies.', 'bbpress' ); ?></div>
	</div>

<?php endif; ?>
