<?php

/**
 * Tests for the user component option functions.
 *
 * @group users
 * @group functions
 * @group options
 */
class BBP_Tests_Users_Functions_Options extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_default_user_options
	 */
	public function test_bbp_get_default_user_options() {
		$defaults = array(
			'_bbp_last_posted' => '0',
			'_bbp_topic_count' => '0',
			'_bbp_reply_count' => '0',
		);
		$this->assertSame( $defaults, bbp_get_default_user_options() );

		$filter = function( $options ) {
			$options['_bbp_custom_option'] = 'custom';
			return $options;
		};
		add_filter( 'bbp_get_default_user_options', $filter );

		try {
			$defaults['_bbp_custom_option'] = 'custom';
			$this->assertSame( $defaults, bbp_get_default_user_options() );
		} finally {
			remove_filter( 'bbp_get_default_user_options', $filter );
		}

		$scalar_filter = function() {
			return 'scalar';
		};
		add_filter( 'bbp_get_default_user_options', $scalar_filter );
		try {
			$this->assertSame( array( 'scalar' ), bbp_get_default_user_options() );
		} finally {
			remove_filter( 'bbp_get_default_user_options', $scalar_filter );
		}
	}

	/**
	 * @covers ::bbp_add_user_options
	 */
	public function test_bbp_add_user_options() {
		$user_id = $this->factory->user->create();
		$called  = null;
		$action  = function( $id ) use ( &$called ) {
			$called = $id;
		};
		$filter  = function( $options ) {
			$options['_bbp_custom_option'] = 'custom';
			return $options;
		};

		update_user_option( $user_id, '_bbp_topic_count', '9' );
		update_user_option( $user_id, 'unrelated_option', 'keep' );
		add_action( 'bbp_add_user_options', $action );
		add_filter( 'bbp_get_default_user_options', $filter );

		try {
			$invalid_user = function() {
				return 0;
			};
			add_filter( 'bbp_get_user_id', $invalid_user );
			try {
				bbp_add_user_options( 0 );
			} finally {
				remove_filter( 'bbp_get_user_id', $invalid_user );
			}
			$this->assertNull( $called );
			bbp_add_user_options( $user_id );
		} finally {
			remove_action( 'bbp_add_user_options', $action );
			remove_filter( 'bbp_get_default_user_options', $filter );
		}

		$this->assertSame( $user_id, $called );
		$this->assertSame( '0', get_user_option( '_bbp_last_posted', $user_id ) );
		$this->assertSame( '0', get_user_option( '_bbp_topic_count', $user_id ) );
		$this->assertSame( '0', get_user_option( '_bbp_reply_count', $user_id ) );
		$this->assertSame( 'custom', get_user_option( '_bbp_custom_option', $user_id ) );
		$this->assertSame( 'keep', get_user_option( 'unrelated_option', $user_id ) );
	}

	/**
	 * @covers ::bbp_delete_user_options
	 */
	public function test_bbp_delete_user_options() {
		$user_id = $this->factory->user->create();
		$called  = null;
		$action  = function( $id ) use ( &$called ) {
			$called = $id;
		};
		$filter  = function( $options ) {
			$options['_bbp_custom_option'] = 'custom';
			return $options;
		};

		add_filter( 'bbp_get_default_user_options', $filter );
		add_action( 'bbp_delete_user_options', $action );

		try {
			bbp_add_user_options( $user_id );
			update_user_option( $user_id, 'unrelated_option', 'keep' );
			$invalid_user = function() {
				return 0;
			};
			add_filter( 'bbp_get_user_id', $invalid_user );
			try {
				bbp_delete_user_options( 0 );
			} finally {
				remove_filter( 'bbp_get_user_id', $invalid_user );
			}
			$this->assertNull( $called );
			$this->assertSame( '0', get_user_option( '_bbp_topic_count', $user_id ) );
			bbp_delete_user_options( $user_id );
		} finally {
			remove_action( 'bbp_delete_user_options', $action );
			remove_filter( 'bbp_get_default_user_options', $filter );
		}

		$this->assertSame( $user_id, $called );
		$this->assertFalse( get_user_option( '_bbp_last_posted', $user_id ) );
		$this->assertFalse( get_user_option( '_bbp_topic_count', $user_id ) );
		$this->assertFalse( get_user_option( '_bbp_reply_count', $user_id ) );
		$this->assertFalse( get_user_option( '_bbp_custom_option', $user_id ) );
		$this->assertSame( 'keep', get_user_option( 'unrelated_option', $user_id ) );
	}

	/**
	 * @covers ::bbp_setup_user_option_filters
	 */
	public function test_bbp_setup_user_option_filters() {
		$keys        = array_keys( bbp_get_default_user_options() );
		$priorities  = array();
		$old_options = bbpress()->user_options;
		$called      = 0;
		$action      = function() use ( &$called ) {
			++$called;
		};

		foreach ( $keys as $key ) {
			$priorities[ $key ] = has_filter( 'get_user_option_' . $key, 'bbp_filter_get_user_option' );
		}

		add_action( 'bbp_setup_user_option_filters', $action );
		try {
			foreach ( $priorities as $key => $priority ) {
				if ( false !== $priority ) {
					remove_filter( 'get_user_option_' . $key, 'bbp_filter_get_user_option', $priority );
				}
				$this->assertFalse( has_filter( 'get_user_option_' . $key, 'bbp_filter_get_user_option' ) );
			}

			bbp_setup_user_option_filters();
			$this->assertSame( 1, $called );
			foreach ( $keys as $key ) {
				$this->assertSame( 10, has_filter( 'get_user_option_' . $key, 'bbp_filter_get_user_option' ) );
			}

			$user_id = $this->factory->user->create();
			update_user_option( $user_id, '_bbp_topic_count', '2' );
			bbpress()->user_options[ $user_id ] = array( '_bbp_topic_count' => '7' );
			$this->assertSame( '7', get_user_option( '_bbp_topic_count', $user_id ) );
		} finally {
			remove_action( 'bbp_setup_user_option_filters', $action );
			foreach ( $priorities as $key => $priority ) {
				remove_filter( 'get_user_option_' . $key, 'bbp_filter_get_user_option', 10 );
				if ( false !== $priority ) {
					add_filter( 'get_user_option_' . $key, 'bbp_filter_get_user_option', $priority, 3 );
				}
			}
			bbpress()->user_options = $old_options;
		}
	}

	/**
	 * @covers ::bbp_setup_user_option_filters
	 */
	public function test_bbp_setup_user_option_filters_accepts_custom_option() {
		$filter = function( $options ) {
			$options['_bbp_custom_option'] = 'custom';
			return $options;
		};
		add_filter( 'bbp_get_default_user_options', $filter );

		try {
			bbp_setup_user_option_filters();
			$this->assertSame( 10, has_filter( 'get_user_option__bbp_custom_option', 'bbp_filter_get_user_option' ) );
		} finally {
			remove_filter( 'bbp_get_default_user_options', $filter );
			remove_filter( 'get_user_option__bbp_custom_option', 'bbp_filter_get_user_option', 10 );
		}
	}

	/**
	 * @covers ::bbp_filter_get_user_option
	 */
	public function test_bbp_filter_get_user_option() {
		$user_id      = $this->factory->user->create();
		$other_id     = $this->factory->user->create();
		$user         = get_userdata( $user_id );
		$other_user   = get_userdata( $other_id );
		$old_options  = bbpress()->user_options;

		update_user_option( $user_id, '_bbp_topic_count', '2' );

		try {
			bbpress()->user_options[ $user_id ] = array(
				'_bbp_topic_count' => '7',
				'_bbp_reply_count' => '0',
			);

			$this->assertSame( '7', bbp_filter_get_user_option( '2', '_bbp_topic_count', $user ) );
			$this->assertSame( '7', get_user_option( '_bbp_topic_count', $user_id ) );
			$this->assertSame( '2', bbp_filter_get_user_option( '2', '_bbp_reply_count', $user ) );
			$this->assertSame( '2', bbp_filter_get_user_option( '2', '_bbp_last_posted', $user ) );
			$this->assertSame( '2', bbp_filter_get_user_option( '2', '_bbp_topic_count', $other_user ) );
			$this->assertSame( '2', bbp_filter_get_user_option( '2', '_bbp_topic_count', $user_id ) );
			$this->assertFalse( bbp_filter_get_user_option( false, '_bbp_topic_count' ) );
		} finally {
			bbpress()->user_options = $old_options;
		}
	}

	/**
	 * @covers ::bbp_update_user_last_posted
	 */
	public function test_bbp_update_user_last_posted() {
		$user_id = $this->factory->user->create();

		$invalid_user = function() {
			return 0;
		};
		add_filter( 'bbp_get_user_id', $invalid_user );
		try {
			$this->assertFalse( bbp_update_user_last_posted( 0, 123 ) );
		} finally {
			remove_filter( 'bbp_get_user_id', $invalid_user );
		}

		$this->assertNotFalse( bbp_update_user_last_posted( $user_id, 123 ) );
		$this->assertSame( 123, (int) get_user_option( '_bbp_last_posted', $user_id ) );

		$before = time();
		$this->assertNotFalse( bbp_update_user_last_posted( $user_id ) );
		$after  = time();
		$stored = (int) get_user_option( '_bbp_last_posted', $user_id );

		$this->assertGreaterThanOrEqual( $before, $stored );
		$this->assertLessThanOrEqual( $after, $stored );
	}

	/**
	 * @covers ::bbp_user_last_posted
	 * @covers ::bbp_get_user_last_posted
	 */
	public function test_bbp_get_user_last_posted() {
		$user_id = $this->factory->user->create();
		$called  = 0;
		$filter  = function( $value ) use ( &$called ) {
			++$called;
			return $value;
		};
		$invalid_user = function() {
			return 0;
		};
		add_filter( 'bbp_get_user_last_posted', $filter );
		add_filter( 'bbp_get_user_id', $invalid_user );
		try {
			$this->assertFalse( bbp_get_user_last_posted( 0 ) );
			$this->assertSame( 0, $called );
		} finally {
			remove_filter( 'bbp_get_user_id', $invalid_user );
			remove_filter( 'bbp_get_user_last_posted', $filter );
		}

		delete_user_option( $user_id, '_bbp_last_posted' );
		$this->assertFalse( bbp_get_user_last_posted( $user_id ) );

		update_user_option( $user_id, '_bbp_last_posted', 123 );
		$filter = function( $value, $id ) use ( $user_id ) {
			$this->assertSame( $user_id, $id );
			return (int) $value + 1;
		};
		add_filter( 'bbp_get_user_last_posted', $filter, 10, 2 );

		try {
			$this->assertSame( 124, bbp_get_user_last_posted( $user_id ) );
			$this->expectOutputString( '124' );
			bbp_user_last_posted( $user_id );
		} finally {
			remove_filter( 'bbp_get_user_last_posted', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_add_user_options
	 * @covers ::bbp_delete_user_options
	 */
	public function test_bbp_user_options_are_site_specific() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$user_id = $this->factory->user->create();
		$site_id = $this->factory->blog->create();

		bbp_add_user_options( $user_id );
		bbp_update_user_last_posted( $user_id, 123 );
		switch_to_blog( $site_id );

		try {
			$this->assertFalse( get_user_option( '_bbp_topic_count', $user_id ) );
			bbp_add_user_options( $user_id );
			update_user_option( $user_id, '_bbp_topic_count', '5' );
			$this->assertSame( '5', get_user_option( '_bbp_topic_count', $user_id ) );
			bbp_delete_user_options( $user_id );
			$this->assertFalse( get_user_option( '_bbp_topic_count', $user_id ) );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( '0', get_user_option( '_bbp_topic_count', $user_id ) );
		$this->assertSame( 123, (int) get_user_option( '_bbp_last_posted', $user_id ) );
	}
}
