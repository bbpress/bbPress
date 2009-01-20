<?php

/* Options/Meta */


/* Internal */

/**
 * Adds and updates meta data in the database
 *
 * @internal
 */
function bb_update_meta( $object_id = 0, $meta_key, $meta_value, $type, $global = false )
{
	global $bbdb;
	if ( !is_numeric( $object_id ) || empty( $object_id ) && !$global ) {
		return false;
	}
	$cache_object_id = $object_id = (int) $object_id;
	switch ( $type ) {
		case 'option':
			$object_type = 'bb_option';
			break;
		case 'user' :
			global $wp_users_object;
			$id = $object_id;
			$return = $wp_users_object->update_meta( compact( 'id', 'meta_key', 'meta_value' ) );
			if ( is_wp_error( $return ) ) {
				return false;
			}
			return $return;
			break;
		case 'forum' :
			$object_type = 'bb_forum';
			break;
		case 'topic' :
			$object_type = 'bb_topic';
			break;
		case 'post' :
			$object_type = 'bb_post';
			break;
		default :
			$object_type = $type;
			break;
	}

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );

	$meta_tuple = compact( 'object_type', 'object_id', 'meta_key', 'meta_value', 'type' );
	$meta_tuple = apply_filters( 'bb_update_meta', $meta_tuple );
	extract( $meta_tuple, EXTR_OVERWRITE );

	$meta_value = $_meta_value = maybe_serialize( $meta_value );
	$meta_value = maybe_unserialize( $meta_value );

	$cur = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM `$bbdb->meta` WHERE `object_type` = %s AND `object_id` = %d AND `meta_key` = %s", $object_type, $object_id, $meta_key ) );
	if ( !$cur ) {
		$bbdb->insert( $bbdb->meta, array( 'object_type' => $object_type, 'object_id' => $object_id, 'meta_key' => $meta_key, 'meta_value' => $_meta_value ) );
	} elseif ( $cur->meta_value != $meta_value ) {
		$bbdb->update( $bbdb->meta, array( 'meta_value' => $_meta_value), array( 'object_type' => $object_type, 'object_id' => $object_id, 'meta_key' => $meta_key ) );
	}

	if ( $object_type == 'bb_option' ) {
		$cache_object_id = $meta_key;
	}
	wp_cache_delete( $cache_object_id, $object_type );
	if ( !$cur ) {
		return true;
	}
}

/**
 * Deletes meta data from the database
 *
 * @internal
 */
function bb_delete_meta( $object_id = 0, $meta_key, $meta_value, $type, $global = false )
{
	global $bbdb;
	if ( !is_numeric( $object_id ) || empty( $object_id ) && !$global ) {
		return false;
	}
	$cache_object_id = $object_id = (int) $object_id;
	switch ( $type ) {
		case 'option':
			$object_type = 'bb_option';
			break;
		case 'user':
			global $wp_users_object;
			$id = $object_id;
			return $wp_users_object->update_meta( compact( 'id', 'meta_key', 'meta_value' ) );
			break;
		case 'forum':
			$object_type = 'bb_forum';
			break;
		case 'topic':
			$object_type = 'bb_topic';
			break;
		case 'post':
			$object_type = 'bb_post';
			break;
		default:
			$object_type = $type;
			break;
	}

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );

	$meta_tuple = compact( 'object_type', 'object_id', 'meta_key', 'meta_value', 'type' );
	$meta_tuple = apply_filters( 'bb_delete_meta', $meta_tuple );
	extract( $meta_tuple, EXTR_OVERWRITE );

	$meta_value = maybe_serialize( $meta_value );

	if ( empty( $meta_value ) ) {
		$meta_sql = $bbdb->prepare( "SELECT `meta_id` FROM `$bbdb->meta` WHERE `object_type` = %s AND `object_id` = %d AND `meta_key` = %s", $object_type, $object_id, $meta_key );
	} else {
		$meta_sql = $bbdb->prepare( "SELECT `meta_id` FROM `$bbdb->meta` WHERE `object_type` = %s AND `object_id` = %d AND `meta_key` = %s AND `meta_value` = %s", $object_type, $object_id, $meta_key, $meta_value );
	}

	if ( !$meta_id = $bbdb->get_var( $meta_sql ) ) {
		return false;
	}

	$bbdb->query( $bbdb->prepare( "DELETE FROM `$bbdb->meta` WHERE `meta_id` = %d", $meta_id ) );

	if ( $object_type == 'bb_option' ) {
		$cache_object_id = $meta_key;
	}
	wp_cache_delete( $cache_object_id, $object_type );
	return true;
}

