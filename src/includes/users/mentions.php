<?php

/**
 * bbPress User Mentions.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Keep mentions out of the legacy comma-separated user-meta strategy.
 *
 * Mentions have no pre-2.7 user-meta data to preserve. Use post metadata so
 * indexing does not scan users and history can paginate in SQL. Leave custom
 * strategies intact; extensions can also override this early filter.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param BBP_User_Engagements_Base $strategy Relationship strategy.
 * @param string                    $rel_key  Relationship key.
 * @param string                    $rel_type Object type.
 * @return BBP_User_Engagements_Base
 */
function bbp_filter_mention_engagements_interface( $strategy, $rel_key, $rel_type ) {

	if ( ( '_bbp_mention' === $rel_key ) && ( 'post' === $rel_type ) && ( 'BBP_User_Engagements_User' === get_class( $strategy ) ) ) {
		$strategy = new BBP_User_Engagements_Meta();
	}

	return $strategy;
}

/**
 * Return the mention pattern shared by linking and indexing.
 *
 * Do not match part of an email address or a dotted username.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return string Regular expression with prefix and username captures.
 */
function bbp_get_mention_pattern() {

	return '#(^|[\s>\[\(])@([0-9a-zA-Z_-]+)(?![0-9a-zA-Z_@-]|\.[0-9a-zA-Z_-])#';
}

/**
 * Check whether saved posts may queue mention email.
 *
 * Importers can suspend notifications without disabling the mention index.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return bool
 */
function bbp_is_mention_notifications_active() {
	return (bool) apply_filters( 'bbp_is_mention_notifications_active', ! ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) );
}

/**
 * Resolve mentions in authored text to active user IDs.
 *
 * HTML attributes, links, quoted text and code are not authored mentions.
 * This does not call the deprecated, pre-2.6 mention parser.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param string $content Unfiltered post content.
 * @return array Unique user IDs.
 */
function bbp_get_mentioned_user_ids( $content = '' ) {
	$user_ids = array();
	$ignored  = array();

	foreach ( wp_html_split( $content ) as $piece ) {
		if ( preg_match( '#^<(/?)(a|code|pre|script|style|blockquote)(?:\s|>)#i', $piece, $tag ) ) {
			$name = strtolower( $tag[2] );
			if ( empty( $tag[1] ) ) {
				$ignored[ $name ] = isset( $ignored[ $name ] ) ? $ignored[ $name ] + 1 : 1;
			} elseif ( ! empty( $ignored[ $name ] ) ) {
				--$ignored[ $name ];
			}
		}

		if ( empty( $piece ) || ( '<' === $piece[0] ) || array_sum( $ignored ) ) {
			continue;
		}

		if ( preg_match_all( bbp_get_mention_pattern(), html_entity_decode( $piece, ENT_QUOTES, 'UTF-8' ), $matches ) ) {
			foreach ( array_unique( $matches[2] ) as $slug ) {
				$user = get_user_by( 'slug', $slug );
				if ( $user && ! bbp_is_user_inactive( $user->ID ) ) {
					$user_ids[] = (int) $user->ID;
				}
			}
		}
	}

	return array_values( array_unique( $user_ids ) );
}

/**
 * Synchronize a topic or reply's mention relationships, without sending email.
 *
 * Can also be used by importers or a batched historical repair.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $post_id Topic or reply ID.
 * @return array Current mentioned user IDs.
 */
function bbp_update_post_mentions( $post_id = 0 ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return array();
	}

	$old_ids = bbp_get_users_for_object( $post_id, '_bbp_mention' );
	$new_ids = bbp_get_mentioned_user_ids( $post->post_content );

	foreach ( array_diff( $old_ids, $new_ids ) as $user_id ) {
		bbp_remove_user_from_object( $post_id, $user_id, '_bbp_mention' );
	}
	foreach ( array_diff( $new_ids, $old_ids ) as $user_id ) {
		bbp_add_user_to_object( $post_id, $user_id, '_bbp_mention' );
	}

	return $new_ids;
}

