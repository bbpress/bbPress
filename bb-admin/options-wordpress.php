<?php
require_once('admin.php');

$action = $_POST['action'];

if ( in_array( $action, array('update-options', 'update-users') ) ) {
	bb_check_admin_referer( 'options-wordpress-' . $action );
	
	foreach ( (array) $_POST as $option => $value ) {
		if ( !in_array( $option, array('_wpnonce', '_wp_http_referer', 'action', 'submit') ) ) {
			$option = trim( $option );
			$value = is_array( $value ) ? $value : trim( $value );
			$value = stripslashes_deep( $value );
			if ($option == 'wp_siteurl' || $option == 'wp_home') {
				if ($value) {
					$value = rtrim($value, '/') . '/';
				}
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
}

switch ($_GET['updated']) {
	case 'update-options':
		bb_admin_notice( __('Settings saved.') );
		break;
	
	case 'update-users':
		bb_admin_notice( __('User role mapping saved.') );
		break;
}

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('WordPress Integration'); ?></h2>

<p>
	<?php _e('Usually, you will have to specify both cookie sharing and user database sharing settings.'); ?>
</p>

<p>
	<?php _e('<strong>Note:</strong> updating these settings may cause you to be logged out!'); ?>
</p>

<form class="settings" method="post" action="<?php bb_option('uri'); ?>bb-admin/options-wordpress.php">
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
			<label for="secret">
				<?php _e('WordPress database secret'); ?>
			</label>
			<div>
				<input class="text" name="secret" id="secret" value="<?php bb_form_option('secret'); ?>" />
				<p><?php _e('This must match the value of the WordPress setting named "secret" in your WordPress installation. Look for the option labeled "secret" in <a href="#" id="getSecretOption" onclick="window.open(this.href); return false;">this WordPress admin page</a>.'); ?></p>
			</div>
		</div>
		<script type="text/javascript" charset="utf-8">
			function updateWordPressOptionURL () {
				var siteURLInputValue = document.getElementById('wp_siteurl').value;
				var outputAnchor = document.getElementById('getSecretOption');
				if (siteURLInputValue) {
					if (siteURLInputValue.substr(-1,1) != '/') {
						siteURLInputValue += '/';
					}
					outputAnchor.href = siteURLInputValue + 'wp-admin/options.php';
				} else {
					outputAnchor.href = '';
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
	<p><?php _e('Also make sure that the "SECRET_KEY" in your WordPress <code>wp-config.php</code> file matches the "BB_SECRET_KEY" in your bbPress <code>bb-config.php</code> file.'); ?></p>
	<fieldset>
		<legend><?php _e('User database'); ?></legend>
		<p><?php _e('User database sharing allows you to store user data in your WordPress database.'); ?></p>
		<div>
			<label for="wp_table_prefix">
				<?php _e('User database table prefix'); ?>
			</label>
			<div>
				<input class="text" name="wp_table_prefix" id="wp_table_prefix" value="<?php bb_form_option('wp_table_prefix'); ?>" />
				<p><?php _e('If your bbPress and WordPress installations share the same database, then this is the same value as <code>$wp_table_prefix</code> in your WordPress <code>wp-config.php</code> file.'); ?></p>
				<p><?php _e('In any case, it is usually <strong>wp_</strong>'); ?></p>
			</div>
		</div>
		<div>
			<label for="user_bbdb_advanced">
				<?php _e('Show advanced database settings'); ?>
			</label>
<?php
$advanced_display = 'none';
if ( bb_get_option('user_bbdb_advanced') ) {
	$advanced_display = 'block';
	$checked = ' checked="checked"';
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
	</fieldset>
	<fieldset id="advanced2" style="display:<?php echo $advanced_display; ?>">
		<legend><?php _e('Custom user tables'); ?></legend>
		<p><?php _e('Only set these values if your user tables do not fit the usual mould of <strong>"wordpressprefix+user"</strong> and <strong>"wordpressprefix+usermeta"</strong>.'); ?></p>
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

<h2 class="after">bbPress config file settings</h2>
<?php
$cookie_settings = array(
	'wp_siteurl',
	'wp_home',
	'wp_table_prefix',
	'user_bbdb_name',
	'user_bbdb_user',
	'user_bbdb_password',
	'user_bbdb_host',
	'custom_user_table',
	'custom_user_meta_table',
	'authcookie',
	'cookiedomain',
	'cookiepath',
	'sitecookiepath'
);
$bb_settings = '';
foreach ($cookie_settings as $bb_setting) {
	if ( isset($bb->$bb_setting) ) {
		$bb_settings .= '$bb->' . $bb_setting . ' = \'' . $bb->$bb_setting . '\';' . "\n";
	}
}
?>
<p><?php _e('If your integration settings will not change, you can help speed up bbPress by adding the following code to your <code>config.php</code> file in the root directory of your bbPress installation. Afterwards, the settings in this form will reflect the hard coded values, but you will not be able to edit them here.'); ?></p>
<pre class="block"><?php echo($bb_settings); ?></pre>

<h2 class="after"><?php _e('User role map'); ?></h2>

<p>
	<?php _e('Here you can match WordPress roles to bbPress roles. This will not work if your user tables are not shared. Only standard WordPress roles are supported. Changes do not affect users with existing roles in both WordPress and bbPress.'); ?>
</p>

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
		$roles .= '<option value="' . $key . '"' . $selected . '>bbPress ' . $value . '</option>' . "\n";
	}
	
	$roles .= '</select>' . "\n";
	
	return $roles;
}

$wpRoles = array(
	'administrator' => 'WordPress Administrator',
	'editor' => 'WordPress Editor',
	'author' => 'WordPress Author',
	'contributor' => 'WordPress Contributor',
	'subscriber' => 'WordPress Subscriber'
);

$wpRolesMap = bb_get_option('wp_roles_map');
?>
<form class="settings" method="post" action="<?php bb_option('uri'); ?>bb-admin/options-wordpress.php">
	<fieldset>
<?php
foreach ($wpRoles as $wpRole => $wpRoleName) {
?>
		<div>
			<label for="wp_roles_map_<?php echo $wpRole; ?>">
				<?php _e($wpRoleName); ?>
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

</div>

<?php
bb_get_admin_footer();
?>
