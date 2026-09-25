<?php

/**
 * bbPress BuddyPress Activity Class.
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_BuddyPress_Activity' ) ) :
/**
 * Loads BuddyPress Activity extension.
 *
 * @since 2.0.0 bbPress (r3395)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_BuddyPress_Activity {

	/** Variables *************************************************************/

	/**
	 * The name of the BuddyPress component, used in activity streams.
	 *
	 * @var string
	 */
	private $component = '';

	/**
	 * Forum Create Activity Action.
	 *
	 * @var string
	 */
	private $forum_create = '';

	/**
	 * Topic Create Activity Action.
	 *
	 * @var string
	 */
	private $topic_create = '';

	/**
	 * Topic Close Activity Action.
	 *
	 * @var string
	 */
	private $topic_close = '';

	/**
	 * Topic Edit Activity Action.
	 *
	 * @var string
	 */
	private $topic_edit = '';

	/**
	 * Topic Open Activity Action.
	 *
	 * @var string
	 */
	private $topic_open = '';

	/**
	 * Reply Create Activity Action.
	 *
	 * @var string
	 */
	private $reply_create = '';

	/**
	 * Reply Edit Activity Action.
	 *
	 * @var string
	 */
	private $reply_edit = '';

	/** Setup Methods *********************************************************/

	/**
	 * The bbPress BuddyPress Activity loader.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
		$this->setup_filters();
		$this->fully_loaded();
	}

	/**
	 * Extension variables.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @access private
	 */
	private function setup_globals() {

		// The name of the BuddyPress component, used in activity streams
		$this->component    = 'bbpress';

		// Forums
		$this->forum_create = 'bbp_forum_create';

		// Topics
		$this->topic_create = 'bbp_topic_create';
		$this->topic_edit   = 'bbp_topic_edit';
		$this->topic_close  = 'bbp_topic_close';
		$this->topic_open   = 'bbp_topic_open';

		// Replies
		$this->reply_create = 'bbp_reply_create';
		$this->reply_edit   = 'bbp_reply_edit';
	}

	/**
	 * Setup the actions.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @access private
	 */
	private function setup_actions() {

		// Register the activity stream actions
		add_action( 'bp_register_activity_actions',      array( $this, 'register_activity_actions' )        );

		// Hook into topic and reply creation
		add_action( 'bbp_new_topic',                     array( $this, 'topic_create'              ), 10, 4 );
		add_action( 'bbp_new_reply',                     array( $this, 'reply_create'              ), 10, 5 );

		// Hook into topic and reply status changes
		add_action( 'edit_post',                         array( $this, 'topic_update'              ), 10, 2 );
		add_action( 'edit_post',                         array( $this, 'reply_update'              ), 10, 2 );

		// Hook into topic and reply deletion
		add_action( 'bbp_delete_topic',                  array( $this, 'topic_delete'              ), 10, 1 );
		add_action( 'bbp_delete_reply',                  array( $this, 'reply_delete'              ), 10, 1 );
	}

	/**
	 * Setup the filters.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @access private
	 */
	private function setup_filters() {

		// Obey BuddyPress commenting rules
		add_filter( 'bp_activity_can_comment',   array( $this, 'activity_can_comment'   )        );

		// Link directly to the topic or reply
		add_filter( 'bp_activity_get_permalink', array( $this, 'activity_get_permalink' ), 10, 2 );

		// Check current forum visibility when activity is read, including older entries.
		add_filter( 'bp_activity_get_where_conditions', array( $this, 'activity_get_where_conditions' ), 10, 2 );
		add_filter( 'bp_activity_get',                  array( $this, 'activity_get'                  ), 10, 2 );
		add_filter( 'bp_activity_user_can_read',       array( $this, 'activity_user_can_read'       ), 10, 3 );
	}

	/**
	 * Allow the variables, actions, and filters to be modified by third party
	 * plugins and themes.
	 *
	 * @since 2.1.0 bbPress (r3902)
	 */
	private function fully_loaded() {
		do_action_ref_array( 'bbp_buddypress_activity_loaded', array( $this ) );
	}

	/** Methods ***************************************************************/

	/**
	 * Identify BuddyPress's REST single-item query, which checks access afterward.
	 *
	 * @param array $args BuddyPress activity query arguments.
	 * @return bool Whether this is a single-item lookup.
	 */
	private function is_single_activity_lookup( $args ) {
		return ! empty( $args['show_hidden'] )
			&& ! empty( $args['display_comments'] )
			&& empty( $args['per_page'] )
			&& ! empty( $args['in'] )
			&& 1 === count( wp_parse_id_list( $args['in'] ) );
	}

	/**
	 * Check the single-item query shape when used through bp_activity_get().
	 *
	 * BuddyPress REST retrieves single items through bp_activity_get_specific(),
	 * which performs its own can-read check. Other callers using the same query
	 * shape must not inherit that query's visibility exception.
	 *
	 * @param array $result BuddyPress activity query result.
	 * @param array $args BuddyPress activity query arguments.
	 * @return array Filtered activity query result.
	 */
	public function activity_get( $result, $args ) {
		if ( ! $this->is_single_activity_lookup( $args ) || empty( $result['activities'] ) ) {
			return $result;
		}

		foreach ( $result['activities'] as $key => $activity ) {
			if ( ! bp_activity_user_can_read( $activity, get_current_user_id() ) ) {
				unset( $result['activities'][ $key ] );
			}
		}

		$result['activities'] = array_values( $result['activities'] );
		$result['total']      = count( $result['activities'] );

		return $result;
	}

	/**
	 * Exclude activity whose topic or reply is now in a restricted forum.
	 *
	 * Activity can outlive a forum visibility or parent change, so the stored
	 * hide_sitewide value alone cannot protect previously public entries.
	 *
	 * @param array $where_conditions BuddyPress activity query conditions.
	 * @param array $args BuddyPress activity query arguments.
	 * @return array Filtered conditions.
	 */
	public function activity_get_where_conditions( $where_conditions, $args ) {
		global $wpdb;

		// The REST single-item lookup needs the object for BuddyPress's can-read check.
		if ( $this->is_single_activity_lookup( $args ) ) {
			return $where_conditions;
		}

		$forum_ids = array();
		foreach ( wp_parse_id_list( bbp_get_excluded_forum_ids() ) as $forum_id ) {
			if ( bbp_is_forum_restricted_for_user( $forum_id, get_current_user_id() ) ) {
				$forum_ids[] = $forum_id;
			}
		}

		if ( empty( $forum_ids ) ) {
			return $where_conditions;
		}

		$excluded       = implode( ', ', array_fill( 0, count( $forum_ids ), '%d' ) );
		$group_component = bp_is_active( 'groups' ) ? buddypress()->groups->id : 'groups';

		// Group activity stores the post ID in secondary_item_id instead.
		$topic_sql = "
			NOT (
				a.type = %s
				AND (
					a.component = %s
					OR a.component = %s
				)
				AND EXISTS (
					SELECT 1
					FROM {$wpdb->posts} AS bbp_topic
					WHERE bbp_topic.ID = CASE
						WHEN a.component = %s THEN a.secondary_item_id
						ELSE a.item_id
					END
						AND bbp_topic.post_parent IN ({$excluded})
				)
			)";
		$topic_values = array_merge( array( $this->topic_create, $this->component, $group_component, $group_component ), $forum_ids );
		$where_conditions['bbpress_topic_visibility'] = $wpdb->prepare( $topic_sql, $topic_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only table names and generated integer placeholders.

		// Reply activity points to the reply; follow its topic to the forum.
		$reply_sql = "
			NOT (
				a.type = %s
				AND (
					a.component = %s
					OR a.component = %s
				)
				AND EXISTS (
					SELECT 1
					FROM {$wpdb->posts} AS bbp_reply
					INNER JOIN {$wpdb->posts} AS bbp_reply_topic
						ON bbp_reply_topic.ID = bbp_reply.post_parent
					WHERE bbp_reply.ID = CASE
						WHEN a.component = %s THEN a.secondary_item_id
						ELSE a.item_id
					END
						AND bbp_reply_topic.post_parent IN ({$excluded})
				)
			)";
		$reply_values = array_merge( array( $this->reply_create, $this->component, $group_component, $group_component ), $forum_ids );
		$where_conditions['bbpress_reply_visibility'] = $wpdb->prepare( $reply_sql, $reply_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only table names and generated integer placeholders.

		return $where_conditions;
	}

	/**
	 * Check the current forum before allowing direct access to activity.
	 *
	 * @param bool                 $can_read Whether BuddyPress permits access.
	 * @param int                  $user_id  User requesting the activity.
	 * @param BP_Activity_Activity $activity Activity being requested.
	 * @return bool Whether the user may read the activity.
	 */
	public function activity_user_can_read( $can_read, $user_id, $activity ) {
		$group_component = bp_is_active( 'groups' ) ? buddypress()->groups->id : 'groups';
		if ( ! $can_read || ! in_array( $activity->component, array( $this->component, $group_component ), true ) ) {
			return $can_read;
		}

		$post_id = ( $group_component === $activity->component )
			? $activity->secondary_item_id
			: $activity->item_id;

		if ( $this->topic_create === $activity->type ) {
			$forum_id = bbp_get_topic_forum_id( $post_id );
		} elseif ( $this->reply_create === $activity->type ) {
			$forum_id = bbp_get_reply_forum_id( $post_id );
		} else {
			return $can_read;
		}

		return ! empty( $forum_id ) && ! bbp_is_forum_restricted_for_user( $forum_id, $user_id );
	}

	/**
	 * Register our activity actions with BuddyPress.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 */
	public function register_activity_actions() {

		// Sitewide topic
		bp_activity_set_action(
			$this->component,
			$this->topic_create,
			esc_html__( 'New forum topic', 'bbpress' ),
			'bbp_format_activity_action_new_topic',
			esc_html__( 'Topics', 'bbpress' ),
			array( 'activity', 'member', 'member_groups', 'group' )
		);

		// Sitewide reply
		bp_activity_set_action(
			$this->component,
			$this->reply_create,
			esc_html__( 'New forum reply', 'bbpress' ),
			'bbp_format_activity_action_new_reply',
			esc_html__( 'Replies', 'bbpress' ),
			array( 'activity', 'member', 'member_groups', 'group' )
		);
	}

	/**
	 * Wrapper for recoding bbPress actions to the BuddyPress activity stream.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @param  array $args Array of arguments for bp_activity_add().
	 *
	 * @return int   Activity ID if successful, false if not.
	 */
	private function record_activity( $args = array() ) {

		// Default activity args
		$activity = bbp_parse_args(
			$args,
			array(
				'id'                => null,
				'user_id'           => bbp_get_current_user_id(),
				'type'              => '',
				'action'            => '',
				'item_id'           => '',
				'secondary_item_id' => '',
				'content'           => '',
				'primary_link'      => '',
				'component'         => $this->component,
				'recorded_time'     => bp_core_current_time(),
				'hide_sitewide'     => false
			),
			'record_activity'
		);

		// Add the activity
		return bp_activity_add( $activity );
	}

	/**
	 * Wrapper for deleting bbPress actions from BuddyPress activity stream.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @param  array $args Array of arguments for bp_activity_add().
	 *
	 * @return int   Activity ID if successful, false if not.
	 */
	public function delete_activity( $args = array() ) {

		// Default activity args
		$activity = bbp_parse_args(
			$args,
			array(
				'item_id'           => false,
				'component'         => $this->component,
				'type'              => false,
				'user_id'           => false,
				'secondary_item_id' => false
			),
			'delete_activity'
		);

		// Delete the activity
		bp_activity_delete_by_item_id( $activity );
	}

	/**
	 * Check for an existing activity stream entry for a given post_id.
	 *
	 * @param int $post_id ID of the topic or reply.
	 * @return int if an activity id is verified, false if not.
	 */
	private static function get_activity_id( $post_id = 0 ) {

		// Try to get the activity ID of the post
		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );

		// Bail if no activity ID is in post meta
		if ( empty( $activity_id ) ) {
			return null;
		}

		// Get the activity stream item, bail if it doesn't exist
		$existing = new BP_Activity_Activity( $activity_id );
		if ( empty( $existing->component ) ) {
			return null;
		}

		// Return the activity ID since we've verified the connection
		return $activity_id;
	}

	/**
	 * Maybe disable activity stream comments on select actions.
	 *
	 * @since 2.0.0 bbPress (r3399)
	 *
	 * @global BP_Activity_Template $activities_template
	 * @param boolean $can_comment
	 * @return boolean
	 */
	public function activity_can_comment( $can_comment = true ) {
		global $activities_template;

		// Already forced off, so comply
		if ( false === $can_comment ) {
			return $can_comment;
		}

		// Check if blog & forum activity stream commenting is off
		if ( ! empty( $activities_template->disable_blogforum_replies ) ) {

			// Get the current action name
			$action_name = bp_get_activity_action_name();

			// Setup the array of possibly disabled actions
			$disabled_actions = array(
				$this->topic_create,
				$this->reply_create
			);

			// Check if this activity stream action is disabled
			if ( in_array( $action_name, $disabled_actions, true ) ) {
				$can_comment = false;
			}
		}

		return $can_comment;
	}

	/**
	 * Maybe link directly to topics and replies in activity stream entries.
	 *
	 * @since 2.0.0 bbPress (r3399)
	 *
	 * @param string $link
	 * @param mixed $activity_object
	 * @return string The link to the activity stream item.
	 */
	public function activity_get_permalink( $link = '', $activity_object = false ) {

		// Setup the array of actions to link directly to
		$disabled_actions = array(
			$this->topic_create,
			$this->reply_create
		);

		// Check if this activity stream action is directly linked
		if ( in_array( $activity_object->type, $disabled_actions, true ) ) {
			$link = $activity_object->primary_link;
		}

		return $link;
	}

	/** Topics ****************************************************************/

	/**
	 * Record an activity stream entry when a topic is created or updated.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author_id
	 * @return Bail early if topic is by anonymous user.
	 */
	public function topic_create( $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $topic_author_id = 0 ) {

		// Bail early if topic is by anonymous user
		if ( ! empty( $anonymous_data ) ) {
			return;
		}

		// Bail if site is private
		if ( ! bbp_is_site_public() ) {
			return;
		}

		// Validate activity data
		$user_id  = bbp_get_user_id( $topic_author_id );
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		// Bail if user is not active
		if ( bbp_is_user_inactive( $user_id ) ) {
			return;
		}

		// Bail if topic is not published
		if ( ! bbp_is_topic_published( $topic_id ) ) {
			return;
		}

		// User link for topic author
		$user_link = bbp_get_user_profile_link( $user_id );

		// Topic
		$topic_permalink = bbp_get_topic_permalink( $topic_id );
		$topic_title     = get_post_field( 'post_title',   $topic_id, 'raw' );
		$topic_content   = get_post_field( 'post_content', $topic_id, 'raw' );
		$topic_link      = '<a href="' . $topic_permalink . '">' . $topic_title . '</a>';

		// Forum
		$forum_permalink = bbp_get_forum_permalink( $forum_id );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );
		$forum_link      = '<a href="' . $forum_permalink . '">' . $forum_title . '</a>';

		// Activity action & text
		$activity_text = sprintf(
			/* translators: 1: User linked profile, 2: Topic linked title, 3: Forum linked title */
			esc_html__( '%1$s started the topic %2$s in the forum %3$s', 'bbpress' ),
			$user_link,
			$topic_link,
			$forum_link
		);
		$activity_action  = apply_filters( 'bbp_activity_topic_create',         $activity_text, $user_id,   $topic_id,   $forum_id );
		$activity_content = apply_filters( 'bbp_activity_topic_create_excerpt', $topic_content                                     );

		// Compile and record the activity stream results
		$activity_id = $this->record_activity(
			array(
				'id'                => $this->get_activity_id( $topic_id ),
				'user_id'           => $user_id,
				'action'            => $activity_action,
				'content'           => $activity_content,
				'primary_link'      => $topic_permalink,
				'type'              => $this->topic_create,
				'item_id'           => $topic_id,
				'secondary_item_id' => $forum_id,
				'recorded_time'     => get_post_time( 'Y-m-d H:i:s', true, $topic_id ),
				'hide_sitewide'     => ! bbp_is_forum_public( $forum_id )
			)
		);

		// Add the activity entry ID as a meta value to the topic
		if ( ! empty( $activity_id ) ) {
			update_post_meta( $topic_id, '_bbp_activity_id', $activity_id );
		}
	}

	/**
	 * Delete the activity stream entry when a topic is spammed, trashed, or deleted.
	 *
	 * @param int $topic_id
	 */
	public function topic_delete( $topic_id = 0 ) {

		// Get activity ID, bail if it doesn't exist
		$activity_id = $this->get_activity_id( $topic_id );
		if ( ! empty( $activity_id ) ) {
			return bp_activity_delete( array( 'id' => $activity_id ) );
		}

		return false;
	}

	/**
	 * Update the activity stream entry when a topic status changes.
	 *
	 * @param int $topic_id
	 * @param obj $post
	 * @return Bail early if not a topic, or topic is by anonymous user.
	 */
	public function topic_update( $topic_id = 0, $post = null ) {

		// Bail early if not a topic
		if ( get_post_type( $post ) !== bbp_get_topic_post_type() ) {
			return;
		}

		// Bail early if revisions are off
		if ( ! bbp_allow_revisions() || ! post_type_supports( bbp_get_topic_post_type(), 'revisions' ) ) {
			return;
		}

		$topic_id = bbp_get_topic_id( $topic_id );

		// Bail early if topic is by anonymous user
		if ( bbp_is_topic_anonymous( $topic_id ) ) {
			return;
		}

		// Action based on new status
		if ( bbp_is_topic_public( $post->ID ) ) {

			// Validate topic data
			$forum_id        = bbp_get_topic_forum_id( $topic_id );
			$topic_author_id = bbp_get_topic_author_id( $topic_id );

			$this->topic_create( $topic_id, $forum_id, array(), $topic_author_id );
		} else {
			$this->topic_delete( $topic_id );
		}
	}

	/** Replies ***************************************************************/

	/**
	 * Record an activity stream entry when a reply is created.
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author_id
	 * @return Bail early if topic is by anonymous user.
	 */
	public function reply_create( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $reply_author_id = 0 ) {

		// Do not log activity of anonymous users
		if ( ! empty( $anonymous_data ) ) {
			return;
		}

		// Bail if site is private
		if ( ! bbp_is_site_public() ) {
			return;
		}

		// Validate activity data
		$user_id  = bbp_get_user_id( $reply_author_id );
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		// Bail if user is not active
		if ( bbp_is_user_inactive( $user_id ) ) {
			return;
		}

		// Bail if reply is not published
		if ( ! bbp_is_reply_published( $reply_id ) ) {
			return;
		}

		// Setup links for activity stream
		$user_link = bbp_get_user_profile_link( $user_id );

		// Reply
		$reply_url     = bbp_get_reply_url( $reply_id );
		$reply_content = get_post_field( 'post_content', $reply_id, 'raw' );

		// Topic
		$topic_permalink = bbp_get_topic_permalink( $topic_id );
		$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
		$topic_link      = '<a href="' . $topic_permalink . '">' . $topic_title . '</a>';

		// Forum
		$forum_permalink = bbp_get_forum_permalink( $forum_id );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );
		$forum_link      = '<a href="' . $forum_permalink . '">' . $forum_title . '</a>';

		// Activity action & text
		$activity_text    = sprintf(
			/* translators: 1: User linked profile, 2: Topic linked title, 3: Forum linked title */
			esc_html__( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' ),
			$user_link,
			$topic_link,
			$forum_link
		);
		$activity_action  = apply_filters( 'bbp_activity_reply_create',         $activity_text, $user_id, $reply_id,  $topic_id );
		$activity_content = apply_filters( 'bbp_activity_reply_create_excerpt', $reply_content                                  );

		// Compile and record the activity stream results
		$activity_id = $this->record_activity(
			array(
				'id'                => $this->get_activity_id( $reply_id ),
				'user_id'           => $user_id,
				'action'            => $activity_action,
				'content'           => $activity_content,
				'primary_link'      => $reply_url,
				'type'              => $this->reply_create,
				'item_id'           => $reply_id,
				'secondary_item_id' => $topic_id,
				'recorded_time'     => get_post_time( 'Y-m-d H:i:s', true, $reply_id ),
				'hide_sitewide'     => ! bbp_is_forum_public( $forum_id )
			)
		);

		// Add the activity entry ID as a meta value to the reply
		if ( ! empty( $activity_id ) ) {
			update_post_meta( $reply_id, '_bbp_activity_id', $activity_id );
		}
	}

	/**
	 * Delete the activity stream entry when a reply is spammed, trashed, or deleted.
	 *
	 * @param int $reply_id
	 */
	public function reply_delete( $reply_id ) {

		// Get activity ID, bail if it doesn't exist
		$activity_id = $this->get_activity_id( $reply_id );
		if ( ! empty( $activity_id ) ) {
			return bp_activity_delete( array( 'id' => $activity_id ) );
		}

		return false;
	}

	/**
	 * Update the activity stream entry when a reply status changes.
	 *
	 * @param int $reply_id
	 * @param obj $post
	 * @return Bail early if not a reply, or reply is by anonymous user.
	 */
	public function reply_update( $reply_id, $post ) {

		// Bail early if not a reply
		if ( get_post_type( $post ) !== bbp_get_reply_post_type() ) {
			return;
		}

		// Bail early if revisions are off
		if ( ! bbp_allow_revisions() || ! post_type_supports( bbp_get_reply_post_type(), 'revisions' ) ) {
			return;
		}

		$reply_id = bbp_get_reply_id( $reply_id );

		// Bail early if reply is by anonymous user
		if ( bbp_is_reply_anonymous( $reply_id ) ) {
			return;
		}

		// Action based on new status
		if ( bbp_get_public_status_id() === $post->post_status ) {

			// Validate reply data
			$topic_id        = bbp_get_reply_topic_id( $reply_id );
			$forum_id        = bbp_get_reply_forum_id( $reply_id );
			$reply_author_id = bbp_get_reply_author_id( $reply_id );

			$this->reply_create( $reply_id, $topic_id, $forum_id, array(), $reply_author_id );
		} else {
			$this->reply_delete( $reply_id );
		}
	}
}
endif;
