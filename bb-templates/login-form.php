<form class="login" method="post" action="<?php option('uri'); ?>bb-login.php">
<p><?php printf(__('<a href="%1$s">Register</a> or login'), bb_get_option('uri').'register.php') ?>:</p>
<p>
	<label><?php _e('Username:'); ?><br />
		<input name="user_login" type="text" id="user_login" size="13" maxlength="40" value="<?php echo wp_specialchars($_COOKIE[ $bb->usercookie ], 1); ?>" />
  </label>
	<label><?php _e('Password:'); ?><br />
		<input name="password" type="password" id="password" size="13" maxlength="40" />
	</label>
	<input type="submit" name="Submit" id="submit" value="<?php _e('Login'); ?> &raquo;" />
</p>
</form>