/**
 * Reserve mention notification recipients once per person and post.
 *
 * The persistent record contains handled recipients, not pending deliveries.
 * Existing content supplies the initial baseline for posts without a record.
 * Always use the oldest row, including when concurrent initialization creates
 * duplicate metadata. Compare both its ID and value when claiming recipients:
 * the metadata API cannot perform this conditional update on a specific row.
 * A failed reservation never queues email.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int   $post_id  Topic or reply ID.
 * @param array $user_ids Recipients to mark handled.
 * @param array $baseline Previously mentioned users for an untracked post.
 * @return array Newly reserved recipients.
 */
function bbp_claim_mention_notification_recipients( $post_id = 0, $user_ids = array(), $baseline = array() ) {
	$bbp_db   = bbp_db();
	$key      = '_bbp_mention_notification';
	$user_ids = wp_parse_id_list( $user_ids );
	$baseline = wp_parse_id_list( $baseline );

	for ( $attempt = 0; $attempt < 3; ++$attempt ) {
		$row = $bbp_db->get_row( $bbp_db->prepare( "SELECT meta_id, meta_value FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1", $post_id, $key ) );

		if ( ! $row ) {
			add_post_meta( $post_id, $key, array( 'handled' => $baseline ), true );
			continue;
		}

		$state = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $state ) || ! isset( $state['handled'] ) || ! is_array( $state['handled'] ) ) {
			return array();
		}

		$handled = wp_parse_id_list( $state['handled'] );
		$new_ids = array_values( array_diff( $user_ids, $handled ) );
		if ( empty( $new_ids ) ) {
			return array();
		}

		$state['handled'] = array_merge( $handled, $new_ids );
		$updated = $bbp_db->update(
			$bbp_db->postmeta,
			array( 'meta_value' => maybe_serialize( $state ) ),
			array(
				'meta_id'    => $row->meta_id,
				'meta_value' => $row->meta_value
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		if ( $updated ) {
			wp_cache_delete( $post_id, 'post_meta' );
			return $new_ids;
		}

		if ( false === $updated ) {
			return array();
		}
	}

	return array();
}

/**
 * Index saved content and queue newly introduced mention recipients.
 *
 * Published edits notify new mentions. Unpublished content waits for approval.
 * Previously handled recipients stay recorded even if removed from the post.
 * Recipients are reserved before queuing, so interrupted shutdown work cannot
 * be resurrected by a later edit. Delivery is best effort, without retries.
 * Imports mark their mentions handled without queuing email.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int          $post_id     Saved post ID.
 * @param WP_Post      $post        Saved post.
 * @param bool         $update      Whether this updates an existing post.
 * @param WP_Post|null $post_before Previous post.
 */
function bbp_save_post_mentions( $post_id, $post, $update, $post_before ) {
	if ( ! in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return;
	}

	$changed  = ! $update || ! $post_before || ( $post->post_content !== $post_before->post_content );
	$user_ids = $changed ? bbp_update_post_mentions( $post_id ) : bbp_get_users_for_object( $post_id, '_bbp_mention' );
	$old_ids  = $update ? ( $post_before ? bbp_get_mentioned_user_ids( $post_before->post_content ) : $user_ids ) : array();

	// Posts that have never mentioned anyone need no notification record.
	if ( empty( $user_ids ) && empty( $old_ids ) ) {
		return;
	}

	$statuses = ( bbp_get_reply_post_type() === $post->post_type ) ? bbp_get_public_reply_statuses() : bbp_get_public_topic_statuses();
	$public   = in_array( $post->post_status, $statuses, true );
	$active   = bbp_is_mention_notifications_active();
	$new_ids  = array();

	if ( ! $active || $public ) {
		$new_ids = $user_ids;

		// A published edit must introduce a mention, not merely repair its index.
		if ( $active && $update && $post_before && in_array( $post_before->post_status, $statuses, true ) ) {
			$new_ids = $changed ? array_diff( $user_ids, $old_ids ) : array();
		}
	}

	$new_ids = bbp_claim_mention_notification_recipients( $post_id, $new_ids, $old_ids );
	if ( $active && $public && ! empty( $new_ids ) ) {
		$blog_id = get_current_blog_id();
		$queue   = (array) bbpress()->mention_notification_queue;
		$queued  = isset( $queue[ $blog_id ][ $post_id ] ) ? $queue[ $blog_id ][ $post_id ] : array();
		$queue[ $blog_id ][ $post_id ] = wp_parse_id_list( array_merge( $queued, $new_ids ) );
		bbpress()->mention_notification_queue = $queue;
	}
}

