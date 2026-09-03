<?php

/**
 * Exercise the installed release build and print the new forum URL.
 *
 * Run with: wp eval-file tests/smoke/cli.php --path=/path/to/wordpress
 *
 * @package bbPress
 */

if ( ! function_exists( 'bbpress' ) ) {
	WP_CLI::error( 'bbPress did not load.' );
}

wp_set_current_user( 1 );

$forum_id = bbp_insert_forum(
	array(
		'post_title'   => 'Smoke Forum',
		'post_content' => 'Forum created by the release-package smoke test.',
	)
);

if ( empty( $forum_id ) ) {
	WP_CLI::error( 'Unable to create the smoke-test forum.' );
}

$topic_id = bbp_insert_topic(
	array(
		'post_parent'  => $forum_id,
		'post_title'   => 'Smoke Topic',
		'post_content' => 'Topic created by the release-package smoke test.',
	),
	array(
		'forum_id' => $forum_id,
	)
);

if ( empty( $topic_id ) ) {
	WP_CLI::error( 'Unable to create the smoke-test topic.' );
}

$reply_id = bbp_insert_reply(
	array(
		'post_parent'  => $topic_id,
		'post_title'   => 'Re: Smoke Topic',
		'post_content' => 'Reply created by the release-package smoke test.',
	),
	array(
		'forum_id' => $forum_id,
		'topic_id' => $topic_id,
	)
);

if ( empty( $reply_id ) ) {
	WP_CLI::error( 'Unable to create the smoke-test reply.' );
}

if ( 1 !== bbp_get_forum_topic_count( $forum_id, true, true ) ) {
	WP_CLI::error( 'The smoke-test forum topic count is incorrect.' );
}

if ( 1 !== bbp_get_topic_reply_count( $topic_id, true ) ) {
	WP_CLI::error( 'The smoke-test topic reply count is incorrect.' );
}

echo esc_url_raw( bbp_get_forum_permalink( $forum_id ) );
