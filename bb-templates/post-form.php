
<form class="postform" method="post" action="<?php option('uri'); ?>bb-post.php">
<?php if ( is_forum() || is_tag() ) : ?>
<p>Before posting a new topic, <a href="<?php option('uri'); ?>search.php">be sure to search</a> to see if one has been started already.</p>
<p>
  <label>Topic title: (be brief and descriptive)<br />
  <input name="topic" type="text" id="topic" size="50" maxlength="80" tabindex="1" />
</label>
<label><input name="support" type="checkbox" id="support" checked="checked" value="1" tabindex="2"/>This is a support question.</label>
</p>
<?php endif; ?>
<p><label>Post:<br />
  <textarea name="post_content" cols="50" rows="8" id="post_content" tabindex="3"></textarea>
  </label>
</p>
<?php if ( is_forum() || is_tag() ) : ?>
<p>Enter a few words (called <a href="<?php tag_page_link(); ?>">tags</a>) separated by spaces to help someone find your topic:<br />
<input name="tags" type="text" size="50" maxlength="100" value="<?php tag_name(); ?> " tabindex="4" />
</p>
<?php endif; ?>
<?php if ( is_tag() ) : ?>
<p>Pick a section:<br />
<?php forum_dropdown(); ?></p>
<?php endif; ?>
<p class="submit">
  <input type="submit" name="Submit" value="Send Post &raquo;" tabindex="6" />
<?php if ( is_forum() ) : ?>
<input type="hidden" name="forum_id" value="<?php forum_id(); ?>" />
<?php else : ?>
<input type="hidden" name="topic_id" value="<?php topic_id(); ?>" />
<?php endif; ?>

</p>
<p>Allowed tags: <code>a em strong code ul ol li blockquote</code>. <br />Put code in between <code>`backticks`</code>.</p>
</form>
