<?php bb_get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Edit Profile</h3>
<h2><?php echo $user->user_login; ?></h2>
<form method="post" action="<?php profile_tab_link($user->ID, 'edit');  ?>">
<fieldset>
<legend>Profile Info</legend>
<table width="100%">
<?php if ( is_array($profile_info_keys) ) : foreach ( $profile_info_keys as $key => $label ) : if ( 'user_email' != $key || $bb_current_user->ID == $user_id ) : ?>
<tr<?php if ( $label[0] ) { echo ' class="required"'; $label[1] .= '<sup>*</sup>'; $required = true; } ?>>
  <th scope="row"><?php echo $label[1]; ?>:</th>
  <td><input name="<?php echo $key; ?>" type="<?php if ( isset($label[2]) ) echo $label[2]; else echo 'text" size="30" maxlength="140'; ?>" id="<?php echo $key; ?>" value="<?php echo $user->$key; ?>" /><?php
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

<?php bb_do_action('extra_profile_info', $user); ?>

<?php if ( bb_current_user_can('edit_users') ) : $required = false; ?>
<fieldset>
<legend>Administration</legend>
<table width="100%">
<tr>
  <th scope="row">User Type:</th>
  <td><select name="role">
<?php foreach( $bb_roles->role_names as $r => $n ) : if ( 'keymaster' != $r || bb_current_user_can('keep_gate') ) : ?>
       <option value="<?php echo $r; ?>"<?php if ( array_key_exists($r, $user->capabilities) ) echo ' selected="selected"'; ?>><?php echo $n; ?></option>
<?php endif; endforeach; ?>
      </select>
  </td>
</tr>
<tr class="extra-caps-row">
  <th scope="row">Allow this user to:</th>
  <td>
<?php foreach( $assignable_caps as $cap => $label ) : ?>
      <label><input name="<?php echo $cap; ?>" value="1" type="checkbox"<?php if ( array_key_exists($cap, $user->capabilities) ) echo ' checked="checked"'; ?> /> <?php echo $label; ?></label><br />
<?php endforeach; ?>
  </td>
</tr>
<?php if ( is_array($profile_admin_keys) ) : foreach ( $profile_admin_keys as $key => $label ) : ?>
<tr<?php if ( $label[0] ) { echo ' class="required"'; $label[1] .= '<sup>*</sup>'; $required = true; } ?>>
  <th scope="row"><?php echo $label[1]; ?>:</th>
  <td><input name="<?php echo $key; ?>" id="<?php echo $key; ?>" type="<?php if ( isset($label[2]) ) echo $label[2]; else echo 'text" size="30" maxlength="140" value="' . $user->$key . '"'; ?>" /><?php
if ( isset($$key) && false === $$key ) :
	_e('<br />The above field is required.');
endif;
?></td>
</tr>
<?php endforeach; endif; ?>
<tr>
  <th scope="row">Delete user:</th>
  <td><label for="user_status"><input type="checkbox" name="user_status" id="user_status" value="1" /> Check to delete user.  This cannote be easily undone.</label>
  </td>
</tr>
</table>
<?php if ( $required ) : ?>
<p><sup>*</sup>These items are <span class="required">required</span>.</p>
<?php endif; ?>
<p>Deletion attributes all content to Anonymous and cannot be easily undone.  A Deleted user can do anything any non-logged in person can do.
A more useful solution to user problems is to change a user's User Type to Inactive or Blocked.
Inactive users can login and look around but not do anything.  Blocked users just see a simple error message when they visit the site.</p>
<p><strong>Note</strong>: Blocking a user does <em>not</em> block any IP addresses.</p>
</fieldset>
<?php endif; ?>

<?php if ( $bb_current_user->ID == $user->ID ) : ?>
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

<?php bb_get_footer(); ?>
