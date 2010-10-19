
<table id="new-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-form">
	<thead>
		<tr>
			<th><?php bbp_current_user_name(); ?></th>
			<th><?php _e( 'Reply', 'bbpress' ); ?></th>
		</tr>
	</thead>

	<tbody>
		<tr>
			<td style="vertical-align: top;">
				<?php bbp_current_user_avatar( 80 ); ?>
			</td>
			<td>
				<form id="new_post" name="new_post" method="post" action="">
					<fieldset>
						<p><label for="bbp_topic_tags"><?php _e( 'Reply:', 'bbpress' ); ?></label><br />
							<textarea id="bbp_reply_description" tabindex="3" name="bbp_reply_description" cols="50" rows="6"></textarea>
						</p>

						<p><label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
							<input type="text" value="" tabindex="5" size="16" name="bbp_topic_tags" id="post_tags" />
						</p>

						<p align="right">
							<button type="submit" tabindex="6" id="bbp_reply_submit" name="bbp_reply_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
						</p>

						<input type="hidden" id="bbp_reply_title" value="<?php printf( __( 'Reply To: %s', 'bbpress' ), bbp_get_topic_title() ); ?>" tabindex="1" size="20" name="bbp_reply_title" />
						<input type="hidden" name="bbp_topic_id" id="bbp_topic_id" value="<?php bbp_topic_id(); ?>" />
						<input type="hidden" name="action" value="post" />

						<?php wp_nonce_field( 'new-post' ); ?>

					</fieldset>
				</form>
			</td>
		</tr>
	</tbody>
</table>
