
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
