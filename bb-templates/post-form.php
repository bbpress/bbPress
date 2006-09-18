<?php if ( is_topic() ) : ?>
<h2 class='post-form'><?php _e('Reply'); ?></h2>
<?php elseif ( is_forum() ) : ?>
<h2 class='post-form'><?php _e('New Topic in this Forum'); ?></h2>
<?php elseif ( is_tag() || is_front() ) : ?>
<h2 class='post-form'><?php _e('Add New Topic'); ?></h2>
<?php endif; ?>

<?php if ( !is_topic() ) : ?>
<p>Before posting a new topic, <a href="<?php option('uri'); ?>search.php">be sure to search</a> to see if one has been started already.</p>
<p>
	<label for="topic"><?php _e('Topic title: (be brief and descriptive)'); ?>
		<input name="topic" type="text" id="topic" size="50" maxlength="80" tabindex="1" />
	</label>
	<label for="support">
		<input name="support" type="checkbox" id="support" checked="checked" value="1" tabindex="2"/><?php _e('This is a support question.'); ?>
	</label>
</p>
<?php endif; ?>
<p>
	<label for="post_content"><?php _e('Post:'); ?>
		<textarea name="post_content" cols="50" rows="8" id="post_content" tabindex="3"></textarea>
	</label>
</p>
<?php if ( !is_topic() ) : ?>
<p>
	<label for="tags">Enter a few words (called <a href="<?php tag_page_link(); ?>">tags</a>) separated by spaces to help someone find your topic:
		<input name="tags" type="text" size="50" maxlength="100" value="<?php tag_name(); ?> " tabindex="4" />
	</label>
</p>
<?php endif; ?>
<?php if ( is_tag() || is_front() ) : ?>
<p>
	<label for="forum_id"><?php _e('Pick a section:'); ?>
		<?php forum_dropdown(); ?>
	</label>
</p>
<?php endif; ?>
<p class="submit">
  <input type="submit" id="postformsub" name="Submit" value="<?php _e('Send Post'); ?> &raquo;" tabindex="4" />
</p>

<p><?php _e('Allowed tags: <code>a em strong code ul ol li blockquote</code>. <br />Put code in between <code>`backticks`</code>.'); ?></p>
