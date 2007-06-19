<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Log in'); ?></h3>

<h2><?php isset($_POST['user_login']) ? _e('Log in Failed') : _e('Log in') ; ?></h2>


<form method="post" action="<?php bb_option('uri'); ?>bb-login.php">
<table width="50%">
<?php if ( $user_exists ) : ?>
	<tr valign="top">
		<th scope="row"><?php _e('Username:'); ?></th>
		<td><input name="user_login" type="text" value="<?php echo $user_login; ?>" /></td>
	</tr>
	<tr valign="top" class="error">
		<th scope="row"><?php _e('Password:'); ?></th>
		<td><input name="password" type="password" /><br />
		<?php _e('Incorrect password'); ?></td>
	</tr>
<?php elseif ( isset($_POST['user_login']) ) : ?>
	<tr valign="top" class="error">
		<th scope="row"><?php _e('Username:'); ?></th>
		<td><input name="user_login" type="text" value="<?php echo $user_login; ?>" /><br />
		<?php _e('This username does not exist.'); ?> <a href="<?php bb_option('uri'); ?>register.php?user=<?php echo $user_login; ?>"><?php _e('Register it?'); ?></a></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Password:'); ?></th>
		<td><input name="password" type="password" /></td>
	</tr>
<?php else : ?>
	<tr valign="top" class="error">
		<th scope="row"><?php _e('Username:'); ?></th>
		<td><input name="user_login" type="text" /><br />
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Password:'); ?></th>
		<td><input name="password" type="password" /></td>
	</tr>
<?php endif; ?>
	<tr>
		<th scope="row">&nbsp;</th>
		<td><input name="re" type="hidden" value="<?php echo $redirect_to; ?>" />
		<input type="submit" value="<?php echo attribute_escape( isset($_POST['user_login']) ? __('Try Again &raquo;'): __('Log in &raquo;') ); ?>" /></td>
	</tr>
</table>
</form>

<?php if ( $user_exists ) : ?>
<hr />
<form method="post" action="<?php bb_option('uri'); ?>bb-reset-password.php">
<p><?php _e('If you would like to recover the password for this account, you may use the following button to start the recovery process:'); ?><br />
<input name="user_login" type="hidden" value="<?php echo $user_login; ?>" />
<input type="submit" value="<?php echo attribute_escape( __('Recover Password &raquo;') ); ?>" /></p>
</form>
<?php endif; ?>

<?php bb_get_footer(); ?>
