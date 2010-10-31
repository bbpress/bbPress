
<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">
	<form id="new_post" name="new_post" method="post" action="">
		<fieldset>
			<legend><?php printf( __( 'New Topic in: &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_forum_title() ); ?></legend>

			<div class="alignleft">

				<?php bbp_current_user_avatar( 80 ); ?>

			</div>

			<div class="alignright">
				<p>
					<label for="bbp_topic_title"><?php _e( 'Title:', 'bbpress' ); ?></label><br />
					<input type="text" id="bbp_topic_title" value="" tabindex="1" size="40" name="bbp_topic_title" />
				</p>

				<p>
					<label for="bbp_topic_content"><?php _e( 'Topic:', 'bbpress' ); ?></label><br />
					<textarea id="bbp_topic_content" tabindex="3" name="bbp_topic_content" cols="62" rows="6"></textarea>
				</p>

				<p>
					<label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
					<input type="text" value="" tabindex="5" size="40" name="bbp_topic_tags" id="post_tags" />
				</p>

				<p align="right">
					<button type="submit" tabindex="7" id="bbp_topic_submit" name="bbp_topic_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
				</p>
			</div>

			<?php bbp_new_topic_form_fields(); ?>

		</fieldset>
	</form>
</div>
