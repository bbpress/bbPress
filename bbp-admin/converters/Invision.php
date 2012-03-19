<?php

/**
 * Implementation of Invision Power Board converter.
 */
class Invision extends BBP_Converter_Base
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
			'from_tablename' => 'forums', 'from_fieldname' => 'id',
			'to_type' => 'forum', 'to_fieldname' => '_bbc_forum_id'
		);
		
		// Forum parent id.  If no parent, than 0. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'forums', 'from_fieldname' => 'parent_id',
			'to_type' => 'forum', 'to_fieldname' => '_bbc_parent_id'
		);
		
		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'forums', 'from_fieldname' => 'name',
			'to_type' => 'forum', 'to_fieldname' => 'post_title'
		);
		
		// Forum slug. Clean name.
		$this->field_map[] = array(
			'from_tablename' => 'forums', 'from_fieldname' => 'name',
			'to_type' => 'forum', 'to_fieldname' => 'post_name',
			'translate_method' => 'translate_title'
		);
		
		// Forum description.
		$this->field_map[] = array(
			'from_tablename' => 'forums', 'from_fieldname' => 'description',
			'to_type' => 'forum', 'to_fieldname' => 'post_content',
			'translate_method' => 'translate_null'
		);
		
		// Forum display order.  Starts from 1.
		$this->field_map[] = array(
			'from_tablename' => 'forums', 'from_fieldname' => 'position',
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
			'from_tablename' => 'topics', 'from_fieldname' => 'tid',
			'to_type' => 'topic', 'to_fieldname' => '_bbc_topic_id'
		);
		
		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'forum_id',
			'to_type' => 'topic', 'to_fieldname' => '_bbc_forum_id',
			'translate_method' => 'translate_forumid'
		);
				
		// Topic author.
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'starter_id',
			'to_type' => 'topic', 'to_fieldname' => 'post_author',
			'translate_method' => 'translate_userid'
		);
			
		// Topic content.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'post',
			'join_tablename' => 'topics', 'join_type' => 'INNER', 'join_expression' => 'ON(topics.tid = posts.topic_id) WHERE posts.new_topic = 1',
			'to_type' => 'topic', 'to_fieldname' => 'post_content',
			'translate_method' => 'translate_html'
		);	
		
		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'title',
			'to_type' => 'topic', 'to_fieldname' => 'post_title'
		);
		
		// Topic slug. Clean name.
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'title',
			'to_type' => 'topic', 'to_fieldname' => 'post_name',
			'translate_method' => 'translate_title'
		);
		
		// Forum id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'forum_id',
			'to_type' => 'topic', 'to_fieldname' => 'post_parent',
			'translate_method' => 'translate_forumid'
		);

		// Topic date update.
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'start_date',
			'to_type' => 'topic', 'to_fieldname' => 'post_date',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'start_date',
			'to_type' => 'topic', 'to_fieldname' => 'post_date_gmt',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'last_post',
			'to_type' => 'topic', 'to_fieldname' => 'post_modified',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'topics', 'from_fieldname' => 'last_post',
			'to_type' => 'topic', 'to_fieldname' => 'post_modified_gmt',
			'translate_method' => 'translate_datetime'
		);

		/** Tags Section ******************************************************/
		
		// Topic id.
		$this->field_map[] = array(
			'from_tablename' => 'core_tags', 'from_fieldname' => 'tag_meta_id',
			'to_type' => 'tags', 'to_fieldname' => 'objectid',
			'translate_method' => 'translate_topicid'
		);
		
		// Tags text.
		$this->field_map[] = array(
			'from_tablename' => 'core_tags', 'from_fieldname' => 'tag_text',
			'to_type' => 'tags', 'to_fieldname' => 'name'
		);	
		
		/** Post Section ******************************************************/

		// Post id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'pid', 'from_expression' => 'WHERE posts.new_topic = 1',
			'to_type' => 'reply', 'to_fieldname' => '_bbc_post_id'
		);
		
		// Forum id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'topic_id',
			'to_type' => 'reply', 'to_fieldname' => '_bbc_forum_id',
			'translate_method' => 'translate_topicid_to_forumid'
		);
		
		// Topic id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'topic_id',
			'to_type' => 'reply', 'to_fieldname' => '_bbc_topic_id',
			'translate_method' => 'translate_topicid'
		);
		
		// Author ip.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'ip_address',
			'to_type' => 'reply', 'to_fieldname' => '__bbc_author_ip'
		);	
			
		// Post author.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'author_id',
			'to_type' => 'reply', 'to_fieldname' => 'post_author',
			'translate_method' => 'translate_userid'
		);
		
		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'post_title',
			'to_type' => 'reply', 'to_fieldname' => 'post_title'
		);
		
		// Topic slug. Clean name.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'post_title',
			'to_type' => 'reply', 'to_fieldname' => 'post_name',
			'translate_method' => 'translate_title'
		);
		
		// Post content.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'post',
			'to_type' => 'reply', 'to_fieldname' => 'post_content',
			'translate_method' => 'translate_html'
		);
		
		// Topic id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'topic_id',
			'to_type' => 'reply', 'to_fieldname' => 'post_parent',
			'translate_method' => 'translate_topicid'
		);

		// Topic date update.
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'post_date',
			'to_type' => 'reply', 'to_fieldname' => 'post_date',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'post_date',
			'to_type' => 'reply', 'to_fieldname' => 'post_date_gmt',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'edit_time',
			'to_type' => 'reply', 'to_fieldname' => 'post_modified',
			'translate_method' => 'translate_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'posts', 'from_fieldname' => 'edit_time',
			'to_type' => 'reply', 'to_fieldname' => 'post_modified_gmt',
			'translate_method' => 'translate_datetime'
		);

		/** User Section ******************************************************/

		// Store old User id. Stores in usermeta.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'member_id',
			'to_type' => 'user', 'to_fieldname' => '_bbc_user_id'
		);
		
		// Store old User password. Stores in usermeta serialized with salt.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'members_pass_hash',
			'to_type' => 'user', 'to_fieldname' => '_bbc_password',
			'translate_method' => 'translate_savepass'
		);

		// Store old User Salt. This is only used for the SELECT row info for the above password save
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'members_pass_salt',
			'to_type' => 'user', 'to_fieldname' => ''
		);
				
		// User password verify class. Stores in usermeta for verifying password.
		$this->field_map[] = array(
			'to_type' => 'user', 'to_fieldname' => '_bbc_class',
			'default' => 'Invision'
		);
		
		// User name.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'name',
			'to_type' => 'user', 'to_fieldname' => 'user_login'
		);
				
		// User email.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'email',
			'to_type' => 'user', 'to_fieldname' => 'user_email'
		);
		
		// User registered.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'joined',
			'to_type' => 'user', 'to_fieldname' => 'user_registered',
			'translate_method' => 'translate_datetime'
		);
				
