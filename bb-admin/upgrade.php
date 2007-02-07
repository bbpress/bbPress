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
define('BB_UPGRADING', true);
set_time_limit(600);

$bb_upgrade = 0;

bb_install_header( __('bbPress &rsaquo; Upgrade') );

// Very old (pre 0.7) installs may need further upgrade functions.  Post to http://lists.bbpress.org/mailman/listinfo/bbdev if needed

// Reversibly break passwords of blocked users.
function upgrade_160() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 536 )
		return 0;

	require_once('admin-functions.php');
	$blocked = get_ids_by_role( 'blocked' );
	foreach ( $blocked as $b )
		bb_break_password( $b );
	return 1;
}

function upgrade_170() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 536 )
		return 0;

	global $bbdb;
	foreach ( (array) $bbdb->get_results("SELECT * FROM $bbdb->usermeta WHERE meta_value LIKE '%&quot;%' OR meta_value LIKE '%&#039;%'") as $meta ) {
		$value = str_replace(array('&quot;', '&#039;'), array('"', "'"), $meta->meta_value);
		$value = stripslashes($value);
		bb_update_usermeta( $meta->user_id, $meta->meta_key, $value);
	}
	bb_update_option( 'bb_db_version', 536 );
	echo "Done updating usermeta<br />";
	return 1;
}

function upgrade_180() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 559 )
		return 0;

	global $bbdb;

	foreach ( (array) $bbdb->get_col("SELECT ID FROM $bbdb->users WHERE user_status = 1") as $user_id )
		bb_delete_user( $user_id );
	bb_update_option( 'bb_db_version', 559 );
	echo "Done clearing deleted users<br />";
	return 1;
}

function upgrade_190() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 630 )
		return 0;

	global $bbdb;
	$topics = (array) $bbdb->get_results("SELECT topic_id, topic_resolved FROM $bbdb->topics" );
	foreach ( $topics  as $topic )
		bb_update_topicmeta( $topic->topic_id, 'topic_resolved', $topic->topic_resolved );
	unset($topics,$topic);

	$bbdb->query("ALTER TABLE $bbdb->topics DROP topic_resolved");

	bb_update_option( 'bb_db_version', 630 );

	echo "Done converting topic_resolved.<br />";
	return 1;
}

function deslash($content) {
    // Note: \\\ inside a regex denotes a single backslash.

    // Replace one or more backslashes followed by a single quote with
    // a single quote.
    $content = preg_replace("/\\\+'/", "'", $content);

    // Replace one or more backslashes followed by a double quote with
    // a double quote.
    $content = preg_replace('/\\\+"/', '"', $content);

    // Replace one or more backslashes with one backslash.
    $content = preg_replace("/\\\+/", "\\", $content);

    return $content;
}

function bb_upgrade_db_version() {
	bb_update_option( 'bb_db_version', bb_get_option( 'bb_db_version' ) );
}

require_once('upgrade-schema.php');
$bb_upgrade += upgrade_160(); // Break blocked users
$bb_upgrade += upgrade_170(); // Escaping in usermeta
$bb_upgrade += upgrade_180(); // Delete users for real
$bb_upgrade += upgrade_190(); // Move topic_resolved to topicmeta
bb_upgrade_db_version();

if ( $bb_upgrade > 0 )
	printf('<p>' . __('Upgrade complete.  <a href="%s">Enjoy!</a>') . '</p>', bb_get_option( 'uri' ) . 'bb-admin/' );
else
	printf('<p>' . __('Nothing to upgrade.  <a href="%s">Get back to work!</a>') . '</p>', bb_get_option( 'uri' ) . 'bb-admin/' );

printf('<p>' . __('%1$d queries and %2$s seconds.') . '</p>', $bbdb->num_queries, bb_timer_stop(0));

bb_install_footer();

if ( $bb_upgrade > 0 )
	$bb_cache->flush_all();
?>
