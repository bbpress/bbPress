<?php

/**
 * Tests for core role and capability functions.
 *
 * @group core
 * @group capabilities
 */
class BBP_Tests_Core_Capabilities extends BBP_UnitTestCase {

	/**
	 * @dataProvider data_role_capabilities
	 *
	 * @covers ::bbp_get_caps_for_role
	 */
	public function test_role_capabilities( $role, $expected_caps, $expected_value ) {
		$caps = bbp_get_caps_for_role( $role );

		$this->assertEqualsCanonicalizing( $expected_caps, array_keys( $caps ) );
		foreach ( $expected_caps as $capability ) {
			$this->assertSame( $expected_value, $caps[ $capability ] );
		}
	}

	/**
	 * Data provider for test_role_capabilities().
	 */
	public function data_role_capabilities() {
		$keymaster_caps = array(
			'keep_gate',
			'spectate',
			'participate',
			'moderate',
			'throttle',
			'view_trash',
			'assign_moderators',
			'publish_forums',
			'edit_forums',
			'edit_others_forums',
			'delete_forums',
			'delete_others_forums',
			'read_private_forums',
			'read_hidden_forums',
			'publish_topics',
			'edit_topics',
			'edit_others_topics',
			'delete_topics',
			'delete_others_topics',
			'read_private_topics',
			'publish_replies',
			'edit_replies',
			'edit_others_replies',
			'delete_replies',
			'delete_others_replies',
			'read_private_replies',
			'manage_topic_tags',
			'edit_topic_tags',
			'delete_topic_tags',
			'assign_topic_tags',
		);

		$moderator_caps = array(
			'spectate',
			'participate',
			'moderate',
			'throttle',
			'view_trash',
			'assign_moderators',
			'publish_forums',
			'edit_forums',
			'read_private_forums',
			'read_hidden_forums',
			'publish_topics',
			'edit_topics',
			'edit_others_topics',
			'delete_topics',
			'delete_others_topics',
			'read_private_topics',
			'publish_replies',
			'edit_replies',
			'edit_others_replies',
			'delete_replies',
			'delete_others_replies',
			'read_private_replies',
			'manage_topic_tags',
			'edit_topic_tags',
			'delete_topic_tags',
			'assign_topic_tags',
		);

		$participant_caps = array(
			'spectate',
			'participate',
			'read_private_forums',
			'publish_topics',
			'edit_topics',
			'publish_replies',
			'edit_replies',
			'assign_topic_tags',
		);

		$blocked_caps = array(
			'spectate',
			'participate',
			'moderate',
			'throttle',
			'view_trash',
			'publish_forums',
			'edit_forums',
			'edit_others_forums',
			'delete_forums',
			'delete_others_forums',
			'read_private_forums',
			'read_hidden_forums',
			'publish_topics',
			'edit_topics',
			'edit_others_topics',
			'delete_topics',
			'delete_others_topics',
			'read_private_topics',
			'publish_replies',
			'edit_replies',
			'edit_others_replies',
			'delete_replies',
			'delete_others_replies',
			'read_private_replies',
			'manage_topic_tags',
			'edit_topic_tags',
			'delete_topic_tags',
			'assign_topic_tags',
		);

		return array(
			'keymaster'            => array( 'bbp_keymaster',   $keymaster_caps,   true  ),
			'moderator'            => array( 'bbp_moderator',   $moderator_caps,   true  ),
			'participant'          => array( 'bbp_participant', $participant_caps, true  ),
			'spectator'            => array( 'bbp_spectator',   array( 'spectate' ), true  ),
			'blocked'              => array( 'bbp_blocked',     $blocked_caps,     false ),
			'unknown defaults'     => array( 'unknown',         $participant_caps, true  ),
			'empty role defaults'  => array( '',                $participant_caps, true  ),
		);
	}

	/**
	 * @dataProvider data_object_capabilities
	 *
	 * @covers ::bbp_get_forum_caps
	 * @covers ::bbp_get_topic_caps
	 * @covers ::bbp_get_reply_caps
	 * @covers ::bbp_get_topic_tag_caps
	 */
	public function test_object_capabilities_are_complete_and_filterable( $function, $filter_name, $expected_caps ) {
		$received_caps = null;
		$filter        = static function( $caps ) use ( &$received_caps ) {
			$received_caps = $caps;

			return (object) array( 'custom_capability' => 'custom_mapping' );
		};

		$this->assertEqualsCanonicalizing( $expected_caps, $function() );

		add_filter( $filter_name, $filter );
		try {
			$this->assertSame( array( 'custom_capability' => 'custom_mapping' ), $function() );
			$this->assertEqualsCanonicalizing( $expected_caps, $received_caps );
		} finally {
			remove_filter( $filter_name, $filter );
		}
	}