/**
 * Check current access to a published mention, independent of the current user.
 *
 * Password-protected content is excluded even if the posting user's browser
 * has a password cookie. Group integrations share the subscription access gate.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $post_id Topic or reply ID.
 * @param int $user_id Reader ID.
 * @return bool Whether the mention may be disclosed.
 */
function bbp_user_can_read_mention( $post_id = 0, $user_id = 0 ) {
	$post = get_post( $post_id );
	if ( ! $post || ! $user_id || bbp_is_user_inactive( $user_id ) || bbp_is_password_protected( $post_id ) ) {
		return false;
	}

	$reply_id = ( bbp_get_reply_post_type() === $post->post_type ) ? $post_id : 0;
	$topic_id = $reply_id ? bbp_get_reply_topic_id( $reply_id ) : $post_id;
	$topic    = get_post( $topic_id );
	$forum_id = $topic ? bbp_get_topic_forum_id( $topic_id ) : 0;
	$forum    = get_post( $forum_id );

	if ( ! $topic || ! $forum || ( bbp_get_topic_post_type() !== $topic->post_type ) || ( bbp_get_forum_post_type() !== $forum->post_type ) || ! in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return false;
	}

	$statuses = $reply_id ? bbp_get_public_reply_statuses() : bbp_get_public_topic_statuses();
	if ( ! in_array( $post->post_status, $statuses, true ) || ! in_array( $topic->post_status, bbp_get_public_topic_statuses(), true ) ) {
		return false;
	}

	$user_ids = bbp_filter_subscription_user_ids( array( $user_id ), $forum_id, $topic_id, $reply_id );

	return ! empty( $user_ids );
}

/**
 * Check the installation-wide mention email preference.
 *
 * Email is opt-in so existing users do not receive new notifications by default.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $user_id Recipient ID.
 * @return bool Whether mention email is enabled.
 */
function bbp_is_user_mention_email_enabled( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id, false, true );
	$setting = metadata_exists( 'user', $user_id, '_bbp_mention_email' )
		? get_user_meta( $user_id, '_bbp_mention_email', true )
		: false;
	$enabled = ( 'yes' === $setting );

	return (bool) apply_filters( 'bbp_is_user_mention_email_enabled', $enabled, $user_id, $setting );
}

/**
 * Remember recipients already handled by subscription notifications.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int   $post_id  Topic or reply ID.
 * @param int   $parent_id Parent ID (unused).
 * @param array $user_ids Subscription recipients.
 */
function bbp_record_mention_subscription_recipients( $post_id, $parent_id, $user_ids ) {
	$sent = (array) bbpress()->mention_subscription_recipients;
	$sent[ get_current_blog_id() ][ $post_id ] = wp_parse_id_list( $user_ids );
	bbpress()->mention_subscription_recipients = $sent;
}

/**
 * Deliver queued mention emails once, after all publication hooks.
 *
 * Recipients were marked handled when queued. Failed or interrupted delivery
 * is not retried by a later edit. No author-supplied title or content is
 * included in these emails.
 *
 * @since 2.7.0 bbPress (r8264)
 */
