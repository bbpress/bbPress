<?php

/**
 * New/Edit Topic
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php if ( ( bbp_is_topic_edit() && current_user_can( 'edit_topic', bbp_get_topic_id() ) ) || current_user_can( 'publish_topics' ) || bbp_allow_anonymous() ) : ?>

	<?php if ( ( !bbp_is_forum_category() && ( !bbp_is_forum_closed() || current_user_can( 'edit_forum', bbp_get_topic_forum_id() ) ) ) || bbp_is_topic_edit() ) : ?>

		<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">

			<form id="new_post" name="new_post" method="post" action="">
				<fieldset>
					<legend>

						<?php
							if ( bbp_is_topic_edit() )
								printf( __( 'Edit topic "%s"', 'bbpress' ), bbp_get_topic_title() );
							else
								bbp_is_forum() ? printf( __( 'Create new topic in: &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_forum_title() ) : _e( 'Create new topic', 'bbpress' );
						?>

					</legend>

					<?php if ( !bbp_is_topic_edit() && bbp_is_forum_closed() ) : ?>

						<div class="bbp-template-notice">
							<p><?php _e( 'This forum is marked as closed to new topics, however your posting capabilities still allow you to do so.', 'bbpress' ); ?></p>
						</div>

					<?php endif; ?>

					<?php do_action( 'bbp_template_notices' ); ?>

					<div class="alignleft">

						<?php bbp_is_topic_edit() ? bbp_topic_author_avatar( bbp_get_topic_id(), 80 ) : bbp_current_user_avatar( 80 ); ?>

					</div>

					<div class="alignleft">

						<?php get_template_part( 'form', 'bbp_anonymous' ); ?>

						<p>
							<label for="bbp_topic_title"><?php _e( 'Title:', 'bbpress' ); ?></label><br />
							<input type="text" id="bbp_topic_title" value="<?php echo ( bbp_is_topic_edit() && !empty( $post->post_title ) ) ? $post->post_title : ''; ?>" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_title" />
						</p>

						<p>
							<label for="bbp_topic_content"><?php _e( 'Topic:', 'bbpress' ); ?></label><br />
							<textarea id="bbp_topic_content" tabindex="<?php bbp_tab_index(); ?>" name="bbp_topic_content" cols="52" rows="6"><?php echo ( bbp_is_topic_edit() && !empty( $post->post_content ) ) ? $post->post_content : ''; ?></textarea>
						</p>

						<p class="form-allowed-tags">
							<label><?php _e( 'You may use these <abbr title="HyperText Markup Language">HTML</abbr> tags and attributes:','bbpress' ); ?></label><br />
							<code><?php bbp_allowed_tags(); ?></code>
						</p>

						<?php if ( !bbp_is_topic_edit() ) : ?>

							<p>
								<label for="bbp_topic_tags"><?php _e( 'Tags:', 'bbpress' ); ?></label><br />
								<input type="text" value="" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_tags" id="bbp_topic_tags" />
							</p>

						<?php endif; ?>

						<?php if ( !bbp_is_forum() ) : ?>

							<p>
								<label for="bbp_forum_id"><?php _e( 'Forum:', 'bbpress' ); ?></label><br />
								<?php bbp_dropdown( array( 'selected' => bbp_is_topic_edit() ? bbp_get_topic_forum_id() : 0 ) ); ?>
							</p>

						<?php endif; ?>

						<?php if ( current_user_can( 'moderate' ) ) : ?>

							<p>

								<label for="bbp_stick_topic"><?php _e( 'Topic Type:', 'bbpress' ); ?></label><br />

								<?php bbp_topic_type_select(); ?>

							</p>

						<?php endif; ?>

						<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() && ( !bbp_is_topic_edit() || ( bbp_is_topic_edit() && !bbp_is_topic_anonymous() ) ) ) : ?>

							<p>
								<?php if ( bbp_is_topic_edit() && $post->post_author != bbp_get_current_user_id() ) : ?>

									<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe"<?php checked( true, bbp_is_user_subscribed( $post->post_author ) ); ?> tabindex="<?php bbp_tab_index(); ?>" />
									<label for="bbp_topic_subscription"><?php _e( 'Notify the author of follow-up replies via email', 'bbpress' ); ?></label>

								<?php else : ?>

									<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe"<?php checked( true, bbp_is_user_subscribed( bbp_get_user_id( 0, false, true ) ) ); ?> tabindex="<?php bbp_tab_index(); ?>" />
									<label for="bbp_topic_subscription"><?php _e( 'Notify me of follow-up replies via email', 'bbpress' ); ?></label>

								<?php endif; ?>
							</p>

						<?php endif; ?>

						<?php if ( bbp_is_topic_edit() ) : ?>

							<fieldset>
								<legend><?php _e( 'Revision', 'bbpress' ); ?></legend>
								<div>
									<input name="bbp_log_topic_edit" id="bbp_log_topic_edit" type="checkbox" value="1" checked="checked" tabindex="<?php bbp_tab_index(); ?>" />
									<label for="bbp_log_topic_edit"><?php _e( 'Keep a log of this edit:', 'bbpress' ); ?></label><br />
								</div>

								<div>
									<label for="bbp_topic_edit_reason"><?php printf( __( 'Optional reason for editing:', 'bbpress' ), bbp_get_current_user_name() ); ?></label><br />
									<input type="text" value="" tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_edit_reason" id="bbp_topic_edit_reason" />
								</div>
							</fieldset>

						<?php endif; ?>

						<p id="bbp_topic_submit_container">
							<button type="submit" tabindex="<?php bbp_tab_index(); ?>" id="bbp_topic_submit" name="bbp_topic_submit"><?php _e( 'Submit', 'bbpress' ); ?></button>
						</p>
					</div>

					<?php bbp_topic_form_fields(); ?>

				</fieldset>
			</form>
		</div>

	<?php elseif ( bbp_is_forum_closed() ) : ?>

		<div class="bbp-template-notice">
			<p><?php _e( 'This forum is closed to new topics.', 'bbpress' ); ?></p>
		</div>

	<?php endif; ?>

<?php else : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<h2 class="entry-title"><?php _e( 'Sorry!', 'bbpress' ); ?></h2>
		<div class="entry-content"><?php is_user_logged_in() ? _e( 'You cannot create new topics at this time.', 'bbpress' ) : _e( 'You must be logged in to create new topics.', 'bbpress' ); ?></div>
	</div>


<?php endif; ?>
