<?php

/**
 * SimplePress 5 converter.
 *
 * @since bbPress (r4638)
 */
class SimplePress5 extends BBP_Converter_Base {
	function __construct() {
		parent::__construct();
		$this->setup_globals();
	}

	public function setup_globals() {

		/** Forum Section ******************************************************/

		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'sfforums',
			'from_fieldname' => 'forum_id',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_forum_id'
		);

		// Forum parent id.  If no parent, than 0. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'sfforums',
			'from_fieldname' => 'parent',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_forum_parent_id'
		);

		// Forum title.
		$this->field_map[] = array(
			'from_tablename' => 'sfforums',
			'from_fieldname' => 'forum_name',
			'to_type'        => 'forum',
			'to_fieldname'   => 'post_title'
		);

		// Forum slug. Clean name.
		$this->field_map[] = array(
			'from_tablename'  => 'sfforums',
			'from_fieldname'  => 'forum_name',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		$this->field_map[] = array(
			'from_tablename'  => 'sfforums',
			'from_fieldname'  => 'forum_desc',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);

		// Forum display order.  Starts from 1.
		$this->field_map[] = array(
			'from_tablename' => 'sfforums',
			'from_fieldname' => 'forum_seq',
			'to_type'        => 'forum',
			'to_fieldname'   => 'menu_order'
		);

		// Forum date update.
		$this->field_map[] = array(
			'to_type'      => 'forums',
			'to_fieldname' => 'forum_last_post_time',
			'default'      => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'      => 'forums',
			'to_fieldname' => 'forum_last_post_time',
			'default'      => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'      => 'forums',
			'to_fieldname' => 'forum_last_post_time',
			'default'      => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'      => 'forums',
			'to_fieldname' => 'forum_last_post_time',
			'default'      => date('Y-m-d H:i:s')
		);

		/** Topic Section ******************************************************/

		// Topic id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'sftopics',
			'from_fieldname' => 'topic_id',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_topic_id'
		);

		// Forum id. Stored in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'user_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'post_content',
			'join_tablename'  => 'sftopics',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE sfposts.post_index = 1',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename' => 'sftopics',
			'from_fieldname' => 'topic_name',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);

		// Topic slug. Clean name.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_name',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Topic date update.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_date',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_date',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_date',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_date',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);

		/** Tags Section ******************************************************/
		/*
		// Topic id.
		$this->field_map[] = array(
			'from_tablename'  => 'tagcontent',
			'from_fieldname'  => 'contentid',
			'to_type'         => 'tags',
			'to_fieldname'    => 'objectid',
			'callback_method' => 'callback_topicid'
		);

		// Tags text.
		$this->field_map[] = array(
			'from_tablename'  => 'tag',
			'from_fieldname'  => 'tagtext',
			'join_tablename'  => 'tagcontent',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (tagid)',
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
		);
		*/

		/** Post Section ******************************************************/

		// Post id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename' => 'sfposts',
			'from_fieldname' => 'post_id',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_post_id'
		);

		// Topic content.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_id',
			'join_tablename'  => 'sfposts',
			'join_type'       => 'LEFT',
			'join_expression' => 'USING (topic_id) WHERE sfposts.post_index != 1',
			'to_type'         => 'reply'
		);

		// Forum id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Topic id. Stores in postmeta.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Author ip.
		$this->field_map[] = array(
			'from_tablename' => 'sfposts',
			'from_fieldname' => 'poster_ip',
			'to_type'        => 'reply',
			'to_fieldname'   => '_bbp_author_ip'
		);

		// Post author.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'user_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic title.
		// Note: We join the sftopics table because sfposts do not have topic_name.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_name',
			'join_tablename'  => 'sfposts',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE sfposts.post_id = sftopics.post_id_held',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_title'
		);

		// Topic slug. Clean name.
		// Note: We join the sftopics table because sfposts do not have topic_name.
		$this->field_map[] = array(
			'from_tablename'  => 'sftopics',
			'from_fieldname'  => 'topic_name',
			'join_tablename'  => 'sfposts',
			'join_type'       => 'INNER',
			'join_expression' => 'USING (topic_id) WHERE sfposts.post_id = sftopics.post_id_held',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Post content.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'post_content',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic id.  If no parent, than 0.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Topic date update.
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'post_date',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'post_date',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'post_date',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'sfposts',
			'from_fieldname'  => 'post_date',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
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

	/**
	 * This callback processes any custom parser.php attributes and custom code with preg_replace
	 */
	protected function callback_html( $field ) {

		// Parse out bbCodes
		require_once( bbpress()->admin->admin_dir . 'parser.php' );
		$bbcode = BBCode::getInstance();
		$bbcode->enable_smileys = false;
		$bbcode->smiley_regex   = false;
		$field = html_entity_decode( $bbcode->Parse( $field ) );

		// Replace SimplePress smilies with the equivelant WordPress
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-confused.gif(.*?)" \/>/'   , ':?'      , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-cool.gif(.*?)" \/>/'       , ':cool:'  , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-cry.gif(.*?)" \/>/'        , ':cry:'   , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-embarassed.gif(.*?)" \/>/' , ':oops:'  , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-frown.gif(.*?)" \/>/'      , ':('      , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-kiss.gif(.*?)" \/>/'       , ':P'      , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-laugh.gif(.*?)" \/>/'      , ':D'      , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-smile.gif(.*?)" \/>/'      , ':smile:' , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-surprised.gif(.*?)" \/>/'  , ':o'      , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-wink.gif(.*?)" \/>/'       , ':wink:'  , $field );
		$field = preg_replace ( '/<img src=(.*?)\/sp-resources\/forum-smileys\/sf-yell.gif(.*?)" \/>/'       , ':x'      , $field );

		return $field;
	}
}
