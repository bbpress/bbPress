
<?php if ( current_user_can( 'publish_topics' ) || bbp_allow_anonymous() ) : ?>

	<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">
		<form id="new_post" name="new_post" method="post" action="">
			<fieldset>
				<legend><?php printf( __( 'New Topic in: &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_forum_title() ); ?></legend>

				<div class="alignleft">

					<?php bbp_current_user_avatar( 80 ); ?>

				</div>

				<div class="alignleft">

					<?php get_template_part( 'form', 'bbp_anonymous' ); ?>

					<p>
						<label for="bbp_topic_title"><?php _e( 'Title:', 'bbpress' ); ?></label><br />
						<input type="text" id="bbp_topic_title" value="" tabindex="8" size="40" name="bbp_topic_title" />
					</p>

					<p>
						<label for="bbp_topic_content"><?php _e( 'Topic:', 'bbpress' ); ?></label><br />
						<textarea id="bbp_topic_content" tabindex="10" name="bbp_topic_content" cols="52" rows="6"></textarea>
					</p>

					<p>
						<label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
						<input type="text" value="" tabindex="12" size="40" name="bbp_topic_tags" id="post_tags" />
					</p>

					<?php if ( !bbp_is_forum() ) : ?>

						<p>
							<label for="bbp_forum_id"><?php _e( 'Forum:', 'bbpress' ); ?></label><br />
							<?php bbp_forum_dropdown(); ?>
						</p>

					<?php endif; ?>

					<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() ) : ?>

						<p>
							<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe" tabindex="16" />
							<label for="bbp_topic_subscription"><?php _e( 'Notify me of follow-up replies via email', 'bbpress' ); ?></label>
						</p>

					<?php endif; ?>

					<p align="right">
						<button type="submit" tabindex="18" id="bbp_topic_submit" name="bbp_topic_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
					</p>
				</div>

				<?php bbp_new_topic_form_fields(); ?>

			</fieldset>
		</form>
	</div>

<?php else : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
		<div class="entry-content"><?php is_user_logged_in() ? _e( 'You cannot create new topics at this time.', 'bbpress' ) : _e( 'You must be logged in to create new topics.', 'bbpress' ); ?></div>
	</div>


<?php endif; ?>
