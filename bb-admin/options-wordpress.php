<?php
require_once('admin.php');

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) )
	$action = @$_POST['action'];
else
	$action = false;

if ( in_array( $action, array('update-users', 'update-options') ) ) {
	bb_check_admin_referer( 'options-wordpress-' . $action );
	
	// Deal with advanced user database checkbox when it isn't checked
	if (!isset($_POST['user_bbdb_advanced'])) {
		$_POST['user_bbdb_advanced'] = false;
	}
	
	foreach ( (array) $_POST as $option => $value ) {
		if ( !in_array( $option, array('_wpnonce', '_wp_http_referer', 'action', 'submit') ) ) {
			$option = trim( $option );
			$value = is_array( $value ) ? $value : trim( $value );
			$value = stripslashes_deep( $value );
			if (($option == 'wp_siteurl' || $option == 'wp_home') && !empty($value)) {
				$value = rtrim($value, " \t\n\r\0\x0B/") . '/';
			}
			if ( $value ) {
				bb_update_option( $option, $value );
			} else {
				bb_delete_option( $option );
			}
		}
	}
	
	if ($action == 'update-users') {
		bb_apply_wp_role_map_to_orphans();
	}
	
	$goback = add_query_arg('updated', $action, wp_get_referer());
	bb_safe_redirect($goback);
	exit;
}

switch (@$_GET['updated']) {
	case 'update-users':
		bb_admin_notice( __('User role mapping saved.') );
		break;
	case 'update-options':
		bb_admin_notice( __('User integration settings saved.') );
		break;
}

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('User Role Map'); ?></h2>

<p><?php _e('Here you can match WordPress roles to bbPress roles.'); ?></p>
<p><?php _e('This will have no effect until your user tables are integrated below. Only standard WordPress roles are supported. Changes do not affect users with existing roles in both WordPress and bbPress.'); ?></p>

<?php
// Setup the role dropdowns
function bb_get_roles_dropdown($id = 'roles', $name = 'roles', $set = false) {
	$roles = '<select id="' . $id . '" name="' . $name . '">' . "\n";
	$roles .= '<option value="">' . __('none') . '</option>' . "\n";
	
	global $wp_roles;
	
	foreach ($wp_roles->get_names() as $key => $value) {
		if ($key == $set) {
			$selected = ' selected="selected"';
		} else {
			$selected = '';
		}
		$roles .= '<option value="' . $key . '"' . $selected . '>' . sprintf( __( 'bbPress %s' ), $value ) . '</option>' . "\n";
	}
	
	$roles .= '</select>' . "\n";
	
	return $roles;
}

$wpRoles = array(
	'administrator' => __('WordPress Administrator'),
	'editor'        => __('WordPress Editor'),
	'author'        => __('WordPress Author'),
	'contributor'   => __('WordPress Contributor'),
	'subscriber'    => __('WordPress Subscriber')
);

$wpRolesMap = bb_get_option('wp_roles_map');
?>
<form class="settings" method="post" action="<?php bb_uri('bb-admin/options-wordpress.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset>
<?php
foreach ($wpRoles as $wpRole => $wpRoleName) {
?>
		<div>
			<label for="wp_roles_map_<?php echo $wpRole; ?>">
				<?php echo $wpRoleName; ?>
			</label>
			<div>
				<?php echo bb_get_roles_dropdown( 'wp_roles_map_' . $wpRole, 'wp_roles_map[' . $wpRole . ']', $wpRolesMap[$wpRole]); ?>
			</div>
		</div>
<?php
}
?>
	</fieldset>
	<fieldset class="submit">
		<?php bb_nonce_field( 'options-wordpress-update-users' ); ?>
		<input type="hidden" name="action" value="update-users" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save User Role Map') ?>" />
	</fieldset>
</form>

<h2 class="after"><?php _e('User Integration'); ?></h2>

<p><?php _e('Usually, you will have to specify both cookie sharing and user database sharing settings.'); ?></p>
<p><?php _e('Make sure you have a "User role map" setup above before trying to add user integration.'); ?></p>
<p><?php _e('<strong>Note:</strong> updating these settings may cause you to be logged out!'); ?></p>

