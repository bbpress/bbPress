<?php

/**
 * bbPress User Engagements User Strategy.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * User strategy for interfacing with User Engagements.
 *
 * This strategy largely exists for backwards compatibility with bbPress 2.5,
 * or installations that have not upgraded their databases to 2.6 or above.
 *
 * Note: this strategy is going to be a bit less tidy than the others, because
 * it needs to do weird things to maintain the 2.5 status-quo. Do not use this
 * strategy as an example when building your own.
 *
 * @since 2.6.0 bbPress (r6876)
 */
class BBP_User_Engagements_User extends BBP_User_Engagements_Base {

	/**
	 * Type of strategy being used.
	 *
	 * @since 2.6.0 bbPress (r6876)
	 *
	 * @var string
	 */
	public $type = 'user';

	/**
	 * Private function to map 2.6 meta keys to 2.5 user-option keys.
	 *
	 * Types other than posts did not exist in 2.5, so their keys include the
	 * type to keep them apart from posts with the same IDs.
	 *
	 * @since 2.6.0 bbPress (r6876)
	 * @since 2.7.0 bbPress (r8318) Added the `$meta_type` parameter.
	 *
	 * @param string $meta_key
	 * @param string $meta_type
	 * @param int    $object_id
	 * @param bool   $prefix
	 * @param string $context_key
	 *
	 * @return string
	 */
	private function get_user_option_key( $meta_key = '', $meta_type = 'post', $object_id = 0, $prefix = false, $context_key = '' ) {
		$meta_key = $this->get_storage_key( $meta_key, $meta_type );

		// Not a post, so pluralize and add the type
		if ( 'post' !== $meta_type ) {
			$key = "{$meta_key}s_{$meta_type}";

			// Maybe prefix the key (for use in raw database queries)
			return ( true === $prefix )
				? bbp_db()->get_blog_prefix() . $key
				: $key;
		}

		switch ( $meta_key ) {

			// Favorites
			case '_bbp_favorite' :
				$key = '_bbp_favorites';
				break;

			// Subscriptions
			case '_bbp_subscription' :

				// Maybe guess at post type
				if ( ! empty( $object_id ) ) {
					$post_type = get_post_type( $object_id );
				} elseif ( 'get_user_forum_subscriptions' === $context_key ) {
					$post_type = bbp_get_forum_post_type();
				} else {
					$post_type = bbp_get_topic_post_type();
				}

				// Forums & Topics used different keys :/
				$key = ( bbp_get_forum_post_type() === $post_type )
					? '_bbp_forum_subscriptions'
					: '_bbp_subscriptions';

				break;

			// Unknown, so pluralize
			default :
				$key = "{$meta_key}s";
				break;
		}

		// Maybe prefix the key (for use in raw database queries)
		if ( true === $prefix ) {
			$key = bbp_db()->get_blog_prefix() . $key;
		}

		// Return the old (pluralized) user option key
		return $key;
	}

	/**
	 * Private function to get a 2.5 compatible cache key.
	 *
	 * This method exists to provide backwards compatibility with bbPress 2.5,
	 * which had caching surrounding the FIND_IN_SET usermeta queries.
	 *
	 * @since 2.6.3 bbPress (r6993)
	 * @since 2.7.0 bbPress (r8318) Added the `$meta_type` parameter.
	 *
	 * @param string $meta_key
	 * @param string $meta_type
	 * @param int    $object_id
	 *
	 * @return string
	 */
	private function get_cache_key( $meta_key = '', $meta_type = 'post', $object_id = 0 ) {
		$meta_key = $this->get_storage_key( $meta_key, $meta_type );

		// No negative numbers in cache keys (zero is weird, but not disallowed)
		$object_id = absint( $object_id );

		// Not a post, so name the key after the type
		if ( 'post' !== $meta_type ) {
			return "bbp_get_users_for_{$meta_type}{$meta_key}_{$object_id}";
		}

		// Maybe guess at post type
		$post_type = ! empty( $object_id )
			? get_post_type( $object_id )
			: bbp_get_topic_post_type();

		switch ( $meta_key ) {

			// Favorites
			case '_bbp_favorite' :
				$key = 'bbp_get_topic_favoriters_';
				break;

			// Subscriptions
			case '_bbp_subscription' :

				// Forums & Topics used different keys :/
				$key = ( bbp_get_forum_post_type() === $post_type )
					? 'bbp_get_forum_subscribers_'
					: 'bbp_get_topic_subscribers_';

				break;

			// Unknown, so pluralize
			default :
				$nounize = rtrim( $meta_key, 'e' );
				$key     = "bbp_get_{$post_type}_{$nounize}ers_";
				break;
		}

		// Return the old (pluralized) user option key with object ID appended
		return "{$key}{$object_id}";
	}

