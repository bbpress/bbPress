<?php

/**
 * bbPress Admin Tools Page
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Admin tools page
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_recount_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses screen_icon() To display the screen icon
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_tools_screen() {

	$recount_list = bbp_recount_list(); ?>

	<div class="wrap">

		<?php screen_icon( 'tools' ); ?>

		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Repair Forums', 'bbpress' ) ); ?></h2>

		<p><?php _e( 'bbPress keeps a running count of things like replies to each topic and topics in each forum. In rare occasions these counts can fall out of sync. Using this form you can have bbPress manually recount these items.', 'bbpress' ); ?></p>
		<p><?php _e( 'You can also use this form to clean out stale items like empty tags.', 'bbpress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php _e( 'Things to recount:', 'bbpress' ) ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Repair', 'bbpress' ) ?></span></legend>

								<?php if ( !empty( $recount_list ) ) :

										foreach ( $recount_list as $item ) {
											echo '<label><input type="checkbox" class="checkbox" name="' . esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ) . '" value="1" /> ' . esc_html( $item[1] ) . '</label><br />' . "\n";
										}
								?>

								<?php else : ?>

									<p><?php _e( 'There are no recount tools available.', 'bbpress' ) ?></p>

								<?php endif; ?>

							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php _e( 'Repair Items', 'bbpress' ); ?>" />
				<?php wp_nonce_field( 'bbpress-do-counts' ); ?>
			</fieldset>
		</form>
	</div>

<?php
}

/**
 * Handle the processing and feedback of the admin tools page
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_recount_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 */
function bbp_admin_tools_handler() {

	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
		check_admin_referer( 'bbpress-do-counts' );

		// Stores messages
		$messages     = array();
		$recount_list = bbp_recount_list();

		wp_cache_flush();

		foreach ( (array) $recount_list as $item ) {
			if ( isset( $item[2] ) && isset( $_POST[$item[0]] ) && 1 == $_POST[$item[0]] && is_callable( $item[2] ) ) {
				$messages[] = call_user_func( $item[2] );
			}
		}

		if ( count( $messages ) ) {
			foreach ( $messages as $message ) {
				bbp_tools_feedback( $message[1] );
			}
		}
	}
}

/**
 * Assemble the admin notices
 *
 * @since bbPress (r2613)
 *
 * @param string|WP_Error $message A message to be displayed or {@link WP_Error}
 * @param string $class Optional. A class to be added to the message div
 * @uses WP_Error::get_error_messages() To get the error messages of $message
 * @uses add_action() Adds the admin notice action with the message HTML
 * @return string The message HTML
 */
function bbp_tools_feedback( $message, $class = false ) {
	if ( is_string( $message ) ) {
		$message = '<p>' . $message . '</p>';
		$class = $class ? $class : 'updated';
	} elseif ( is_wp_error( $message ) ) {
		$errors = $message->get_error_messages();

		switch ( count( $errors ) ) {
			case 0:
				return false;
				break;

			case 1:
				$message = '<p>' . $errors[0] . '</p>';
				break;

			default:
				$message = '<ul>' . "\n\t" . '<li>' . join( '</li>' . "\n\t" . '<li>', $errors ) . '</li>' . "\n" . '</ul>';
				break;
		}

		$class = $class ? $class : 'error';
	} else {
		return false;
	}

	$message = '<div id="message" class="' . esc_attr( $class ) . '">' . $message . '</div>';
	$message = str_replace( "'", "\'", $message );
	$lambda  = create_function( '', "echo '$message';" );

	add_action( 'admin_notices', $lambda );

	return $lambda;
}


/**
 * Get the array of the recount list
 *
 * @since bbPress (r2613)
 *
 * @uses apply_filters() Calls 'bbp_recount_list' with the recount list array
 * @return array Recount list
 */
function bbp_recount_list() {
	$recount_list = array(
		0  => array( 'bbp-sync-topic-meta',        __( 'Recalculate the parent topic for each post',          'bbpress' ), 'bbp_recount_topic_meta'           ),
		5  => array( 'bbp-sync-forum-meta',        __( 'Recalculate the parent forum for each post',          'bbpress' ), 'bbp_recount_forum_meta'           ),
		10 => array( 'bbp-forum-topics',           __( 'Count topics in each forum',                          'bbpress' ), 'bbp_recount_forum_topics'         ),
		15 => array( 'bbp-forum-replies',          __( 'Count replies in each forum',                         'bbpress' ), 'bbp_recount_forum_replies'        ),
		20 => array( 'bbp-topic-replies',          __( 'Count replies in each topic',                         'bbpress' ), 'bbp_recount_topic_replies'        ),
		25 => array( 'bbp-topic-voices',           __( 'Count voices in each topic',                          'bbpress' ), 'bbp_recount_topic_voices'         ),
		30 => array( 'bbp-topic-hidden-replies',   __( 'Count spammed & trashed replies in each topic',       'bbpress' ), 'bbp_recount_topic_hidden_replies' ),
		35 => array( 'bbp-topics-replied',         __( 'Count replies for each user',                         'bbpress' ), 'bbp_recount_user_topics_replied'  ),
		40 => array( 'bbp-clean-favorites',        __( 'Remove trashed topics from user favorites',           'bbpress' ), 'bbp_recount_clean_favorites'      ),
		45 => array( 'bbp-clean-subscriptions',    __( 'Remove trashed topics from user subscriptions',       'bbpress' ), 'bbp_recount_clean_subscriptions'  ),
		50 => array( 'bbp-sync-all-topics-forums', __( 'Recalculate last activity in each topic and forum',   'bbpress' ), 'bbp_recount_rewalk'               )
	);

	ksort( $recount_list );
	return apply_filters( 'bbp_recount_list', $recount_list );
}

