<form class="login" method="post" action="<?php option('uri'); ?>bb-login.php">
<p> <a href="<?php option('uri'); ?>register.php">Register</a> or login:<br />
  <label>Username: 
  <input name="username" type="text" id="username" size="15" maxlength="40" value="<?php echo bb_specialchars($_COOKIE[ $bb->usercookie ], 1); ?>" />
  </label> 

  <label>Password:
  <input name="password" type="password" id="password" size="15" maxlength="40" />
  </label>
  <input type="submit" name="Submit" value="Login &raquo;" />
</p>
</form>
