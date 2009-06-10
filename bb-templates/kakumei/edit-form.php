
<?php if ( $topic_title ) : ?>
<p role="main">
  <label><?php _e('Topic:'); ?><br />

  <input name="topic" type="text" id="topic" size="50" maxlength="80"  value="<?php echo esc_attr( get_topic_title() ); ?>" />
</label>
</p>
<?php endif; do_action( 'edit_form_pre_post' ); ?>
<p><label><?php _e('Post:'); ?><br />
  <textarea name="post_content" cols="50" rows="8" id="post_content"><?php echo apply_filters('edit_text', get_post_text() ); ?></textarea>
  </label>
</p>
<p class="submit">
<input type="submit" name="Submit" value="<?php echo esc_attr( __('Edit Post &raquo;') ); ?>" />
<input type="hidden" name="post_id" value="<?php post_id(); ?>" />
<input type="hidden" name="topic_id" value="<?php topic_id(); ?>" />
</p>
<p><?php _e('Allowed markup:'); ?> <code><?php allowed_markup(); ?></code>. <br /><?php _e('Put code in between <code>`backticks`</code>.'); ?></p>
