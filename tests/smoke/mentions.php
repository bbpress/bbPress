<?php

/**
 * Exercise the mention upgrade tool in the package smoke site.
 *
 * Included by cli.php, which has already created a forum.
 *
 * @package bbPress
 */

require_once bbpress()->includes_dir . 'admin/actions.php';
bbp_setup_admin();

$recipient = wp_insert_user( array(
	'user_login'    => 'mention-cli-smoke',
	'user_nicename' => 'mention-cli-smoke',
	'user_email'    => 'mention-cli-smoke@example.org',
	'user_pass'     => wp_generate_password(),
	'role'          => bbp_get_participant_role(),
) );
if ( is_wp_error( $recipient ) ) {
	WP_CLI::error( $recipient );
}
bbp_update_user_notification_preferences( $recipient, array( 'mentions' => true ) );

remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
$historical = bbp_insert_topic( array(
	'post_parent'  => $forum_id,
	'post_title'   => 'Historical mention',
	'post_content' => '@mention-cli-smoke',
), array( 'forum_id' => $forum_id ) );
add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );

delete_option( '_bbp_mention_backfill' );
delete_option( bbp_admin_repair_tool_batch_option_name( 'bbp-user-mentions' ) );
bbp_add_pending_upgrade( 'bbp-user-mentions' );
$options = array( 'launch' => false, 'return' => true );
$mail = 0;
$capture = static function() use ( &$mail ) {
	++$mail;
	return true;
};
add_filter( 'pre_wp_mail', $capture );

WP_CLI::runcommand( 'bbp tool run bbp-user-mentions --batch-size=1 --max-batches=1', $options );
list( $read, $partial ) = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions' );
$queue   = json_decode( WP_CLI::runcommand( 'bbp tool status --format=json', $options ), true );
$paused  = is_array( $queue ) && in_array( 'paused', array_column( $queue, 'status' ), true );
if ( ! $read || ! is_array( $partial ) || ( $partial['cursor'] >= $historical ) || ! $paused ) {
	WP_CLI::error( 'Mention backfill did not stop at the requested batch limit.' );
}

WP_CLI::runcommand( 'bbp tool run bbp-user-mentions', $options );
$complete = get_option( '_bbp_mention_backfill' );
$pending = bbp_get_pending_upgrades();
list( $read, $checkpoint ) = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions' );
if ( ! $read || $checkpoint || ! $complete['complete'] || in_array( 'bbp-user-mentions', $pending, true ) ) {
	WP_CLI::error( 'Mention backfill did not complete the pending upgrade.' );
}
if ( array( $recipient ) !== bbp_get_users_for_object( $historical, '_bbp_mention' ) ) {
	WP_CLI::error( 'Historical mentions were not indexed.' );
}

WP_CLI::runcommand( 'bbp tool run bbp-user-mentions --batch-size=1 --max-batches=1', $options );
list( $read, $restarted ) = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions' );
if ( ! $read || ! is_array( $restarted ) || ( $restarted['cursor'] !== $partial['cursor'] ) ) {
	WP_CLI::error( 'Mention backfill did not restart at the beginning.' );
}
WP_CLI::runcommand( 'bbp tool run bbp-user-mentions', $options );
$tool_ids = WP_CLI::runcommand( 'bbp tool list --format=ids', $options );
if ( in_array( 'bbp-mentions', explode( ' ', trim( $tool_ids ) ), true ) ) {
	WP_CLI::error( 'The removed mention repair tool is still registered.' );
}
wp_update_post( array( 'ID' => $historical, 'post_content' => 'Still @mention-cli-smoke' ) );
bbp_shutdown();
remove_filter( 'pre_wp_mail', $capture );
if ( $mail ) {
	WP_CLI::error( 'Historical mentions queued an email.' );
}
