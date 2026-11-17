<?php

/**
 * Exercise the registered repair tools in the disposable package smoke site.
 *
 * Included by cli.php, which has already created a topic and a reply.
 *
 * @package bbPress
 */

$previous_user = get_current_user_id();
wp_set_current_user( 1 );

$tool_options = array( 'launch' => false, 'return' => true );
$tool_list    = json_decode( WP_CLI::runcommand( 'bbp tool list --format=json', $tool_options ), true );
$registered   = is_array( $tool_list ) && in_array( 'bbp-topic-replies', array_column( $tool_list, 'id' ), true );
if ( ! $registered ) {
	WP_CLI::error( 'The topic reply count repair is missing from WP-CLI.' );
}

$tool_ids = WP_CLI::runcommand( 'bbp tool list --format=ids', $tool_options );
if ( ! in_array( 'bbp-topic-replies', explode( ' ', trim( $tool_ids ) ), true ) ) {
	WP_CLI::error( 'The tool IDs output is incorrect.' );
}

update_post_meta( $topic_id, '_bbp_reply_count', 7 );
WP_CLI::runcommand( 'bbp tool run bbp-topic-replies --dry-run', $tool_options );
if ( 7 !== (int) get_post_meta( $topic_id, '_bbp_reply_count', true ) ) {
	WP_CLI::error( 'The tool Dry Run changed the topic reply count.' );
}

$other_topic_id = bbp_insert_topic(
	array(
		'post_parent'  => $forum_id,
		'post_title'   => 'Second Smoke Topic',
		'post_content' => 'Second topic for the CLI batch test.',
	),
	array( 'forum_id' => $forum_id )
);
if ( empty( $other_topic_id ) ) {
	WP_CLI::error( 'Unable to create the second smoke-test topic.' );
}

WP_CLI::runcommand( 'bbp tool run bbp-topic-replies --batch-size=1 --max-batches=1', $tool_options );
$status = json_decode( WP_CLI::runcommand( 'bbp tool status --format=json', $tool_options ), true );
$paused = is_array( $status ) && in_array( 'paused', array_column( $status, 'status' ), true );
if ( ! $paused ) {
	WP_CLI::error( 'The CLI tool did not save its paused batch.' );
}

WP_CLI::runcommand( 'bbp tool run bbp-topic-replies --batch-size=1', $tool_options );
if ( 1 !== (int) get_post_meta( $topic_id, '_bbp_reply_count', true ) ) {
	WP_CLI::error( 'The tool did not repair the topic reply count.' );
}
if ( bbp_admin_repair_tool_get_run_state( 'repair' ) || bbp_admin_repair_tool_get_run_state( 'repair', 'dry-run' ) ) {
	WP_CLI::error( 'A completed forum tool kept its run state.' );
}

wp_set_current_user( $previous_user );