function bbp_send_mention_notifications() {
	$queue = (array) bbpress()->mention_notification_queue;
	$sent  = (array) bbpress()->mention_subscription_recipients;
	bbpress()->mention_notification_queue = array();

	foreach ( $queue as $blog_id => $posts ) {
		$switched = is_multisite() && ( get_current_blog_id() !== (int) $blog_id );
		if ( $switched ) {
			switch_to_blog( $blog_id );
		}

		foreach ( $posts as $post_id => $user_ids ) {
			$post = get_post( $post_id );
			if ( ! $post || ! is_array( $user_ids ) ) {
				continue;
			}

			$recipients = array_intersect( $user_ids, bbp_get_users_for_object( $post_id, '_bbp_mention' ) );
			$excluded   = isset( $sent[ $blog_id ][ $post_id ] ) ? $sent[ $blog_id ][ $post_id ] : array();
			$excluded[] = (int) $post->post_author;
			foreach ( array_diff( $recipients, $excluded ) as $user_id ) {
				if ( ! bbp_is_user_mention_email_enabled( $user_id ) || ! bbp_user_can_read_mention( $post_id, $user_id ) ) {
					continue;
				}

				$user = get_userdata( $user_id );
				if ( ! $user || ! is_email( $user->user_email ) ) {
					continue;
				}

				$switched_locale = switch_to_locale( get_user_locale( $user ) );
				$link = ( bbp_get_reply_post_type() === $post->post_type ) ? bbp_get_reply_url( $post_id ) : bbp_get_topic_permalink( $post_id );
				/* translators: %s: Site name */
				$subject = sprintf( __( '[%s] You were mentioned in a forum discussion', 'bbpress' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
				/* translators: 1: Discussion URL, 2: Profile settings URL */
				$message = sprintf( __( "Someone mentioned you in a forum discussion:\n\n%1\$s\n\nYou can turn off mention emails in your profile settings:\n%2\$s", 'bbpress' ), $link, apply_filters( 'bbp_mention_email_settings_url', bbp_get_user_profile_edit_url( $user_id ), $user_id ) );
				$message = apply_filters( 'bbp_mention_notification_message', $message, $post_id, $user_id );
				$subject = apply_filters( 'bbp_mention_notification_subject', $subject, $post_id, $user_id );

				if ( $subject && $message ) {
					wp_mail( $user->user_email, $subject, $message );
				}
				if ( $switched_locale ) {
					restore_previous_locale();
				}
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}
	}
}

/**
 * Restrict mention history to the owner and users allowed to edit that owner.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $user_id Profile owner ID.
 * @return bool Whether the current user can view the history.
 */
function bbp_current_user_can_view_mentions( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id );

	return $user_id && current_user_can( 'spectate' ) && ( ( get_current_user_id() === $user_id ) || current_user_can( 'edit_user', $user_id ) );
}

/**
 * Populate the search loop with published posts mentioning a user.
 *
 * Use the configured engagements strategy to query matching topics and replies
 * directly. Only their forum IDs and the requested page of posts are loaded.
 * Numbered pagination and exact totals are provided by the search loop.
 * Extensions restricting individual posts should constrain the relationship
 * query so the same restrictions apply to its results and counts.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $user_id Profile owner ID.
 * @return bool Whether there are mentions to display.
 */
function bbp_get_user_mentions( $user_id = 0 ) {
	$user_id      = bbp_get_user_id( $user_id );
	$bbp_db       = bbp_db();
	$forums_query = new WP_Query();
	$posts_query  = new WP_Query();
	$forum_ids    = array();
	$topic_type   = bbp_get_topic_post_type();
	$reply_type   = bbp_get_reply_post_type();
	$statuses     = bbp_get_public_topic_statuses();
	$can_view     = bbp_current_user_can_view_mentions( $user_id ) && ! bbp_is_user_inactive( $user_id );
	$args         = $can_view ? bbp_get_user_object_query( $user_id, 'mentions', '_bbp_mention' ) : array( 'post__in' => array( 0 ) );
	$args         = array_merge(
		$args,
		array(
			'post_type'      => array( $topic_type, $reply_type ),
			'post_status'    => array_unique( array_merge( $statuses, bbp_get_public_reply_statuses() ) ),
			'has_password'   => false,
			'posts_per_page' => bbp_get_replies_per_page(),
			'paged'          => bbp_get_paged(),
			's'              => '',
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC'
			)
		)
	);

	$clauses_filter = function ( $clauses, $query ) use ( $forums_query, $posts_query, $bbp_db, $topic_type, $reply_type, $statuses, &$forum_ids ) {
		if ( ( $query !== $forums_query ) && ( $query !== $posts_query ) ) {
			return $clauses;
		}

		$topic_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$reply_statuses     = bbp_get_public_reply_statuses();
		$reply_placeholders = implode( ', ', array_fill( 0, count( $reply_statuses ), '%s' ) );

		// Empty status sets must not broaden a history query.
		if ( empty( $statuses ) ) {
			$clauses['where'] .= ' AND 1 = 0';
			return $clauses;
		}

		// Join each parent by its primary key, for both topics and replies.
		$clauses['join'] .= " INNER JOIN {$bbp_db->posts} AS bbp_mention_parent
				ON bbp_mention_parent.ID = {$bbp_db->posts}.post_parent
			LEFT JOIN {$bbp_db->posts} AS bbp_mention_forum
				ON bbp_mention_forum.ID = bbp_mention_parent.post_parent";

		// A topic's parent is its forum; a reply's parent is its published topic.
		$clauses['where'] .= $bbp_db->prepare(
			" AND (
				( {$bbp_db->posts}.post_type = %s
					AND {$bbp_db->posts}.post_status IN ({$topic_placeholders})
					AND bbp_mention_parent.post_type = %s )
				OR ( {$bbp_db->posts}.post_type = %s
					AND bbp_mention_parent.post_type = %s
					AND bbp_mention_parent.post_status IN ({$topic_placeholders})
					AND bbp_mention_parent.post_password = ''
					AND bbp_mention_forum.post_type = %s )
			)",
			array_merge( array( $topic_type ), $statuses, array( bbp_get_forum_post_type(), $reply_type, $topic_type ), $statuses, array( bbp_get_forum_post_type() ) )
		);
		if ( empty( $reply_statuses ) ) {
			$clauses['where'] .= $bbp_db->prepare( " AND {$bbp_db->posts}.post_type = %s", $topic_type );
		} else {
			$clauses['where'] .= $bbp_db->prepare(
				" AND ( {$bbp_db->posts}.post_type <> %s OR {$bbp_db->posts}.post_status IN ({$reply_placeholders}) )",
				array_merge( array( $reply_type ), $reply_statuses )
			);
		}

		$forum_field = $bbp_db->prepare( "CASE WHEN {$bbp_db->posts}.post_type = %s THEN bbp_mention_parent.ID ELSE bbp_mention_forum.ID END", $topic_type );

		// Check each matching forum once, without fetching its mention history.
		if ( $query === $forums_query ) {
			$clauses['fields']  = $forum_field;
			$clauses['groupby'] = $forum_field;
		} else {
			$ids = ! empty( $forum_ids ) ? implode( ',', wp_parse_id_list( $forum_ids ) ) : '0';
			$clauses['where'] .= " AND ({$forum_field}) IN ({$ids})";
		}

		return $clauses;
	};
	add_filter( 'posts_clauses', $clauses_filter, 10, 2 );

	try {
		if ( $can_view ) {
			$forum_args = array_merge(
				$args,
				array(
					'fields'                 => 'ids',
					'orderby'                => 'none',
					'nopaging'               => true,
					'no_found_rows'          => true,
					'cache_results'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false
				)
			);
			$forums = $forums_query->query( $forum_args );
			$readers = array_unique( array( $user_id, get_current_user_id() ) );

			foreach ( $forums as $forum_id ) {
				$allowed = bbp_filter_subscription_user_ids( $readers, $forum_id );
				if ( ( count( $allowed ) === count( $readers ) ) && ! bbp_is_password_protected( $forum_id ) ) {
					$forum_ids[] = (int) $forum_id;
				}
			}
		}

		$posts_query->query( $args );
	} finally {
		remove_filter( 'posts_clauses', $clauses_filter, 10 );
	}

	// With no search text, the search loop uses this already populated query.
	bbpress()->search_query = $posts_query;

	return bbp_has_search_results( $args );
}

/**
 * Delete mention relationships when a topic or reply is permanently deleted.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $post_id Deleted post ID.
 */
function bbp_delete_post_mentions( $post_id = 0 ) {
	if ( in_array( get_post_type( $post_id ), array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		bbp_remove_object_from_all_users( $post_id, '_bbp_mention' );
	}
}

/**
 * Save the global mention email preference from an updated user profile.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $user_id Updated user ID.
 */
function bbp_save_mention_email_preference( $user_id = 0 ) {
	if ( ! bbp_current_user_can_edit_user_field( 'email', $user_id ) || ! isset( $_POST['_bbp_mention_nonce'] ) || ! is_string( $_POST['_bbp_mention_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_bbp_mention_nonce'] ), 'bbp-mention-preferences-' . $user_id ) ) {
		return;
	}

	update_user_meta( $user_id, '_bbp_mention_email', empty( $_POST['bbp_mention_email'] ) ? 'no' : 'yes' );
}
