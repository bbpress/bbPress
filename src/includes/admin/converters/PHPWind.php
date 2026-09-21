<?php

/**
 * bbPress PHPWind Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * Implementation of PHPWind Forum converter.
 *
 * @since 2.5.0 bbPress (r5142)
 *
 * @link Codex Docs https://codex.bbpress.org/import-forums/phpwind
 */
class PHPWind extends BBP_Converter_Base {

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
			'from_tablename' => 'bbs_forum',
			'from_fieldname' => 'fid',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_old_forum_id'
		);

		// Forum parent id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum',
			'from_fieldname'  => 'parentid',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_parent_id'
		);

		// Forum topic count (Stored in postmeta)
		// Note: We join the 'bbs_forum_statistics' table because 'bbs_forum' table does not include topic and reply counts.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum_statistics',
			'from_fieldname'  => 'threads',
			'join_tablename'  => 'bbs_forum',
			'join_type'       => 'LEFT',
			'join_expression' => 'USING (fid)',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_topic_count'
		);

		// Forum reply count (Stored in postmeta)
		// Note: We join the 'bbs_forum_statistics' table because 'bbs_forum' table does not include topic and reply counts.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum_statistics',
			'from_fieldname'  => 'posts',
			'join_tablename'  => 'bbs_forum',
			'join_type'       => 'LEFT',
			'join_expression' => 'USING (fid)',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_reply_count'
		);

		// Forum total topic count (Stored in postmeta)
		// Note: We join the 'bbs_forum_statistics' table because 'bbs_forum' table does not include topic and reply counts.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum_statistics',
			'from_fieldname'  => 'threads',
			'join_tablename'  => 'bbs_forum',
			'join_type'       => 'LEFT',
			'join_expression' => 'USING (fid)',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_total_topic_count'
		);

		// Forum total reply count (Stored in postmeta)
		// Note: We join the 'bbs_forum_statistics' table because 'bbs_forum' table does not include topic and reply counts.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum_statistics',
			'from_fieldname'  => 'posts',
			'join_tablename'  => 'bbs_forum',
			'join_type'       => 'LEFT',
			'join_expression' => 'USING (fid)',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_total_reply_count'
		);

		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'bbs_forum',
			'from_fieldname' => 'name',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_title'
		);

		// Forum slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum',
			'from_fieldname'  => 'name',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum',
			'from_fieldname'  => 'descrip',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);

		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename' => 'bbs_forum',
			'from_fieldname' => 'vieworder',
			'to_type'        => 'forum',
			'to_fieldname'   => 'menu_order'
		);

		// Forum type (Category = category or Forum = forum, sub or sub2,  Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_forum',
			'from_fieldname'  => 'type',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_forum_type',
			'callback_method' => 'callback_forum_type'
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
			'from_tablename' => 'bbs_threads',
			'from_fieldname' => 'tid',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'replies',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'replies',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_total_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'fid',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'created_userid',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic Author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'created_ip',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_author_ip'
		);

		// Topic content.
		// Note: We join the 'bbs_threads_content' table because 'bbs_threads' table does not have content.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads_content',
			'from_fieldname'  => 'content',
			'join_tablename'  => 'bbs_threads',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (tid)',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'bbs_threads',
			'from_fieldname' => 'subject',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'subject',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic parent forum id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'fid',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Topic dates.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'created_time',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'created_time',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'lastpost_time',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'lastpost_time',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'lastpost_time',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_last_active_time',
			'callback_method' => 'callback_datetime'
		);

		// Topic status (PHPWind v9.x bitmask: 1=locked & 2=closed)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_threads',
			'from_fieldname'  => 'tpcstatus',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_closed_status_id',
			'callback_method' => 'callback_topic_status'
		);

		/** Tags Section ******************************************************/

		/**
		 * PHPWind v9.x Forums do not support topic tags out of the box
		 */

		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'bbs_posts',
			'from_fieldname' => 'pid',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_old_reply_id'
		);

		// Reply parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'fid',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Reply parent topic id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'tid',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'bbs_posts',
			'from_fieldname' => 'created_ip',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_author_ip'
		);

		// Reply author.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'created_userid',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'content',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Reply parent topic id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'tid',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Reply dates.
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'created_time',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'created_time',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'created_time',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbs_posts',
			'from_fieldname'  => 'created_time',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);

		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'windid_user',
			'from_fieldname' => 'uid',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta serialized with salt)
		$this->field_map[] = array(
			'from_tablename'  => 'windid_user',
			'from_fieldname'  => 'password',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_password',
			'callback_method' => 'callback_savepass'
		);

		// Store old user salt (This is only used for the SELECT row info for the above password save)
		$this->field_map[] = array(
			'from_tablename' => 'windid_user',
			'from_fieldname' => 'salt',
			'to_type'        => 'user',
			'to_fieldname'   => ''
		);
		// User password verify class (Stored in usermeta for verifying password)
		$this->field_map[] = array(
			'to_type'      => 'user',
			'to_fieldname' => '_bbp_class',
			'default'      => 'PHPWind'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'windid_user',
			'from_fieldname' => 'username',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename' => 'windid_user',
			'from_fieldname' => 'username',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'windid_user',
			'from_fieldname' => 'email',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_email'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename'  => 'windid_user',
			'from_fieldname'  => 'regdate',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_registered',
			'callback_method' => 'callback_datetime'
		);

		// User display name.
		$this->field_map[] = array(
			'from_tablename'  => 'windid_user_info',
			'from_fieldname'  => 'realname',
			'join_tablename'  => 'windid_user',
			'join_type'       => 'LEFT',
			'join_expression' => 'ON windid_user.uid = windid_user_info.uid',
			'to_type'         => 'user',
			'to_fieldname'    => 'display_name'
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
	 * Save the salt and password together so both values are available during
	 * authentication. Pre-slash the salt because WordPress removes one layer of
	 * slashes when storing user metadata.
	 */
	public function callback_savepass( $field, $row ) {
		$pass_array = array(
			'hash' => $field,
			'salt' => wp_slash( isset( $row['salt'] ) ? (string) $row['salt'] : '' )
		);

		return $pass_array;
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass ) {
		if ( ! is_string( $password ) || ! is_string( $serialized_pass ) ) {
			return false;
		}

		// Unserialize the password, with safeguards
		$pass_array = $this->unserialize_pass( $serialized_pass );

		// Bail if missing values
		if ( ! is_array( $pass_array ) || ! isset( $pass_array['hash'], $pass_array['salt'] ) || ! is_string( $pass_array['hash'] ) || ! is_string( $pass_array['salt'] ) ) {
			return false;
		}

		// Return comparison
		return hash_equals(
			$pass_array['hash'],
			md5( md5( $password ) . $pass_array['salt'] )
		);
	}

	/**
	 * Upgrade password metadata written by earlier PHPWind imports.
	 *
	 * Earlier imports stored PHPWind's random synchronization token instead of
	 * its login hash and salt. New imports use the parent callback and serialized
	 * metadata containing the login hash and salt.
	 *
	 * @param string      $username    WordPress user login.
	 * @param string      $password    Unslashed password for PHPWind.
	 * @param string|null $wp_password Optional slashed password for WordPress.
	 */
	public function callback_pass( $username = '', $password = '', $wp_password = null ) {
		$user = get_user_by( 'login', $username );

		if ( ! empty( $user ) && '' === $user->user_pass ) {
			$stored_hash = get_user_meta( $user->ID, '_bbp_password', true );

			if ( is_string( $stored_hash ) && preg_match( '/^[a-f0-9]{32}$/i', $stored_hash ) ) {
				$serialized_pass = $this->get_source_password( $user, $stored_hash );

				if ( is_string( $serialized_pass ) && $this->authenticate_pass( $password, $serialized_pass ) ) {
					$this->upgrade_user_pass( $user->ID, is_null( $wp_password ) ? $password : $wp_password );
				}

				return;
			}
		}

		parent::callback_pass( $username, $password, $wp_password );
	}

	/**
	 * Upgrade a PHPWind hash copied directly into wp_users by an old import.
	 *
	 * Those imports stored PHPWind's random synchronization token, not its login
	 * hash. Match that token against the retained source row before retrieving
	 * the login hash and salt from WindID.
	 *
	 * @param WP_User     $user        WordPress user.
	 * @param string      $password    Unslashed password for PHPWind.
	 * @param string|null $wp_password Optional slashed password for WordPress.
	 */
	public function callback_user_pass( $user, $password, $wp_password = null ) {
		if ( ! ( $user instanceof WP_User ) || ! is_string( $password ) || ! preg_match( '/^[a-f0-9]{32}$/i', $user->user_pass ) ) {
			return;
		}

		$wp_password = is_null( $wp_password ) ? $password : $wp_password;

		// Do not replace a password that WordPress or a password plugin already
		// recognizes, regardless of the stored hash format.
		if ( wp_check_password( $wp_password, $user->user_pass, $user->ID ) ) {
			return;
		}

		$serialized_pass = $this->get_source_password( $user, $user->user_pass );

		if ( is_string( $serialized_pass ) && $this->authenticate_pass( $password, $serialized_pass ) ) {
			$this->upgrade_user_pass( $user->ID, $wp_password );
		}
	}

	/**
	 * Retrieve login metadata for a token written by an earlier import.
	 *
	 * @param WP_User $user        WordPress user.
	 * @param string  $stored_hash Random token stored by the earlier import.
	 * @return string|bool Serialized password metadata, or false on failure.
	 */
	private function get_source_password( $user, $stored_hash ) {
		$old_user_id = (int) get_user_meta( $user->ID, '_bbp_old_user_id', true );
		if ( $old_user_id < 1 || get_transient( 'bbp_phpwind_source_connection_failed' ) ) {
			return false;
		}

		// Block concurrent login attempts while the source connection or query is pending.
		// Keep the backoff when either fails, so an unavailable source cannot stall
		// every login attempt for an imported username.
		set_transient( 'bbp_phpwind_source_connection_failed', 1, 5 * MINUTE_IN_SECONDS );

		if ( ! $this->opdb->db_connect( false ) ) {
			return false;
		}

		$legacy_table = $this->opdb->prefix . 'user';
		$windid_table = $this->opdb->prefix . 'windid_user';
		$source_user = $this->opdb->get_row(
			$this->opdb->prepare( "SELECT legacy.password AS legacy_password, windid.password, windid.salt FROM {$legacy_table} AS legacy INNER JOIN {$windid_table} AS windid ON windid.uid = legacy.uid WHERE legacy.uid = %d LIMIT 1", $old_user_id ),
			ARRAY_A
		);
		if ( empty( $this->opdb->last_error ) ) {
			delete_transient( 'bbp_phpwind_source_connection_failed' );
		}

		if ( ! is_array( $source_user ) || ! isset( $source_user['legacy_password'], $source_user['password'], $source_user['salt'] ) || ! is_string( $source_user['legacy_password'] ) || ! is_string( $source_user['password'] ) || ! is_string( $source_user['salt'] ) || ! hash_equals( $stored_hash, $source_user['legacy_password'] ) ) {
			return false;
		}

		return serialize(
			array(
				'hash' => $source_user['password'],
				'salt' => $source_user['salt'],
			)
		);
	}

	/**
	 * Replace a verified PHPWind hash with a WordPress password.
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
	 * Translate the forum type from PHPWind v9.x Capitalised case to WordPress's non-capatilise case strings.
	 *
	 * @param int $status PHPWind v9.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_forum_type( $status = 1 ) {
		switch ( $status ) {
			case 'category' :
				$status = 'category';
				break;

			case 'sub' :
				$status = 'forum';
				break;

			case 'sub2' :
				$status = 'forum';
				break;

			case 'forum' :
			default :
				$status = 'forum';
				break;
		}
		return $status;
	}

	/**
	 * Translate the post status from PHPWind v9.x numerics to WordPress's strings.
	 *
	 * @param int $status PHPWind v9.x numeric topic status
	 * @return string WordPress topic status.
	 */
	public function callback_topic_status( $status = 0 ) {
		// PHPWind stores locked (0b0001) and closed (0b0010) flags in tpcstatus. bbPress represents either state as closed.
		$locked_or_closed_mask = 0b0011;

		return ( (int) $status & $locked_or_closed_mask ) ? 'closed' : 'publish';
	}

	/**
	 * Verify the topic/reply count.
	 *
	 * @param int $count PHPWind v9.x topic/reply counts
	 * @return int Non-negative reply count.
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( $count );
		return $count;
	}
}