/*	
 * Table pfields_content AND pfields_data	
		// User homepage.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'homepage',
			'to_type' => 'user', 'to_fieldname' => 'user_url'
		);		
		
		// User aim.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'aim',
			'to_type' => 'user', 'to_fieldname' => 'aim'
		);
		
		// User yahoo.
		$this->field_map[] = array(
			'from_tablename' => 'members', 'from_fieldname' => 'yahoo',
			'to_type' => 'user', 'to_fieldname' => 'yim'
		);
*/		
		
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
		$pass_array = array( 'hash' => $field, 'salt' => $row['members_pass_salt'] );
		return $pass_array;
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 */
	public function authenticate_pass( $password, $serialized_pass )
	{
		$pass_array = unserialize( $serialized_pass );
		return ( $pass_array['hash'] == md5( md5( $pass_array['salt'] ) . md5( $this->to_char( $password ) ) ) );
	}

	public function to_char( $input )
	{
		$output = "";
		for( $i = 0; $i < strlen( $input ); $i++ )
		{
			$j = ord( $input{$i} );
			if( ( $j >= 65 && $j <= 90 )
				|| ( $j >= 97 && $j <= 122 )
				|| ( $j >= 48 && $j <= 57 ) )
			{
				$output .= $input{$i};
			}
			else
			{
				$output .= "&#" . ord( $input{$i} ) . ";";
			}
		}
		return $output;
	}
}
?>