/**
 * Adds an objects meta data to the object
 *
 * This is the only function that should add to user / topic - NOT bbdb::prepared
 *
 * @internal
 */
function bb_append_meta( $object, $type )
{
	global $bbdb;
	switch ( $type ) {
		case 'user':
			global $wp_users_object;
			return $wp_users_object->append_meta( $object );
			break;
		case 'forum':
			$object_id_column = 'forum_id';
			$object_type = 'bb_forum';
			$slug = 'forum_slug';
			break;
		case 'topic':
			$object_id_column = 'topic_id';
			$object_type = 'bb_topic';
			$slug = 'topic_slug';
			break;
		case 'post':
			$object_id_column = 'post_id';
			$object_type = 'bb_post';
			$slug = 'post_slug';
			break;
	}

	if ( is_array( $object ) && $object ) {
		$trans = array();
		foreach ( array_keys( $object ) as $i ) {
			$trans[$object[$i]->$object_id_column] =& $object[$i];
		}
		$ids = join( ',', array_map( 'intval', array_keys( $trans ) ) );
		if ( $metas = $bbdb->get_results( "SELECT `object_id`, `meta_key`, `meta_value` FROM `$bbdb->meta` WHERE `object_type` = '$object_type' AND `object_id` IN ($ids) /* bb_append_meta */" ) ) {
			usort( $metas, '_bb_append_meta_sort' );
			foreach ( $metas as $meta ) {
				$trans[$meta->object_id]->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $bbdb->prefix) === 0 ) {
					$trans[$meta->object_id]->{substr($meta->meta_key, strlen($bbdb->prefix))} = maybe_unserialize( $meta->meta_value );
				}
			}
		}
		foreach ( array_keys( $trans ) as $i ) {
			wp_cache_add( $i, $trans[$i], $object_type );
			if ( $slug ) {
				wp_cache_add( $trans[$i]->$slug, $i, 'bb_' . $slug );
			}
		}
		return $object;
	} elseif ( $object ) {
		if ( $metas = $bbdb->get_results( $bbdb->prepare( "SELECT `meta_key`, `meta_value` FROM `$bbdb->meta` WHERE `object_type` = '$object_type' AND `object_id` = %d /* bb_append_meta */", $object->$object_id_column ) ) ) {
			usort( $metas, '_bb_append_meta_sort' );
			foreach ( $metas as $meta ) {
				$object->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
				if ( strpos( $meta->meta_key, $bbdb->prefix ) === 0 ) {
					$object->{substr( $meta->meta_key, strlen( $bbdb->prefix ) )} = $object->{$meta->meta_key};
				}
			}
		}
		if ( $object->$object_id_column ) {
			wp_cache_set( $object->$object_id_column, $object, $object_type );
			if ( $slug ) {
				wp_cache_add( $object->$slug, $object->$object_id_column, 'bb_' . $slug );
			}
		}
		return $object;
	}
}

/**
 * Sorts meta keys by length to ensure $appended_object->{$bbdb->prefix} key overwrites $appended_object->key as desired
 *
 * @internal
 */
function _bb_append_meta_sort( $a, $b )
{
	return strlen( $a->meta_key ) - strlen( $b->meta_key );
}



/* Options */

/**
 * Echoes the requested bbPress option by calling bb_get_option()
 *
 * @param string The option to be echoed
 * @return void
 */
function bb_option( $option )
{
	echo apply_filters( 'bb_option_' . $option, bb_get_option( $option ) );
}

/**
 * Returns the requested bbPress option from the meta table or the $bb object
 *
 * @param string The option to be echoed
 * @return mixed The value of the option
 */
