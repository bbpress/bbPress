<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Edit Profile</h3>
<h2><?php echo $user->username; ?></h2>
<form method="post" action="<?php option('uri'); ?>profile-edit.php">
<fieldset>
<legend>Optional Profile Info</legend>
<table width="100%">
<tr>
  <th width="33%" scope="row">Website:</th>
  <td><input name="website" type="text" id="website" size="30" maxlength="100" value="<?php echo $current_user->user_website; ?>" /></td>
</tr>
<tr>
  <th scope="row">Location:</th>
  <td><input name="location" type="text" id="location" size="30" maxlength="100" value="<?php echo $current_user->user_from; ?>" /></td>
</tr>
<tr>
  <th scope="row">Interests</th>
  <td><input name="interests" type="text" id="interests" size="30" maxlength="100" value="<?php echo $current_user->user_interest; ?>" /></td>
</tr>
</table>
</fieldset>

<fieldset>
<legend>Password</legend>
<p>If you wish to update your password, you may enter a new password twice below:</p>
<table width="100%">
<tr>
  <th width="33%" scope="row">New password:</th>
  <td><input name="pass1" type="password" id="pass1" size="15" maxlength="100" /></td>
</tr>
<tr>
  <th></th>
  <td><input name="pass2" type="password" id="pass2" size="15" maxlength="100" /></td>
</tr>
</table>
</fieldset>
<p class="submit">
  <input type="submit" name="Submit" value="Update Profile &raquo;" />
</p>
</form>

<?php get_footer(); ?>