<?php

/**
 * bbPress Admin Tools Page
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Repair ********************************************************************/

/**
 * Admin repair page
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_admin_repair_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses screen_icon() To display the screen icon
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_repair() {
?>

	<div class="wrap">

		<?php screen_icon( 'tools' ); ?>

		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Repair Forums', 'bbpress' ) ); ?></h2>

		<p><?php _e( 'bbPress keeps track of relationships between forums, topics, replies, and topic tags, and users. Occasionally these relationships become out of sync, most often after an import or migration. Use the tools below to manually recalculate these relationships.', 'bbpress' ); ?></p>
		<p class="description"><?php _e( 'Some of these tools create substantial database overhead. Avoid running more than 1 repair job at a time.', 'bbpress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php _e( 'Relationships to Repair:', 'bbpress' ) ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Repair', 'bbpress' ) ?></span></legend>

								<?php foreach ( bbp_admin_repair_list() as $item ) : ?>

									<label><input type="checkbox" class="checkbox" name="<?php echo esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ); ?>" value="1" /> <?php echo esc_html( $item[1] ); ?></label><br />

								<?php endforeach; ?>

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
 * @uses bbp_admin_repair_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 */
function bbp_admin_repair_handler() {

	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
		check_admin_referer( 'bbpress-do-counts' );

		// Stores messages
		$messages = array();

		wp_cache_flush();

		foreach ( (array) bbp_admin_repair_list() as $item ) {
			if ( isset( $item[2] ) && isset( $_POST[$item[0]] ) && 1 == $_POST[$item[0]] && is_callable( $item[2] ) ) {
				$messages[] = call_user_func( $item[2] );
			}
		}

		if ( count( $messages ) ) {
			foreach ( $messages as $message ) {
				bbp_admin_tools_feedback( $message[1] );
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
function bbp_admin_tools_feedback( $message, $class = false ) {
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
 * Get the array of the repair list
 *
 * @since bbPress (r2613)
 *
 * @uses apply_filters() Calls 'bbp_repair_list' with the list array
 * @return array Repair list of options
 */
function bbp_admin_repair_list() {
	$repair_list = array(
		0  => array( 'bbp-sync-topic-meta',        __( 'Recalculate the parent topic for each post',          'bbpress' ), 'bbp_admin_repair_topic_meta'               ),
		5  => array( 'bbp-sync-forum-meta',        __( 'Recalculate the parent forum for each post',          'bbpress' ), 'bbp_admin_repair_forum_meta'               ),
		10 => array( 'bbp-forum-topics',           __( 'Count topics in each forum',                          'bbpress' ), 'bbp_admin_repair_forum_topic_count'        ),
		15 => array( 'bbp-forum-replies',          __( 'Count replies in each forum',                         'bbpress' ), 'bbp_admin_repair_forum_reply_count'        ),
		20 => array( 'bbp-topic-replies',          __( 'Count replies in each topic',                         'bbpress' ), 'bbp_admin_repair_topic_reply_count'        ),
		25 => array( 'bbp-topic-voices',           __( 'Count voices in each topic',                          'bbpress' ), 'bbp_admin_repair_topic_voice_count'        ),
		30 => array( 'bbp-topic-hidden-replies',   __( 'Count spammed & trashed replies in each topic',       'bbpress' ), 'bbp_admin_repair_topic_hidden_reply_count' ),
		35 => array( 'bbp-user-replies',           __( 'Count topics for each user',                          'bbpress' ), 'bbp_admin_repair_user_topic_count'         ),
		35 => array( 'bbp-user-topics',            __( 'Count replies for each user',                         'bbpress' ), 'bbp_admin_repair_user_reply_count'         ),
		40 => array( 'bbp-user-favorites',         __( 'Remove trashed topics from user favorites',           'bbpress' ), 'bbp_admin_repair_user_favorites'           ),
		45 => array( 'bbp-user-subscriptions',     __( 'Remove trashed topics from user subscriptions',       'bbpress' ), 'bbp_admin_repair_user_subscriptions'       ),
		50 => array( 'bbp-sync-all-topics-forums', __( 'Recalculate last activity in each topic and forum',   'bbpress' ), 'bbp_admin_repair_freshness'                )
	);
	ksort( $repair_list );

	// DO NOT USE: Legacy filter
	$repair_list = apply_filters( 'bbp_recount_list', $repair_list );

	return (array) apply_filters( 'bbp_repair_list', $repair_list );
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
function bbp_admin_repair_topic_reply_count() {
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
function bbp_admin_repair_topic_voice_count() {
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
function bbp_admin_repair_topic_hidden_reply_count() {
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
function bbp_admin_repair_forum_topic_count() {
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
function bbp_admin_repair_forum_reply_count() {
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
function bbp_admin_repair_user_reply_count() {
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
function bbp_admin_repair_user_favorites() {
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
function bbp_admin_repair_user_subscriptions() {
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
function bbp_admin_repair_freshness() {
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
function bbp_admin_repair_forum_meta() {
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
function bbp_admin_repair_topic_meta() {
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

/** Reset ********************************************************************/

/**
 * Admin reset page
 *
 * @since bbPress (r2613)
 *
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses screen_icon() To display the screen icon
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_reset() {
?>

	<div class="wrap">

		<?php screen_icon( 'tools' ); ?>

		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Reset Forums', 'bbpress' ) ); ?></h2>
		<p><?php _e( 'This will revert your forums back to a brand new installation. This process cannot be undone. <strong>Backup your database before proceeding</strong>.', 'bbpress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php _e( 'The following data will be removed:', 'bbpress' ) ?></th>
						<td>
							<?php _e( 'All Forums',           'bbpress' ); ?><br />
							<?php _e( 'All Topics',           'bbpress' ); ?><br />
							<?php _e( 'All Replies',          'bbpress' ); ?><br />
							<?php _e( 'All Topic Tags',       'bbpress' ); ?><br />
							<?php _e( 'Related Meta Data',    'bbpress' ); ?><br />
							<?php _e( 'Forum Settings',       'bbpress' ); ?><br />
							<?php _e( 'Forum Activity',       'bbpress' ); ?><br />
							<?php _e( 'Forum User Roles',     'bbpress' ); ?><br />
							<?php _e( 'Importer Helper Data', 'bbpress' ); ?><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Are you sure you want to do this?', 'bbpress' ) ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( "Say it ain't so!", 'bbpress' ) ?></span></legend>
								<label><input type="checkbox" class="checkbox" name="bbpress-are-you-sure" id="bbpress-are-you-sure" value="1" /> <?php _e( 'This process cannot be undone.', 'bbpress' ); ?></label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php _e( 'Reset bbPress', 'bbpress' ); ?>" />
				<?php wp_nonce_field( 'bbpress-reset' ); ?>
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
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 */
function bbp_admin_reset_handler() {
	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['bbpress-are-you-sure'] ) ) {
		check_admin_referer( 'bbpress-reset' );

		global $wpdb;

		// Stores messages
		$messages = array();
		$failed   = __( 'Failed',   'bbpress' );
		$success  = __( 'Success!', 'bbpress' );

		// Flush the cache; things are about to get ugly.
		wp_cache_flush();

		/** Posts *************************************************************/

		$statement  = __( 'Deleting Posts&hellip; %s', 'bbpress' );
		$sql_posts  = $wpdb->get_results( "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type` IN ('forum', 'topic', 'reply')", OBJECT_K );
		$sql_delete = "DELETE FROM `{$wpdb->posts}` WHERE `post_type` IN ('forum', 'topic', 'reply')";
		$result     = is_wp_error( $wpdb->query( $sql_delete ) ) ? $failed : $success;
		$messages[] = sprintf( $statement, $result );

		
		/** Post Meta *********************************************************/

		if ( !empty( $sql_posts ) ) {
			foreach( $sql_posts as $key => $value ) {
				$sql_meta[] = $key;
			}
			$statement  = __( 'Deleting Post Meta&hellip; %s', 'bbpress' );
			$sql_meta   = implode( "', '", $sql_meta );
			$sql_delete = "DELETE FROM `{$wpdb->postmeta}` WHERE `post_id` IN ('{$sql_meta}');";
			$result     = is_wp_error( $wpdb->query( $sql_delete ) ) ? $failed : $success;
			$messages[] = sprintf( $statement, $result );
		}

		/** Topic Tags ********************************************************/

		// @todo

		/** User Meta *********************************************************/

		$statement  = __( 'Deleting User Meta&hellip; %s', 'bbpress' );
		$sql_delete = "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE '%%_bbp_%%';";
		$result     = is_wp_error( $wpdb->query( $sql_delete ) ) ? $failed : $success;
		$messages[] = sprintf( $statement, $result );

		/** Converter *********************************************************/

		$statement  = __( 'Deleting Conversion Table&hellip; %s', 'bbpress' );
		$table_name = $wpdb->prefix . 'bbp_converter_translator';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) == $table_name ) {
			$wpdb->query( "DROP TABLE {$table_name}" );
			$result = $success;
		} else {
			$result = $failed;
		}
		$messages[] = sprintf( $statement, $result );
		
		/** Options ***********************************************************/

		$statement  = __( 'Deleting Settings&hellip; %s', 'bbpress' );
		$sql_delete = bbp_delete_options();
		$messages[] = sprintf( $statement, $success );

		/** Roles *************************************************************/

		$statement  = __( 'Deleting Roles and Capabilities&hellip; %s', 'bbpress' );
		$sql_delete = bbp_remove_roles();
		$sql_delete = bbp_remove_caps();
		$messages[] = sprintf( $statement, $success );

		/** Output ************************************************************/

		if ( count( $messages ) ) {
			foreach ( $messages as $message ) {
				bbp_admin_tools_feedback( $message );
			}
		}
	}
}

?>
