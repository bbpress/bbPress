
<form name="post" id="post" method="post" action="<?php option('uri'); ?>bb-post.php">
<?php if ( is_forum() ) : ?>
<p>Before posting a new topic, <a href="<?php option('uri'); ?>search.php">be sure to search</a> to see if one has been started already.</p>
<p>
  <label>Topic:<br />
  <input name="topic" type="text" id="topic" size="50" maxlength="80" />
</label>
</p>
<?php endif; ?>
<p><label>Post:<br />
  <textarea name="post_content" cols="50" rows="8" id="post_content"></textarea>
  </label>
</p>
<p class="submit">
  <input type="submit" name="Submit" value="Send Post &raquo;" />
<?php if ( is_forum() ) : ?>
<input type="hidden" name="forum_id" value="<?php forum_id(); ?>" />
<?php else : ?>
<input type="hidden" name="topic_id" value="<?php topic_id(); ?>" />
<?php endif; ?>

</p>
<p>Allowed tags: <code>a em strong code ul ol li blockquote</code>. <br />Put code in between <code>`backticks`</code>.</p>
</form>
