<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Register'); ?></h3>

<h2 id="register"><?php _e('Registration'); ?></h2>

<?php if ( !bb_is_user_logged_in() ) : ?>
<form method="post" action="<?php bb_option('uri'); ?>register.php">
<fieldset>
<legend><?php _e('Profile Information'); ?></legend>
<p><?php _e("Your password will be emailed to the address you provide."); ?></p>
<table width="100%">
<?php if ( $user_safe === false ) : ?>
<tr class="error">
<th scope="row"><?php _e('Username:'); ?></th>
<td><input name="user_login" type="text" id="user_login" size="30" maxlength="30" /><br />
<?php _e('Your username was not valid, please try again'); ?></td>
</tr>
<?php else : ?>
<tr class="required">
<th scope="row"><?php _e('Username<sup>*</sup>:'); ?></th>
<td><input name="user_login" type="text" id="user_login" size="30" maxlength="30" value="<?php if (1 != $user_login) echo $user_login; ?>" /></td>
</tr>
<?php endif; ?>
<?php if ( is_array($profile_info_keys) ) : foreach ( $profile_info_keys as $key => $label ) : ?>
<tr<?php if ( $label[0] ) { echo ' class="required"'; $label[1] .= '<sup>*</sup>'; } ?>>
  <th scope="row"><?php echo $label[1]; ?>:</th>
  <td><input name="<?php echo $key; ?>" type="text" id="<?php echo $key; ?>" size="30" maxlength="140" value="<?php echo $$key; ?>" /><?php
if ( $$key === false ) :
	if ( $key == 'user_email' )
		_e('<br />There was a problem with your email; please check it.');
	else
		_e('<br />The above field is required.');
endif;
?></td>
</tr>
<?php endforeach; endif; ?>
</table>
<p><sup>*</sup><?php _e('These items are <span class="required">required</span>.') ?></p>
</fieldset>

<?php do_action('extra_profile_info', $user); ?>

<p class="submit">
  <input type="submit" name="Submit" value="<?php echo attribute_escape( __('Register &raquo;') ); ?>" />
</p>
</form>
<?php else : ?>
<p><?php _e('You&#8217;re already logged in, why do you need to register?'); ?></p>
<?php endif; ?>

<?php bb_get_footer(); ?>
