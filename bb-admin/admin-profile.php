<?php get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Forums Administration</h3>

<?php if ( current_user_can('manage_forums' ) ) : ?>
<div id="manage-forums">
<h2>Forum Management</h2>
<form method="post" id="add-forum" action="<?php option('uri'); ?>bb-admin/bb-forum.php">
	<fieldset>
	<legend>Add forum</legend>
		<table>
		 <tr><th scope="row">Forum Name:</th>
		     <td><input type="text" name="forum" id="forum" tabindex="10" /></td>
		 </tr>
		 <tr><th scope="row">Forum Descriptions:</th>
		     <td><input type="text" name="forum-desc" id="forum-desc" tabindex="11" /></td>
		 </tr>
		 <tr><th scope="row">Position:</th>
		     <td><input type="text" name="forum-order" id="forum-order" tabindex="12" maxlength="10" /></td>
		 </tr>
		</table>
	</fieldset> 
	<p class="submit"><input name="Submit" type="submit" value="Add Forum" tabindex="13" /><input type="hidden" name="action" value="add" /></p>
</form>
<?php if ( $forums = get_forums() ) : ?>
<form method="post" id="update-forums" action="<?php option('uri'); ?>bb-admin/bb-forum.php">
	<fieldset>
	<legend>Update forum information</legend>
		<table>
		 <tr><th>Name</th>
		     <th>Description</th>
		     <th>Position</th>
		 </tr>
<?php $t = 20; foreach ( $forums as $forum ) : ?>
		 <tr><td><input type="text" name="name-<?php forum_id(); ?>"  value="<?php echo bb_specialchars( get_forum_name(), 1 ); ?>" tabindex="<?php echo $t++; ?>" /></td>
		     <td><input type="text" name="desc-<?php forum_id(); ?>"  value="<?php echo bb_specialchars( get_forum_description(), 1 ); ?>" tabindex="<?php echo $t++; ?>" /></td>
		     <td><input type="text" name="order-<?php forum_id(); ?>" value="<?php echo $forum->forum_order; ?>" maxlength="10" tabindex="<?php echo $t++; ?>" /></td>
		 </tr>
<?php endforeach; ?>
		</table>
	</fieldset>
	<p class="submit"><input name="Submit" type="submit" value="Update" tabindex="<?php echo $t; ?>" /><input type="hidden" name="action" value="update" /></p>
</form>
<?php endif; ?>
</div>
<?php endif; ?>

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
	<legend>Choose items to recalculate</legend>
		<label for="topic-posts"><input name="topic-posts" id="topic-posts" type="checkbox" value="1" tabindex="100" />Count posts of every topic.</label><br />
		<label for="forums"><input name="forums" id="forums" type="checkbox" value="1" tabindex="101" />Count topics and posts in every forum (relies on the above).</label><br />
		<label for="topics-replied"><input name="topics-replied" id="topics-replied" type="checkbox" value="1" tabindex="102" />Count topics to which each user has replied.</label><br />
		<label for="topic-tag-count"><input name="topic-tag-count" id="topic-tag-count" type="checkbox" value="1" tabindex="103" />Count tags for every topic.</label><br />
		<label for="tags-tag-count"><input name="tags-tag-count" id="tags-tag-count" type="checkbox" value="1" tabindex="104" />Count topics for every tag.</label><br />
		<label for="zap-tags"><input name="zap-tags" id="zap-tags" type="checkbox" value="1" tabindex="105" />DELETE tags with no topics.  Only functions if the above checked.</label><br />
	</fieldset>
	<p class="submit"><input name="Submit" type="submit" value="Count!" tabindex="106" /></p>
</form>
</div>
<?php endif; ?>

<?php get_footer(); ?>
