<?php

/**
 * Implementation of Vbulletin converter.
 */
class Vbulletin extends BBP_Converter_Base
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
			'from_tablename' => 'forum', 'from_fieldname' => 'forumid',
			'to_type' => 'forum', 'to_fieldname' => '_bbc_forum_id'
		);
		
		// Forum parent id.  If no parent, than 0. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forum', 'from_fieldname' => 'parentid',
			'to_type' => 'forum', 'to_fieldname' => '_bbc_parent_id'
		);
		
		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'forum', 'from_fieldname' => 'title',
			'to_type' => 'forum', 'to_fieldname' => 'post_title'
		);
		
		// Forum slug. Clean name.
		$this->field_map[] = array(
			'from_tablename' => 'forum', 'from_fieldname' => 'title_clean',
			'to_type' => 'forum', 'to_fieldname' => 'post_name',
			'translate_method' => 'translate_title'
		);
		
		// Forum description.
		$this->field_map[] = array(
			'from_tablename' => 'forum', 'from_fieldname' => 'description',
			'to_type' => 'forum', 'to_fieldname' => 'post_content',
			'translate_method' => 'translate_null'
		);
		
		// Forum display order.  Starts from 1.
		$this->field_map[] = array(
			'from_tablename' => 'forum', 'from_fieldname' => 'displayorder',
			'to_type' => 'forum', 'to_fieldname' => 'menu_order'
		);
		
		// Forum date update.
		$this->field_map[] = array(
			'to_type' => 'forum', 'to_fieldname' => 'post_date',
			'default' => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type' => 'forum', 'to_fieldname' => 'post_date_gmt',
			'default' => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type' => 'forum', 'to_fieldname' => 'post_modified',
			'default' => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type' => 'forum', 'to_fieldname' => 'post_modified_gmt',
			'default' => date('Y-m-d H:i:s')
		);

		/** Topic Section ******************************************************/

		// Topic id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'threadid',
			'to_type' => 'topic', 'to_fieldname' => '_bbc_topic_id'
		);
		
		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'forumid',
			'to_type' => 'topic', 'to_fieldname' => '_bbc_forum_id',
			'translate_method' => 'translate_forumid'
		);
				
		// Topic author.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'postuserid',
			'to_type' => 'topic', 'to_fieldname' => 'post_author',
			'translate_method' => 'translate_userid'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'pagetext',
			'join_tablename' => 'thread', 'join_type' => 'INNER', 'join_expression' => 'USING (threadid) WHERE post.parentid = 0',
			'to_type' => 'topic', 'to_fieldname' => 'post_content',
			'translate_method' => 'translate_html'
		);	

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'title',
			'to_type' => 'topic', 'to_fieldname' => 'post_title'
		);
		
		// Topic slug. Clean name.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'title',
			'to_type' => 'topic', 'to_fieldname' => 'post_name',
			'translate_method' => 'translate_title'
		);
		
		// Forum id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'forumid',
			'to_type' => 'topic', 'to_fieldname' => 'post_parent',
			'translate_method' => 'translate_forumid'
		);

		// Topic date update.
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'dateline',
			'to_type' => 'topic', 'to_fieldname' => 'post_date',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'dateline',
			'to_type' => 'topic', 'to_fieldname' => 'post_date_gmt',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'dateline',
			'to_type' => 'topic', 'to_fieldname' => 'post_modified',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'thread', 'from_fieldname' => 'dateline',
			'to_type' => 'topic', 'to_fieldname' => 'post_modified_gmt',
			'translate_method' => 'translate_datetime'
		);

		/** Tags Section ******************************************************/
		
		// Topic id.
		$this->field_map[] = array(
			'from_tablename' => 'tagcontent', 'from_fieldname' => 'contentid',
			'to_type' => 'tags', 'to_fieldname' => 'objectid',
			'translate_method' => 'translate_topicid'
		);
		
		// Tags text.
		$this->field_map[] = array(
			'from_tablename' => 'tag', 'from_fieldname' => 'tagtext',
			'join_tablename' => 'tagcontent', 'join_type' => 'INNER', 'join_expression' => 'USING (tagid)',
			'to_type' => 'tags', 'to_fieldname' => 'name'
		);		

		/** Post Section ******************************************************/

		// Post id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'postid', 'from_expression' => 'WHERE post.parentid != 0',
			'to_type' => 'reply', 'to_fieldname' => '_bbc_post_id'
		);
		
		// Forum id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'threadid',
			'to_type' => 'reply', 'to_fieldname' => '_bbc_forum_id',
			'translate_method' => 'translate_topicid_to_forumid'
		);
		
		// Topic id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'threadid',
			'to_type' => 'reply', 'to_fieldname' => '_bbc_topic_id',
			'translate_method' => 'translate_topicid'
		);
		
		// Author ip.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'ipaddress',
			'to_type' => 'reply', 'to_fieldname' => '__bbc_author_ip'
		);	
			
		// Post author.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'userid',
			'to_type' => 'reply', 'to_fieldname' => 'post_author',
			'translate_method' => 'translate_userid'
		);
		
		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'title',
			'to_type' => 'reply', 'to_fieldname' => 'post_title'
		);
		
		// Topic slug. Clean name.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'title',
			'to_type' => 'reply', 'to_fieldname' => 'post_name',
			'translate_method' => 'translate_title'
		);
		
		// Post content.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'pagetext',
			'to_type' => 'reply', 'to_fieldname' => 'post_content',
			'translate_method' => 'translate_html'
		);
		
		// Topic id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'threadid',
			'to_type' => 'reply', 'to_fieldname' => 'post_parent',
			'translate_method' => 'translate_topicid'
		);

		// Topic date update.
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'dateline',
			'to_type' => 'reply', 'to_fieldname' => 'post_date',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'dateline',
			'to_type' => 'reply', 'to_fieldname' => 'post_date_gmt',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'dateline',
			'to_type' => 'reply', 'to_fieldname' => 'post_modified',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'post', 'from_fieldname' => 'dateline',
			'to_type' => 'reply', 'to_fieldname' => 'post_modified_gmt',
			'translate_method' => 'translate_datetime'
		);

		/** User Section ******************************************************/

		// Store old User id. Stores in usermeta.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'userid',
			'to_type' => 'user', 'to_fieldname' => '_bbc_user_id'
		);
		
		// Store old User password. Stores in usermeta serialized with salt.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'password',
			'to_type' => 'user', 'to_fieldname' => '_bbc_password',
			'translate_method' => 'translate_savepass'
		);

		// Store old User Salt. This is only used for the SELECT row info for the above password save
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'salt',
			'to_type' => 'user', 'to_fieldname' => ''
		);
				
		// User password verify class. Stores in usermeta for verifying password.
		$this->field_map[] = array(
			'to_type' => 'user', 'to_fieldname' => '_bbc_class',
			'default' => 'Vbulletin'
		);
		
		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'username',
			'to_type' => 'user', 'to_fieldname' => 'user_login'
		);
				
		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'email',
			'to_type' => 'user', 'to_fieldname' => 'user_email'
		);
		
		// User homepage.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'homepage',
			'to_type' => 'user', 'to_fieldname' => 'user_url'
		);
		
		// User registered.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'joindate',
			'to_type' => 'user', 'to_fieldname' => 'user_registered',
			'translate_method' => 'translate_datetime'
		);
		
		// User aim.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'aim',
			'to_type' => 'user', 'to_fieldname' => 'aim'
		);
		
		// User yahoo.
		$this->field_map[] = array(
			'from_tablename' => 'user', 'from_fieldname' => 'yahoo',
			'to_type' => 'user', 'to_fieldname' => 'yim'
		);	
	}
	
	/**
	 * This method allows us to indicates what is or is not converted for each
	 * converter.
	 */
	public function info()
	{
		return '';
	}

	/**
	 * This method is to save the salt and password together.  That
	 * way when we authenticate it we can get it out of the database
	 * as one value. Array values are auto sanitized by wordpress.
	 */
	public function translate_savepass( $field, $row )
	{
		$pass_array = array( 'hash' => $field, 'salt' => $row['salt'] );
		return $pass_array;
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass )
	{
		$pass_array = unserialize( $serialized_pass );
		return ( $pass_array['hash'] == md5( md5( $password ). $pass_array['salt'] ) );
	}
}
?>