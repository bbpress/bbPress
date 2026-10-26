<?php

/**
 * bbPress BuddyPress Activity Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_BuddyPress_Activity' ) ) :
/**
 * Loads BuddyPress Activity extension
 *
 * @since 2.2.0 bbPress (r4394)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_BuddyPress_Activity {

	/** Variables *************************************************************/

	/**
	 * The name of the BuddyPress component, used in activity streams
	 *
	 * @var string
	 */
	private $component = '';

	/**
	 * Forum Create Activity Action
	 *
	 * @var string
	 */
	private $forum_create = '';

	/**
	 * Topic Create Activity Action
	 *
	 * @var string
	 */
	private $topic_create = '';

	/**
	 * Topic Close Activity Action
	 *
	 * @var string
	 */
	private $topic_close = '';

	/**
	 * Topic Edit Activity Action
	 *
	 * @var string
	 */
	private $topic_edit = '';

	/**
	 * Topic Open Activity Action
	 *
	 * @var string
	 */
	private $topic_open = '';

	/**
	 * Reply Create Activity Action
	 *
	 * @var string
	 */
	private $reply_create = '';

	/**
	 * Reply Edit Activity Action
	 *
	 * @var string
	 */
	private $reply_edit = '';

	/** Setup Methods *********************************************************/

	/**
	 * The bbPress BuddyPress Activity loader
	 *
	 * @since 2.2.0 bbPress (r4394)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
		$this->setup_filters();
		$this->fully_loaded();
	}

	/**
	 * Extension variables
	 *
	 * @since 2.2.0 bbPress (r4394)
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
	 * Setup the actions
	 *
	 * @since 2.2.0 bbPress (r4394)
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
		add_action( 'post_updated',                      array( $this, 'post_visibility_update'    ), 10, 3 );

		// Hook into topic and reply deletion
		add_action( 'bbp_delete_topic',                  array( $this, 'topic_delete'              ), 10, 1 );
		add_action( 'bbp_delete_reply',                  array( $this, 'reply_delete'              ), 10, 1 );
	}

	/**
	 * Setup the filters
	 *
	 * @since 2.2.0 bbPress (r4394)
	 *
	 * @access private
	 */
	private function setup_filters() {

		// Obey BuddyPress commenting rules
		add_filter( 'bp_activity_can_comment',   array( $this, 'activity_can_comment'   )        );

		// Link directly to the topic or reply
		add_filter( 'bp_activity_get_permalink', array( $this, 'activity_get_permalink' ), 10, 2 );

		// Check current forum visibility when activity is read, including older entries.
		if ( version_compare( bp_get_version(), '15.0.0-alpha', '>=' ) ) {
			add_filter( 'bp_activity_get_sql_clauses', array( $this, 'activity_get_sql_clauses' ), 10, 2 );
		} else {
			add_filter( 'bp_activity_get_where_conditions', array( $this, 'activity_get_where_conditions' ), 10, 2 );
		}
		add_filter( 'bp_activity_get',                  array( $this, 'activity_get'                  ), 10, 2 );
		add_filter( 'bp_activity_user_can_read',       array( $this, 'activity_user_can_read'       ), 10, 3 );
	}

	/**
	 * Allow the variables, actions, and filters to be modified by third party
	 * plugins and themes.
	 *
	 * @since 2.2.0 bbPress (r4394)
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
	 * Normalize activity filter values when they are safe to compare in PHP.
	 *
	 * @param mixed $values Activity filter values.
	 * @return array|null Normalized values, or null when SQL may compare differently.
	 */
	private function normalize_filter_values( $values ) {
		if ( ! is_array( $values ) ) {
			if ( ! is_scalar( $values ) ) {
				return null;
			}

			$values = explode( ',', (string) $values );
		}

		$normalized = array();
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				return null;
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			// BuddyPress binds numeric values as integers, changing SQL comparison semantics.
			if ( is_numeric( $value ) ) {
				return null;
			}

			// Non-ASCII identifiers may compare equal under the database collation.
			if ( ! preg_match( '/\A[A-Za-z0-9_-]+\z/', $value ) ) {
				return null;
			}

			$normalized[] = strtolower( $value );
		}

		return $normalized;
	}

	/**
	 * Check whether an activity query can contain bbPress topic or reply activity.
	 *
	 * @param array $args BuddyPress activity query arguments.
	 * @return bool Whether bbPress visibility conditions may be needed.
	 */
	private function query_can_include_forum_activity( $args ) {
		if ( empty( $args['filter'] ) || ! is_array( $args['filter'] ) ) {
			return true;
		}

		$filter = $args['filter'];

		if ( ! empty( $filter['action'] ) ) {
			$actions        = $this->normalize_filter_values( $filter['action'] );
			$activity_types = $this->normalize_filter_values( array( $this->topic_create, $this->reply_create ) );
			if ( ( null === $actions ) || ( null === $activity_types ) ) {
				return true;
			}

			if ( ! array_intersect( $actions, $activity_types ) ) {
				return false;
			}
		}

		if ( ! empty( $filter['object'] ) ) {
			$objects         = $this->normalize_filter_values( $filter['object'] );
			$group_component = bp_is_active( 'groups' ) ? buddypress()->groups->id : 'groups';
			$components      = $this->normalize_filter_values( array( $this->component, $group_component ) );
			if ( ( null === $objects ) || ( null === $components ) ) {
				return true;
			}

			if ( ! array_intersect( $objects, $components ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get password-protected bbPress post IDs for activity collections.
	 *
	 * Collections always omit protected posts. Direct reads continue through
	 * activity_user_can_read(), which honors password cookies and filters.
	 *
	 * @return array[] Post IDs keyed by post type.
	 */
	private function get_password_protected_post_ids() {
		$bbp_db     = bbp_db();
		$post_types = bbp_get_post_types();
		$post_ids   = array_fill_keys( $post_types, array() );
		$types      = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$key        = md5( serialize( array( $bbp_db->posts, $post_types ) ) );
		$cache_key  = 'bbp_activity_password_post_ids:' . $key . ':' . wp_cache_get_last_changed( 'bbpress_posts' );
		$cached     = wp_cache_get( $cache_key, 'bbpress_posts' );

		if ( false === $cached ) {
			// Padded collations can treat whitespace passwords as empty strings.
			$protected = $bbp_db->get_results( $bbp_db->prepare( "SELECT ID, post_type FROM {$bbp_db->posts} WHERE post_type IN ({$types}) AND CHAR_LENGTH( post_password ) > 0", $post_types ) );

			// Do not retain a failed lookup.
			if ( '' === $bbp_db->last_error ) {
				foreach ( $protected as $post ) {
					if ( isset( $post_ids[ $post->post_type ] ) ) {
						$post_ids[ $post->post_type ][] = (int) $post->ID;
					}
				}

				wp_cache_set( $cache_key, $post_ids, 'bbpress_posts' );
			}
		} else {
			$post_ids = $cached;
		}

		// A password on a forum also protects every descendant forum.
		$forum_type = bbp_get_forum_post_type();
		$parents    = bbp_forum_query_all_descendant_ids( $post_ids[ $forum_type ] );
		$post_ids[ $forum_type ] = $parents;
		$seen       = array_fill_keys( $parents, true );
		$index      = 0;
		while ( isset( $parents[ $index ] ) ) {
			$parent_id = $parents[ $index ];
			++$index;

			// Preserve the existing child-ID filter, including synthetic children.
			$children = array();
			foreach ( bbp_forum_query_subforum_ids( $parent_id ) as $forum_id ) {
				$forum_id = (int) $forum_id;
				if ( ( $forum_id > 0 ) && ! isset( $seen[ $forum_id ] ) ) {
					$children[] = $forum_id;
				}
			}

			if ( empty( $children ) ) {
				continue;
			}

			// Filter-added forums can also have non-countable descendants.
			foreach ( bbp_forum_query_all_descendant_ids( $children ) as $forum_id ) {
				if ( ! isset( $seen[ $forum_id ] ) ) {
					$seen[ $forum_id ]         = true;
					$post_ids[ $forum_type ][] = $forum_id;
					$parents[]                  = $forum_id;
				}
			}
		}

		return $post_ids;
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

		if ( ! $this->query_can_include_forum_activity( $args ) ) {
			return $where_conditions;
		}

		$forum_ids = array();
		foreach ( wp_parse_id_list( bbp_get_excluded_forum_ids() ) as $forum_id ) {
			if ( bbp_is_forum_restricted_for_user( $forum_id, get_current_user_id() ) ) {
				$forum_ids[] = $forum_id;
			}
		}

		$password_ids = $this->get_password_protected_post_ids();
		$forum_ids   = wp_parse_id_list( array_merge( $forum_ids, $password_ids[ bbp_get_forum_post_type() ] ) );
		$topic_ids   = wp_parse_id_list( $password_ids[ bbp_get_topic_post_type() ] );
		$reply_ids   = wp_parse_id_list( $password_ids[ bbp_get_reply_post_type() ] );

		if ( empty( $forum_ids ) && empty( $topic_ids ) && empty( $reply_ids ) ) {
			return $where_conditions;
		}

		$group_component = bp_is_active( 'groups' ) ? buddypress()->groups->id : 'groups';

		// Group activity stores the post ID in secondary_item_id instead.
		$topic_restrictions = array();
		$topic_values       = array( $this->topic_create, $this->component, $group_component, $group_component );
		if ( ! empty( $forum_ids ) ) {
			$topic_restrictions[] = 'bbp_topic.post_parent IN (' . implode( ', ', array_fill( 0, count( $forum_ids ), '%d' ) ) . ')';
			$topic_values         = array_merge( $topic_values, $forum_ids );
		}
		if ( ! empty( $topic_ids ) ) {
			$topic_restrictions[] = 'bbp_topic.ID IN (' . implode( ', ', array_fill( 0, count( $topic_ids ), '%d' ) ) . ')';
			$topic_values         = array_merge( $topic_values, $topic_ids );
		}

		if ( ! empty( $topic_restrictions ) ) {
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
						AND (" . implode( ' OR ', $topic_restrictions ) . ')
				)
			)';
			$where_conditions['bbpress_topic_visibility'] = $wpdb->prepare( $topic_sql, $topic_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only table names and generated integer placeholders.
		}

		// Reply activity points to the reply; follow its topic to the forum.
		$reply_restrictions = array();
		$reply_values       = array( $this->reply_create, $this->component, $group_component, $group_component );
		if ( ! empty( $forum_ids ) ) {
			$reply_restrictions[] = 'bbp_reply_topic.post_parent IN (' . implode( ', ', array_fill( 0, count( $forum_ids ), '%d' ) ) . ')';
			$reply_values         = array_merge( $reply_values, $forum_ids );
		}
		if ( ! empty( $topic_ids ) ) {
			$reply_restrictions[] = 'bbp_reply_topic.ID IN (' . implode( ', ', array_fill( 0, count( $topic_ids ), '%d' ) ) . ')';
			$reply_values         = array_merge( $reply_values, $topic_ids );
		}
		if ( ! empty( $reply_ids ) ) {
			$reply_restrictions[] = 'bbp_reply.ID IN (' . implode( ', ', array_fill( 0, count( $reply_ids ), '%d' ) ) . ')';
			$reply_values         = array_merge( $reply_values, $reply_ids );
		}

		if ( ! empty( $reply_restrictions ) ) {
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
						AND (" . implode( ' OR ', $reply_restrictions ) . ')
				)
			)';
			$where_conditions['bbpress_reply_visibility'] = $wpdb->prepare( $reply_sql, $reply_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only table names and generated integer placeholders.
		}

		return $where_conditions;
	}

	/**
	 * Exclude restricted forum activity using BuddyPress's SQL clauses filter.
	 *
	 * @since 2.6.20 bbPress (r8023)
	 *
	 * @param array $sql_clauses BuddyPress activity query SQL clauses.
	 * @param array $args        BuddyPress activity query arguments.
	 * @return array Filtered SQL clauses.
	 */
	public function activity_get_sql_clauses( $sql_clauses, $args ) {
		if ( isset( $sql_clauses['where_conditions'] ) && is_array( $sql_clauses['where_conditions'] ) ) {
			$sql_clauses['where_conditions'] = $this->activity_get_where_conditions( $sql_clauses['where_conditions'], $args );
		}

		return $sql_clauses;
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

		return ! empty( $forum_id )
			&& ! bbp_is_forum_restricted_for_user( $forum_id, $user_id )
			&& ! bbp_get_password_required_id( $post_id );
	}

	/**
	 * Register our activity actions with BuddyPress
	 *
	 * @since 2.2.0 bbPress (r4394)
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
	 * Wrapper for recoding bbPress actions to the BuddyPress activity stream
	 *
	 * @since 2.2.0 bbPress (r4394)
	 *
	 * @param  array $args Array of arguments for bp_activity_add()
	 *
	 * @return int   Activity ID if successful, false if not
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
	 * Wrapper for deleting bbPress actions from BuddyPress activity stream
	 *
	 * @since 2.2.0 bbPress (r4394)
	 *
	 * @param  array $args Array of arguments for bp_activity_add()
	 *
	 * @return int   Activity ID if successful, false if not
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
	 * Check for an existing activity stream entry for a given post_id
	 *
	 * @param int $post_id ID of the topic or reply
	 * @return int if an activity id is verified, false if not
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
	 * Maybe disable activity stream comments on select actions
	 *
	 * @since 2.2.0 bbPress (r4394)
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
	 * Maybe link directly to topics and replies in activity stream entries
	 *
	 * @since 2.2.0 bbPress (r4394)
	 *
	 * @param string $link
	 * @param mixed $activity_object
	 * @return string The link to the activity stream item
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
	 * Record an activity stream entry when a topic is created or updated
	 *
	 * @since 2.2.0 bbPress (r4394)
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author_id
	 * @return Bail early if topic is by anonymous user
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

		// Do not retain activity for password-protected topics
		if ( bbp_is_password_protected( $topic_id ) ) {
			$this->topic_delete( $topic_id );
			return;
		}

		// Bail if user is not active
		if ( bbp_is_user_inactive( $user_id ) ) {
			return;
		}

		// Bail if topic is not publicly viewable
		if ( ! bbp_is_topic_public( $topic_id ) ) {
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
	 * Delete the activity stream entry when a topic is spammed, trashed, or deleted
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
	 * Update the activity stream entry when a topic status changes
	 *
	 * @param int $topic_id
	 * @param obj $post
	 * @return Bail early if not a topic, or topic is by anonymous user
	 */
	public function topic_update( $topic_id = 0, $post = null ) {

		// Bail early if not a topic
		if ( get_post_type( $post ) !== bbp_get_topic_post_type() ) {
			return;
		}

		$topic_id = bbp_get_topic_id( $topic_id );

		// Delete activity for unpublished or password-protected topics
		if ( ! bbp_is_topic_public( $post->ID ) || bbp_is_password_protected( $topic_id ) ) {
			$this->topic_delete( $topic_id );
			return;
		}

		// Bail early if topic is by anonymous user
		if ( bbp_is_topic_anonymous( $topic_id ) ) {
			return;
		}

		// Preserve the revision setting for activity updates
		if ( ! bbp_allow_revisions() ) {
			return;
		}

		// Validate topic data
		$forum_id        = bbp_get_topic_forum_id( $topic_id );
		$topic_author_id = bbp_get_topic_author_id( $topic_id );

		$this->topic_create( $topic_id, $forum_id, array(), $topic_author_id );
	}

	/** Replies ***************************************************************/

	/**
	 * Record an activity stream entry when a reply is created
	 *
	 * @since 2.2.0 bbPress (r4394)
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author_id
	 * @return Bail early if topic is by anonymous user
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

		// Do not retain activity for password-protected replies
		if ( bbp_is_password_protected( $reply_id ) ) {
			$this->reply_delete( $reply_id );
			return;
		}

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
	 * Delete the activity stream entry when a reply is spammed, trashed, or deleted
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
	 * Update the activity stream entry when a reply status changes
	 *
	 * @param int $reply_id
	 * @param obj $post
	 * @return Bail early if not a reply, or reply is by anonymous user
	 */
	public function reply_update( $reply_id, $post ) {

		// Bail early if not a reply
		if ( get_post_type( $post ) !== bbp_get_reply_post_type() ) {
			return;
		}

		$reply_id = bbp_get_reply_id( $reply_id );

		// Delete activity for unpublished or password-protected replies
		if ( ( bbp_get_public_status_id() !== $post->post_status ) || bbp_is_password_protected( $reply_id ) ) {
			$this->reply_delete( $reply_id );
			return;
		}

		// Bail early if reply is by anonymous user
		if ( bbp_is_reply_anonymous( $reply_id ) ) {
			return;
		}

		// Preserve the revision setting for activity updates
		if ( ! bbp_allow_revisions() ) {
			return;
		}

		// Validate reply data
		$topic_id        = bbp_get_reply_topic_id( $reply_id );
		$forum_id        = bbp_get_reply_forum_id( $reply_id );
		$reply_author_id = bbp_get_reply_author_id( $reply_id );

		$this->reply_create( $reply_id, $topic_id, $forum_id, array(), $reply_author_id );
	}

	/**
	 * Restore activity after a topic or reply becomes publicly visible.
	 *
	 * Normal edits recreate activity through topic_update() and reply_update().
	 * Handle visibility transitions separately when revision logging is disabled.
	 *
	 * @since 2.6.21 bbPress (r8113)
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function post_visibility_update( $post_id = 0, $post_after = null, $post_before = null ) {
		$post_types = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );

		// Bail unless this is a topic or reply update
		if ( ! ( $post_after instanceof WP_Post ) || ! ( $post_before instanceof WP_Post ) || ! in_array( $post_after->post_type, $post_types, true ) ) {
			return;
		}

		// Bail when normal edit handling applies or protection remains
		if ( bbp_allow_revisions() || bbp_is_password_protected( $post_id ) ) {
			return;
		}

		// Restore topic activity
		if ( bbp_get_topic_post_type() === $post_after->post_type ) {
			$was_public = in_array( $post_before->post_status, bbp_get_public_topic_statuses(), true );
			$is_public  = in_array( $post_after->post_status, bbp_get_public_topic_statuses(), true );

			// Bail unless the topic became publicly visible
			if ( ( $was_public && empty( $post_before->post_password ) ) || ! $is_public || bbp_is_topic_anonymous( $post_id ) ) {
				return;
			}

			$forum_id = bbp_get_topic_forum_id( $post_id );
			$user_id  = bbp_get_topic_author_id( $post_id );

			$this->topic_create( $post_id, $forum_id, array(), $user_id );

		// Restore reply activity
		} elseif ( bbp_get_reply_post_type() === $post_after->post_type ) {
			$was_public = ( bbp_get_public_status_id() === $post_before->post_status );
			$is_public  = ( bbp_get_public_status_id() === $post_after->post_status  );

			// Bail unless the reply became publicly visible
			if ( ( $was_public && empty( $post_before->post_password ) ) || ! $is_public || bbp_is_reply_anonymous( $post_id ) ) {
				return;
			}

			$topic_id = bbp_get_reply_topic_id( $post_id );
			$forum_id = bbp_get_reply_forum_id( $post_id );
			$user_id  = bbp_get_reply_author_id( $post_id );

			$this->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		}
	}
}
endif;