<form class="settings" method="post" action="<?php bb_uri('bb-admin/options-wordpress.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset>
		<legend><?php _e('Cookies'); ?></legend>
		<p><?php _e('Cookie sharing allows users to log in to either your bbPress or your WordPress site, and have access to both.'); ?></p>
		<div>
			<label for="wp_siteurl">
				<?php _e('WordPress address (URL)'); ?>
			</label>
			<div>
				<input class="text long" name="wp_siteurl" id="wp_siteurl" value="<?php bb_form_option('wp_siteurl'); ?>" />
				<p><?php _e('This value should exactly match the <strong>WordPress address (URL)</strong> setting in your WordPress general settings.'); ?></p>
			</div>
		</div>
		<div>
			<label for="wp_home">
				<?php _e('Blog address (URL)'); ?>
			</label>
			<div>
				<input class="text long" name="wp_home" id="wp_home" value="<?php bb_form_option('wp_home'); ?>" />
				<p><?php _e('This value should exactly match the <strong>Blog address (URL)</strong> setting in your WordPress general settings.'); ?></p>
			</div>
		</div>
		<div>
			<label for="bb_auth_salt">
				<?php _e('WordPress "auth" cookie salt'); ?>
			</label>
			<div>
<?php
if ( defined( 'BB_AUTH_SALT' ) ) {
?>
				<p><?php printf( __( 'You have defined the "%s" constant which overides this setting.' ), 'BB_AUTH_SALT' ); ?></p>
<?php
} else {
?>
				<input class="text" name="bb_auth_salt" id="bb_auth_salt" value="<?php bb_form_option('bb_auth_salt'); ?>" />
				<p><?php _e('This must match the value of the WordPress setting named "auth_salt" in your WordPress installation. Look for the option labeled "auth_salt" in <a href="#" id="getAuthSaltOption" onclick="window.open(this.href); return false;">this WordPress admin page</a>.'); ?></p>
<?php
}
?>
			</div>
		</div>
		<div>
			<label for="bb_secure_auth_salt">
				<?php _e('WordPress "secure auth" cookie salt'); ?>
			</label>
			<div>
<?php
if ( defined( 'BB_SECURE_AUTH_SALT' ) ) {
?>
				<p><?php printf( __( 'You have defined the "%s" constant which overides this setting.' ), 'BB_SECURE_AUTH_SALT' ); ?></p>
<?php
} else {
?>
				<input class="text" name="bb_secure_auth_salt" id="bb_secure_auth_salt" value="<?php bb_form_option('bb_secure_auth_salt'); ?>" />
				<p><?php _e('This must match the value of the WordPress setting named "secure_auth_salt" in your WordPress installation. Look for the option labeled "secure_auth_salt" in <a href="#" id="getSecureAuthSaltOption" onclick="window.open(this.href); return false;">this WordPress admin page</a>. Sometimes this value is not set in WordPress, in that case you can leave this setting blank as well.'); ?></p>
<?php
}
?>
			</div>
		</div>
		<div>
			<label for="bb_logged_in_salt">
				<?php _e('WordPress "logged in" cookie salt'); ?>
			</label>
			<div>
<?php
if ( defined( 'BB_LOGGED_IN_SALT' ) ) {
?>
				<p><?php printf( __( 'You have defined the "%s" constant which overides this setting.' ), 'BB_LOGGED_IN_SALT' ); ?></p>
<?php
} else {
?>
				<input class="text" name="bb_logged_in_salt" id="bb_logged_in_salt" value="<?php bb_form_option('bb_logged_in_salt'); ?>" />
				<p><?php _e('This must match the value of the WordPress setting named "logged_in_salt" in your WordPress installation. Look for the option labeled "logged_in_salt" in <a href="#" id="getLoggedInSaltOption" onclick="window.open(this.href); return false;">this WordPress admin page</a>.'); ?></p>
<?php
}
?>
			</div>
		</div>
		<script type="text/javascript" charset="utf-8">
/* <![CDATA[ */
			function updateWordPressOptionURL () {
				var siteURLInputValue = document.getElementById('wp_siteurl').value;
				if (siteURLInputValue && siteURLInputValue.substr(-1,1) != '/') {
					siteURLInputValue += '/';
				}
				var authSaltAnchor = document.getElementById('getAuthSaltOption');
				var secureAuthSaltAnchor = document.getElementById('getSecureAuthSaltOption');
				var loggedInSaltAnchor = document.getElementById('getLoggedInSaltOption');
				if (siteURLInputValue) {
					authSaltAnchor.href = siteURLInputValue + 'wp-admin/options.php';
					secureAuthSaltAnchor.href = siteURLInputValue + 'wp-admin/options.php';
					loggedInSaltAnchor.href = siteURLInputValue + 'wp-admin/options.php';
				} else {
					authSaltAnchor.href = '';
					secureAuthSaltAnchor.href = '';
					loggedInSaltAnchor.href = '';
				}
			}
			var siteURLInput = document.getElementById('wp_siteurl');
			if (siteURLInput.value) {
				updateWordPressOptionURL();
			}
			siteURLInput.onkeyup = updateWordPressOptionURL;
			siteURLInput.onblur = updateWordPressOptionURL;
			siteURLInput.onclick = updateWordPressOptionURL;
			siteURLInput.onchange = updateWordPressOptionURL;