	/**
	 * Get the user engagement cache for a given meta key and object ID.
	 *
	 * This method exists to provide backwards compatibility with bbPress 2.5,
	 * which had caching surrounding the FIND_IN_SET queries in usermeta.
	 *
	 * @since 2.6.3 bbPress (r6993)
	 * @since 2.7.0 bbPress (r8318) Added the `$meta_type` parameter.
	 *
	 * @param string $meta_key
	 * @param string $meta_type
	 * @param int    $object_id
	 *
	 * @return mixed Results from cache get.
	 */
	private function cache_get( $meta_key = '', $meta_type = 'post', $object_id = 0 ) {
		$cache_key = $this->get_cache_key( $meta_key, $meta_type, $object_id );

		return wp_cache_get( $cache_key, 'bbpress_engagements' );
	}

	/**
	 * Set the user engagement cache for a given meta key and object ID.
	 *
	 * This method exists to provide backwards compatibility with bbPress 2.5,
	 * which had caching surrounding the FIND_IN_SET queries in usermeta.
	 *
	 * @since 2.6.3 bbPress (r6993)
	 * @since 2.7.0 bbPress (r8318) Added the `$meta_type` parameter.
	 *
	 * @param string $meta_key
	 * @param string $meta_type
	 * @param int    $object_id
	 * @param array  $user_ids
	 *
	 * @return mixed Results from cache set.
	 */
	private function cache_set( $meta_key = '', $meta_type = 'post', $object_id = 0, $user_ids = array() ) {
		$cache_key = $this->get_cache_key( $meta_key, $meta_type, $object_id );
		$user_ids  = $this->parse_comma_list( $user_ids );

		return wp_cache_set( $cache_key, $user_ids, 'bbpress_engagements' );
	}

	/**
	 * Delete the user engagement cache for a given meta key and object ID.
	 *
	 * This method exists to provide backwards compatibility with bbPress 2.5,
	 * which had caching surrounding the FIND_IN_SET queries in usermeta.
	 *
	 * @since 2.6.3 bbPress (r6993)
	 * @since 2.7.0 bbPress (r8318) Added the `$meta_type` parameter.
	 *
	 * @param string $meta_key
	 * @param string $meta_type
	 * @param int    $object_id
	 *
	 * @return mixed Results from cache delete.
	 */
	private function cache_delete( $meta_key = '', $meta_type = 'post', $object_id = 0 ) {
		$cache_key = $this->get_cache_key( $meta_key, $meta_type, $object_id );

		return wp_cache_delete( $cache_key, 'bbpress_engagements' );
	}

	/**
	 * Turn a comma-separated string into an array of integers
	 *
	 * @since 2.6.0 bbPress (r6876)
	 *
	 * @param string $results
	 * @return array
	 */
	private function parse_comma_list( $results = '' ) {
		return array_filter( wp_parse_id_list( $results ) );
	}

