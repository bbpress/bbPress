<?php

/**
 * @group admin
 * @group mentions
 * @group metaboxes
 * @ticket BBP2773
 */
class BBP_Tests_Admin_Mentions extends BBP_UnitTestCase {
	private $user_query;

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$this->user_query = bbpress()->user_query;
	}

	public function tearDown(): void {
		bbpress()->user_query = $this->user_query;
		parent::tearDown();
	}

	private function render( $post_id ) {
		ob_start();
		try {
			bbp_mentions_metabox( get_post( $post_id ) );
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	public function test_boxes_show_only_mentions_in_the_saved_topic_or_reply() {
		$editor = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$one    = $this->factory->user->create( array( 'user_nicename' => 'box-topic-reader' ) );
		$two    = $this->factory->user->create( array( 'user_nicename' => 'box-reply-reader' ) );
		$other  = $this->factory->user->create();
		$forum  = $this->factory->forum->create();
		$topic  = $this->factory->topic->create( array( 'post_parent' => $forum, 'post_content' => '@box-topic-reader' ) );
		$reply  = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_content' => '@box-reply-reader' ) );
		bbp_add_user_engagement( $other, $topic );
		bbp_set_user_role( $editor, bbp_get_keymaster_role() );
		$this->set_current_user( $editor );
		$this->assertTrue( current_user_can( 'moderate' ) );

		foreach ( array( $topic => $one, $reply => $two ) as $post_id => $user_id ) {
			$output = $this->render( $post_id );
			$this->assertStringContainsString( esc_url( bbp_get_user_profile_url( $user_id ) ), $output );
			$this->assertStringContainsString( 'avatar', $output );
			$this->assertStringNotContainsString( esc_url( bbp_get_user_profile_url( $other ) ), $output );
			$this->assertStringNotContainsString( esc_url( bbp_get_user_profile_url( ( $user_id === $one ) ? $two : $one ) ), $output );
			$this->assertStringNotContainsString( '<input', $output );
		}

		wp_update_post( array( 'ID' => $reply, 'post_content' => 'Mention removed.' ) );
		$this->assertStringContainsString( 'No users are mentioned', $this->render( $reply ) );
	}

	public function test_empty_box_does_not_query_the_user_directory() {
		$editor = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$topic  = $this->factory->topic->create( array( 'post_content' => 'No mentions.' ) );
		bbp_set_user_role( $editor, bbp_get_keymaster_role() );
		$this->set_current_user( $editor );
		$this->assertTrue( current_user_can( 'moderate' ) );
		$queries = 0;
		$count   = function() use ( &$queries ) { ++$queries; };
		add_action( 'pre_get_users', $count );
		try {
			$this->assertStringContainsString( 'No users are mentioned', $this->render( $topic ) );
			$this->assertSame( 0, $queries );
		} finally {
			remove_action( 'pre_get_users', $count );
		}
	}

	public function test_box_requires_permission_to_edit_the_post() {
		$reader = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$author = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum  = $this->factory->forum->create();
		$topic  = $this->factory->topic->create( array( 'post_parent' => $forum, 'post_author' => $author ) );
		$reply  = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_author' => $author ) );

		foreach ( array( 0, $reader ) as $user_id ) {
			$this->set_current_user( $user_id );
			foreach ( array( $topic, $reply ) as $post_id ) {
				$this->assertFalse( current_user_can( ( $post_id === $topic ) ? 'edit_topic' : 'edit_reply', $post_id ) );
				$this->assertSame( '', $this->render( $post_id ) );
			}
		}
	}

	public function test_boxes_register_on_edit_screens_only() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/topics.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/replies.php';
		$hooks = array_map( function( $hook ) { return clone $hook; }, $GLOBALS['wp_filter'] );
		$get   = $_GET;
		$boxes = isset( $GLOBALS['wp_meta_boxes'] ) ? $GLOBALS['wp_meta_boxes'] : array();

		try {
			foreach ( array( bbp_get_topic_post_type() => new BBP_Topics_Admin(), bbp_get_reply_post_type() => new BBP_Replies_Admin() ) as $type => $admin ) {
				$this->assertSame( 10, has_action( 'add_meta_boxes', array( $admin, 'mentions_metabox' ) ) );
				$GLOBALS['wp_meta_boxes'] = array();
				unset( $_GET['action'] );
				$admin->mentions_metabox();
				$this->assertSame( array(), $GLOBALS['wp_meta_boxes'] );
				$_GET['action'] = 'edit';
				$admin->mentions_metabox();
				$this->assertSame( 'bbp_mentions_metabox', $GLOBALS['wp_meta_boxes'][ $type ]['side']['low']['bbp_mentions_metabox']['callback'] );
			}
		} finally {
			$_GET                     = $get;
			$GLOBALS['wp_filter']      = $hooks;
			$GLOBALS['wp_meta_boxes']  = $boxes;
		}
	}

	/**
	 * @dataProvider profile_screens
	 */
	public function test_profile_notifications_render_once_inside_the_forums_table( $screen, $hook, $show_roles ) {
		$editor = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target = ( 'show_user_profile' === $hook ) ? $editor : $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $editor );
		if ( is_multisite() ) {
			grant_super_admin( $editor );
		}
		update_user_meta( $target, '_bbp_mention_email', 'no' );
		$hooks = array_map( function( $hook ) { return clone $hook; }, $GLOBALS['wp_filter'] );
		$old_screen = get_current_screen();
		set_current_screen( $screen );
		try {
			remove_all_actions( $hook );
			new BBP_Users_Admin();
			ob_start();
			do_action( $hook, get_userdata( $target ) );
			$html = ob_get_clean();
			$document = new DOMDocument();
			$document->loadHTML( $html );
			$xpath = new DOMXPath( $document );
			$this->assertSame( 1, $xpath->query( '//h2[text()="Forums"]' )->length );
			$this->assertSame( 1, $xpath->query( '//h2[text()="Forums"]/following-sibling::table[1]//tr[th="Notifications"]/td//input[@name="bbp_mention_email"]' )->length );
			$this->assertSame( 1, $xpath->query( '//input[@name="bbp_mention_email"]' )->length );
			$this->assertSame( 1, $xpath->query( '//input[@name="_bbp_mention_nonce"]' )->length );
			$this->assertSame( 0, $xpath->query( '//input[@name="bbp_mention_email"]/@checked' )->length );
			$this->assertSame( $show_roles ? 1 : 0, $xpath->query( '//select[@name="bbp-forums-role"]' )->length );
			$this->assertSame( $show_roles ? 1 : 0, $xpath->query( '//input[@id="bbp-auto-role-exclusion"]' )->length );
		} finally {
			$GLOBALS['wp_filter'] = $hooks;
			$GLOBALS['current_screen'] = $old_screen;
			if ( is_multisite() ) {
				revoke_super_admin( $editor );
			}
		}
	}

	public static function profile_screens() {
		return array(
			'edit another user' => array( 'user-edit', 'edit_user_profile', true ),
			'own profile'       => array( 'profile', 'show_user_profile', false ),
			'network user'      => array( 'user-edit-network', 'edit_user_profile', false ),
			'network profile'   => array( 'profile-network', 'show_user_profile', false ),
		);
	}

	public function test_profile_notifications_respect_email_field_permissions() {
		$editor = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target = $this->factory->user->create();
		$this->set_current_user( $editor );
		$deny_email = function( $allowed, $field ) { return ( 'email' === $field ) ? false : $allowed; };
		add_filter( 'bbp_current_user_can_edit_user_field', $deny_email, 10, 2 );
		try {
			ob_start();
			BBP_Users_Admin::secondary_role_display( get_userdata( $target ) );
			$html = ob_get_clean();
			$this->assertStringNotContainsString( 'bbp_mention_email', $html );
			$this->assertStringNotContainsString( 'Notifications', $html );
		} finally {
			remove_filter( 'bbp_current_user_can_edit_user_field', $deny_email, 10 );
		}
	}
}
