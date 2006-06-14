<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<h2><?php _e('Recount') ?></h2>
<p><?php _e("The following checkboxes allow you to recalculate various numbers stored in
the database.  These numbers are used for things like counting the number of
pages worth of posts a particular topic has.  You shouldn't need to do do any of
this unless you're upgrading from one version to another or are seeing
pagination oddities.") ?></p>

<form method="post" action="<?php option('uri'); ?>bb-admin/bb-do-counts.php">
	<fieldset>
	<legend><?php _e('Choose items to recalculate') ?></legend>
		<ol>
		<?php bb_recount_list(); if ( $recount_list ) : $i = 100; foreach ( $recount_list as $item ) : ?>
		 <li<?php alt_class('recount'); ?>><label for="<?php echo $item[0]; ?>"><input name="<?php echo $item[0]; ?>" id="<?php echo $item[0]; ?>" type="checkbox" value="1" tabindex="<?php echo $i++; ?>" /> <?php echo $item[1]; ?>.</label></li>
		<?php endforeach; endif; ?>
		</ol>
		<p class="submit alignleft"><input name="Submit" type="submit" value="<?php _e('Count!') ?>" tabindex="<?php echo $i++; ?>" /></p>
	</fieldset>
</form>

<?php bb_get_admin_footer(); ?>
