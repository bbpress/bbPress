<form class="login" method="post" action="<?php bb_option('uri'); ?>bb-login.php">
	<p><?php printf(__('<a href="%1$s">Register</a> or log in'), bb_get_option('uri').'register.php') ?>:</p>
	<div>
		<label><?php _e('Username:'); ?><br />
			<input name="user_login" type="text" id="user_login" size="13" maxlength="40" value="<?php echo $user_login; ?>" tabindex="1" />
		</label>
		<label><?php _e('Password:'); ?><br />
			<input name="password" type="password" id="password" size="13" maxlength="40" tabindex="2" />
		</label>
		<input name="re" type="hidden" value="<?php echo $re; ?>" />
		<?php wp_referer_field(); ?>
		<input type="submit" name="Submit" id="submit" value="<?php echo attribute_escape( __('Log in &raquo;') ); ?>" tabindex="4" />
	</div>
	<div class="remember">
		<label>
			<input name="remember" type="checkbox" id="remember" value="1" tabindex="3"<?php echo $remember_checked; ?> />
			<?php _e('Remember me'); ?>
		</label>
	</div>
</form>
