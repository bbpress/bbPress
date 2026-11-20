<?php

/**
 * Exercise registered bbPress commands against an installed release build.
 *
 * @package bbPress
 */

wp_set_current_user( 1 );

$cli_options = array(
	'launch' => false,
	'return' => true
);
$cli_error_options = array(
	'launch'     => false,
	'return'     => 'all',
	'exit_error' => false
);

$cli_forum_title   = 'CLI-Smoke-Forum\\Path';
$cli_forum_content = 'CLI-Smoke-Forum\\Content';
$cli_forum_id      = (int) trim( WP_CLI::runcommand( 'bbp forum create --title=' . $cli_forum_title . ' --content=' . $cli_forum_content . ' --porcelain', $cli_options ) );
if ( ! bbp_is_forum( $cli_forum_id ) || $cli_forum_title !== get_post_field( 'post_title', $cli_forum_id ) || $cli_forum_content !== get_post_field( 'post_content', $cli_forum_id ) ) {
	WP_CLI::error( 'The CLI forum command did not preserve the forum title and content.' );
}

$cli_topic_title   = 'CLI-Smoke-Topic\\Path';
$cli_topic_content = 'CLI-Smoke-Topic\\Content';
$cli_topic_id      = (int) trim( WP_CLI::runcommand( 'bbp topic create --forum=' . $cli_forum_id . ' --title=' . $cli_topic_title . ' --content=' . $cli_topic_content . ' --porcelain', $cli_options ) );
if ( ! bbp_is_topic( $cli_topic_id ) || $cli_forum_id !== bbp_get_topic_forum_id( $cli_topic_id ) || $cli_topic_title !== get_post_field( 'post_title', $cli_topic_id ) || $cli_topic_content !== get_post_field( 'post_content', $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not preserve the topic title, content, and forum.' );
}

$cli_reply_content = 'CLI-Smoke-Reply\\Content';
$cli_reply_id      = (int) trim( WP_CLI::runcommand( 'bbp reply create --topic=' . $cli_topic_id . ' --content=' . $cli_reply_content . ' --porcelain', $cli_options ) );
if ( ! bbp_is_reply( $cli_reply_id ) || $cli_topic_id !== bbp_get_reply_topic_id( $cli_reply_id ) || $cli_forum_id !== bbp_get_reply_forum_id( $cli_reply_id ) || $cli_reply_content !== get_post_field( 'post_content', $cli_reply_id ) ) {
	WP_CLI::error( 'The CLI reply command did not preserve the reply content, topic, and forum.' );
}

WP_CLI::runcommand( 'bbp topic close ' . $cli_topic_id, $cli_options );
if ( ! bbp_is_topic_closed( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not close the topic.' );
}

WP_CLI::runcommand( 'bbp topic open ' . $cli_topic_id, $cli_options );
if ( ! bbp_is_topic_open( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not reopen the topic.' );
}

WP_CLI::runcommand( 'bbp forum close ' . $cli_forum_id, $cli_options );
if ( ! bbp_is_forum_closed( $cli_forum_id, false ) ) {
	WP_CLI::error( 'The CLI forum command did not close the forum.' );
}
WP_CLI::runcommand( 'bbp forum open ' . $cli_forum_id, $cli_options );
if ( ! bbp_is_forum_open( $cli_forum_id, false ) ) {
	WP_CLI::error( 'The CLI forum command did not reopen the forum.' );
}

WP_CLI::runcommand( 'bbp topic stick ' . $cli_topic_id . ' --no-super', $cli_options );
if ( ! bbp_is_topic_sticky( $cli_topic_id ) || bbp_is_topic_super_sticky( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not stick the topic in its forum.' );
}
$cli_sticky_result = WP_CLI::runcommand( 'bbp topic stick ' . $cli_topic_id . ' --super', $cli_error_options );
if ( 1 !== $cli_sticky_result->return_code || bbp_is_topic_super_sticky( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command changed the scope of an already sticky topic.' );
}
WP_CLI::runcommand( 'bbp topic unstick ' . $cli_topic_id, $cli_options );
if ( bbp_is_topic_sticky( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not unstick the topic.' );
}
$cli_unsticky_result = WP_CLI::runcommand( 'bbp topic unstick ' . $cli_topic_id, $cli_error_options );
if ( 1 !== $cli_unsticky_result->return_code ) {
	WP_CLI::error( 'The CLI topic command reported success for a topic that was not sticky.' );
}

WP_CLI::runcommand( 'bbp reply spam ' . $cli_reply_id, $cli_options );
if ( ! bbp_is_reply_spam( $cli_reply_id ) ) {
	WP_CLI::error( 'The CLI reply command did not mark the reply as spam.' );
}
WP_CLI::runcommand( 'bbp reply ham ' . $cli_reply_id, $cli_options );
if ( bbp_is_reply_spam( $cli_reply_id ) ) {
	WP_CLI::error( 'The CLI reply command did not restore the reply.' );
}

WP_CLI::runcommand( 'bbp reply unapprove ' . $cli_reply_id, $cli_options );
if ( ! bbp_is_reply_pending( $cli_reply_id ) ) {
	WP_CLI::error( 'The CLI reply command did not unapprove the reply.' );
}
WP_CLI::runcommand( 'bbp reply approve ' . $cli_reply_id, $cli_options );
if ( bbp_is_reply_pending( $cli_reply_id ) ) {
	WP_CLI::error( 'The CLI reply command did not approve the reply.' );
}

WP_CLI::runcommand( 'bbp topic unapprove ' . $cli_topic_id, $cli_options );
if ( ! bbp_is_topic_pending( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not unapprove the topic.' );
}
WP_CLI::runcommand( 'bbp topic approve ' . $cli_topic_id, $cli_options );
if ( ! bbp_is_topic_published( $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI topic command did not approve the topic.' );
}

$cli_engaged_users = WP_CLI::runcommand( 'bbp engagement list-users ' . $cli_topic_id, $cli_options );
if ( ! in_array( '1', explode( ' ', trim( $cli_engaged_users ) ), true ) ) {
	WP_CLI::error( 'The CLI engagement command did not list the topic author.' );
}

WP_CLI::runcommand( 'bbp favorite add 1 ' . $cli_topic_id, $cli_options );
if ( ! bbp_is_user_favorite( 1, $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI favorite command did not add the favorite.' );
}
WP_CLI::runcommand( 'bbp favorite remove 1 ' . $cli_topic_id, $cli_options );
if ( bbp_is_user_favorite( 1, $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI favorite command did not remove the favorite.' );
}

WP_CLI::runcommand( 'bbp subscription add 1 ' . $cli_forum_id, $cli_options );
if ( ! bbp_is_user_subscribed( 1, $cli_forum_id ) ) {
	WP_CLI::error( 'The CLI subscription command did not add the subscription.' );
}
WP_CLI::runcommand( 'bbp subscription remove 1 ' . $cli_forum_id, $cli_options );
if ( bbp_is_user_subscribed( 1, $cli_forum_id ) ) {
	WP_CLI::error( 'The CLI subscription command did not remove the subscription.' );
}

WP_CLI::runcommand( 'bbp moderator add ' . $cli_forum_id . ' 1', $cli_options );
if ( ! in_array( 1, array_map( 'intval', bbp_get_moderator_ids( $cli_forum_id ) ), true ) ) {
	WP_CLI::error( 'The CLI moderator command did not assign the moderator.' );
}
$cli_duplicate_result = WP_CLI::runcommand( 'bbp moderator add ' . $cli_forum_id . ' 1', $cli_error_options );
if ( 1 !== $cli_duplicate_result->return_code || 1 !== count( get_post_meta( $cli_forum_id, '_bbp_moderator_id', false ) ) ) {
	WP_CLI::error( 'The CLI moderator command duplicated a forum assignment.' );
}
WP_CLI::runcommand( 'bbp moderator remove ' . $cli_forum_id . ' 1', $cli_options );
if ( in_array( 1, array_map( 'intval', bbp_get_moderator_ids( $cli_forum_id ) ), true ) ) {
	WP_CLI::error( 'The CLI moderator command did not remove the moderator.' );
}
add_filter( 'bbp_allow_forum_mods', '__return_false' );
$cli_disabled_moderator_result = WP_CLI::runcommand( 'bbp moderator add ' . $cli_forum_id . ' 1', $cli_error_options );
remove_filter( 'bbp_allow_forum_mods', '__return_false' );
if ( 1 !== $cli_disabled_moderator_result->return_code || in_array( 1, array_map( 'intval', bbp_get_moderator_ids( $cli_forum_id ) ), true ) ) {
	WP_CLI::error( 'The CLI moderator command assigned a moderator while the feature was disabled.' );
}

$cli_user_login = 'cli-smoke-' . wp_generate_password( 10, false, false );
$cli_user_id    = wp_create_user( $cli_user_login, wp_generate_password(), $cli_user_login . '@example.org' );
if ( is_wp_error( $cli_user_id ) ) {
	WP_CLI::error( 'The CLI smoke test could not create a user.' );
}
WP_CLI::runcommand( 'bbp user set-role ' . $cli_user_id . ' ' . bbp_get_spectator_role(), $cli_options );
if ( bbp_get_spectator_role() !== bbp_get_user_role( $cli_user_id ) ) {
	WP_CLI::error( 'The CLI user command did not set the forum role.' );
}

$cli_blocked_login = 'cli-smoke-' . wp_generate_password( 10, false, false );
$cli_blocked_id    = wp_create_user( $cli_blocked_login, wp_generate_password(), $cli_blocked_login . '@example.org' );
if ( is_wp_error( $cli_blocked_id ) ) {
	WP_CLI::error( 'The CLI smoke test could not create a blocked user.' );
}
$cli_blocked_user = get_userdata( $cli_blocked_id );
$cli_blocked_user->remove_role( bbp_get_participant_role() );
$cli_blocked_user->add_role( bbp_get_blocked_role() );
$cli_blocked_user->add_role( bbp_get_participant_role() );
if ( bbp_get_blocked_role() !== bbp_get_user_role( $cli_blocked_id ) ) {
	WP_CLI::error( 'The CLI smoke test could not set up mixed forum roles.' );
}
WP_CLI::runcommand( 'bbp user set-role ' . $cli_blocked_id . ' ' . bbp_get_blocked_role(), $cli_options );
if ( in_array( bbp_get_participant_role(), get_userdata( $cli_blocked_id )->roles, true ) ) {
	WP_CLI::error( 'The CLI user command left another forum role on a blocked user.' );
}

$cli_category_id = bbp_insert_forum(
	array( 'post_title' => 'CLI-Smoke-Category' ),
	array( 'forum_type' => 'category' )
);
if ( ! $cli_category_id || ! bbp_is_forum_category( $cli_category_id ) ) {
	WP_CLI::error( 'The CLI smoke test could not create a category forum.' );
}
$cli_category_result = WP_CLI::runcommand( 'bbp topic create --forum=' . $cli_category_id . ' --title=CLI-Smoke-Topic --content=CLI-Smoke-Content', $cli_error_options );
if ( 1 !== $cli_category_result->return_code ) {
	WP_CLI::error( 'The CLI topic command created a topic in a category forum.' );
}

wp_set_current_user( $cli_user_id );
$cli_denied_topic_result = WP_CLI::runcommand( 'bbp topic close ' . $cli_topic_id, $cli_error_options );
$cli_denied_forum_result = WP_CLI::runcommand( 'bbp forum create --title=CLI-Smoke-Denied', $cli_error_options );
$cli_denied_reset_result = WP_CLI::runcommand( 'bbp reset --yes', $cli_error_options );
$cli_denied_moderator_result = WP_CLI::runcommand( 'bbp moderator add ' . $cli_forum_id . ' 1', $cli_error_options );
$cli_denied_favorite_result = WP_CLI::runcommand( 'bbp favorite add 1 ' . $cli_topic_id, $cli_error_options );
if (
	1 !== $cli_denied_topic_result->return_code
	|| 1 !== $cli_denied_forum_result->return_code
	|| 1 !== $cli_denied_reset_result->return_code
	|| 1 !== $cli_denied_moderator_result->return_code
	|| 1 !== $cli_denied_favorite_result->return_code
	|| ! bbp_is_topic_open( $cli_topic_id )
	|| ! bbp_is_forum( $cli_forum_id )
	|| bbp_is_user_favorite( 1, $cli_topic_id )
) {
	WP_CLI::error( 'A CLI command allowed a spectator to change another user\'s forum data.' );
}
WP_CLI::runcommand( 'bbp favorite add ' . $cli_user_id . ' ' . $cli_topic_id, $cli_options );
if ( ! bbp_is_user_favorite( $cli_user_id, $cli_topic_id ) ) {
	WP_CLI::error( 'The CLI favorite command did not allow a user to manage their own favorite.' );
}
WP_CLI::runcommand( 'bbp favorite remove ' . $cli_user_id . ' ' . $cli_topic_id, $cli_options );
wp_set_current_user( 1 );
