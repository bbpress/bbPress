<?php
// Remove these lines if you want to upgrade and are using safe mode
if ( ini_get('safe_mode') )
	die("You're running in safe mode which does not allow this upgrade
	script to set a running time limit.  Depending on the size of your
	database and on which parts of the script you are running, the script
	can take quite some time to run (or it could take just a few seconds).
	To throw caution to the wind and run the script in safe mode anyway,
	remove the first few lines of code in the <code>bb-admin/upgrade.php</code>
	file. Backups are always a good idea.");
// Stop removing lines

// Very old (pre 0.7) installs may need further upgrade utilities.
// Post to http://lists.bbpress.org/mailman/listinfo/bbdev if needed

require('../bb-load.php');
require( BB_PATH . 'bb-admin/upgrade-functions.php' );

$step = 'unrequired';

if ( bb_get_option( 'bb_db_version' ) > bb_get_option_from_db( 'bb_db_version' ) || $_GET['force'] == 1 ) {
	
	$form_action_querystring = '';
	if ($_GET['force'] == 1) {
		$form_action_querystring = '?force=1';
	}
	
	$step = 'required';
	
	if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
		
		bb_check_admin_referer( 'bbpress-upgrader' );
		
		define('BB_UPGRADING', true);
		
		$bbdb->hide_errors();
		
		$messages = bb_upgrade_all();
		
		$bbdb->show_errors();
		
		$upgrade_log = array(__('Beginning upgrade&hellip;'));
		if (is_array($messages['messages'])) {
			$upgrade_log = array_merge($upgrade_log, $messages['messages']);
		}
		$upgrade_log[] = '>>> ' . __('Done');
		
		$error_log = array();
		if (is_array($messages['errors'])) {
			$error_log = $messages['errors'];
		}
		
		if ( bb_get_option( 'bb_db_version' ) === bb_get_option_from_db( 'bb_db_version' ) && !count($error_log) ) {
			$step = 'complete';
		} else {
			$step = 'error';
		}
		
		wp_cache_flush();
	}
	
}

bb_install_header( __('bbPress database upgrade'), __('bbPress database upgrade') );
?>
		<script type="text/javascript" charset="utf-8">
			function toggleAdvanced(toggle, target) {
				var toggleObj = document.getElementById(toggle);
				var targetObj = document.getElementById(target);
				if (toggleObj.checked) {
					targetObj.style.display = 'block';
				} else {
					targetObj.style.display = 'none';
				}
			}
		</script>
<?php
switch ($step) {
	case 'unrequired':
?>
		<p class="last">
			<?php printf( __('Nothing to upgrade.  <a href="%s">Get back to work!</a>'), bb_get_uri('bb-admin/', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN) ); ?>
		</p>
<?php
		break;
	
	case 'required'
?>
		<div class="open">
			<div>
				<h2><?php _e('Database upgrade required'); ?></h2>
				<p class="error">
					<span class="first">!</span> <?php _e('It looks like your database is out-of-date.<br />You can update it here.'); ?>
				</p>
				<form action="upgrade.php<?php echo $form_action_querystring; ?>" method="post">
					<fieldset class="buttons">
						<?php bb_nonce_field( 'bbpress-upgrader' ); ?>
						<label for="upgrade_next" class="forward">
							<input class="button" id="upgrade_next" type="submit" value="<?php _e('Upgrade database &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
			</div>
		</div>
		<div class="open"></div>
<?php
		break;
	
	case 'complete':
?>
		<div class="open">
			<div>
				<h2><?php _e('Database upgrade complete'); ?></h2>
				<p class="message">
					<span class="first">!</span> <?php _e('Your database has been successfully updated.<br />Enjoy!'); ?>
				</p>
				<form action="<?php bb_uri('bb-admin/', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>" method="get">
					<label for="upgrade_log_container_toggle">
						<?php _e('Show upgrade log:'); ?>
						<input class="checkbox" type="checkbox" id="upgrade_log_container_toggle" value="1" onclick="toggleAdvanced('upgrade_log_container_toggle', 'upgrade_log_container');" />
					</label>
					<div class="toggle" id="upgrade_log_container" style="display:none;">
						<fieldset>
							<label for="upgrade_log">
								<?php _e('Upgrade log:'); ?>
								<textarea id="upgrade_log" class="short"><?php echo(join("\n", $upgrade_log)); ?></textarea>
							</label>
						</fieldset>
					</div>
					<fieldset class="buttons">
						<label for="upgrade_next" class="forward">
							<input class="button" id="upgrade_next" type="submit" value="<?php _e('Go to admin &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
			</div>
		</div>
		<div class="open"></div>
<?php
		break;
	
	case 'error':
?>
		<div class="open">
			<div>
				<h2><?php _e('Database upgrade failed'); ?></h2>
				<p class="error">
					<span class="first">!</span> <?php _e('The upgrade process seems to have failed. Check the upgrade messages below for more information.<br /><br />Attempting to go to the admin area without resolving the listed errors will return you to this upgrade page.'); ?>
				</p>
				<form action="upgrade.php<?php echo $form_action_querystring; ?>" method="post">
					<?php bb_nonce_field( 'bbpress-upgrader' ); ?>
					<label for="upgrade_log_container_toggle">
						<?php _e('Show upgrade messages:'); ?>
						<input class="checkbox" type="checkbox" id="upgrade_log_container_toggle" value="1" onclick="toggleAdvanced('upgrade_log_container_toggle', 'upgrade_log_container');" />
					</label>
					<div class="toggle" id="upgrade_log_container" style="display:none;">
						<fieldset>
<?php
		if (count($error_log)) {
?>
							<label for="error_log">
								<?php _e('Error log:'); ?>
								<textarea id="error_log" class="short"><?php echo(join("\n", $error_log)); ?></textarea>
							</label>
<?php
		}
?>
							<label for="upgrade_log">
								<?php _e('Upgrade log:'); ?>
								<textarea id="upgrade_log" class="short"><?php echo(join("\n", $upgrade_log)); ?></textarea>
							</label>
						</fieldset>
					</div>
					<fieldset class="buttons">
						<label for="upgrade_next" class="back">
							<input class="button" id="upgrade_back" type="button" value="<?php _e('&laquo; Go back to forums'); ?>" onclick="location.href='<?php bb_form_option('uri'); ?>'; return false;" />
						</label>
						<label for="upgrade_next" class="forward">
							<input class="button" id="upgrade_next" type="submit" value="<?php _e('Try again &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
			</div>
		</div>
		<div class="open"></div>
<?php
		break;
}

bb_install_footer();
?>