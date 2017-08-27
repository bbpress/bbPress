<?php

/**
 * bbPress 1.x Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * bbPress 1.1 Converter
 *
 * @since 2.1.0 bbPress (r3816)
 *
 * @link Codex Docs https://codex.bbpress.org/import-forums/bbpress-1-x-buddypress-group-forums
 */
class bbPress1 extends BBP_Converter_Base {

	/**
	 * Main constructor
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
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_id',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_old_forum_id'
		);

		// Forum parent id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_parent',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_old_forum_parent_id'
		);

		// Forum topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_topic_count'
		);

		// Forum reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'posts',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_reply_count'
		);

		// Forum total topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_topic_count'
		);

		// Forum total reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'posts',
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

		// Forum slug (Clean name to avoid confilcts)
		$this->field_map[] = array(
			'from_tablename'   => 'forums',
			'from_fieldname'   => 'forum_slug',
			'to_type'          => 'forum',
			'to_fieldname'     => 'post_name',
			'callback_method'  => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'   => 'forums',
			'from_fieldname'   => 'forum_desc',
			'to_type'          => 'forum',
			'to_fieldname'     => 'post_content',
			'callback_method'  => 'callback_null'
		);

		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_order',
			'to_type'        => 'forum',
			'to_fieldname'   => 'menu_order'
		);

		// Forum type (bbPress v1.x Forum > 0 or Category = 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'meta',
			'from_fieldname'  => 'meta_value',
			'join_tablename'  => 'forums',
			'join_type'       => 'LEFT',
			'join_expression' => 'ON meta.object_id = forums.forum_id AND meta.meta_key = "forum_is_category"',
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

		/** Forum Subscriptions Section ***************************************/

		/**
		 * bbPress 1.x Forums do not support forum subscriptions
		 */

		/** Topic Section *****************************************************/

