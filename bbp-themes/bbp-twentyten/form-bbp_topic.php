
<table id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">
	<thead>
		<tr>
			<th><?php bbp_current_user_name(); ?></th>
			<th><?php _e( 'New Topic', 'bbpress' ); ?></th>
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
						<p><label for="bbp_topic_title"><?php _e( 'Title:', 'bbpress' ); ?></label><br />
							<input type="text" id="bbp_topic_title" value="" tabindex="1" size="40" name="bbp_topic_title" />
						</p>

						<p><label for="bbp_topic_tags"><?php _e( 'Topic:', 'bbpress' ); ?></label><br />
							<textarea id="bbp_topic_description" tabindex="3" name="bbp_topic_description" cols="50" rows="6"></textarea>
						</p>

						<p><label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
							<input type="text" value="" tabindex="5" size="16" name="bbp_topic_tags" id="post_tags" />
						</p>

						<p align="right">
							<button type="submit" tabindex="7" id="bbp_topic_submit" name="bbp_topic_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
						</p>

						
						<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />
						<input type="hidden" name="action" value="post" />

						<?php wp_nonce_field( 'new-post' ); ?>

					</fieldset>
				</form>
			</td>
		</tr>
	</tbody>
</table>
