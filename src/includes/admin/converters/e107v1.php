<?php

/**
 * bbPress e107 1.x Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * Implementation of e107 v1.x Forum converter.
 *
 * @since 2.6.0 bbPress (r5352)
 *
 * @link Codex Docs https://codex.bbpress.org/import-forums/e107
 */
class e107v1 extends BBP_Converter_Base {

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

		// Setup smiley URL & path
		$this->bbcode_parser_properties = array(
			'smiley_url' => false,
			'smiley_dir' => false
		);

		/** Forum Section *****************************************************/

		// Old forum id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_id',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_old_forum_id'
		);

		// Forum parent id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum',
			'from_fieldname'  => 'forum_parent',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_parent_id'
		);

		// Forum topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_threads',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_topic_count'
		);

		// Forum reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_replies',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_reply_count'
		);

		// Forum total topic count (Includes unpublished topics, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_threads',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_topic_count'
		);

		// Forum total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_replies',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_reply_count'
		);

		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_name',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_title'
		);

		// Forum slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'forum',
			'from_fieldname'  => 'forum_name',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'  => 'forum',
			'from_fieldname'  => 'forum_description',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);

		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename' => 'forum',
			'from_fieldname' => 'forum_order',
			'to_type'        => 'forum',
			'to_fieldname'   => 'menu_order'
		);

		// Forum type (Category = 0 or Forum > 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum',
			'from_fieldname'  => 'forum_parent',
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
			'default'      => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_date_gmt',
			'default'      => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_modified',
			'default'      => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_modified_gmt',
			'default'      => date('Y-m-d H:i:s')
		);

		/** Topic Section *****************************************************/

		// Old topic id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_id',
			'from_expression' => 'WHERE thread_parent = 0',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_total_replies',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_total_replies',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_total_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		// Note: Uses a custom callback to transform user id from '1.Administrator e107v1' to numeric user id.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_user',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_e107v1_userid'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_thread',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'forum_t',
			'from_fieldname' => 'thread_name',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_name',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic status (Open or Closed, e107 v1.x open = 1 & closed = 0)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_active',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_closed_status_id',
			'callback_method' => 'callback_topic_status'
		);

		// Topic parent forum id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Sticky status (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_s',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_sticky_status_id',
			'callback_method' => 'callback_sticky_status'
		);

		// Topic dates.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_last_active_time',
			'callback_method' => 'callback_datetime'
		);

		/** Tags Section ******************************************************/

		/**
		 * e107 v1.x Forums do not support topic tags out of the box
		 */

		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_id',
			'from_expression' => 'WHERE thread_parent != 0',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_reply_id'
		);

		// Reply parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_forum_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Reply parent topic id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_parent',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply author.
		// Note: Uses a custom callback to transform user id from '1.Administrator e107v1' to numeric user id.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_user',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_e107v1_userid'
		);

		// Reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_thread',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Reply parent topic id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_parent',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Reply dates.
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forum_t',
			'from_fieldname'  => 'thread_datestamp',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);

		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'user',
			'from_fieldname' => 'user_id',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta serialized with salt)
		$this->field_map[] = array(
			'from_tablename'  => 'user',
			'from_fieldname'  => 'user_password',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_password',
			'callback_method' => 'callback_savepass'
		);

		// User password verify class (Stored in usermeta for verifying password)
		$this->field_map[] = array(
			'to_type'      => 'user',
			'to_fieldname' => '_bbp_class',
			'default'      => 'e107v1'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'user',
			'from_fieldname' => 'user_loginname',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename' => 'user',
			'from_fieldname' => 'user_loginname',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'user',
			'from_fieldname' => 'user_email',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_email'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename'  => 'user',
			'from_fieldname'  => 'user_join',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_registered',
			'callback_method' => 'callback_datetime'
		);

		// User display name.
		$this->field_map[] = array(
			'from_tablename' => 'user',
			'from_fieldname' => 'user_name',
			'to_type'        => 'user',
			'to_fieldname'   => 'display_name'
		);

		// Store Signature (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'user',
			'from_fieldname'  => 'user_signature',
			'to_fieldname'    => '_bbp_e107v1_user_sig',
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
	 * This method is to save the salt and password together. That
	 * way when we authenticate it we can get it out of the database
	 * as one value. Array values are auto sanitized by WordPress.
	 */
	public function callback_savepass( $field, $row ) {
		$pass_array = array( 'hash' => $field, 'salt' => $row['salt'] );
		return $pass_array;
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass ) {
		$pass_array = unserialize( $serialized_pass );
		return ( $pass_array['hash'] == md5( md5( $password ). $pass_array['salt'] ) );
	}

	/**
	 * Translate the forum type from e107 v1.x numerics to WordPress's strings.
	 *
	 * @param int $status e107 v1.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_forum_type( $status = 0 ) {
		if ( $status == 0 ) {
			$status = 'category';
		} else {
			$status = 'forum';
		}
		return $status;
	}

	/**
	 * Translate the post status from e107 v1.x numerics to WordPress's strings.
	 *
	 * @param int $status e107 v1.x numeric topic status
	 * @return string WordPress safe
	 */
	public function callback_topic_status( $status = 1 ) {
		switch ( $status ) {
			case 0 :
				$status = 'closed';
				break;

			case 1 :
			default :
				$status = 'publish';
				break;
		}
		return $status;
	}

	/**
	 * Translate the topic sticky status type from e107 v1.x numerics to WordPress's strings.
	 *
	 * @param int $status e107 v1.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_sticky_status( $status = 0 ) {
		switch ( $status ) {
			case 2 :
				$status = 'super-sticky'; // e107 Announcement Sticky 'thread_s = 2'
				break;

			case 1 :
				$status = 'sticky';       // e107 Sticky 'thread_s = 1'
				break;

			case 0 :
			default :
				$status = 'normal';       // e107 normal topic 'thread_s = 0'
				break;
		}
		return $status;
	}

	/**
	 * Verify the topic/reply count.
	 *
	 * @param int $count e107 v1.x topic/reply counts
	 * @return string WordPress safe
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( (int) $count - 1 );
		return $count;
	}

	/**
	 * Override the `callback_user` function in 'converter.php' for custom e107v1 imports
	 *
	 * A mini cache system to reduce database calls to user ID's
	 *
	 * @param string $field
	 * @return string
	 */
	protected function callback_e107v1_userid( $field ) {

		// Strip only the user id from the topic and reply authors
		$field = preg_replace( '/(\d+?)+\.[\S\s]+/', '$1', $field );

		if ( ! isset( $this->map_userid[ $field ] ) ) {
			if ( ! empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_user_id', $field ) );
			} else {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT user_id AS value_id FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_user_id', $field ) );
			}

			if ( ! is_null( $row ) ) {
				$this->map_userid[ $field ] = $row->value_id;
			} else {
				if ( true === $this->convert_users ) {
					$this->map_userid[ $field ] = 0;
				} else {
					$this->map_userid[ $field ] = $field;
				}
			}
		}
		return $this->map_userid[ $field ];
	}

	/**
	 * This callback processes any custom parser.php attributes and custom code with preg_replace
	 */
	protected function callback_html( $field ) {

		// Strips custom e107v1 'magic_url' and 'bbcode_uid' first from $field before parsing $field to parser.php
		$e107v1_markup = $field;
		$e107v1_markup = html_entity_decode( $e107v1_markup );

		// Replace '[blockquote]' with '<blockquote>'
		$e107v1_markup = preg_replace( '/\[blockquote\]/',   '<blockquote>',  $e107v1_markup );
		// Replace '[/blockquote]' with '</blockquote>'
		$e107v1_markup = preg_replace( '/\[\/blockquote\]/', '</blockquote>', $e107v1_markup );

		// Replace '[quote$1=$2]' with '<em>$2 wrote:</em><blockquote>"
		$e107v1_markup = preg_replace( '/\[quote(.*?)=(.*?)\]/', '<em>@$2 wrote:</em><blockquote>', $e107v1_markup );
		// Replace '[/quote$1]' with '</blockquote>"
		$e107v1_markup = preg_replace( '/\[\/quote(.*?)\]/' ,    '</blockquote>',                   $e107v1_markup );

		// Remove '[justify]'
		$e107v1_markup = preg_replace( '/\[justify\]/',   '', $e107v1_markup );
		// Remove '[/justify]'
		$e107v1_markup = preg_replace( '/\[\/justify\]/', '', $e107v1_markup );

		// Replace '[link=(https|http)://$2]$3[/link]' with '<a href="$1://$2">$3</a>'
		$e107v1_markup = preg_replace( '/\[link\=(https|http)\:\/\/(.*?)\](.*?)\[\/link\]/i',  '<a href="$1://$2">$3</a>',  $e107v1_markup );

		// Replace '[list=(decimal|lower-roman|upper-roman|lower-alpha|upper-alpha)]' with '[list]'
		$e107v1_markup = preg_replace( '/\[list\=(decimal|lower-roman|upper-roman|lower-alpha|upper-alpha)\]/i',  '[list]',  $e107v1_markup );

		// Replace '[youtube]$1[/youtube]' with '$1"
		$e107v1_markup = preg_replace( '/\[youtube\](.*?)\[\/youtube\]/', 'https://youtu.be/$1', $e107v1_markup );

		// Now that e107v1 custom HTML has been stripped put the cleaned HTML back in $field
		$field = $e107v1_markup;

		// Parse out any bbCodes in $field with the BBCode 'parser.php'
		return parent::callback_html( $field );
	}
}
