<?php get_header(); ?>
<?php profile_menu(); ?>

<?php if ( current_user_can('recount') ) : ?>
<div id="recount">
<h2>Recount</h2>
<p>The following checkboxes allow you to recalculate various numbers stored in
the database.  These numbers are used for things like counting the number of
pages worth of posts a particular topic has.  You shouldn't need to do do any of
this unless you're upgrading from one version to another or are seeing
pagination oddities.</p>

<form method="post" action="<?php option('uri'); ?>bb-admin/bb-do-counts.php">
	<fieldset>
		<label for="topic-posts"><input name="topic-posts" id="topic-posts" type="checkbox" value="1" tabindex="1" />Count posts of every topic.</label><br />
		<label for="forums"><input name="forums" id="forums" type="checkbox" value="1" tabindex="2" />Count topics and posts in every forum (relies on the above).</label><br />
		<label for="topics-replied"><input name="topics-replied" id="topics-replied" type="checkbox" value="1" tabindex="3" />Count topics to which each user has replied.</label><br />
		<label for="topic-tag-count"><input name="topic-tag-count" id="topic-tag-count" type="checkbox" value="1" tabindex="4" />Count tags for every topic.</label><br />
		<label for="tags-tag-count"><input name="tags-tag-count" id="tags-tag-count" type="checkbox" value="1" tabindex="5" />Count topics for every tag.</label><br />
		<label for="zap-tags"><input name="zap-tags" id="zap-tags" type="checkbox" value="1" tabindex="6" />DELETE tags with no topics.  Only functions if the above checked.</label><br />
	</fieldset>
	<p class="submit"><input name="Submit" type="submit" value="Count!" tabindex="7" /></p>
</form>
</div>
<?php endif; ?>

<?php get_footer(); ?>