function bb_get_option( $option )
{
	global $bb;

	switch ( $option ) {
		case 'language':
			$r = str_replace( '_', '-', get_locale() );
			break;
		case 'text_direction':
			global $bb_locale;
			$r = $bb_locale->text_direction;
			break;
		case 'version':
			return '1.0-alpha-6'; // Don't filter
			break;
		case 'bb_db_version' :
			return '1884'; // Don't filter
			break;
		case 'html_type':
			$r = 'text/html';
			break;
		case 'charset':
			$r = 'UTF-8';
			break;
		case 'bb_table_prefix':
		case 'table_prefix':
			global $bbdb;
			return $bbdb->prefix; // Don't filter;
			break;
		case 'url':
			$option = 'uri';
		default:
			if ( isset( $bb->$option ) ) {
				$r = $bb->$option;
				if ( $option === 'mod_rewrite' ) {
					if ( is_bool( $r ) ) {
						$r = (integer) $r;
					}
				}
				break;
			}

			$r = bb_get_option_from_db( $option );

			if ( !$r ) {
				switch ( $option ) {
					case 'wp_table_prefix' :
						global $wp_table_prefix;
						return $wp_table_prefix; // Don't filter;
						break;
					case 'mod_rewrite':
						$r = 0;
						break;
					case 'page_topics':
						$r = 30;
						break;
					case 'edit_lock':
						$r = 60;
						break;
					case 'gmt_offset':
						$r = 0;
						break;
					case 'uri_ssl':
						$r = preg_replace( '|^http://|i', 'https://', bb_get_option( 'uri' ) );
						break;
				}
			}
			break;
	}

	return apply_filters( 'bb_get_option_' . $option, $r, $option );
}

/**
 * Retrieves and returns the requested bbPress option from the meta table
 *
 * @param string The option to be echoed
 * @return void
 */
function bb_get_option_from_db( $option )
{
	global $bbdb;
	$option = preg_replace( '|[^a-z0-9_]|i', '', $option );

	if ( false === $r = wp_cache_get( $option, 'bb_option' ) ) {
		if ( BB_INSTALLING ) $bbdb->suppress_errors();
		$row = $bbdb->get_row( $bbdb->prepare( "SELECT `meta_value` FROM `$bbdb->meta` WHERE `object_type` = 'bb_option' AND `meta_key` = %s", $option ) );
		if ( BB_INSTALLING ) $bbdb->suppress_errors(false);

		if ( is_object( $row ) ) {
			$r = maybe_unserialize( $row->meta_value );
			wp_cache_set( $option, $r, 'bb_option' );
		} else {
			$r = null;
		}
	}
	return apply_filters( 'bb_get_option_from_db_' . $option, $r, $option );
}

function bb_form_option( $option )
{
	echo bb_get_form_option( $option );
}

function bb_get_form_option( $option )
{
	return attribute_escape( bb_get_option( $option ) );
}

