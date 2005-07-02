<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Register</h3>

<h2>Registration</h2>

<?php if ( !$current_user ) : ?>
<form method="post" action="<?php option('uri'); ?>register.php">
<fieldset>
<legend>Required</legend>
<table width="100%">
<?php if ( $user_safe ) : ?>
<tr>
<th width="33%" scope="row">Username:</th>
<td><input name="user_login" type="text" id="user_login" size="30" maxlength="30" value="<?php if (1 != $user_login) echo $user_login; ?>" /></td>
</tr>
<?php else : ?>
<tr class="error">
<th width="33%" scope="row">Username:</th>
<td><input name="user_login" type="text" id="user_login" size="30" maxlength="30" /><br />
Your username was not valid, please try again</td>
</tr>
<?php endif; ?>

<?php if ( $email ) : ?>
<tr>
<th scope="row">Email:</th>
<td><input name="email" type="text" id="email" size="30" maxlength="140" value="<?php if (1 != $email) echo $email; ?>" /></td>
</tr>
<?php else : ?>
<tr>
<th scope="row">Email:</th>
<td><input name="email" type="text" id="email" size="30" maxlength="140" /> <br />
There was a problem with your email, please check it.</td>
</tr>
<?php endif; ?>
</table>
<p>A password will be mailed to the email address you provide. Make sure to whitelist our domain (<?php echo $bb->domain; ?>) so the confirmation email doesn't get caught by any  filters. </p>
</fieldset>
<fieldset>
<legend>Optional Profile Info</legend>
<table width="100%">
<tr>
  <th width="33%" scope="row">Website:</th>
  <td><input name="url" type="text" id="url" size="30" maxlength="100" value="<?php if (1 != $url) echo $url; ?>" /></td>
</tr>
<tr>
  <th scope="row">Location:</th>
  <td><input name="location" type="text" id="location" size="30" maxlength="100" value="<?php if (1 != $location) echo $location; ?>" /></td>
</tr>
<tr>
  <th scope="row">Interests</th>
  <td><input name="interests" type="text" id="interests" size="30" maxlength="100" value="<?php if (1 != $interests) echo $interests; ?>" /></td>
</tr>
</table>
</fieldset>
<p class="submit">
  <input type="submit" name="Submit" value="Register &raquo;" />
</p>
</form>
<?php else : ?>
<p>You're already logged in, why do you need to register?</p>
<?php endif; ?>
<?php get_footer(); ?>
