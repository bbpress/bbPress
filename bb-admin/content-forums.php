<?php
require_once('admin.php');

$forums = get_forums();
$forums_count = $forums ? count($forums) : 0;

if ( 'delete' == $_GET['action'] ) {
	$forum_to_delete = (int) $_GET['id'];
	$deleted_forum = get_forum( $forum_to_delete );
	if ( !$deleted_forum || $forums_count < 2 || !bb_current_user_can( 'delete_forum', $forum_to_delete ) )
		wp_redirect( add_query_arg( array('action' => false, 'id' => false) ) );
}

if ( isset($_GET['message']) ) {
	switch ( $_GET['message'] ) :
	case 'deleted' :
		bb_admin_notice( sprintf(__('Forum deleted.  You should have bbPress <a href="%s">recount your site information</a>.'), bb_get_option( 'uri' ) . 'bb-admin/site.php') );
		break;
	endswitch;
}

bb_get_admin_header();
?>

<h2><?php _e('Forum Management'); ?></h2>
<?php  if ( 'delete' == $_GET['action'] ) : ?>
<div class="ays narrow">
	<p><big><?php printf(__('Are you sure you want to delete the "<strong>%s</strong>" forum?'), $deleted_forum->forum_name); ?></big></p>
	<p>This forum contains</p>
	<ul>
		<li><?php printf(__ngettext('%d topic', '%d topics', $deleted_forum->topics), $deleted_forum->topics); ?></li>
		<li><?php printf(__ngettext('%d post', '%d posts', $deleted_forum->posts), $deleted_forum->posts); ?></li>
	</ul>

	<form method="post" id="delete-forums" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
		<p>
			<label for="move-topics-delete"><input type="radio" name="move_topics" id="move-topics-delete" value="delete" /> <?php _e('Delete all topics and posts in this forum. <em>This can never be undone.</em>'); ?></label><br />
			<label for="move-topics-move"><input type="radio" name="move_topics" id="move-topics-move" value="move" checked="checked" /> <?php _e('Move topics from this forum into'); ?></label>
			<?php $forums = get_forums(); ?>
			<select name="move_topics_forum" id="move-topics-forum">
				<?php foreach ($forums as $forum ) : ?>
					<?php if ($forum->forum_id != $deleted_forum->forum_id) : ?>

						<option value="<?php forum_id(); ?>"><?php forum_name(); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			
		</p>
		<p class="submit alignright">
			<input class="delete" name="Submit" type="submit" value="<?php _e('Delete forum &raquo;'); ?>" tabindex="10" />
			<input type="hidden" name="action" value="delete" />
			<input type="hidden" name="forum_id" value="<?php echo $deleted_forum->forum_id; ?>" />
		</p>
		<?php bb_nonce_field( 'delete-forums' ); ?>
	</form>
	<form method="get" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
		<p class="submit alignleft">
			<input type="submit" value="<?php _e('&laquo; Go back'); ?>" tabindex="10" />
		</p>
	</form>
</div>
<?php else: // action ?>

<form method="post" id="add-forum" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
	<h3><?php _e('Add forum'); ?></h3>
	<fieldset>
		<table>
		 <tr><th scope="row"><?php _e('Forum Name:'); ?></th>
		     <td><input type="text" name="forum" id="forum" tabindex="10" /></td>
		 </tr>
		 <tr><th scope="row"><?php _e('Forum Description:'); ?></th>
		     <td><input type="text" name="forum-desc" id="forum-desc" tabindex="11" /></td>
		 </tr>
		 <tr><th scope="row"><?php _e('Position:'); ?></th>
		     <td><input type="text" name="forum-order" id="forum-order" tabindex="12" maxlength="10" /></td>
		 </tr>
		</table>
		<p class="submit alignleft"><input name="Submit" type="submit" value="<?php _e('Add Forum'); ?>" tabindex="13" /><input type="hidden" name="action" value="add" /></p>
	</fieldset> 
	<?php bb_nonce_field( 'add-forum' ); ?>
</form>
<?php if ( $forums ) : ?>
<form method="post" id="update-forums" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
	<h3><?php _e('Update forum information'); ?></h3>
	<fieldset>
		<table>
		 <tr><th><?php _e('Name'); ?></th>
		     <th><?php _e('Description'); ?></th>
		     <th><?php _e('Position'); ?></th>
<?php if ( bb_current_user_can( 'delete_forums' ) && 1 < $forums_count ) : ?>
		     <th><?php _e('Action'); ?></th>
<?php endif; ?>
		 </tr>
<?php $t = 20; foreach ( $forums as $forum ) : ?>
		 <tr><td><input type="text" name="name-<?php forum_id(); ?>"  value="<?php echo wp_specialchars( get_forum_name(), 1 ); ?>" tabindex="<?php echo $t++; ?>" /></td>
		     <td><input type="text" name="desc-<?php forum_id(); ?>"  value="<?php echo wp_specialchars( get_forum_description(), 1 ); ?>" tabindex="<?php echo $t++; ?>" /></td>
		     <td><input type="text" name="order-<?php forum_id(); ?>" value="<?php echo $forum->forum_order; ?>" maxlength="10" tabindex="<?php echo $t++; ?>" /></td>
<?php if ( bb_current_user_can( 'delete_forums' ) && 1 < $forums_count ) : ?>
		     <td><?php if ( bb_current_user_can( 'delete_forum', $forum->forum_id ) ) : ?><a class="delete" href="<?php bb_option('uri'); ?>bb-admin/content-forums.php?action=delete&id=<?php forum_id();?>"><?php _e('Delete'); ?></a><?php endif; ?></td>
<?php endif; ?>
		 </tr>
<?php endforeach; ?>
		</table>
	<p class="submit alignleft"><input name="Submit" type="submit" value="<?php _e('Update'); ?>" tabindex="<?php echo $t; ?>" /><input type="hidden" name="action" value="update" /></p>
	</fieldset>
	<?php bb_nonce_field( 'update-forums' ); ?>
</form>
<?php endif; // $forums ?>

<?php endif; // action ?>

<?php bb_get_admin_footer(); ?>
