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
require( BBPATH . 'bb-admin/upgrade-functions.php' );

$step = 'unrequired';

if ( bb_get_option( 'bb_db_version' ) > bb_get_option_from_db( 'bb_db_version' ) ) {
	
	$step = 'required';
	
	if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
		
		bb_check_admin_referer( 'bbpress-upgrader' );
		
		define('BB_UPGRADING', true);
		
		$bbdb->return_errors();
		
		$upgrade_log_raw = bb_upgrade_all();
		
		$bbdb->show_errors();
		
		$upgrade_log = array();
		
		foreach ($upgrade_log_raw as $key => $item) {
			if (is_array($item)) {
				$upgrade_log[] = $item['original']['message'];
				$upgrade_log[] = '>>> ' . $item['error']['message'];
			} elseif ($item) {
				$upgrade_log[] = $item;
			}
		}
		
		if ( bb_get_option( 'bb_db_version' ) === bb_get_option_from_db( 'bb_db_version' ) ) {
			$step = 'complete';
		} else {
			$step = 'error';
		}
		
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
			<?php printf( __('Nothing to upgrade.  <a href="%s">Get back to work!</a>'), bb_get_option( 'uri' ) . 'bb-admin/' ); ?>
		</p>
<?php
		break;
	
	case 'required'
?>
		<div class="open">
			<div>
				<h2><?php _e('Database upgrade required'); ?></h2>
				<p class="error">
					<span class="first">!</span> <?php _e('It look\'s like your database is out-of-date.<br />You can update it here.'); ?>
				</p>
				<form action="upgrade.php" method="post">
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
				<form action="<?php bb_option('uri'); ?>bb-admin/" method="get">
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
					<span class="first">!</span> <?php printf( __('The upgrade process seems to have failed, you can either try again here, or go <a href="%s">back to your forums</a>.<br /><br />Attempting to go to the admin area will launch the database upgrader again.'), bb_get_option('uri')); ?>
				</p>
				<form action="upgrade.php" method="post">
					<?php bb_nonce_field( 'bbpress-upgrader' ); ?>
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

if ( $bb_upgrade > 0 ) {
	$bb_cache->flush_all();
}
?>
