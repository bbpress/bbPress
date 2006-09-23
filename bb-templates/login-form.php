<form class="login" method="post" action="<?php option('uri'); ?>bb-login.php">
<p> <a href="<?php option('uri'); ?>register.php"><?php _e('Register'); ?></a> <?php _e('or login'); ?>:<br />
  <label><?php _e('Username:'); ?>
  <input name="user_login" type="text" id="user_login" size="15" maxlength="40" value="<?php echo wp_specialchars($_COOKIE[ $bb->usercookie ], 1); ?>" />
  </label> 

  <label><?php _e('Password:'); ?>
  <input name="password" type="password" id="password" size="15" maxlength="40" />
  </label>
  <input type="submit" name="Submit" value="<?php _e('Login'); ?> &raquo;" />
</p>
</form>