/* ]]> */
		</script>
<?php
$cookie_settings = array(
	'cookiedomain' => 'COOKIE_DOMAIN',
	'cookiepath' => 'COOKIEPATH'
);
$wp_settings = '';
foreach ($cookie_settings as $bb_setting => $wp_setting) {
	if ( isset($bb->$bb_setting) ) {
		$wp_settings .= 'define(\'' . $wp_setting . '\', \'' . $bb->$bb_setting . '\');' . "\n";
	}
}
?>
	</fieldset>
	<p><?php _e('bbPress has automatically determined the best cookie settings for WordPress. In some cases integration may work without these settings, but if not add the following code to your <code>wp-config.php</code> file in the root directory of your WordPress installation.'); ?></p>
	<pre class="block"><?php echo($wp_settings); ?></pre>
	<p><?php _e('You will also have to manually ensure that the following constants are equivalent in WordPress\' and bbPress\' respective config files.'); ?></p>
	<table class="block">
		<tr>
			<th><?php _e('WordPress (wp-config.php)'); ?></th>
			<th><?php _e('bbPress (bb-config.php)'); ?></th>
		</tr>
		<tr>
			<td>AUTH_KEY</td>
			<td>BB_AUTH_KEY</td>
		</tr>
		<tr>
			<td>SECURE_AUTH_KEY</td>
			<td>BB_SECURE_AUTH_KEY</td>
		</tr>
		<tr>
			<td>LOGGED_IN_KEY</td>
			<td>BB_LOGGED_IN_KEY</td>
		</tr>
	</table>
	<fieldset>
		<legend><?php _e('User database'); ?></legend>
		<p><?php _e('User database sharing allows you to store user data in your WordPress database.'); ?></p>
		<p><?php _e('You should setup a "User role map" before'); ?></p>
		<div>
			<label for="wp_table_prefix">
				<?php _e('User database table prefix'); ?>
			</label>
			<div>
				<input class="text" name="wp_table_prefix" id="wp_table_prefix" value="<?php bb_form_option('wp_table_prefix'); ?>" />
				<p><?php _e('If your bbPress and WordPress installations share the same database, then this is the same value as <code>$table_prefix</code> in your WordPress <code>wp-config.php</code> file.'); ?></p>
				<p><?php _e('In any case, it is usually <strong>wp_</strong>'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_advanced">
				<?php _e('Show advanced database settings'); ?>
			</label>
<?php
if ( bb_get_option('user_bbdb_advanced') ) {
	$advanced_display = 'block';
	$checked = ' checked="checked"';
} else {
	$advanced_display = 'none';
	$checked = '';
}
	
?>
			<script type="text/javascript" charset="utf-8">
				function toggleAdvanced(checkedObj) {
					var advanced1 = document.getElementById('advanced1');
					var advanced2 = document.getElementById('advanced2');
					if (checkedObj.checked) {
						advanced1.style.display = 'block';
						advanced2.style.display = 'block';
					} else {
						advanced1.style.display = 'none';
						advanced2.style.display = 'none';
					}
				}
			</script>
			<div>
				<input name="user_bbdb_advanced" id="user_bbdb_advanced" type="checkbox" value="1" onclick="toggleAdvanced(this);"<?php echo $checked; ?> />
				<p><?php _e('If your bbPress and WordPress installations do not share the same database, then you will need to add advanced settings.'); ?></p>
			</div>
		</div>
	</fieldset>
	<fieldset id="advanced1" style="display:<?php echo $advanced_display; ?>">
		<legend><?php _e('Separate user database settings'); ?></legend>
		<p><?php _e('Most of the time these settings are <em>not</em> required. Look before you leap!'); ?></p>
		<p><?php _e('All settings except for the character set must be specified.'); ?></p>
		<div>
			<label for="user_bbdb_name">
				<?php _e('User database name:'); ?>
			</label>
			<div>
				<input class="text" name="user_bbdb_name" id="user_bbdb_name" value="<?php bb_form_option('user_bbdb_name'); ?>" />
				<p><?php _e('The name of the database in which your user tables reside.'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_user">
				<?php _e('User database user:'); ?>
			</label>
			<div>
				<input class="text" name="user_bbdb_user" id="user_bbdb_user" value="<?php bb_form_option('user_bbdb_user'); ?>" />
				<p><?php _e('The database user that has access to that database.'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_password">
				<?php _e('User database password:'); ?>
			</label>
			<div>
				<input class="text" type="password" name="user_bbdb_password" id="user_bbdb_password" value="<?php bb_form_option('user_bbdb_password'); ?>" />
				<p><?php _e('That database user\'s password.'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_host">
				<?php _e('User database host:'); ?>
			</label>
			<div>
				<input class="text" name="user_bbdb_host" id="user_bbdb_host" value="<?php bb_form_option('user_bbdb_host'); ?>" />
				<p><?php _e('The domain name or IP address of the server where the database is located. If the database is on the same server as the web site, then this probably should remain <strong>localhost</strong>.'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_charset">
				<?php _e('User database character set:'); ?>
			</label>
			<div>
				<input class="text" name="user_bbdb_charset" id="user_bbdb_charset" value="<?php bb_form_option('user_bbdb_charset'); ?>" />
				<p><?php _e('The best choice is <strong>utf8</strong>, but you will need to match the character set which you created the database with.'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_collate">
				<?php _e('User database character collation:'); ?>
			</label>
			<div>
				<input class="text" name="user_bbdb_collate" id="user_bbdb_collate" value="<?php bb_form_option('user_bbdb_collate'); ?>" />
				<p><?php _e('The character collation value set when the user database was created.'); ?></p>
			</div>
		</div>
	</fieldset>
	<fieldset id="advanced2" style="display:<?php echo $advanced_display; ?>">
		<legend><?php _e('Custom user tables'); ?></legend>
		<p><?php _e('Only set these values if your user tables differ from the default WordPress naming convention.'); ?></p>
		<div>
			<label for="custom_user_table">
				<?php _e('User database "user" table:'); ?>
			</label>
			<div>
				<input class="text" name="custom_user_table" id="custom_user_table" value="<?php bb_form_option('custom_user_table'); ?>" />
				<p><?php _e('The complete table name, including any prefix.'); ?></p>
			</div>
		</div>
		<div>
			<label for="custom_user_meta_table">
				<?php _e('User database "user meta" table:'); ?>
			</label>
			<div>
				<input class="text" name="custom_user_meta_table" id="custom_user_meta_table" value="<?php bb_form_option('custom_user_meta_table'); ?>" />
				<p><?php _e('The complete table name, including any prefix.'); ?></p>
			</div>
		</div>
	</fieldset>
	<fieldset class="submit">
		<?php bb_nonce_field( 'options-wordpress-update-options' ); ?>
		<input type="hidden" name="action" value="update-options" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>

<h2 class="after"><?php _e('Manual bbPress config file settings'); ?></h2>
<?php
$cookie_settings = array(
	'// Start integration speedups',
	'',
	'// WordPress database integration speedup',
	'wp_table_prefix',
	'user_bbdb_name',
	'user_bbdb_user',
	'user_bbdb_password',
	'user_bbdb_host',
	'user_bbdb_charset',
	'user_bbdb_collate',
	'custom_user_table',
	'custom_user_meta_table',
	'',
	'// WordPress cookie integration speedup',
	'wp_siteurl',
	'wp_home',
	'cookiedomain',
	'cookiepath',
	'authcookie',
	'secure_auth_cookie',
	'logged_in_cookie',
	'admin_cookie_path',
	'core_plugins_cookie_path',
	'user_plugins_cookie_path',
	'sitecookiepath',
	'wp_admin_cookie_path',
	'wp_plugins_cookie_path',
	'',
	'// End integration speedups'
);
$bb_settings = '';
foreach ($cookie_settings as $bb_setting) {
	if ($bb_setting === '') {
		$bb_settings .= "\n";
	} elseif (substr($bb_setting, 0, 2) == '//') {
		$bb_settings .= $bb_setting . "\n";
	} elseif ( isset($bb->$bb_setting) ) {
		$bb_settings .= '$bb->' . $bb_setting . ' = \'' . $bb->$bb_setting . '\';' . "\n";
	}
}
?>
<p><?php _e('If your integration settings will not change, you can help speed up bbPress by adding the following code to your <code>bb-config.php</code> file in the root directory of your bbPress installation. Afterwards, the settings in this form will reflect the hard coded values, but you will not be able to edit them here.'); ?></p>
<pre class="block"><?php echo($bb_settings); ?></pre>

</div>

<?php
bb_get_admin_footer();
?>
