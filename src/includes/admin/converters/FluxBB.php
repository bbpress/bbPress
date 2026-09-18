<?php

/**
 * bbPress FluxBB Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * Implementation of FluxBB Forum converter.
 *
 * @since 2.5.0 bbPress (r5138)
 *
 * @link Codex Docs https://codex.bbpress.org/import-forums/fluxbb
 */
class FluxBB extends BBP_Converter_Base {

	/**
	 * @var bool Whether source-dependent fields have been set up.
	 * @since 2.6.18
	 */
	private $source_fields_setup = false;

	/**
	 * Main Constructor
	 *
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
			'from_tablename' => 'forums',
			'from_fieldname' => 'id',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_old_forum_id'
		);

		// Forum topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'num_topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_topic_count'
		);

		// Forum reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'num_posts',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_reply_count'
		);

		// Forum total topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'num_topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_topic_count'
		);

		// Forum total reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'num_posts',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_reply_count'
		);

		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_name',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_title'
		);

		// Forum slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'forums',
			'from_fieldname'  => 'forum_name',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'  => 'forums',
			'from_fieldname'  => 'forum_desc',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);

		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'disp_position',
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

		/** Forum Subscriptions Section ***************************************/

		// Subscribed forum ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_subscriptions',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'forum_subscriptions',
			'to_fieldname'    => '_bbp_forum_subscriptions'
		);

		// Subscribed user ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_subscriptions',
			'from_fieldname'  => 'user_id',
			'to_type'         => 'forum_subscriptions',
			'to_fieldname'    => 'user_id',
			'callback_method' => 'callback_userid'
		);

		/** Topic Section *****************************************************/

		// Old topic id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'id',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'num_replies',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'num_replies',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_total_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		// Note: We join the 'posts' table because 'topics' table does include numeric user id.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'poster_id',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'ON topics.first_post_id = posts.id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic Author ip (Stored in postmeta)
		// Note: We join the 'posts' table because 'topics' table does not include author IP addresses.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'poster_ip',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'ON topics.first_post_id = posts.id',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_author_ip'
		);

		// Topic content.
		// Note: We join the 'posts' table because 'topics' table does not include topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'message',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'ON topics.first_post_id = posts.id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'subject',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'subject',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic parent forum id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Sticky status (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'sticky',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_sticky_status_id',
			'callback_method' => 'callback_sticky_status'
		);

		// Topic dates.
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'posted',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'posted',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'posted',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'posted',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'posted',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_last_active_time',
			'callback_method' => 'callback_datetime'
		);

		// Topic status (Open = 0 or Closed = 1, FluxBB v1.5.3)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'closed',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_closed_status_id',
			'callback_method' => 'callback_topic_status'
		);

		/** Tags Section ******************************************************/

		/**
		 * FluxBB v1.5.3 Forums do not support topic tags out of the box
		 */

		/** Topic Subscriptions Section ***************************************/

		// Subscribed topic ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topic_subscriptions',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'topic_subscriptions',
			'to_fieldname'    => '_bbp_subscriptions'
		);

		// Subscribed user ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topic_subscriptions',
			'from_fieldname'  => 'user_id',
			'to_type'         => 'topic_subscriptions',
			'to_fieldname'    => 'user_id',
			'callback_method' => 'callback_userid'
		);

		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_reply_id'
		);

		// Reply parent forum id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Reply parent topic id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'posts',
			'from_fieldname' => 'poster_ip',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_author_ip'
		);

		// Reply author.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'poster_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'message',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Reply parent topic id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Reply dates.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'posted',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'posted',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'posted',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'posted',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);

		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'id',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta serialized with salt)
		$this->field_map[] = array(
			'from_tablename'  => 'users',
			'from_fieldname'  => 'password',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_password',
			'callback_method' => 'callback_savepass'
		);

		// User password verify class (Stored in usermeta for verifying password)
		$this->field_map[] = array(
			'to_type'      => 'user',
			'to_fieldname' => '_bbp_class',
			'default'      => 'FluxBB'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'username',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'username',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'email',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_email'
		);

		// User homepage.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'url',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_url'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename'  => 'users',
			'from_fieldname'  => 'registered',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_registered',
			'callback_method' => 'callback_datetime'
		);

		// User display name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'realname',
			'to_type'        => 'user',
			'to_fieldname'   => 'display_name'
		);

		// User AIM (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'aim',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_aim'
		);

		// User Yahoo (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'yahoo',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_yim'
		);

	// Store Jabber
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'jabber',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_jabber'
		);

		// Store ICQ (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'icq',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_icq'
		);

		// Store MSN (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'msn',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_msn'
		);

		// Store Location (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'location',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_location'
		);

		// Store Signature (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'signature',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_sig',
			'callback_method' => 'callback_html'
		);

		// Store Admin Note (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'admin_note',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_fluxbb_user_admin_note'
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
	 * Setup the optional legacy salt field after connecting to the source.
	 *
	 * @since 2.6.18
	 */
	protected function setup_source_fields() {
		if ( $this->source_fields_setup ) {
			return;
		}

		$this->source_fields_setup = true;

		if ( $this->source_has_salt() ) {
			$this->field_map[] = array(
				'from_tablename' => 'users',
				'from_fieldname' => 'salt',
				'to_type'        => 'user',
				'to_fieldname'   => ''
			);
		}
	}

	/**
	 * Check whether the source users table retains the legacy FluxBB salt field.
	 *
	 * @since 2.6.18
	 *
	 * @return bool True when the salt field exists, false otherwise.
	 */
	private function source_has_salt() {
		if ( empty( $this->opdb ) ) {
			return false;
		}

		return (bool) $this->opdb->get_var( "SHOW COLUMNS FROM {$this->opdb->prefix}users LIKE 'salt'" );
	}

	/**
	 * Save the salt and password together. Pre-slash the salt because WordPress
	 * removes one layer of slashes when storing user metadata.
	 */
	public function callback_savepass( $field, $row ) {
		$pass_array = array(
			'hash' => $field,
			'salt' => isset( $row['salt'] ) ? wp_slash( (string) $row['salt'] ) : ''
		);

		return $pass_array;
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass ) {

		// Unserialize the password, with safeguards
		$pass_array = $this->unserialize_pass( $serialized_pass );

		// Bail if missing or invalid values
		if ( ! is_array( $pass_array ) || ! isset( $pass_array['hash'] ) || ! array_key_exists( 'salt', $pass_array ) || ! is_string( $pass_array['hash'] ) || ( ! is_string( $pass_array['salt'] ) && ! is_null( $pass_array['salt'] ) ) ) {
			return false;
		}

		$salt = (string) $pass_array['salt'];

		// Salted SHA-1 from FluxBB 1.3
		if ( ! empty( $salt ) ) {
			return hash_equals(
				$pass_array['hash'],
				sha1( $salt . sha1( $password ) )
			);
		}

		// Unsalted MD5 from FluxBB 1.2
		if ( 40 !== strlen( $pass_array['hash'] ) ) {
			return hash_equals(
				$pass_array['hash'],
				md5( $password )
			);
		}

		// SHA-1 from FluxBB 1.4 and newer
		return hash_equals(
			$pass_array['hash'],
			sha1( $password )
		);
	}

	/**
	 * Translate the post status from FluxBB v1.5.3 numerics to WordPress's strings.
	 *
	 * @param int $status FluxBB v1.5.3 numeric topic status
	 * @return string WordPress safe
	 */
	public function callback_topic_status( $status = 0 ) {
		switch ( $status ) {
			case 1 :
				$status = 'closed';
				break;

			case 0  :
			default :
				$status = 'publish';
				break;
		}
		return $status;
	}

	/**
	 * Translate the topic sticky status type from FluxBB v1.5.3 numerics to WordPress's strings.
	 *
	 * @param int $status FluxBB v1.5.3 numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_sticky_status( $status = 0 ) {
		switch ( $status ) {
			case 1 :
				$status = 'sticky';       // FluxBB Sticky 'sticky = 1'
				break;

			case 0  :
			default :
				$status = 'normal';       // FluxBB Normal Topic 'sticky = 0'
				break;
		}
		return $status;
	}

	/**
	 * Verify the topic/reply count.
	 *
	 * @param int $count FluxBB v1.5.3 topic/reply counts
	 * @return string WordPress safe
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( (int) $count - 1 );
		return $count;
	}
}
