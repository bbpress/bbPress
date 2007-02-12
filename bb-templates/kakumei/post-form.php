<?php if ( !is_topic() ) : ?>
<p>
	<label for="topic"><?php _e('Topic title: (be brief and descriptive)'); ?>
		<input name="topic" type="text" id="topic" size="50" maxlength="80" tabindex="1" />
	</label>
</p>
<?php endif; do_action( 'post_form_pre_post' ); ?>
<p>
	<label for="post_content"><?php _e('Post:'); ?>
		<textarea name="post_content" cols="50" rows="8" id="post_content" tabindex="3"></textarea>
	</label>
</p>
<?php if ( !is_topic() ) : ?>
<p>
	<label for="tags-input"><?php printf(__('Enter a few words (called <a href="%s">tags</a>) separated by spaces to help someone find your topic:'), get_tag_page_link()) ?>
		<input id="tags-input" name="tags" type="text" size="50" maxlength="100" value="<?php tag_name(); ?> " tabindex="4" />
	</label>
</p>
<?php endif; ?>
<?php if ( is_tag() || is_front() ) : ?>
<p>
	<label for="forum_id"><?php _e('Pick a section:'); ?>
		<?php bb_new_topic_forum_dropdown(); ?>
	</label>
</p>
<?php endif; ?>
<p class="submit">
  <input type="submit" id="postformsub" name="Submit" value="<?php echo attribute_escape( __('Send Post &raquo;') ); ?>" tabindex="4" />
</p>

<p><?php _e('Allowed markup:'); ?> <code><?php allowed_markup(); ?></code>. <br /><?php _e('Put code in between <code>`backticks`</code>.'); ?></p>
