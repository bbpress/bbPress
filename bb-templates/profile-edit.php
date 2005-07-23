<?php get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Edit Profile</h3>
<h2><?php echo $user->user_login; ?></h2>
<form method="post" action="<?php profile_tab_link($user->ID, 'edit');  ?>">
<fieldset>
<legend>Profile Info</legend>
<table width="100%">
<?php foreach ( $profile_info_keys as $key => $label ) : if ( 'user_email' != $key || $current_user->ID == $user_id ) : ?>
<tr<?php if ( $label[0] ) { echo ' class="required"'; $label[1] .= '<sup>*</sup>'; $required = true; } ?>>
  <th scope="row"><?php echo $label[1]; ?>:</th>
  <td><input name="<?php echo $key; ?>" type="text" id="<?php echo $key; ?>" size="30" maxlength="140" value="<?php echo $user->$key; ?>" /><?php
if ( $$key === false ) :
	if ( $key == 'user_email' )
		_e('<br />There was a problem with your email; please check it.');
	else
		_e('<br />The above field is required.');
endif;
?></td>
</tr>
<?php endif; endforeach; ?>
</table>
<?php if ( $required ) : ?>
<p><sup>*</sup>These items are <span class="required">required</span>.</p>
<?php endif; ?>
</fieldset>

<?php if ( $current_user->ID == $user->ID ) : ?>
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
<?php endif; ?>
<p class="submit">
  <input type="submit" name="Submit" value="Update Profile &raquo;" />
</p>
</form>

<?php get_footer(); ?>
