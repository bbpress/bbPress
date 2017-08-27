<?php

/**
 * bbPress Vanilla Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * Implementation of Vanilla 2.0.18.1 Converter
 *
 * @since 2.3.0 bbPress (r4717)
 *
 * @link Codex Docs https://codex.bbpress.org/import-forums/vanilla
 */
class Vanilla extends BBP_Converter_Base {

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
			'from_tablename'  => 'Category',
			'from_fieldname'  => 'CategoryID',
			'from_expression' => 'WHERE Category.CategoryID > 0',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_id'
		);

		// Forum parent id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Category',
			'from_fieldname'  => 'ParentCategoryID',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_parent_id',
			'callback_method' => 'callback_forum_parent'
		);

		// Forum topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'CountDiscussions',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_topic_count'
		);

		// Forum reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'CountComments',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_reply_count'
		);

		// Forum total topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'CountDiscussions',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_topic_count'
		);

		// Forum total reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'CountComments',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_reply_count'
		);

		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'Name',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_title'
		);

		// Forum slug (Clean name to avoid confilcts)
		$this->field_map[] = array(
			'from_tablename'  => 'Category',
			'from_fieldname'  => 'Name',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'  => 'Category',
			'from_fieldname'  => 'Description',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);

		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'Sort',
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
			'from_tablename' => 'Category',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_date',
		);
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_date_gmt',
		);
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'DateUpdated',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_modified',
		);
		$this->field_map[] = array(
			'from_tablename' => 'Category',
			'from_fieldname' => 'DateUpdated',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_modified_gmt',
		);

		/** Topic Section *****************************************************/

		// Old topic id (Stored in postmeta)
		// Don't import Vanilla 2's deleted topics
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'DiscussionID',
			'from_expression' => 'WHERE Format != "Deleted"',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'CountComments',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'CountComments',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_total_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'CategoryID',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'InsertUserID',
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
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'InsertUserID',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_is_topic_anonymous_id',
			'callback_method' => 'callback_check_anonymous'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'Discussion',
			'from_fieldname' => 'Name',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'Name',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'Body',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic status (Open or Closed)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'closed',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_closed_status_id',
			'callback_method' => 'callback_topic_status'
		);

		// Topic author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'InsertIPAddress',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_author_ip'
		);

		// Topic parent forum id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'CategoryID',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Sticky status (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Discussion',
			'from_fieldname'  => 'Announce',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_sticky_status_id',
			'callback_method' => 'callback_sticky_status'
		);

		// Topic dates.
		$this->field_map[] = array(
			'from_tablename' => 'Discussion',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_date'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Discussion',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_date_gmt'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Discussion',
			'from_fieldname' => 'DateUpdated',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_modified'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Discussion',
			'from_fieldname' => 'DateUpdated',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_modified_gmt'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Discussion',
			'from_fieldname' => 'DateLastComment',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_last_active_time'
		);

		/** Tags Section ******************************************************/

		// Topic id.
		$this->field_map[] = array(
			'from_tablename'  => 'TagDiscussion',
			'from_fieldname'  => 'DiscussionID',
			'to_type'         => 'tags',
			'to_fieldname'    => 'objectid',
			'callback_method' => 'callback_topicid'
		);

		// Taxonomy ID.
		$this->field_map[] = array(
			'from_tablename'  => 'TagDiscussion',
			'from_fieldname'  => 'TagID',
			'to_type'         => 'tags',
			'to_fieldname'    => 'taxonomy'
		);

		// Term text.
		$this->field_map[] = array(
			'from_tablename'  => 'Tag',
			'from_fieldname'  => 'Name',
			'join_tablename'  => 'TagDiscussion',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (tagid)',
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
		);

		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		// Don't import Vanilla 2's deleted replies
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'CommentID',
			'from_expression' => 'WHERE Format != "Deleted"',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_reply_id'
		);

		// Reply parent topic id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'DiscussionID',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'DiscussionID',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Reply author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'Comment',
			'from_fieldname' => 'InsertIPAddress',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_author_ip'
		);

		// Reply author.
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'InsertUserID',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Reply author name (Stored in postmeta as _bbp_anonymous_name)
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => '_bbp_old_reply_author_name_id',
			'default'      => 'Anonymous'
		);

		// Is the reply anonymous (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'InsertUserID',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_is_reply_anonymous_id',
			'callback_method' => 'callback_check_anonymous'
		);

		// Reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'Body',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Reply parent topic id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'Comment',
			'from_fieldname'  => 'DiscussionID',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Reply dates.
		$this->field_map[] = array(
			'from_tablename' => 'Comment',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_date'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Comment',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_date_gmt'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Comment',
			'from_fieldname' => 'DateUpdated',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_modified'
		);
		$this->field_map[] = array(
			'from_tablename' => 'Comment',
			'from_fieldname' => 'DateUpdated',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_modified_gmt'
		);

		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		// Don't import user Vanilla's deleted users
		$this->field_map[] = array(
			'from_tablename'  => 'User',
			'from_fieldname'  => 'UserID',
			'from_expression' => 'WHERE Deleted !=1',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'User',
			'from_fieldname' => 'Password',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_password'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'User',
			'from_fieldname' => 'Name',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename' => 'User',
			'from_fieldname' => 'Name',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'User',
			'from_fieldname' => 'Email',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_email'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename' => 'User',
			'from_fieldname' => 'DateInserted',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_registered'
		);

		// Display Name
		$this->field_map[] = array(
			'from_tablename' => 'User',
			'from_fieldname' => 'Name',
			'to_type'        => 'user',
			'to_fieldname'   => 'display_name'
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
	 * Translate the topic status from Vanilla v2.x numerics to WordPress's strings.
	 *
	 * @param int $status Vanilla v2.x numeric topic status
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
	 * Translate the topic sticky status type from Vanilla v2.x numerics to WordPress's strings.
	 *
	 * @param int $status Vanilla v2.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_sticky_status( $status = 0 ) {
		switch ( $status ) {
			case 1 :
				$status = 'sticky';       // Vanilla Sticky 'Announce = 1'
				break;

			case 0  :
			default :
				$status = 'normal';       // Vanilla normal topic 'Announce = 0'
				break;
		}
		return $status;
	}

	/**
	 * Clean Root Parent ID -1 to 0
	 *
	 * @param int $parent Vanilla v2.x Parent ID
	 * @return int
	 */
	public function callback_forum_parent( $parent = 0 ) {
		if ( $parent == -1 ) {
			return 0;
		} else {
			return $parent;
		}
	}

	/**
	 * Verify the topic reply count.
	 *
	 * @param int $count Vanilla v2.x reply count
	 * @return string WordPress safe
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( (int) $count - 1 );
		return $count;
	}

	/**
	 * This method is to save the salt and password together. That
	 * way when we authenticate it we can get it out of the database
	 * as one value. Array values are auto sanitized by WordPress.
	 */
	public function callback_savepass( $field, $row ) {
		return false;
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass ) {
		return false;
	}
}
