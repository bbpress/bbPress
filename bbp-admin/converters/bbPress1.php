<?php

/**
 * Implementation of bbPress Stand Alone converter.
 */
class bbPress1 extends BBP_Converter_Base
{
	function __construct()
	{
		parent::__construct();
		$this->setup_globals();
	}

	public function setup_globals()
	{
		/** Forum Section ******************************************************/

		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_id',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_converter_forum_id'
		);

		// Forum parent id.  If no parent, than 0. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums',
			'from_fieldname' => 'forum_parent',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_converter_parent_id'
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
			'translate_method' => 'translate_title'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'   => 'forums',
			'from_fieldname'   => 'forum_desc',
			'to_type'          => 'forum',
			'to_fieldname'     => 'post_content',
			'translate_method' => 'translate_null'
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
			'to_fieldname'   => '_bbp_converter_topic_id'
		);

		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename'   => 'topics',
			'from_fieldname'   => 'forum_id',
			'to_type'          => 'topic',
			'to_fieldname'     => '_bbp_converter_forum_id',
			'translate_method' => 'translate_forumid'
		);

		// Topic author.
		$this->field_map[] = array(
			'from_tablename'   => 'topics',
			'from_fieldname'   => 'topic_poster',
			'to_type'          => 'topic',
			'to_fieldname'     => 'post_author',
			'translate_method' => 'translate_userid'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'   => 'posts',
			'from_fieldname'   => 'post_text',
			'join_tablename'   => 'topics',
			'join_type'        => 'INNER',
			'join_expression'  => 'USING (topic_id) WHERE posts.post_position = 1',
			'to_type'          => 'topic',
			'to_fieldname'     => 'post_content',
			'translate_method' => 'translate_html'
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
			'from_tablename'   => 'topics',
			'from_fieldname'   => 'topic_title',
			'to_type'          => 'topic',
			'to_fieldname'     => 'post_name',
			'translate_method' => 'translate_title'
		);

		// Forum id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename'   => 'topics',
			'from_fieldname'   => 'forum_id',
			'to_type'          => 'topic',
			'to_fieldname'     => 'post_parent',
			'translate_method' => 'translate_forumid'
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

		/** Tags Section ******************************************************/

		// Topic id.
		$this->field_map[] = array(
			'from_tablename'   => 'term_relationships',
			'from_fieldname'   => 'object_id',
			'to_type'          => 'tags',
			'to_fieldname'     => 'objectid',
			'translate_method' => 'translate_topicid'
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

		/** Post Section ******************************************************/

		// Post id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'posts',
			'from_fieldname'  => 'post_id',
			'from_expression' => 'WHERE posts.post_position != 1',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_converter_post_id'
		);

		// Forum id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'   => 'posts',
			'from_fieldname'   => 'forum_id',
			'to_type'          => 'reply',
			'to_fieldname'     => '_bbp_converter_forum_id',
			'translate_method' => 'translate_topicid_to_forumid'
		);

		// Topic id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'   => 'posts',
			'from_fieldname'   => 'topic_id',
			'to_type'          => 'reply',
			'to_fieldname'     => '_bbp_converter_topic_id',
			'translate_method' => 'translate_topicid'
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
			'from_tablename'   => 'posts',
			'from_fieldname'   => 'poster_id',
			'to_type'          => 'reply',
			'to_fieldname'     => 'post_author',
			'translate_method' => 'translate_userid'
		);

		// Topic title.
//		$this->field_map[] = array(
//			'from_tablename' => 'posts',
//			'from_fieldname' => 'title',
//			'to_type'        => 'reply',
//			'to_fieldname' => 'post_title'
//		);

		// Topic slug. Clean name.
//		$this->field_map[] = array(
//			'from_tablename'   => 'posts',
//			'from_fieldname'   => 'title',
//			'to_type'          => 'reply',
//			'to_fieldname'     => 'post_name',
//			'translate_method' => 'translate_title'
//		);

		// Post content.
		$this->field_map[] = array(
			'from_tablename' => 'posts',
			'from_fieldname' => 'post_text',
			'to_type'        => 'reply',
			'to_fieldname'   => 'post_content'
		);

		// Topic id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename'   => 'posts',
			'from_fieldname'   => 'topic_id',
			'to_type'          => 'reply',
			'to_fieldname'     => 'post_parent',
			'translate_method' => 'translate_topicid'
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
			'to_fieldname'   => '_bbp_converter_user_id'
		);

		// Store User password.
//		$this->field_map[] = array(
//			'from_tablename' => 'users',
//			'from_fieldname' => 'user_pass',
//			'to_type'        => 'user',
//			'to_fieldname'   => 'user_pass'
//		);

		// Store old User password. Stores in usermeta.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_pass',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_converter_password'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'users',
			'from_fieldname' => 'user_login',
			'to_type'        => 'user',
			'to_fieldname'   => 'user_login'
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
	 * as one value. Array values are auto sanitized by wordpress.
	 */
	public function translate_savepass( $field, $row ) {
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