/**
 * Recount topic replies
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_reply_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();
	$cps = bbp_get_closed_status_id();

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (
			SELECT `topics`.`ID` AS `post_id`, '_bbp_reply_count' AS `meta_key`, COUNT(`replies`.`ID`) As `meta_value`
				FROM `{$wpdb->posts}` AS `topics`
					LEFT JOIN `{$wpdb->posts}` as `replies`
						ON  `replies`.`post_parent` = `topics`.`ID`
						AND `replies`.`post_status` = '{$pps}'
						AND `replies`.`post_type`   = '{$rpt}'
				WHERE `topics`.`post_type` = '{$tpt}'
					AND `topics`.`post_status` IN ( '{$pps}', '{$cps}' )
				GROUP BY `topics`.`ID`);";

	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount topic voices
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_voices() {
	global $wpdb;

	$statement = __( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_voice_count';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();
	$cps = bbp_get_closed_status_id();

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (
			SELECT `postmeta`.`meta_value`, '_bbp_voice_count', COUNT(DISTINCT `post_author`) as `meta_value`
				FROM `{$wpdb->posts}` AS `posts`
				LEFT JOIN `{$wpdb->postmeta}` AS `postmeta`
					ON `posts`.`ID` = `postmeta`.`post_id`
					AND `postmeta`.`meta_key` = '_bbp_topic_id'
				WHERE `posts`.`post_type` IN ( '{$tpt}', '{$rpt}' )
					AND `posts`.`post_status` IN ( '{$pps}', '{$cps}' )
					AND `posts`.`post_author` != '0'
				GROUP BY `postmeta`.`meta_value`);";

	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount topic hidden replies (spammed/trashed)
 *
 * @since bbPress (r2747)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_hidden_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of spammed and trashed replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` = '_bbp_reply_count_hidden';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$sql = "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_reply_count_hidden', COUNT(`post_status`) as `meta_value` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` IN ( '" . join( "','", array( bbp_get_trash_status_id(), bbp_get_spam_status_id() ) ) . "') GROUP BY `post_parent`);";
	if ( is_wp_error( $wpdb->query( $sql ) ) )
		return array( 2, sprintf( $statement, $result ) );

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount forum topics
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_posts() To get the forums
 * @uses bbp_update_forum_topic_count() To update the forum topic count
 * @return array An array of the status code and the message
 */
