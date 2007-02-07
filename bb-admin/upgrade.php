<?php
if ( ini_get('safe_mode') )
	die("You're running in safe mode which does not allow this upgrade
	script to set a running time limit.  Depending on the size of your
	database and on which parts of the script you are running, the script
	can take quite some time to run (or it could take just a few seconds).
	To throw caution to the wind and run the script in safe mode anyway,
	remove the first two lines of code in this file.  Backups are always a
	good idea.");
require('../bb-load.php');
require( BBPATH . 'bb-admin/upgrade-functions.php' );
define('BB_UPGRADING', true);

$bb_upgrade = 0;

bb_install_header( __('bbPress &rsaquo; Upgrade') );

// Very old (pre 0.7) installs may need further upgrade utilities.  Post to http://lists.bbpress.org/mailman/listinfo/bbdev if needed

$bb_upgrade = bb_upgrade_all();

if ( $bb_upgrade > 0 )
	printf('<p>' . __('Upgrade complete.  <a href="%s">Enjoy!</a>') . '</p>', bb_get_option( 'uri' ) . 'bb-admin/' );
else
	printf('<p>' . __('Nothing to upgrade.  <a href="%s">Get back to work!</a>') . '</p>', bb_get_option( 'uri' ) . 'bb-admin/' );

printf('<p>' . __('%1$d queries and %2$s seconds.') . '</p>', $bbdb->num_queries, bb_timer_stop(0));

bb_install_footer();

if ( $bb_upgrade > 0 )
	$bb_cache->flush_all();
?>