		// Old topic id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_id',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_posts',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic total reply count (Includes unpublished replies, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_posts',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_total_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_poster',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_title',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_slug',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic content.
		// Note: We join the 'posts' table because 'topics' table does not include content.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_text',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position IN (0,1)',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic status (Spam, Trash or Publish, bbPress v1.x publish = 0, trash = 1 & spam = 2)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_status',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position IN (0,1)',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_status',
			'callback_method' => 'callback_status'
		);

		// Topic status (Publish or Closed to new replies)
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_open',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_closed_status_id',
			'callback_method' => 'callback_topic_status'
		);

		// Topic author ip (Stored in postmeta)
		// Note: We join the 'posts' table because 'topics' table does not include author ip.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'poster_ip',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position IN (0,1)',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_author_ip'
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
			'from_fieldname'  => 'topic_sticky',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_sticky_status_id',
			'callback_method' => 'callback_sticky_status'
		);

		// Topic dates.
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_start_time',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_date'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_start_time',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_date_gmt'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_time',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_modified'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_time',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_modified_gmt'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_time',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_last_active_time'
		);

		/** Tags Section ******************************************************/

		// Topic id.
		$this->field_map[] = array(
			'from_tablename'  => 'term_relationships',
			'from_fieldname'  => 'object_id',
			'to_type'         => 'tags',
			'to_fieldname'    => 'objectid',
			'callback_method' => 'callback_topicid'
		);

		// Taxonomy ID.
		$this->field_map[] = array(
			'from_tablename'  => 'term_taxonomy',
			'from_fieldname'  => 'term_taxonomy_id',
			'join_tablename'  => 'term_relationships',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_taxonomy_id)',
			'to_type'         => 'tags',
			'to_fieldname'    => 'taxonomy'
		);

		// Term description.
		$this->field_map[] = array(
			'from_tablename'  => 'term_taxonomy',
			'from_fieldname'  => 'description',
			'join_tablename'  => 'term_relationships',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_taxonomy_id)',
			'to_type'         => 'tags',
			'to_fieldname'    => 'description',
			'callback_method' => 'callback_html'
		);

		// Term text.
		$this->field_map[] = array(
			'from_tablename'  => 'terms',
			'from_fieldname'  => 'name',
			'join_tablename'  => 'term_taxonomy',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_id) WHERE term_taxonomy.taxonomy = "bb_topic_tag"',
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
		);

		// Term slug.
		$this->field_map[] = array(
			'from_tablename'  => 'terms',
			'from_fieldname'  => 'slug',
			'join_tablename'  => 'term_taxonomy',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_id) WHERE term_taxonomy.taxonomy = "bb_topic_tag"',
			'to_type'         => 'tags',
			'to_fieldname'    => 'slug',
			'callback_method' => 'callback_slug'
		);

		/** Topic Subscriptions Section ***************************************/

		// Subscribed user ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'term_relationships',
			'from_fieldname'  => 'user_id',
			'to_type'         => 'topic_subscriptions',
			'to_fieldname'    => 'user_id',
			'callback_method' => 'callback_userid'
		);

		// Join the 'term_taxonomy' table to link 'terms' 'term_relationships' tables
		$this->field_map[] = array(
			'from_tablename'  => 'term_taxonomy',
			'from_fieldname'  => 'term_taxonomy_id',
			'join_tablename'  => 'term_relationships',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_taxonomy_id)',
			'to_type'         => 'topic_subscriptions'
		);

		// Subscribed topic ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'terms',
			'from_fieldname'  => 'name',
			'join_tablename'  => 'term_taxonomy',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_id) WHERE term_taxonomy.taxonomy = "bb_subscribe"',
			'to_type'         => 'topic_subscriptions',
			'to_fieldname'    => '_bbp_subscriptions',
			'callback_method' => 'callback_topic_subscriptions'
		);

		/** Favorites Section *************************************************/

		// Favorited topic ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'usermeta',
			'from_fieldname'  => 'meta_value',
			'from_expression' => 'WHERE usermeta.meta_key = "bb_favorites"',
			'to_type'         => 'favorites',
			'to_fieldname'    => '_bbp_favorites'
		);

		// Favorited user ID (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'usermeta',
			'from_fieldname'  => 'user_id',
			'from_expression' => 'WHERE usermeta.meta_key = "bb_favorites"',
			'to_type'         => 'favorites',
			'to_fieldname'    => 'user_id',
			'callback_method' => 'callback_userid'
		);

		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_reply_id'
		);

		// Reply parent topic id (If no parent, then 0. Stored in postmeta)
		// Note: We join the 'topics' table to limit the replies section to only import replies
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_id',
			'join_tablename'  => 'posts',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position NOT IN (0,1)',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
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

		// Reply status (Spam, Trash or Publish, bbPress v1.x publish = 0, trash = 1 & spam = 2)
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_status',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_status',
			'callback_method' => 'callback_status'
		);

		// Reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_text',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Reply order.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_position',
			'to_type'         => 'reply',
			'to_fieldname'    => 'menu_order'
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
			'from_tablename' => 'posts',
			'from_fieldname' => 'post_time',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_date'
		);
		$this->field_map[] = array(
			'from_tablename' => 'posts',
			'from_fieldname' => 'post_time',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_date_gmt'
		);
		$this->field_map[] = array(
			'from_tablename' => 'posts',
			'from_fieldname' => 'post_time',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_modified'
		);
		$this->field_map[] = array(
			'from_tablename' => 'posts',
			'from_fieldname' => 'post_time',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_modified_gmt'
		);

		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'ID',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_pass',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_password'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_login',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_nicename',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_email',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_email'
		);

		// User homepage.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_url',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_url'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_registered',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_registered'
		);

		// User status.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_status',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_status'
		);

		// User display name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'display_name',
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
	 * Translate the post status from bbPress 1's numerics to WordPress's
	 * strings.
	 *
	 * @param int $status bbPress 1.x numeric post status
	 * @return string WordPress safe
	 */
	public function callback_status( $status = 0 ) {
		switch ( $status ) {
			case 2 :
				$status = 'spam';    // bbp_get_spam_status_id()
				break;

			case 1 :
				$status = 'trash';   // bbp_get_trash_status_id()
				break;

			case 0  :
			default :
				$status = 'publish'; // bbp_get_public_status_id()
				break;
		}
		return $status;
	}

	/**
	 * Translate the forum type from bbPress 1.x numerics to WordPress's strings.
	 *
	 * @param int $status bbPress 1.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_forum_type( $status = 0 ) {
		if ( $status == 1 ) {
			$status = 'category';
		} else {
			$status = 'forum';
		}
		return $status;
	}

	/**
	 * Translate the topic status from bbPress 1's numerics to WordPress's
	 * strings.
	 *
	 * @param int $topic_status bbPress 1.x numeric status
	 * @return string WordPress safe
	 */
	public function callback_topic_status( $topic_status = 1 ) {
		switch ( $topic_status ) {
			case 0 :
				$topic_status = 'closed';  // bbp_get_closed_status_id()
				break;

			case 1 :
				default :
				$topic_status = 'publish'; // bbp_get_public_status_id()
				break;
		}
		return $topic_status;
	}

	/**
	 * Translate the topic sticky status type from bbPress 1.x numerics to WordPress's strings.
	 *
	 * @param int $status bbPress 1.x numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_sticky_status( $status = 0 ) {
		switch ( $status ) {
			case 2 :
				$status = 'super-sticky'; // bbPress Super Sticky 'topic_sticky = 2'
				break;

			case 1 :
				$status = 'sticky';       // bbPress Sticky 'topic_sticky = 1'
				break;

			case 0  :
			default :
				$status = 'normal';       // bbPress Normal Topic 'topic_sticky = 0'
				break;
		}
		return $status;
	}

	/**
	 * Verify the topic reply count.
	 *
	 * @param int $count bbPress 1.x topic and reply counts
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

	/**
	 * This callback strips `topic-` from topic subscriptions taxonomy
	 *
	 * @since 2.6.0 bbPress (r5572)
	 *
	 * @param string $field Topic ID
	 * @return integer WordPress safe
	 */
	protected function callback_topic_subscriptions( $field ) {

		// Replace 'topic-' with '' so that only the original topic ID remains
		$field = absint( (int) preg_replace( '/(topic-)(\d+)/', '$2', $field ) );
		return $field;
	}
}