// Don't use the return value; use the API. Only returns options stored in DB.
function bb_cache_all_options()
{
	global $bbdb;
	$results = $bbdb->get_results( "SELECT `meta_key`, `meta_value` FROM `$bbdb->meta` WHERE `object_type` = 'bb_option'" );

	if ( !$results || !is_array( $results ) || !count( $results ) ) {
		// Let's assume that the options haven't been populated from the old topicmeta table
		if ( !BB_INSTALLING ) {
			$topicmeta_exists = $bbdb->query( "SELECT * FROM $bbdb->topicmeta LIMIT 1" );
			if ($topicmeta_exists) {
				require_once( BB_PATH . 'bb-admin/includes/defaults.bb-schema.php' );
				// Create the meta table
				$bbdb->query( $bb_queries['meta'] );
				// Copy options
				$bbdb->query( "INSERT INTO `$bbdb->meta` (`meta_key`, `meta_value`) SELECT `meta_key`, `meta_value` FROM `$bbdb->topicmeta` WHERE `topic_id` = 0;" );
				// Copy topic meta
				$bbdb->query( "INSERT INTO `$bbdb->meta` (`object_id`, `meta_key`, `meta_value`) SELECT `topic_id`, `meta_key`, `meta_value` FROM `$bbdb->topicmeta` WHERE `topic_id` != 0;" );
				// Entries with an object_id are topic meta at this stage
				$bbdb->query( "UPDATE `$bbdb->meta` SET `object_type` = 'bb_topic' WHERE `object_id` != 0" );
			}
			unset($topicmeta_exists);

			return bb_cache_all_options();
		}

		return false;
	} else {
		foreach ( $results as $options ) {
			wp_cache_set( $options->meta_key, maybe_unserialize($options->meta_value), 'bb_option' );
		}
	}

	$base_options = array(
		'bb_db_version' => 0,
		'name' => __('Please give me a name!'),
		'description' => '',
		'uri_ssl' => '',
		'from_email' => '',
		'secret' => '',
		'page_topics' => '',
		'edit_lock' => '',
		'bb_active_theme' => '',
		'active_plugins' => '',
		'mod_rewrite' => '',
		'datetime_format' => '',
		'date_format' => '',
		'avatars_show' => '',
		'avatars_default' => '',
		'avatars_rating' => '',
		'wp_table_prefix' => '',
		'user_bbdb_name' => '',
		'user_bbdb_user' => '',
		'user_bbdb_password' => '',
		'user_bbdb_host' => '',
		'user_bbdb_charset' => '',
		'user_bbdb_collate' => '',
		'custom_user_table' => '',
		'custom_user_meta_table' => '',
		'wp_siteurl' => '',
		'wp_home' => '',
		'cookiedomain' => '',
		'usercookie' => '',
		'passcookie' => '',
		'authcookie' => '',
		'cookiepath' => '',
		'sitecookiepath' => '',
		'secure_auth_cookie' => '',
		'logged_in_cookie' => '',
		'admin_cookie_path' => '',
		'core_plugins_cookie_path' => '',
		'user_plugins_cookie_path' => '',
		'wp_admin_cookie_path' => '',
		'wp_plugins_cookie_path' => '',
		'enable_xmlrpc' => 0,
		'enable_pingback' => 0,
		'throttle_time' => 30,
		'bb_xmlrpc_allow_user_switching' => false,
		'bp_bbpress_cron' => ''
	);

	foreach ( $base_options as $base_option => $base_option_default ) {
		if ( false === wp_cache_get( $base_option, 'bb_option' ) ) {
			wp_cache_set( $base_option, $base_option_default, 'bb_option' );
		}
	}

	return true;
}

// Can store anything but NULL.
function bb_update_option( $option, $value )
{
	return bb_update_meta( 0, $option, $value, 'option', true );
}

function bb_delete_option( $option, $value = '' )
{
	return bb_delete_meta( 0, $option, $value, 'option', true );
}

/**
 * BB_URI_CONTEXT_* - Bitwise definitions for bb_uri() and bb_get_uri() contexts
 *
 * @since 1.0
 */
define( 'BB_URI_CONTEXT_HEADER',               1 );
define( 'BB_URI_CONTEXT_TEXT',                 2 );
define( 'BB_URI_CONTEXT_A_HREF',               4 );
define( 'BB_URI_CONTEXT_FORM_ACTION',          8 );
define( 'BB_URI_CONTEXT_IMG_SRC',              16 );
define( 'BB_URI_CONTEXT_LINK_STYLESHEET_HREF', 32 );
define( 'BB_URI_CONTEXT_LINK_ALTERNATE_HREF',  64 );
define( 'BB_URI_CONTEXT_LINK_OTHER',           128 );
define( 'BB_URI_CONTEXT_SCRIPT_SRC',           256 );
define( 'BB_URI_CONTEXT_IFRAME_SRC',           512 );
define( 'BB_URI_CONTEXT_BB_FEED',              1024 );
define( 'BB_URI_CONTEXT_BB_USER_FORMS',        2048 );
define( 'BB_URI_CONTEXT_BB_ADMIN',             4096 );
define( 'BB_URI_CONTEXT_BB_XMLRPC',            8192 );
define( 'BB_URI_CONTEXT_WP_HTTP_REQUEST',      16384 );
//define( 'BB_URI_CONTEXT_*',                    32768 );  // Reserved for future definitions
//define( 'BB_URI_CONTEXT_*',                    65536 );  // Reserved for future definitions
//define( 'BB_URI_CONTEXT_*',                    131072 ); // Reserved for future definitions
//define( 'BB_URI_CONTEXT_*',                    262144 ); // Reserved for future definitions
define( 'BB_URI_CONTEXT_AKISMET',              524288 );