function bbp_recount_forum_topics() {
	global $wpdb;

	$statement = __( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ( '_bbp_topic_count', '_bbp_total_topic_count' );";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$forums = get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'numberposts' => -1 ) );
	if ( !empty( $forums ) ) {
		foreach( $forums as $forum ) {
			bbp_update_forum_topic_count( $forum->ID );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount forum replies
 *
 * @since bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_posts() To get the forums
 * @uses bbp_update_forum_reply_count() To update the forum reply count
 * @return array An array of the status code and the message
 */
function bbp_recount_forum_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` IN ( '_bbp_reply_count', '_bbp_total_reply_count' );";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$forums = get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'numberposts' => -1 ) );
	if ( !empty( $forums ) ) {
		foreach( $forums as $forum ) {
			bbp_update_forum_reply_count( $forum->ID );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount topic replied by the users
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_user_topics_replied() {
	global $wpdb;

	$statement = __( 'Counting the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_select = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "' GROUP BY `post_author`;";
	$insert_rows = $wpdb->get_results( $sql_select );

	if ( is_wp_error( $insert_rows ) )
		return array( 1, sprintf( $statement, $result ) );

	$insert_values = array();
	foreach ( $insert_rows as $insert_row )
		$insert_values[] = "('{$insert_row->post_author}', '_bbp_topics_replied', '{$insert_row->_count}')";

	if ( !count( $insert_values ) )
		return array( 2, sprintf( $statement, $result ) );

	$sql_delete = "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` = '_bbp_topics_replied';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 3, sprintf( $statement, $result ) );

	$insert_values = array_chunk( $insert_values, 10000 );
	foreach ( $insert_values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$wpdb->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";

		if ( is_wp_error( $wpdb->query( $sql_insert ) ) )
			return array( 4, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Clean the users' favorites
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_clean_favorites() {
	global $wpdb;

	$statement = __( 'Removing trashed topics from user favorites&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );
	$key       = bbp_get_favorites_key();
	$users     = $wpdb->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `{$wpdb->usermeta}` WHERE `meta_key` = '{$key}';" );

	if ( is_wp_error( $users ) )
		return array( 1, sprintf( $statement, $result ) );

	$topics = $wpdb->get_col( "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "';" );

	if ( is_wp_error( $topics ) )
		return array( 2, sprintf( $statement, $result ) );

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->favorites ) || !is_string( $user->favorites ) )
			continue;

		$favorites = array_intersect( $topics, (array) explode( ',', $user->favorites ) );
		if ( empty( $favorites ) || !is_array( $favorites ) )
			continue;

		$favorites = join( ',', $favorites );
		$values[] = "('{$user->user_id}', '{$key}, '{$favorites}')";
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 4, sprintf( $statement, $result ) );

	$values = array_chunk( $values, 10000 );
	foreach ( $values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `$wpdb->usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";
		if ( is_wp_error( $wpdb->query( $sql_insert ) ) )
			return array( 5, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Clean the users' subscriptions
 *
 * @since bbPress (r2668)
 *
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_clean_subscriptions() {
	global $wpdb;

	$statement = __( 'Removing trashed topics from user subscriptions&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );
	$key       = bbp_get_subscriptions_key();
	$users     = $wpdb->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$wpdb->usermeta}` WHERE `meta_key` = '{$key}';" );

	if ( is_wp_error( $users ) )
		return array( 1, sprintf( $statement, $result ) );

	$topics = $wpdb->get_col( "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "';" );
	if ( is_wp_error( $topics ) )
		return array( 2, sprintf( $statement, $result ) );

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->subscriptions ) || !is_string( $user->subscriptions ) )
			continue;

		$subscriptions = array_intersect( $topics, (array) explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || !is_array( $subscriptions ) )
			continue;

		$subscriptions = join( ',', $subscriptions );
		$values[] = "('{$user->user_id}', '{$key}', '{$subscriptions}')";
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 4, sprintf( $statement, $result ) );

	$values = array_chunk( $values, 10000 );
	foreach ( $values as $chunk ) {
		$chunk = "\n" . join( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$wpdb->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES $chunk;";
		if ( is_wp_error( $wpdb->query( $sql_insert ) ) )
			return array( 5, sprintf( $statement, $result ) );
	}

	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recaches the last post in every topic and forum
 *
 * @since bbPress (r3040)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_rewalk() {
	global $wpdb;

	$statement = __( 'Recomputing latest post in every topic and forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $wpdb->query( "DELETE FROM `$wpdb->postmeta` WHERE `meta_key` IN ( '_bbp_last_reply_id', '_bbp_last_topic_id', '_bbp_last_active_id', '_bbp_last_active_time' );" ) ) )
		return array( 1, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_reply_id', MAX( `reply`.`ID` )
			FROM `$wpdb->posts` AS `topic` INNER JOIN `$wpdb->posts` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` IN ( '" . bbp_get_public_status_id() . "' ) AND `topic`.`post_type` = 'topic' AND `reply`.`post_type` = 'reply'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 2, sprintf( $statement, $result ) );

	// For any remaining topics, give a reply ID of 0.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_reply_id', 0
			FROM `$wpdb->posts` AS `topic` LEFT JOIN `$wpdb->postmeta` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_reply_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = 'topic' );" ) ) )
		return array( 3, sprintf( $statement, $result ) );

	// Now we give all the forums with topics the ID their last topic.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_last_topic_id', `topic`.`ID`
			FROM `$wpdb->posts` AS `forum` INNER JOIN `$wpdb->posts` AS `topic` ON `forum`.`ID` = `topic`.`post_parent`
			WHERE `topic`.`post_status` IN ( '" . bbp_get_public_status_id() . "' ) AND `forum`.`post_type` = 'forum' AND `topic`.`post_type` = 'topic'
			GROUP BY `forum`.`ID` );" ) ) )
		return array( 4, sprintf( $statement, $result ) );

	// For any remaining forums, give a topic ID of 0.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_topic_id', 0
			FROM `$wpdb->posts` AS `forum` LEFT JOIN `$wpdb->postmeta` AS `topic`
			ON `forum`.`ID` = `topic`.`post_id` AND `topic`.`meta_key` = '_bbp_last_topic_id'
			WHERE `topic`.`meta_id` IS NULL AND `forum`.`post_type` = 'forum' );" ) ) )
		return array( 5, sprintf( $statement, $result ) );

	// After that, we give all the topics with replies the ID their last reply (again, this time for a different reason).
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_id', MAX( `reply`.`ID` )
			FROM `$wpdb->posts` AS `topic` INNER JOIN `$wpdb->posts` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` IN ( '" . bbp_get_public_status_id() . "' ) AND `topic`.`post_type` = 'topic' AND `reply`.`post_type` = 'reply'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 6, sprintf( $statement, $result ) );

	// For any remaining topics, give a reply ID of themself.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_id', `ID`
			FROM `$wpdb->posts` AS `topic` LEFT JOIN `$wpdb->postmeta` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = 'topic' );" ) ) )
		return array( 7, sprintf( $statement, $result ) );

	// Give topics with replies their last update time.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_time', MAX( `reply`.`post_date` )
			FROM `$wpdb->posts` AS `topic` INNER JOIN `$wpdb->posts` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` IN ( '" . bbp_get_public_status_id() . "' ) AND `topic`.`post_type` = 'topic' AND `reply`.`post_type` = 'reply'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 8, sprintf( $statement, $result ) );

	// Give topics without replies their last update time.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_time', `post_date`
			FROM `$wpdb->posts` AS `topic` LEFT JOIN `$wpdb->postmeta` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_time'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = 'topic' );" ) ) )
		return array( 9, sprintf( $statement, $result ) );

	// Forums need to know what their last active item is as well. Now it gets a bit more complex to do in the database.
	$forums = $wpdb->get_col( "SELECT `ID` FROM `$wpdb->posts` WHERE `post_type` = 'forum' and `post_status` != 'auto-draft';" );
	if ( is_wp_error( $forums ) )
		return array( 10, sprintf( $statement, $result ) );

 	// Loop through forums
 	foreach ( $forums as $forum_id ) {
		if ( !bbp_is_forum_category( $forum_id ) ) {
			bbp_update_forum( array( 'forum_id' => $forum_id ) );
		}
	}

	// Loop through categories when forums are done
	foreach ( $forums as $forum_id ) {
		if ( bbp_is_forum_category( $forum_id ) ) {
			bbp_update_forum( array( 'forum_id' => $forum_id ) );
		}
	}
	
	// Complete results
	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recaches the forum for each post
 *
 * @since bbPress (r3876)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_forum_meta() {
	global $wpdb;

	$statement = __( 'Recalculating the forum for each post &hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $wpdb->query( "DELETE FROM `$wpdb->postmeta` WHERE `meta_key` = '_bbp_forum_id';" ) ) )
		return array( 1, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_forum_id', `forum`.`post_parent`
			FROM `$wpdb->posts`
				AS `forum`
			WHERE `forum`.`post_type` = 'forum'
			GROUP BY `forum`.`ID` );" ) ) )
		return array( 2, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_forum_id', `topic`.`post_parent`
			FROM `$wpdb->posts`
				AS `topic`
			WHERE `topic`.`post_type` = 'topic'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 3, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `reply`.`ID`, '_bbp_forum_id', `topic`.`post_parent`
			FROM `$wpdb->posts`
				AS `reply`
			INNER JOIN `$wpdb->posts`
				AS `topic`
				ON `reply`.`post_parent` = `topic`.`ID`
			WHERE `topic`.`post_type` = 'topic'
				AND `reply`.`post_type` = 'reply'
			GROUP BY `reply`.`ID` );" ) ) )
		return array( 4, sprintf( $statement, $result ) );

	// Complete results
	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recaches the topic for each post
 *
 * @since bbPress (r3876)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_recount_topic_meta() {
	global $wpdb;

	$statement = __( 'Recalculating the topic for each post &hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $wpdb->query( "DELETE FROM `$wpdb->postmeta` WHERE `meta_key` = '_bbp_topic_id';" ) ) )
		return array( 1, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_topic_id', `topic`.`ID`
			FROM `$wpdb->posts`
				AS `topic`
			WHERE `topic`.`post_type` = 'topic'
			GROUP BY `topic`.`ID` );" ) ) )
		return array( 3, sprintf( $statement, $result ) );

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $wpdb->query( "INSERT INTO `$wpdb->postmeta` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `reply`.`ID`, '_bbp_topic_id', `topic`.`ID`
			FROM `$wpdb->posts`
				AS `reply`
			INNER JOIN `$wpdb->posts`
				AS `topic`
				ON `reply`.`post_parent` = `topic`.`ID`
			WHERE `topic`.`post_type` = 'topic'
				AND `reply`.`post_type` = 'reply'
			GROUP BY `reply`.`ID` );" ) ) )
		return array( 4, sprintf( $statement, $result ) );

	// Complete results
	$result = __( 'Complete!', 'bbpress' );
	return array( 0, sprintf( $statement, $result ) );
}

?>
