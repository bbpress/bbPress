<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Log in'); ?></h3>

<h2 id="userlogin"><?php isset($_POST['user_login']) ? _e('Log in Failed') : _e('Log in') ; ?></h2>

<form method="post" action="<?php bb_option('uri'); ?>bb-login.php">
<fieldset>
<table>
<?php if ( $user_exists ) : ?>
	<tr valign="top">
		<th scope="row"><label for="user_login"><?php _e('Username:'); ?></label></th>
		<td><input name="user_login" id="user_login" type="text" value="<?php echo $user_login; ?>" /></td>
	</tr>
	<tr valign="top" class="error">
		<th scope="row"><label for="password"><?php _e('Password:'); ?></label></th>
		<td><input name="password" id="password" type="password" /><br />
		<?php _e('Incorrect password'); ?></td>
	</tr>
<?php elseif ( isset($_POST['user_login']) ) : ?>
	<tr valign="top" class="error">
		<th scope="row"><label for="user_login"><?php _e('Username:'); ?></label></th>
		<td><input name="user_login" id="user_login" type="text" value="<?php echo $user_login; ?>" /><br />
		<?php _e('This username does not exist.'); ?> <a href="<?php bb_option('uri'); ?>register.php?user=<?php echo $user_login; ?>"><?php _e('Register it?'); ?></a></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="password"><?php _e('Password:'); ?></label></th>
		<td><input name="password" id="password" type="password" /></td>
	</tr>
<?php else : ?>
	<tr valign="top" class="error">
		<th scope="row"><label for="user_login"><?php _e('Username:'); ?></label></th>
		<td><input name="user_login" id="user_login" type="text" /><br />
	</tr>
	<tr valign="top">
		<th scope="row"><label for="password"><?php _e('Password:'); ?></label></th>
		<td><input name="password" id="password" type="password" /></td>
	</tr>
<?php endif; ?>
	<tr valign="top">
		<th scope="row"><label for="remember"><?php _e('Remember me:'); ?></label></th>
		<td><input name="remember" type="checkbox" id="remember" value="1"<?php echo $remember_checked; ?> /></td>
	</tr>
	<tr>
		<th scope="row">&nbsp;</th>
		<td>
			<input name="re" type="hidden" value="<?php echo $redirect_to; ?>" />
			<input type="submit" value="<?php echo attribute_escape( isset($_POST['user_login']) ? __('Try Again &raquo;'): __('Log in &raquo;') ); ?>" />
			<?php wp_referer_field(); ?>
		</td>
	</tr>
</table>
</fieldset>
</form>

<?php if ( $user_exists ) : ?>
<form method="post" action="<?php bb_option('uri'); ?>bb-reset-password.php">
<fieldset>
	<p><?php _e('If you would like to recover the password for this account, you may use the following button to start the recovery process:'); ?></p>
	<table>
		<tr>
			<th></th>
			<td>
				<input name="user_login" type="hidden" value="<?php echo $user_login; ?>" />
				<input type="submit" value="<?php echo attribute_escape( __('Recover Password &raquo;') ); ?>" />
			</td>
		</tr>
	</table>
</fieldset>
</form>
<?php endif; ?>

<?php bb_get_footer(); ?>