/**
 * bb_uri() - echo a URI based on the URI setting
 *
 * @since 1.0
 *
 * @param $resource string The directory, may include a querystring
 * @param $query mixed The query arguments as a querystring or an associative array
 * @param $context integer The context of the URI, use BB_URI_CONTEXT_*
 * @return void
 */
function bb_uri( $resource = null, $query = null, $context = BB_URI_CONTEXT_A_HREF )
{
	echo apply_filters( 'bb_uri', bb_get_uri( $resource, $query, $context ), $resource, $query, $context );
}

/**
 * bb_get_uri() - return a URI based on the URI setting
 *
 * @since 1.0
 *
 * @param $resource string The directory, may include a querystring
 * @param $query mixed The query arguments as a querystring or an associative array
 * @param $context integer The context of the URI, use BB_URI_CONTEXT_*
 * @return string The complete URI
 */
function bb_get_uri( $resource = null, $query = null, $context = BB_URI_CONTEXT_A_HREF )
{
	// If there is a querystring in the resource then extract it
	if ( $resource && strpos( $resource, '?' ) !== false ) {
		list( $_resource, $_query ) = explode( '?', trim( $resource ), 2 );
		$resource = $_resource;
		$_query = wp_parse_args( $_query );
	} else {
		// Make sure $_query is an array for array_merge()
		$_query = array();
	}

	// $query can be an array as well as a string
	if ( $query ) {
		if ( is_string( $query ) ) {
			$query = ltrim( trim( $query ), '?' );
		}
		$query = wp_parse_args( $query );
	}

	// Make sure $query is an array for array_merge()
	if ( !$query ) {
		$query = array();
	}

	// Merge the queries into a single array
	$query = array_merge( $_query, $query );

	// Make sure context is an integer
	if ( !$context || !is_integer( $context ) ) {
		$context = BB_URI_CONTEXT_A_HREF;
	}

	// Get the base URI
	static $_uri;
	if( !isset( $_uri ) ) {
		$_uri = bb_get_option( 'uri' );
	}
	$uri = $_uri;

	// Use https?
	if (
		( ( $context & BB_URI_CONTEXT_BB_USER_FORMS ) && bb_force_ssl_user_forms() ) // Force https when required on user forms
	||
		( ( $context & BB_URI_CONTEXT_BB_ADMIN ) && bb_force_ssl_admin() ) // Force https when required in admin
	) {
		static $_uri_ssl;
		if( !isset( $_uri_ssl ) ) {
			$_uri_ssl = bb_get_option( 'uri_ssl' );
		}
		$uri = $_uri_ssl;
	}

	// Add the directory
	$uri .= ltrim( $resource, '/' );

	// Add the query string to the URI
	$uri = add_query_arg( $query, $uri );

	return apply_filters( 'bb_get_uri', $uri, $resource, $context );
}

/**
 * bb_force_ssl_user_forms() - Whether SSL should be forced when sensitive user data is being submitted.
 *
 * @since 1.0
 *
 * @param string|bool $force Optional.
 * @return bool True if forced, false if not forced.
 */
function bb_force_ssl_user_forms( $force = '' )
{
	static $forced;

	if ( '' != $force ) {
		$old_forced = $forced;
		$forced = $force;
		return $old_forced;
	}

	return $forced;
}

/**
 * bb_force_ssl_admin() - Whether SSL should be forced when using the admin area.
 *
 * @since 1.0
 *
 * @param string|bool $force Optional.
 * @return bool True if forced, false if not forced.
 */
