<?php

/**
 * Exercise mention backfill commands in the disposable package smoke site.
 *
 * Included by cli.php, which has already created a forum.
 *
 * @package bbPress
 */

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
update_user_meta( $recipient, '_bbp_mention_email', 'yes' );

remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
$historical = bbp_insert_topic( array(
	'post_parent'  => $forum_id,
	'post_title'   => 'Historical mention',
	'post_content' => '@mention-cli-smoke',
), array( 'forum_id' => $forum_id ) );
add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );

delete_option( '_bbp_mention_backfill' );
bbp_add_pending_upgrade( 'bbp-user-mentions' );
$options = array( 'launch' => false, 'return' => true );
$mail = 0;
$capture = static function() use ( &$mail ) {
	++$mail;
	return true;
};
add_filter( 'pre_wp_mail', $capture );

WP_CLI::runcommand( 'bbp mentions backfill --batch-size=1 --max-batches=1', $options );
$partial = json_decode( WP_CLI::runcommand( 'bbp mentions status', $options ), true );
if ( ! is_array( $partial ) || $partial['complete'] || ( $partial['cursor'] >= $historical ) ) {
	WP_CLI::error( 'Mention backfill did not stop at the requested batch limit.' );
}

WP_CLI::runcommand( 'bbp mentions backfill', $options );
$complete = json_decode( WP_CLI::runcommand( 'bbp mentions status', $options ), true );
$pending = bbp_get_pending_upgrades();
if ( ! $complete['complete'] || in_array( 'bbp-user-mentions', $pending, true ) ) {
	WP_CLI::error( 'Mention backfill did not complete the pending upgrade.' );
}
if ( array( $recipient ) !== bbp_get_users_for_object( $historical, '_bbp_mention' ) ) {
	WP_CLI::error( 'Historical mentions were not indexed.' );
}

WP_CLI::runcommand( 'bbp mentions backfill --restart --batch-size=1 --max-batches=1', $options );
$restarted = json_decode( WP_CLI::runcommand( 'bbp mentions status', $options ), true );
if ( $restarted['complete'] || ( $restarted['cursor'] !== $partial['cursor'] ) ) {
	WP_CLI::error( 'Mention backfill did not restart at the beginning.' );
}
WP_CLI::runcommand( 'bbp mentions backfill', $options );
wp_update_post( array( 'ID' => $historical, 'post_content' => 'Still @mention-cli-smoke' ) );
bbp_shutdown();
remove_filter( 'pre_wp_mail', $capture );
if ( $mail ) {
	WP_CLI::error( 'Historical mentions queued an email.' );
}