	/**
	 * Data provider for test_object_capabilities_are_complete_and_filterable().
	 */
	public function data_object_capabilities() {
		return array(
			'forums' => array(
				'bbp_get_forum_caps',
				'bbp_get_forum_caps',
				array(
					'create_posts'        => 'create_forums',
					'edit_posts'          => 'edit_forums',
					'edit_others_posts'   => 'edit_others_forums',
					'publish_posts'       => 'publish_forums',
					'read_private_posts'  => 'read_private_forums',
					'read_hidden_posts'   => 'read_hidden_forums',
					'delete_posts'        => 'delete_forums',
					'delete_others_posts' => 'delete_others_forums',
				),
			),
			'topics' => array(
				'bbp_get_topic_caps',
				'bbp_get_topic_caps',
				array(
					'create_posts'        => 'create_topics',
					'edit_posts'          => 'edit_topics',
					'edit_others_posts'   => 'edit_others_topics',
					'publish_posts'       => 'publish_topics',
					'read_private_posts'  => 'read_private_topics',
					'read_hidden_posts'   => 'read_hidden_topics',
					'delete_posts'        => 'delete_topics',
					'delete_others_posts' => 'delete_others_topics',
				),
			),
			'replies' => array(
				'bbp_get_reply_caps',
				'bbp_get_reply_caps',
				array(
					'create_posts'        => 'create_replies',
					'edit_posts'          => 'edit_replies',
					'edit_others_posts'   => 'edit_others_replies',
					'publish_posts'       => 'publish_replies',
					'read_private_posts'  => 'read_private_replies',
					'delete_posts'        => 'delete_replies',
					'delete_others_posts' => 'delete_others_replies',
				),
			),
			'topic tags' => array(
				'bbp_get_topic_tag_caps',
				'bbp_get_topic_tag_caps',
				array(
					'manage_terms' => 'manage_topic_tags',
					'edit_terms'   => 'edit_topic_tags',
					'delete_terms' => 'delete_topic_tags',
					'assign_terms' => 'assign_topic_tags',
				),
			),
		);
	}

