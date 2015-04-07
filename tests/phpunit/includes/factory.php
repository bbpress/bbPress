<?php

class BBP_UnitTest_Factory extends WP_UnitTest_Factory {

	public $forum = null;

	public function __construct() {
		parent::__construct();

		$this->forum = new BBP_UnitTest_Factory_For_Forum( $this );
	}
}

class BBP_UnitTest_Factory_For_Forum extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		parent::__construct( $factory );

		$this->default_generation_definitions = array(
			'post_title'   => new WP_UnitTest_Generator_Sequence( 'Forum %s' ),
			'post_content' => new WP_UnitTest_Generator_Sequence( 'Content of Forum %s' ),
		);
	}

	public function create_object( $args ) {
		return bbp_insert_forum( $args );
	}

	public function update_object( $forum_id, $fields ) {
		$fields['forum_id'] = $forum_id;
		return bbp_update_forum( $fields );
	}

	public function get_object_by_id( $forum_id ) {
		return bbp_get_forum( $forum_id );
	}
}
