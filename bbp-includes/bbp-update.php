<?php

/**
 * Walk through the DB and update any old meta_key's to their new names
 *
 * @uses get_option()
 * @uses update_option()
 *
 * @global DB $wpdb
 */
function bbp_update() {
	if ( '104' != get_option( '_bbp_db_version' ) ) {
		global $wpdb;

		// _bbp_visibility
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_visibility' WHERE meta_key = '_bbp_forum_visibility'" ) );

		// _bbp_status
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_status' WHERE meta_key = '_bbp_forum_status'" ) );

		// _bbp_forum_id
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_forum_id' WHERE meta_key = '_bbp_topic_forum_id'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_forum_id' WHERE meta_key = '_bbp_reply_forum_id'" ) );

		// _bbp_topic_id
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_topic_id' WHERE meta_key = '_bbp_reply_topic_id'" ) );

		// _bbp_reply_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_reply_count' WHERE meta_key = '_bbp_forum_reply_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_reply_count' WHERE meta_key = '_bbp_topic_reply_count'" ) );

		// _bbp_total_reply_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_total_reply_count' WHERE meta_key = '_bbp_forum_total_reply_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_total_reply_count' WHERE meta_key = '_bbp_topic_total_reply_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_total_reply_count' WHERE meta_key = '_bbp_reply_count_total'" ) );

		// _bbp_hidden_reply_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_hidden_reply_count' WHERE meta_key = '_bbp_forum_hidden_reply_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_hidden_reply_count' WHERE meta_key = '_bbp_topic_hidden_reply_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_hidden_reply_count' WHERE meta_key = '_bbp_reply_count_hidden'" ) );

		// _bbp_topic_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_topic_count' WHERE meta_key = '_bbp_forum_topic_count'" ) );

		// _bbp_total_topic_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_total_topic_count' WHERE meta_key = '_bbp_forum_total_topic_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_total_topic_count' WHERE meta_key = '_bbp_topic_count_total'" ) );

		// _bbp_hidden_topic_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_hidden_topic_count' WHERE meta_key = '_bbp_forum_hidden_topic_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_hidden_topic_count' WHERE meta_key = '_bbp_topic_count_hidden'" ) );

		// _bbp_total_voice_count
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_voice_count' WHERE meta_key = '_bbp_forum_voice_count'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_voice_count' WHERE meta_key = '_bbp_topic_voice_count'" ) );

		// _bbp_last_active_time
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_active_time' WHERE meta_key = '_bbp_topic_last_active'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_active_time' WHERE meta_key = '_bbp_forum_last_active'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_active_time' WHERE meta_key = '_bbp_reply_last_active'" ) );

		// _bbp_last_active_id
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_active_id' WHERE meta_key = '_bbp_topic_last_active_id'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_active_id' WHERE meta_key = '_bbp_forum_last_active_id'" ) );

		// _bbp_last_topic_id
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_topic_id' WHERE meta_key = '_bbp_forum_last_topic_id'" ) );

		// _bbp_last_reply_id
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_reply_id' WHERE meta_key = '_bbp_forum_last_reply_id'" ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = '_bbp_last_reply_id' WHERE meta_key = '_bbp_topic_last_reply_id'" ) );

		// Set the new DB version
		update_option( '_bbp_db_version', '104' );
	}
}
add_action( 'init', 'bbp_update', 1 );

?>