	/**
	 * @covers ::bbp_get_caps_for_role
	 */
	public function test_role_capabilities_are_filterable_with_the_requested_role() {
		$expected_caps  = bbp_get_caps_for_role( 'custom_role' );
		$received_caps  = null;
		$requested_role = null;
		$filter         = function( $caps, $role ) use ( &$received_caps, &$requested_role ) {
			$received_caps  = $caps;
			$requested_role = $role;
			return array( 'custom_capability' => true );
		};

		add_filter( 'bbp_get_caps_for_role', $filter, 10, 2 );
		try {
			$this->assertSame( array( 'custom_capability' => true ), bbp_get_caps_for_role( 'custom_role' ) );
			$this->assertSame( $expected_caps, $received_caps );
			$this->assertSame( 'custom_role', $requested_role );
		} finally {
			remove_filter( 'bbp_get_caps_for_role', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_add_caps
	 * @covers ::bbp_remove_caps
	 */
	public function test_caps_are_added_to_and_removed_from_wordpress_roles() {
		global $wp_roles;

		$original_wp_roles = $wp_roles;
		$wp_roles          = $this->get_test_roles(
			array(
				'test_role' => array(
					'name'         => 'Test Role',
					'capabilities' => array( 'read' => true ),
				),
			)
		);

		$filter  = function( $caps, $role ) {
			return ( 'test_role' === $role )
				? array(
					'test_granted_capability' => true,
					'test_denied_capability'  => false,
				)
				: array();
		};
		$added   = 0;
		$removed = 0;
		$added_action   = function() use ( &$added ) {
			$added++;
		};
		$removed_action = function() use ( &$removed ) {
			$removed++;
		};

		add_filter( 'bbp_get_caps_for_role', $filter, 10, 2 );
		add_action( 'bbp_add_caps', $added_action );
		add_action( 'bbp_remove_caps', $removed_action );

		try {
			bbp_add_caps();
			$this->assertTrue( $wp_roles->role_objects['test_role']->has_cap( 'test_granted_capability' ) );
			$this->assertFalse( $wp_roles->role_objects['test_role']->has_cap( 'test_denied_capability' ) );
			$this->assertTrue( $wp_roles->roles['test_role']['capabilities']['test_granted_capability'] );
			$this->assertFalse( $wp_roles->roles['test_role']['capabilities']['test_denied_capability'] );
			$this->assertSame( 1, $added );

			bbp_remove_caps();
			$this->assertFalse( $wp_roles->role_objects['test_role']->has_cap( 'test_granted_capability' ) );
			$this->assertFalse( $wp_roles->role_objects['test_role']->has_cap( 'test_denied_capability' ) );
			$this->assertArrayNotHasKey( 'test_granted_capability', $wp_roles->roles['test_role']['capabilities'] );
			$this->assertArrayNotHasKey( 'test_denied_capability', $wp_roles->roles['test_role']['capabilities'] );
			$this->assertTrue( $wp_roles->role_objects['test_role']->has_cap( 'read' ) );
			$this->assertSame( 1, $removed );
		} finally {
			remove_filter( 'bbp_get_caps_for_role', $filter, 10 );
			remove_action( 'bbp_add_caps', $added_action );
			remove_action( 'bbp_remove_caps', $removed_action );
			$wp_roles = $original_wp_roles;
		}
	}

	/**
	 * @covers ::bbp_add_caps
	 * @covers ::bbp_remove_caps
	 */
	public function test_wordpress_roles_receive_and_remove_participant_capabilities() {
		global $wp_roles;

		$original_wp_roles = $wp_roles;
		$wp_roles          = $this->get_test_roles(
			array(
				'subscriber' => array(
					'name'         => 'Subscriber',
					'capabilities' => array( 'read' => true ),
				),
			)
		);
		$participant_caps  = bbp_get_caps_for_role( 'subscriber' );

		try {
			bbp_add_caps();
			$this->assertTrue( $wp_roles->role_objects['subscriber']->has_cap( 'read' ) );
			foreach ( $participant_caps as $capability => $value ) {
				$this->assertSame( $value, $wp_roles->roles['subscriber']['capabilities'][ $capability ] );
			}

			bbp_remove_caps();
			$this->assertSame( array( 'read' => true ), $wp_roles->roles['subscriber']['capabilities'] );
		} finally {
			$wp_roles = $original_wp_roles;
		}
	}

	/**
	 * @covers ::bbp_get_blog_roles
	 * @covers ::bbp_filter_blog_editable_roles
	 */
	public function test_blog_roles_exclude_dynamic_roles_and_preserve_filtered_roles() {
		$editable_filter = function( $roles ) {
			$roles['custom_blog_role'] = array(
				'name'         => 'Custom Blog Role',
				'capabilities' => array( 'read' => true ),
			);
			return $roles;
		};
		$received_roles = null;
		$result_filter  = function( $roles, $wp_roles ) use ( &$received_roles ) {
			$received_roles = $wp_roles;
			return $roles;
		};

		add_filter( 'editable_roles', $editable_filter, 5 );
		add_filter( 'bbp_get_blog_roles', $result_filter, 10, 2 );
		try {
			$this->assertArrayHasKey( bbp_get_participant_role(), bbp_get_wp_roles()->roles );
			$roles = bbp_get_blog_roles();

			$this->assertArrayHasKey( 'administrator', $roles );
			$this->assertArrayHasKey( 'custom_blog_role', $roles );
			$this->assertArrayNotHasKey( bbp_get_participant_role(), $roles );
			$this->assertSame( bbp_get_wp_roles(), $received_roles );
		} finally {
			remove_filter( 'editable_roles', $editable_filter, 5 );
			remove_filter( 'bbp_get_blog_roles', $result_filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_add_forums_roles
	 */
	public function test_forum_roles_are_added_to_a_wordpress_roles_object() {
		$wp_roles = $this->get_test_roles(
			array(
				'custom_blog_role' => array(
					'name'         => 'Custom Blog Role',
					'capabilities' => array( 'read' => true ),
				),
				'bbp_participant' => array(
					'name'         => 'Stale Participant',
					'capabilities' => array(),
				),
			)
		);

		$result = bbp_add_forums_roles( $wp_roles );

		$this->assertSame( $wp_roles, $result );
		$this->assertArrayHasKey( 'custom_blog_role', $result->roles );
		foreach ( bbp_get_dynamic_roles() as $role_id => $details ) {
			$this->assertSame( $details, $result->roles[ $role_id ] );
			$this->assertSame( $details['name'], $result->role_names[ $role_id ] );
			$this->assertSame( $details['capabilities'], $result->role_objects[ $role_id ]->capabilities );
		}
	}

	/**
	 * @covers ::bbp_add_forums_roles
	 */
	public function test_forum_roles_can_be_added_to_the_global_roles_object() {
		global $wp_roles;

		$original_wp_roles = $wp_roles;
		$wp_roles          = $this->get_test_roles(
			array(
				'custom_blog_role' => array(
					'name'         => 'Custom Blog Role',
					'capabilities' => array( 'read' => true ),
				),
			)
		);

		try {
			$this->assertSame( $wp_roles, bbp_add_forums_roles() );
			$this->assertArrayHasKey( bbp_get_participant_role(), $wp_roles->roles );
		} finally {
			$wp_roles = $original_wp_roles;
		}
	}

	/**
	 * @covers ::bbp_add_forums_roles
	 */
	public function test_forum_roles_avoid_recursive_global_initialization() {
		global $wp_current_filter;

		$wp_current_filter[] = 'wp_roles_init';
		try {
			$this->assertNull( bbp_add_forums_roles() );
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * @covers ::bbp_add_forums_roles
	 */
	public function test_forum_roles_reject_an_invalid_roles_object() {
		$this->assertNull( bbp_add_forums_roles( new stdClass() ) );
	}

	/**
	 * @covers ::bbp_filter_user_roles_option
	 */
	public function test_user_roles_option_helper_adds_the_dynamic_roles_filter() {
		$tag      = 'option_' . bbp_db()->prefix . 'user_roles';
		$priority = has_filter( $tag, '_bbp_reinit_dynamic_roles' );

		if ( false !== $priority ) {
			remove_filter( $tag, '_bbp_reinit_dynamic_roles', $priority );
		}

		try {
			$this->assertFalse( has_filter( $tag, '_bbp_reinit_dynamic_roles' ) );
			bbp_filter_user_roles_option();
			$this->assertSame( 10, has_filter( $tag, '_bbp_reinit_dynamic_roles' ) );
		} finally {
			remove_filter( $tag, '_bbp_reinit_dynamic_roles', 10 );
			if ( false !== $priority ) {
				add_filter( $tag, '_bbp_reinit_dynamic_roles', $priority );
			}
		}
	}

	/**
	 * @covers ::_bbp_reinit_dynamic_roles
	 */
	public function test_dynamic_roles_are_merged_into_saved_roles() {
		$saved_roles = array(
			'custom_blog_role'       => array( 'name' => 'Custom Blog Role' ),
			bbp_get_keymaster_role() => array( 'name' => 'Stale Keymaster' ),
		);

		$result = _bbp_reinit_dynamic_roles( $saved_roles );

		$this->assertSame( array( 'name' => 'Custom Blog Role' ), $result['custom_blog_role'] );
		foreach ( bbp_get_dynamic_roles() as $role_id => $details ) {
			$this->assertSame( $details, $result[ $role_id ] );
		}
	}

	/**
	 * @covers ::bbp_get_dynamic_roles
	 */
	public function test_dynamic_roles_are_arrays_and_are_filterable_with_source_objects() {
		$source_roles = null;
		$filter       = function( $roles, $wp_roles ) use ( &$source_roles ) {
			$source_roles         = $wp_roles;
			$roles['custom_role'] = array(
				'name'         => 'Custom Role',
				'capabilities' => array( 'read' => true ),
			);
			return $roles;
		};

		add_filter( 'bbp_get_dynamic_roles', $filter, 10, 2 );
		try {
			$roles = bbp_get_dynamic_roles();

			$this->assertSame( bbpress()->roles, $source_roles );
			$this->assertSame( 'Keymaster', $roles[ bbp_get_keymaster_role() ]['name'] );
			$this->assertSame( bbp_get_caps_for_role( bbp_get_keymaster_role() ), $roles[ bbp_get_keymaster_role() ]['capabilities'] );
			$this->assertSame( array( 'name' => 'Custom Role', 'capabilities' => array( 'read' => true ) ), $roles['custom_role'] );
		} finally {
			remove_filter( 'bbp_get_dynamic_roles', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_dynamic_role_name
	 */
	public function test_dynamic_role_name_handles_known_unknown_and_filtered_roles() {
		$this->assertSame( 'Keymaster', bbp_get_dynamic_role_name( bbp_get_keymaster_role() ) );
		$this->assertSame( '', bbp_get_dynamic_role_name( 'missing_role' ) );

		$received = array();
		$filter   = function( $name, $role_id, $roles ) use ( &$received ) {
			$received = array( $name, $role_id, $roles );
			return 'Filtered Role';
		};

		add_filter( 'bbp_get_dynamic_role_name', $filter, 10, 3 );
		try {
			$this->assertSame( 'Filtered Role', bbp_get_dynamic_role_name( 'missing_role' ) );
			$this->assertSame( '', $received[0] );
			$this->assertSame( 'missing_role', $received[1] );
			$this->assertSame( bbp_get_dynamic_roles(), $received[2] );
		} finally {
			remove_filter( 'bbp_get_dynamic_role_name', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_filter_blog_editable_roles
	 */
	public function test_editable_roles_filter_removes_only_dynamic_roles() {
		$roles = array(
			'administrator'         => array( 'name' => 'Administrator' ),
			bbp_get_moderator_role() => array( 'name' => 'Moderator' ),
			'bbp_moderator_custom'  => array( 'name' => 'Custom Moderator' ),
		);

		$this->assertSame(
			array(
				'administrator'        => array( 'name' => 'Administrator' ),
				'bbp_moderator_custom' => array( 'name' => 'Custom Moderator' ),
			),
			bbp_filter_blog_editable_roles( $roles )
		);
	}

	/**
	 * @dataProvider data_role_id_filters
	 *
	 * @covers ::bbp_get_keymaster_role
	 * @covers ::bbp_get_moderator_role
	 * @covers ::bbp_get_participant_role
	 * @covers ::bbp_get_spectator_role
	 * @covers ::bbp_get_blocked_role
	 */
	public function test_role_ids_are_filterable( $function, $default ) {
		$this->assertSame( $default, $function() );
		$expected_caps = bbp_get_caps_for_role( $default );

		$filter = function() {
			return 'custom_forum_role';
		};
		add_filter( $function, $filter );
		try {
			$this->assertSame( 'custom_forum_role', $function() );
			$this->assertSame( $expected_caps, bbp_get_caps_for_role( 'custom_forum_role' ) );
		} finally {
			remove_filter( $function, $filter );
		}
	}

	/**
	 * Data provider for test_role_ids_are_filterable().
	 */
	public function data_role_id_filters() {
		return array(
			'keymaster'   => array( 'bbp_get_keymaster_role',   'bbp_keymaster'   ),
			'moderator'   => array( 'bbp_get_moderator_role',   'bbp_moderator'   ),
			'participant' => array( 'bbp_get_participant_role', 'bbp_participant' ),
			'spectator'   => array( 'bbp_get_spectator_role',   'bbp_spectator'   ),
			'blocked'     => array( 'bbp_get_blocked_role',     'bbp_blocked'     ),
		);
	}

	/**
	 * @covers ::bbp_add_roles
	 */
	public function test_deprecated_add_roles_reports_incorrect_usage() {
		$this->setExpectedIncorrectUsage( 'bbp_add_roles' );
		$this->assertNull( bbp_add_roles() );
	}

	/**
	 * @covers ::bbp_remove_roles
	 */
	public function test_remove_roles_removes_dynamic_and_legacy_roles_only() {
		global $wp_roles;

		$original_wp_roles = $wp_roles;
		$roles             = array(
			'subscriber'  => array(
				'name'         => 'Subscriber',
				'capabilities' => array( 'read' => true ),
			),
			'bbp_visitor' => array(
				'name'         => 'Visitor',
				'capabilities' => array( 'spectate' => true ),
			),
		);
		foreach ( bbp_get_dynamic_roles() as $role_id => $details ) {
			$roles[ $role_id ] = $details;
		}

		$wp_roles = $this->get_test_roles( $roles );
		try {
			bbp_remove_roles();

			$this->assertArrayHasKey( 'subscriber', $wp_roles->roles );
			foreach ( array( 'roles', 'role_objects', 'role_names' ) as $property ) {
				$this->assertArrayNotHasKey( 'bbp_visitor', $wp_roles->{$property} );
			}
			foreach ( array_keys( bbp_get_dynamic_roles() ) as $role_id ) {
				foreach ( array( 'roles', 'role_objects', 'role_names' ) as $property ) {
					$this->assertArrayNotHasKey( $role_id, $wp_roles->{$property} );
				}
			}
		} finally {
			$wp_roles = $original_wp_roles;
		}
	}

	/**
	 * Create an in-memory WP_Roles object.
	 *
	 * @param array $roles Role definitions.
	 * @return WP_Roles
	 */
	private function get_test_roles( $roles ) {
		// WP_Roles initializes from the database before these arrays are replaced.
		$wp_roles               = new WP_Roles();
		$wp_roles->use_db       = false;
		$wp_roles->roles        = $roles;
		$wp_roles->role_objects = array();
		$wp_roles->role_names   = array();

		foreach ( $roles as $role_id => $details ) {
			$wp_roles->role_objects[ $role_id ] = new WP_Role( $role_id, $details['capabilities'] );
			$wp_roles->role_names[ $role_id ]   = $details['name'];
		}

		return $wp_roles;
	}
}
