
<?php if ( current_user_can( 'publish_replies' ) || bbp_allow_anonymous() ) : ?>

	<div id="new-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-form">
		<form id="new_post" name="new_post" method="post" action="">
			<fieldset>
				<legend>
					<?php printf( __( 'Reply to: &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_topic_title() ); ?>
				</legend>

				<div class="alignleft">
					<?php bbp_current_user_avatar( 80 ); ?>
				</div>

				<div class="alignleft">
					<p>
						<label for="bbp_reply_content"><?php _e( 'Reply:', 'bbpress' ); ?></label><br />
						<textarea id="bbp_reply_content" tabindex="3" name="bbp_reply_content" cols="52" rows="6"></textarea>
					</p>

					<p>
						<label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
						<input id="bbp_topic_tags" type="text" value="" tabindex="5" size="40" name="bbp_topic_tags" id="post_tags" />
					</p>

					<p align="right">
						<button type="submit" tabindex="6" id="bbp_reply_submit" name="bbp_reply_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
					</p>
				</div>

				<?php bbp_new_reply_form_fields(); ?>

			</fieldset>
		</form>
	</div>

<?php else : ?>

	<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
		<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
		<div class="entry-content"><?php is_user_logged_in() ? _e( 'You cannot reply to this topic.', 'bbpress' ) : _e( 'You must be logged in to reply to this topic.', 'bbpress' ); ?></div>
	</div>

<?php endif; ?>
