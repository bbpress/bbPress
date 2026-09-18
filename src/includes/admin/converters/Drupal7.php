<?php

/**
 * bbPress Drupal7 Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * Implementation of Drupal v7.x Forum converter.
 *
 * @since 2.5.0 bbPress (r5138)
 *
 * @link Codex Docs https://codex.bbpress.org/import-forums/drupal
 */
class Drupal7 extends BBP_Converter_Base {

	/**
	 * Main Constructor
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Sets up the field mappings
	 */
	public function setup_globals() {

		/** Forum Section *****************************************************/

		// Old forum id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'taxonomy_term_data',
			'from_fieldname' => 'tid',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_old_forum_id'
		);

		// Forum parent id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'taxonomy_term_hierarchy',
			'from_fieldname'  => 'parent',
			'join_tablename'  => 'taxonomy_term_data',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (tid)',
			'from_expression' => 'LEFT JOIN taxonomy_vocabulary AS taxonomy_vocabulary USING (vid) WHERE module = "forum"',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_parent_id'
		);

		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'taxonomy_term_data',
			'from_fieldname' => 'name',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_title'
		);

		// Forum slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'taxonomy_term_data',
			'from_fieldname'  => 'name',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'  => 'taxonomy_term_data',
			'from_fieldname'  => 'description',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);

		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename' => 'taxonomy_term_data',
			'from_fieldname' => 'weight',
			'to_type'        => 'forum',
			'to_fieldname'   => 'menu_order'
		);

		// Forum type (Set a default value 'forum', Stored in postmeta)
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => '_bbp_forum_type',
			'default'      => 'forum'
		);

		// Forum status (Set a default value 'open', Stored in postmeta)
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => '_bbp_status',
			'default'      => 'open'
		);

		// Forum dates.
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_date',
			'default'      => date( 'Y-m-d H:i:s' ) // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_date_gmt',
			'default'      => date( 'Y-m-d H:i:s' ) // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_modified',
			'default'      => date( 'Y-m-d H:i:s' ) // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_modified_gmt',
			'default'      => date( 'Y-m-d H:i:s' ) // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		);

		/** Topic Section *****************************************************/

		// Old topic id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forum_index',
			'from_fieldname' => 'nid',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'comment_count',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'comment_count',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_total_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'tid',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		// Note: We join the 'node' table because 'forum_index' table does not include author id.
		$this->field_map[] = array(
			'from_tablename'  => 'node',
			'from_fieldname'  => 'uid',
			'join_tablename'  => 'forum_index',
			'join_type'       => 'INNER',
			'join_expression' => 'ON node.nid = forum_index.nid',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic author name (Stored in postmeta as _bbp_anonymous_name)
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => '_bbp_old_topic_author_name_id',
			'default'      => 'Anonymous'
		);

		// Is the topic anonymous (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'node',
			'from_fieldname'  => 'uid',
			'join_tablename'  => 'forum_index',
			'join_type'       => 'INNER',
			'join_expression' => 'ON node.nid = forum_index.nid',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_is_topic_anonymous_id',
			'callback_method' => 'callback_check_anonymous'
		);

		// Topic content.
		// Note: We join the 'field_data_body' table because 'node' or 'forum_index' table does not include topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'field_data_body',
			'from_fieldname'  => 'body_value',
			'join_tablename'  => 'node',
			'join_type'       => 'INNER',
			'join_expression' => 'ON field_data_body.revision_id = node.vid',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'forum_index',
			'from_fieldname' => 'title',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'title',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic parent forum id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'tid',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Topic status (Publish or Unpublished, Drupal v7.x publish = 1, pending = 0)
		$this->field_map[] = array(
			'from_tablename'  => 'node',
			'from_fieldname'  => 'status',
			'join_tablename'  => 'forum_index',
			'join_type'       => 'INNER',
			'join_expression' => 'ON node.nid = forum_index.nid',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_status',
			'callback_method' => 'callback_status'
		);

		// Sticky status (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'sticky',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_sticky_status_id',
			'callback_method' => 'callback_sticky_status'
		);

		// Topic dates.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'created',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'created',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'last_comment_timestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'last_comment_timestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_index',
			'from_fieldname'  => 'last_comment_timestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_last_active_time',
			'callback_method' => 'callback_datetime'
		);

		// Topic status (Drupal v7.x Comments Enabled no = 0, closed = 1 & open = 2)
		$this->field_map[] = array(
			'from_tablename'  => 'node',
			'from_fieldname'  => 'comment',
			'join_tablename'  => 'forum_index',
			'join_type'       => 'INNER',
			'join_expression' => 'ON node.nid = forum_index.nid',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_closed_status_id',
			'callback_method' => 'callback_topic_status'
		);

		/** Tags Section ******************************************************/

		// Topic id.
		$this->field_map[] = array(
			'from_tablename'  => 'field_data_field_tags',
			'from_fieldname'  => 'entity_id',
			'to_type'         => 'tags',
			'to_fieldname'    => 'objectid',
			'callback_method' => 'callback_topicid'
		);

		// Taxonomy ID.
		$this->field_map[] = array(
			'from_tablename'  => 'field_data_field_tags',
			'from_fieldname'  => 'field_tags_tid',
			'to_type'         => 'tags',
			'to_fieldname'    => 'taxonomy'
		);

		// Term name.
		$this->field_map[] = array(
			'from_tablename'  => 'taxonomy_term_data',
			'from_fieldname'  => 'name',
			'join_tablename'  => 'field_data_field_tags',
			'join_type'       => 'INNER',
			'join_expression' => 'ON field_tags_tid = taxonomy_term_data.tid',
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
		);

		// Term slug.
		$this->field_map[] = array(
			'from_tablename'  => 'taxonomy_term_data',
			'from_fieldname'  => 'name',
			'join_tablename'  => 'field_data_field_tags',
			'join_type'       => 'INNER',
			'join_expression' => 'ON field_tags_tid = taxonomy_term_data.tid',
			'to_type'         => 'tags',
			'to_fieldname'    => 'slug',
			'callback_method' => 'callback_slug'
		);

		// Term description.
		$this->field_map[] = array(
			'from_tablename'  => 'taxonomy_term_data',
			'from_fieldname'  => 'description',
			'join_tablename'  => 'field_data_field_tags',
			'join_type'       => 'INNER',
			'join_expression' => 'ON field_tags_tid = taxonomy_term_data.tid',
			'to_type'         => 'tags',
			'to_fieldname'    => 'description'
		);

		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'comment',
			'from_fieldname' => 'cid',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_old_reply_id'
		);

		// Reply parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'nid',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Reply parent topic id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'nid',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply parent reply id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'pid',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_reply_to_id'
		);

		// Reply author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'comment',
			'from_fieldname' => 'hostname',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_author_ip'
		);

		// Reply author.
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'uid',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Reply status (Publish or Unpublished, Drupal v7.x publish = 1, pending = 0)
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'status',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_status',
			'callback_method' => 'callback_status'
		);

		// Reply author name (Stored in postmeta as _bbp_anonymous_name)
		$this->field_map[] = array(
			'from_tablename' => 'comment',
			'from_fieldname' => 'name',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_old_reply_author_name_id'
		);

		// Is the reply anonymous  (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'uid',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_is_reply_anonymous_id',
			'callback_method' => 'callback_check_anonymous'
		);

		// Reply content.
		// Note: We join the 'field_data_comment_body' table because 'comment' table does not include reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'field_data_comment_body',
			'from_fieldname'  => 'comment_body_value',
			'join_tablename'  => 'comment',
			'join_type'       => 'INNER',
			'join_expression' => 'ON field_data_comment_body.entity_id = comment.cid',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Reply parent topic id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'nid',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Reply dates.
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'created',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'created',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'changed',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'comment',
			'from_fieldname'  => 'changed',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);

		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		// Don't import user uid = 0, this is Drupal 7's guest user
		$this->field_map[] = array(
			'from_tablename'  => 'users',
			'from_fieldname'  => 'uid',
			'from_expression' => 'WHERE uid != 0',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta as a serialized array)
		$this->field_map[] = array(
			'from_tablename'  => 'users',
			'from_fieldname'  => 'pass',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_password',
			'callback_method' => 'callback_savepass'
		);

		// User password verify class (Stored in usermeta for verifying password)
		$this->field_map[] = array(
			'to_type'      => 'user',
			'to_fieldname' => '_bbp_class',
			'default'      => 'Drupal7'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'name',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'name',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'mail',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_email'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename'  => 'users',
			'from_fieldname'  => 'created',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_registered',
			'callback_method' => 'callback_datetime'
		);

		// Store Signature (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'users',
			'from_fieldname'  => 'signature',
			'to_fieldname'    => '_bbp_drupal7_user_sig',
			'to_type'         => 'user',
			'callback_method' => 'callback_html'
		);
	}

	/**
	 * This method allows us to indicates what is or is not converted for each
	 * converter.
	 */
	public function info() {
		return '';
	}

	/**
	 * Store the Drupal password hash in a serialized array.
	 *
	 * Array values are automatically serialized by WordPress.
	 */
	public function callback_savepass( $field, $row ) {
		return array( 'hash' => $field );
	}

	/**
	 * Upgrade password metadata written by earlier Drupal 7 imports.
	 *
	 * Earlier imports stored the hash without serializing it. New imports use
	 * the parent callback and the serialized format from callback_savepass().
	 *
	 * @param string      $username    WordPress user login.
	 * @param string      $password    Unslashed password for Drupal.
	 * @param string|null $wp_password Optional slashed password for WordPress.
	 */
	public function callback_pass( $username = '', $password = '', $wp_password = null ) {
		$user = get_user_by( 'login', $username );

		if ( ! empty( $user ) && '' === $user->user_pass ) {
			$stored_hash = get_user_meta( $user->ID, '_bbp_password', true );

			if ( is_string( $stored_hash ) && $this->is_drupal_hash( $stored_hash ) ) {
				if ( $this->authenticate_pass( $password, serialize( array( 'hash' => $stored_hash ) ) ) ) {
					$this->upgrade_user_pass( $user->ID, is_null( $wp_password ) ? $password : $wp_password );
				}

				return;
			}
		}

		parent::callback_pass( $username, $password, $wp_password );
	}

	/**
	 * Upgrade a Drupal hash copied directly into wp_users by an old import.
	 *
	 * @param WP_User     $user        WordPress user.
	 * @param string      $password    Unslashed password for Drupal.
	 * @param string|null $wp_password Optional slashed password for WordPress.
	 */
	public function callback_user_pass( $user, $password, $wp_password = null ) {
		$wp_password = is_null( $wp_password ) ? $password : $wp_password;

		// Do not replace a password that WordPress or a password plugin already
		// recognizes, regardless of the stored hash prefix.
		if ( ! ( $user instanceof WP_User ) || wp_check_password( $wp_password, $user->user_pass, $user->ID ) || ! $this->is_drupal_hash( $user->user_pass, false ) ) {
			return;
		}

		if ( $this->authenticate_pass( $password, serialize( array( 'hash' => $user->user_pass ) ) ) ) {
			$this->upgrade_user_pass( $user->ID, $wp_password );
		}
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass ) {

		// Unserialize the password, with safeguards
		$pass_array = $this->unserialize_pass( $serialized_pass );

		// Bail if missing or invalid values
		if ( ! is_string( $password ) || ! is_array( $pass_array ) || ! isset( $pass_array['hash'] ) || ! is_string( $pass_array['hash'] ) ) {
			return false;
		}

		$stored_hash = $pass_array['hash'];

		// Drupal 6 passwords upgraded to Drupal 7 use an MD5 pre-hash
		if ( 0 === strpos( $stored_hash, 'U$' ) ) {
			$stored_hash = substr( $stored_hash, 1 );
			$password    = md5( $password );
		}

		switch ( substr( $stored_hash, 0, 3 ) ) {
			case '$S$' :
				$hash = $this->hash_password( 'sha512', $password, $stored_hash );
				break;

			case '$H$' :
			case '$P$' :
				$hash = $this->hash_password( 'md5', $password, $stored_hash );
				break;

			default :
				return false;
		}

		return is_string( $hash ) && hash_equals( $stored_hash, $hash );
	}

	/**
	 * Hash a password using Drupal 7's portable password algorithm.
	 *
	 * @param string $algorithm Hash algorithm.
	 * @param string $password  Plain-text password.
	 * @param string $setting   Stored hash or hash setting.
	 * @return string|bool Password hash on success, false on failure.
	 */
	private function hash_password( $algorithm, $password, $setting ) {
		if ( strlen( $password ) > 512 ) {
			return false;
		}

		$setting = substr( $setting, 0, 12 );
		if ( 12 !== strlen( $setting ) || '$' !== $setting[0] || '$' !== $setting[2] ) {
			return false;
		}

		$characters = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$count_log2 = strpos( $characters, $setting[3] );
		$salt       = substr( $setting, 4, 8 );

		if ( false === $count_log2 || $count_log2 < 7 || $count_log2 > 30 || 8 !== strlen( $salt ) ) {
			return false;
		}

		$count = 1 << $count_log2;
		$hash  = hash( $algorithm, $salt . $password, true );

		do {
			$hash = hash( $algorithm, $hash . $password, true );
		} while ( --$count );

		$output   = $setting . $this->base64_encode_password( $hash, strlen( $hash ) );
		$expected = 12 + ceil( ( 8 * strlen( $hash ) ) / 6 );

		return ( strlen( $output ) === (int) $expected )
			? substr( $output, 0, 55 )
			: false;
	}

	/**
	 * Encode bytes using Drupal 7's password base64 alphabet.
	 *
	 * @param string $input Bytes to encode.
	 * @param int    $count Number of bytes to encode.
	 * @return string Encoded bytes.
	 */
	private function base64_encode_password( $input, $count ) {
		$output     = '';
		$index      = 0;
		$characters = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		do {
			$value   = ord( $input[ $index++ ] );
			$output .= $characters[ $value & 0x3f ];
			if ( $index < $count ) {
				$value |= ord( $input[ $index ] ) << 8;
			}
			$output .= $characters[ ( $value >> 6 ) & 0x3f ];
			if ( $index++ >= $count ) {
				break;
			}
			if ( $index < $count ) {
				$value |= ord( $input[ $index ] ) << 16;
			}
			$output .= $characters[ ( $value >> 12 ) & 0x3f ];
			if ( $index++ >= $count ) {
				break;
			}
			$output .= $characters[ ( $value >> 18 ) & 0x3f ];
		} while ( $index < $count );

		return $output;
	}

	/**
	 * Determine whether a stored value is a Drupal password hash.
	 *
	 * @param string $hash          Stored password hash.
	 * @param bool   $allow_phpass Whether to include WordPress-compatible $P$.
	 * @return bool True when the value has a supported Drupal hash prefix.
	 */
	private function is_drupal_hash( $hash, $allow_phpass = true ) {
		if ( ! is_string( $hash ) ) {
			return false;
		}

		// A $P$ hash in metadata is unambiguously from Drupal, while one copied
		// into user_pass is also a WordPress-compatible password hash.
		$prefixes = $allow_phpass
			? array( '$S$', '$H$', '$P$', 'U$' )
			: array( '$S$', '$H$', 'U$' );

		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $hash, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace a verified Drupal hash with a WordPress password.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $password Password to hash for WordPress.
	 */
	private function upgrade_user_pass( $user_id, $password ) {
		wp_set_password( $password, $user_id );
		delete_user_meta( $user_id, '_bbp_password' );
		delete_user_meta( $user_id, '_bbp_class' );
	}

	/**
	 * Translate the post status from Drupal v7.x numerics to WordPress's
	 * strings.
	 *
	 * @param int $status Drupal v7.x numeric post status
	 * @return string WordPress safe
	 */
	public function callback_status( $status = 1 ) {
		switch ( $status ) {
			case 0 :
				$status = 'pending'; // bbp_get_pending_status_id()
				break;

			case 1  :
			default :
				$status = 'publish'; // bbp_get_public_status_id()
				break;
		}
		return $status;
	}

	/**
	 * Translate the post status from Drupal v7.x numerics to WordPress's strings.
	 *
	 * @param int $status Drupal v7.x numeric topic status
	 * @return string WordPress safe
	 */
	public function callback_topic_status( $status = 2 ) {
		switch ( $status ) {
			case 1 :
				$status = 'closed';
				break;

			case 2  :
			default :
				$status = 'publish';
				break;
		}
		return $status;
	}

	/**
	 * Translate the topic sticky status type from Drupal v7.x numerics to WordPress's strings.
	 *
	 * @param int $status Drupal v7.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_sticky_status( $status = 0 ) {
		switch ( $status ) {
			case 1 :
				$status = 'sticky'; // Drupal Sticky 'topic_sticky = 1'
				break;

			case 0  :
			default :
				$status = 'normal'; // Drupal Normal Topic 'sticky = 0'
				break;
		}
		return $status;
	}

	/**
	 * Verify the topic/reply count.
	 *
	 * @param int $count Drupal v7.x topic/reply counts
	 * @return string WordPress safe
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( (int) $count - 1 );
		return $count;
	}
}
