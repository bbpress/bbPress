<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<h2><?php _e('Forum Management'); ?></h2>
<form method="post" id="add-forum" action="<?php option('uri'); ?>bb-admin/bb-forum.php">
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
</form>
<?php if ( $forums = get_forums() ) : ?>
<form method="post" id="update-forums" action="<?php option('uri'); ?>bb-admin/bb-forum.php">
	<h3><?php _e('Update forum information'); ?></h3>
	<fieldset>
		<table>
		 <tr><th><?php _e('Name'); ?></th>
		     <th><?php _e('Description'); ?></th>
		     <th><?php _e('Position'); ?></th>
		 </tr>
<?php $t = 20; foreach ( $forums as $forum ) : ?>
		 <tr><td><input type="text" name="name-<?php forum_id(); ?>"  value="<?php echo bb_specialchars( get_forum_name(), 1 ); ?>" tabindex="<?php echo $t++; ?>" /></td>
		     <td><input type="text" name="desc-<?php forum_id(); ?>"  value="<?php echo bb_specialchars( get_forum_description(), 1 ); ?>" tabindex="<?php echo $t++; ?>" /></td>
		     <td><input type="text" name="order-<?php forum_id(); ?>" value="<?php echo $forum->forum_order; ?>" maxlength="10" tabindex="<?php echo $t++; ?>" /></td>
		 </tr>
<?php endforeach; ?>
		</table>
	<p class="submit alignleft"><input name="Submit" type="submit" value="<?php _e('Update'); ?>" tabindex="<?php echo $t; ?>" /><input type="hidden" name="action" value="update" /></p>
	</fieldset>
</form>
<?php endif; ?>

<?php bb_get_admin_footer(); ?>
