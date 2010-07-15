
<?php if ( $topic_title ) : ?>
<p role="main">
	<label for="topic"><?php _e( 'Topic:' ); ?><br />
		<input name="topic" type="text" id="topic" size="50" maxlength="80" tabindex="1" value="<?php echo esc_attr( get_topic_title() ); ?>" />
	</label>
</p>
<?php endif; do_action( 'edit_form_pre_post' ); ?>

<p>
	<label for="post_content"><?php _e( 'Post:' ); ?><br />
		<textarea name="post_content" cols="50" rows="8" tabindex="2" id="post_content"><?php echo apply_filters( 'edit_text', get_post_text() ); ?></textarea>
	</label>
</p>

<?php if ( bb_is_user_logged_in() && bb_is_subscriptions_active() ) : ?>
<p id="post-form-subscription-container" class="left">
	<?php bb_user_subscribe_checkbox( 'tab=3' ); ?>
</p>
<?php endif; ?>

<p class="submit">
	<input type="submit" name="Submit" value="<?php esc_attr_e( 'Edit Post &raquo;' ); ?>" tabindex="4" />
	<input type="hidden" name="post_id" value="<?php post_id(); ?>" />
	<input type="hidden" name="topic_id" value="<?php topic_id(); ?>" />
</p>

<p><?php _e( 'Allowed markup:' ); ?> <code><?php allowed_markup(); ?></code>. <br /><?php _e( 'Put code in between <code>`backticks`</code>.' ); ?></p>