	/**
	 * Add a user id to an object.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 * @since 2.6.20 bbPress (r7989) Normalize object IDs before strict lookups.
	 *
	 * @param int    $object_id The object id.
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 * @param bool   $unique    Whether meta key should be unique to the object.
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function add_user_to_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post', $unique = false ) {
		$object_id  = absint( $object_id );
		$retval     = false;
		$option_key = $this->get_user_option_key( $meta_key, $meta_type, $object_id );
		$object_ids = $this->parse_comma_list( get_user_option( $option_key, $user_id ) );
		$exists     = array_search( $object_id, $object_ids, true );

		// Not already added, so add it
		if ( false === $exists ) {
			$object_ids[] = $object_id;
			$object_ids   = implode( ',', $this->parse_comma_list( $object_ids ) );
			$retval       = update_user_option( $user_id, $option_key, $object_ids );

			// Delete cache if successful (accounts for int & true)
			if ( false !== $retval ) {
				$this->cache_delete( $meta_key, $meta_type, $object_id );
			}
		}

		// Return true if added, or false if not
		return $retval;
	}

	/**
	 * Remove a user id from an object.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 * @since 2.6.20 bbPress (r7989) Normalize object IDs before strict lookups.
	 *
	 * @param int    $object_id The object id.
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$object_id  = absint( $object_id );
		$retval     = false;
		$option_key = $this->get_user_option_key( $meta_key, $meta_type, $object_id );
		$object_ids = $this->parse_comma_list( get_user_option( $option_key, $user_id ) );
		$exists     = array_search( $object_id, $object_ids, true );

		// Exists, so remove it
		if ( false !== $exists ) {
			unset( $object_ids[ $exists ] );

			$object_ids = implode( ',', $this->parse_comma_list( $object_ids ) );
			$retval     = ! empty( $object_ids )
				? update_user_option( $user_id, $option_key, $object_ids )
				: delete_user_option( $user_id, $option_key );

			// Delete cache if successful (accounts for int & true)
			if ( false !== $retval ) {
				$this->cache_delete( $meta_key, $meta_type, $object_id );
			}
		}

		// Return true if removed, or false if not
		return $retval;
	}

	/**
	 * Remove a user id from all objects.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 *
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {

		// Get the key
		$option_key = $this->get_user_option_key( $meta_key, $meta_type );

		// Get the option
		$object_ids = $this->parse_comma_list( get_user_option( $option_key, $user_id ) );

		// Attempt to delete the user option
		$retval = delete_user_option( $user_id, $option_key );

		// Try to delete caches, but only if everything else succeeded
		if ( ! empty( $retval ) && ! empty( $object_ids ) ) {
			foreach ( $object_ids as $object_id ) {
				$this->cache_delete( $meta_key, $meta_type, $object_id );
			}
		}

		// Return true if user was removed, or false if not
		return $retval;
	}

	/**
	 * Remove an object from all users.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 * @since 2.6.20 bbPress (r7988) Report partial cleanup failures.
	 *
	 * @param int    $object_id The object id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {

		// Query for users
		$user_ids = $this->get_users_for_object( $object_id, $meta_key, $meta_type );
		$u_count  = count( $user_ids );

		// Count number of removals
		$r_count  = 0;

		// Users have engaged, so remove them
		if ( ! empty( $u_count ) ) {

			// Loop through users and remove them from the object
			foreach ( $user_ids as $user_id ) {
				if ( $this->remove_user_from_object( $object_id, $user_id, $meta_key, $meta_type ) ) {
					++$r_count;
				}
			}
		}

		// Return true if successfully removed from all users
		return ( $r_count === $u_count );
	}

	/**
	 * Remove all users from all objects.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 * @since 2.6.20 bbPress (r7988) Report partial cleanup failures.
	 *
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {

		// Query for users
		$option_key = $this->get_user_option_key( $meta_key, $meta_type, 0, true );
		$bbp_db     = bbp_db();
		$user_ids   = $bbp_db->get_col( $bbp_db->prepare( "SELECT user_id FROM {$bbp_db->usermeta} WHERE meta_key = %s", $option_key ) );
		$u_count    = count( $user_ids );

		// Count number of removals
		$r_count    = 0;

		// Users have engaged, so remove them
		if ( ! empty( $u_count ) ) {

			// Loop through users and remove their user options
			foreach ( $user_ids as $user_id ) {
				if ( $this->remove_user_from_all_objects( $user_id, $meta_key, $meta_type ) ) {
					++$r_count;
				}
			}
		}

		// Return true if successfully removed from all users
		return ( $r_count === $u_count );
	}

	/**
	 * Get users of an object.
	 *
	 * The database queries in this function were cached in bbPress versions
	 * older than 2.6, but no longer are to avoid cache pollution.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 *
	 * @param int    $object_id The object id.
	 * @param string $meta_key  The key used to index this relationship.
	 * @param string $meta_type The type of meta to look in.
	 *
	 * @return array Returns ids of users.
	 */
	public function get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {

		// Try to get user IDs from cache
		$user_ids = $this->cache_get( $meta_key, $meta_type, $object_id );

		// Cache is empty, so hit the database
		if ( false === $user_ids ) {
			$option_key = $this->get_user_option_key( $meta_key, $meta_type, $object_id, true );
			$bbp_db     = bbp_db();
			$user_ids   = $bbp_db->get_col( $bbp_db->prepare( "SELECT user_id FROM {$bbp_db->usermeta} WHERE meta_key = %s AND FIND_IN_SET( %d, meta_value ) > 0", $option_key, $object_id ) );

			// Always cache results (even if empty, to prevent multiple misses)
			$this->cache_set( $meta_key, $meta_type, $object_id, $user_ids );
		}

		// Return parsed IDs
		return $this->parse_comma_list( $user_ids );
	}

	/**
	 * Get the part of the query responsible for JOINing objects to relationships.
	 *
	 * @since 2.6.0 bbPress (r6844)
	 * @since 2.7.0 bbPress (r8318) Use the engagement type's query arguments.
	 *
	 * @param array  $args
	 * @param string $meta_key
	 * @param string $meta_type
	 *
	 * @return array Query arguments. An empty array means callers must not run a query.
	 */
	public function get_query( $args = array(), $context_key = '', $meta_key = '', $meta_type = 'post' ) {
		$user_id    = bbp_get_user_id( $args, true, true );
		$option_key = $this->get_user_option_key( $meta_key, $meta_type, 0, false, $context_key );
		$object_ids = $this->parse_comma_list( get_user_option( $option_key, $user_id ) );

		// Maybe trick WP_Query into ".ID IN (0)" to return no results
		if ( empty( $object_ids ) ) {
			$object_ids = array( 0 );
		}

		// Limit the query to these objects
		$type = bbp_get_engagement_type( $meta_type );
		$args = ! empty( $type )
			? (array) $type->get_query_args( $object_ids )
			: array();

		// Bail if a registered type cannot query objects
		if ( ! empty( $type ) && empty( $args ) ) {
			return array();

		// Default unregistered types to post IDs for backwards compatibility
		} elseif ( empty( $args ) ) {
			$args = array(
				'post__in' => $object_ids
			);
		}

		// Parse arguments
		return bbp_parse_args( $args, array(), $context_key );
	}
}
