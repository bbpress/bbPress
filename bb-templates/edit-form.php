
<?php if ( $topic_title ) : ?>
<p>
  <label><?php _e('Topic:'); ?><br />
  <input name="topic" type="text" id="topic" size="50" maxlength="80"  value="<?php echo wp_specialchars(get_topic_title(), 1); ?>" />
</label>
</p>
<?php endif; ?>
<p><label><?php _e('Post:'); ?><br />
  <textarea name="post_content" cols="50" rows="8" id="post_content"><?php echo apply_filters('edit_text', get_post_text() ); ?></textarea>
  </label>
</p>
<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Edit Post'); ?> &raquo;" />
<input type="hidden" name="post_id" value="<?php post_id(); ?>" />
<input type="hidden" name="topic_id" value="<?php topic_id(); ?>" />
</p>
<p><?php _e('Allowed markup: <code>a em strong code ul ol li blockquote</code>. <br />Put code in between <code>`backticks`</code>.'); ?></p>