function bb_force_ssl_admin( $force = '' )
{
	static $forced;

	if ( '' != $force ) {
		$old_forced = $forced;
		$forced = $force;
		return $old_forced;
	}

	return $forced;
}

/**
 * bb_ssl_redirect() - Forces redirection to an SSL page when required
 *
 * @since 1.0
 *
 * @return void
 */
function bb_ssl_redirect()
{
	do_action( 'bb_ssl_redirect' );

	$page = bb_get_location();

	if ( BB_IS_ADMIN && !bb_force_ssl_admin() ) {
		return;
	}

	switch ( $page ) {
		case 'login-page':
		case 'register-page':
			if ( !bb_force_ssl_user_forms() ) {
				return;
			}
			break;
		case 'profile-page':
			global $self;
			if ( $self == 'profile-edit.php' ) {
				if ( !bb_force_ssl_user_forms() ) {
					return;
				}
			} else {
				return;
			}
			break;
		default:
			return;
			break;
	}

	if ( bb_is_ssl() ) {
		return;
	}

	if ( 0 === strpos( $_SERVER['REQUEST_URI'], bb_get_option( 'uri' ) ) ) {
		$uri = $_SERVER['REQUEST_URI'];
	} else {
		$uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	$uri = bb_get_option( 'uri_ssl' ) . substr( $uri, strlen( bb_get_option( 'uri' ) ) );

	bb_safe_redirect( $uri );

	return;
}

/**
 * bb_is_ssl() - Determine if SSL is used.
 *
 * @since 1.0
 *
 * @return bool True if SSL, false if not used.
 */
function bb_is_ssl()
{
	return ( 'on' == strtolower( @$_SERVER['HTTPS'] ) ) ? true : false;
}



/* User meta */

function bb_get_usermeta( $user_id, $meta_key )
{
	if ( !$user = bb_get_user( $user_id ) ) {
		return;
	}

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );
	if ( !isset( $user->$meta_key ) ) {
		return;
	}
	return $user->$meta_key;
}

function bb_update_usermeta( $user_id, $meta_key, $meta_value )
{
	return bb_update_meta( $user_id, $meta_key, $meta_value, 'user' );
}

function bb_delete_usermeta( $user_id, $meta_key, $meta_value = '' )
{
	return bb_delete_meta( $user_id, $meta_key, $meta_value, 'user' );
}



/* Forum meta */

function bb_get_forummeta( $forum_id, $meta_key )
{
	if ( !$forum = bb_get_forum( $forum_id ) ) {
		return;
	}

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );
	if ( !isset( $forum->$meta_key ) ) {
		return;
	}
	return $forum->$meta_key;
}

function bb_update_forummeta( $forum_id, $meta_key, $meta_value )
{
	return bb_update_meta( $forum_id, $meta_key, $meta_value, 'forum' );
}

function bb_delete_forummeta( $forum_id, $meta_key, $meta_value = '' )
{
	return bb_delete_meta( $forum_id, $meta_key, $meta_value, 'forum' );
}



/* Topic meta */

function bb_get_topicmeta( $topic_id, $meta_key )
{
	if ( !$topic = get_topic( $topic_id ) ) {
		return;
	}

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );
	if ( !isset($topic->$meta_key) ) {
		return;
	}
	return $topic->$meta_key;
}

function bb_update_topicmeta( $topic_id, $meta_key, $meta_value )
{
	return bb_update_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}

function bb_delete_topicmeta( $topic_id, $meta_key, $meta_value = '' )
{
	return bb_delete_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}



/* Post meta */

function bb_get_postmeta( $post_id, $meta_key )
{
	if ( !$post = bb_get_post( $post_id ) ) {
		return;
	}

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );
	if ( !isset( $post->$meta_key ) ) {
		return;
	}
	return $post->$meta_key;
}

function bb_update_postmeta( $post_id, $meta_key, $meta_value )
{
	return bb_update_meta( $post_id, $meta_key, $meta_value, 'post' );
}

function bb_delete_postmeta( $post_id, $meta_key, $meta_value = '' )
{
	return bb_delete_meta( $post_id, $meta_key, $meta_value, 'post' );
}
