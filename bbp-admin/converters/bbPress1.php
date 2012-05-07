<?php

/**
 * Implementation of bbPress Stand Alone converter.
 */
class bbPress1 extends BBP_Converter_Base {
	function __construct() {
		parent::__construct();
		$this->setup_globals();
	}

	public function setup_globals() {

		/** Forum Section ******************************************************/

		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_id',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_forum_id',
		);

		// Forum parent id.  If no parent, than 0. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_parent',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_forum_parent_id'
		);

		// Forum topic count. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_topic_count'
		);

		// Forum reply count. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'posts',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_reply_count'
		);

		// Forum topic count. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_topic_count'
		);

		// Forum reply count. Stored in postmeta.
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

		// Forum slug. Clean name.
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

		// Forum display order.  Starts from 1.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_order',
			'to_type'        => 'forum',
			'to_fieldname'   => 'menu_order'
		);

		// Forum date update.
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

		/** Topic Section ******************************************************/

		// Topic id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'topics',
			'from_fieldname' => 'topic_id',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_topic_id'
		);

		// Reply count. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_posts',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Forum id. Stored in postmeta.
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

		// Topic slug. Clean name.
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_title',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_text',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position = 1',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic status.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_status',
			'join_tablename'  => 'topics',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position = 1',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_status',
			'callback_method' => 'callback_status'
		);

		// Forum id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Topic date update.
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

		// Tags text.
		$this->field_map[] = array(
			'from_tablename'  => 'terms',
			'from_fieldname'  => 'name',
			'join_tablename'  => 'term_taxonomy',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (term_id)',
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
		);

		/** Reply Section *****************************************************/

		// Post id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_post_id'
		);

		// Topic id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Forum id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'topics',
			'from_fieldname'  => 'topic_title',
			'join_tablename'  => 'posts',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE posts.post_position != 1',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_title',
			'callback_method' => 'callback_reply_title'
		);

		// Author ip.
		$this->field_map[] = array(
			'from_tablename' => 'posts',
			'from_fieldname' => 'poster_ip',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_author_ip'
		);

		// Post author.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'poster_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Reply status
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_status',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_status',
			'callback_method' => 'callback_status'
		);

		// Post content.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_text',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Topic date update.
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

		// Store old User id. Stores in usermeta.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'ID',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_user_id'
		);

		// Store old User password. Stores in usermeta.
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

		// User status.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'display_name',
			'to_type'        => 'user',
			'to_fieldname'   => 'display_name'
		);

		// User aim.
//		$this->field_map[] = array(
//			'from_tablename' => 'users',
//			'from_fieldname' => 'aim',
//			'to_type'        => 'user',
//			'to_fieldname'   => 'aim'
//		);

		// User yahoo.
//		$this->field_map[] = array(
//			'from_tablename' => 'users',
//			'from_fieldname' => 'yahoo',
//			'to_type'        => 'user',
//			'to_fieldname'   => 'yim'
//		);

		/** Meta Section ******************************************************/

		return;

		// User registered.
		$this->field_map[] = array(
			'from_tablename' => 'meta',
			'from_fieldname' => 'meta_value',
			'to_type'        => 'postmeta',
			'to_fieldname'   => '_bbp_'
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
	 * Translate the post status from bbPress 1's numeric's to WordPress's
	 * strings.
	 *
	 * @param int $status bbPress 1.x numeric status
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
	 * Verify the topic reply count.
	 *
	 * @param int $count bbPress 1.x reply count
	 * @return string WordPress safe
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( (int) $count - 1 );
		return $count;
	}

	/**
	 * Set the reply title
	 *
	 * @param string $title bbPress 1.x topic title of this reply
	 * @return string Prefixed topic title, or empty string
	 */
	public function callback_reply_title( $title = '' ) {
		$title = !empty( $title ) ? __( 'Re: ', 'bbpress' ) . html_entity_decode( $title ) : '';
		return $title;
	}

	/**
	 * This method is to save the salt and password together. That
	 * way when we authenticate it we can get it out of the database
	 * as one value. Array values are auto sanitized by wordpress.
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

?>
