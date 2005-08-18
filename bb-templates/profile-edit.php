<?php get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Edit Profile</h3>
<h2><?php echo $user->user_login; ?></h2>
<form method="post" action="<?php profile_tab_link($user->ID, 'edit');  ?>">
<fieldset>
<legend>Profile Info</legend>
<table width="100%">
<?php if ( is_array($profile_info_keys) ) : foreach ( $profile_info_keys as $key => $label ) : if ( 'user_email' != $key || $current_user->ID == $user_id ) : ?>
<tr<?php if ( $label[0] ) { echo ' class="required"'; $label[1] .= '<sup>*</sup>'; $required = true; } ?>>
  <th scope="row"><?php echo $label[1]; ?>:</th>
  <td><input name="<?php echo $key; ?>" type="text" id="<?php echo $key; ?>" size="30" maxlength="140" value="<?php echo $user->$key; ?>" /><?php
if ( isset($$key) && false === $$key) :
	if ( $key == 'user_email' )
		_e('<br />There was a problem with your email; please check it.');
	else
		_e('<br />The above field is required.');
endif;
?></td>
</tr>
<?php endif; endforeach; endif; ?>
</table>
<?php if ( $required ) : ?>
<p><sup>*</sup>These items are <span class="required">required</span>.</p>
<?php endif; ?>
</fieldset>
<?php if ( current_user_can('edit_users') ) : $required = false; ?>
<fieldset>
<legend>Administration</legend>
<table width="100%">
<tr>
  <th scope="row">User Type:</th>
  <td><select name="role">
<?php foreach( $bb_roles->role_names as $r => $n ) : if ( 'keymaster' != $r || current_user_can('keep_gate') ) : ?>
       <option value="<?php echo $r; ?>"<?php if ( array_key_exists($r, $user->capabilities) ) echo ' selected="selected"'; ?>><?php echo $n; ?></option>
<?php endif; endforeach; ?>
      </select>
  </td>
</tr>
<?php if ( is_array($profile_admin_keys) ) : foreach ( $profile_admin_keys as $key => $label ) : ?>
<tr<?php if ( $label[0] ) { echo ' class="required"'; $label[1] .= '<sup>*</sup>'; $required = true; } ?>>
  <th scope="row"><?php echo $label[1]; ?>:</th>
  <td><input name="<?php echo $key; ?>" type="text" id="<?php echo $key; ?>" size="30" maxlength="140" value="<?php echo $user->$key; ?>" /><?php
if ( isset($$key) && false === $$key ) :
	_e('<br />The above field is required.');
endif;
?></td>
</tr>
<?php endforeach; endif; ?>
<tr>
  <th scope="row">Delete user<sup>**</sup>:</th>
  <td><label for="user_status"><input type="checkbox" name="user_status" id="user_status" value="1" /> Check to delete user.  This cannote be easily undone.</label>
  </td>
</tr>
</table>
<?php if ( $required ) : ?>
<p><sup>*</sup>These items are <span class="required">required</span>.</p>
<?php endif; ?>
<p><sup>**</sup>Deletion attributes all content to Anonymous and cannot be easily undone.  To keep proper attribution but still keep a user from logging in, consider changing their user type to "Inactive".</p>
<p>User types Inactive and Blocked have no practical difference at the moment.  Both can log in and view content.</p>
</fieldset>
<?php endif; ?>

<?php if ( $current_user->ID == $user->ID ) : ?>
<fieldset>
<legend>Password</legend>
<p>If you wish to update your password, you may enter a new password twice below:</p>
<table width="100%">
<tr>
  <th scope="row">New password:</th>
  <td><input name="pass1" type="password" id="pass1" size="15" maxlength="100" /></td>
</tr>
<tr>
  <th></th>
  <td><input name="pass2" type="password" id="pass2" size="15" maxlength="100" /></td>
</tr>
</table>
</fieldset>
<?php endif; ?>
<p class="submit">
  <input type="submit" name="Submit" value="Update Profile &raquo;" />
</p>
</form>

<?php get_footer(); ?>
