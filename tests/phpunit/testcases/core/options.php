<?php

/**
 * Tests for core option functions.
 *
 * @group core
 * @group options
 */
class BBP_Tests_Core_Options extends BBP_UnitTestCase {

	private $saved_options = array();

	public function tearDown(): void {
		foreach ( $this->saved_options as $option => $saved ) {
			if ( $saved['exists'] ) {
				update_option( $option, $saved['value'] );
			} else {
				delete_option( $option );
			}
		}

		parent::tearDown();
	}

	private function set_option( $option, $value ) {
		if ( ! isset( $this->saved_options[ $option ] ) ) {
			$missing = new stdClass();
			$current = get_option( $option, $missing );

			$this->saved_options[ $option ] = array(
				'exists' => ( $missing !== $current ),
				'value'  => $current,
			);
		}

		delete_option( $option );
		add_option( $option, $value );
	}

	/**
	 * @ticket BBP3707
	 * @covers ::bbp_allow_threaded_replies
	 */
	public function test_bbp_allow_threaded_replies_preserves_the_legacy_filter_before_the_public_filter() {
		$old_options = bbpress()->options;
		$order       = array();
		$legacy      = function( $allow ) use ( &$order ) {
			$order[] = 'legacy';
			$this->assertTrue( $allow );
			return false;
		};
		$public      = function( $allow ) use ( &$order ) {
			$order[] = 'public';
			$this->assertFalse( $allow );
			return true;
		};

		bbpress()->options['_bbp_allow_threaded_replies'] = 1;
		add_filter( '_bbp_allow_threaded_replies', $legacy );
		add_filter( 'bbp_allow_threaded_replies', $public );
		try {
			$this->assertTrue( bbp_allow_threaded_replies() );
		} finally {
			remove_filter( '_bbp_allow_threaded_replies', $legacy );
			remove_filter( 'bbp_allow_threaded_replies', $public );
			bbpress()->options = $old_options;
		}

		$this->assertSame( array( 'legacy', 'public' ), $order );
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers ::bbp_is_valid_engagements_strategy
	 */
	public function test_bbp_is_valid_engagements_strategy_requires_a_supported_class() {
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'meta' ) );
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'user' ) );
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'term' ) );
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'standalone' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 'missing' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 'base' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 'BASE' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( '' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( array( 'meta' ) ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( null ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 1 ) );
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers ::bbp_engagements_strategy
	 */
	public function test_bbp_engagements_strategy_requires_a_supported_class() {
		$this->set_option( '_bbp_engagements_strategy', 'user' );
		$this->assertSame( 'user', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', 'term' );
		$this->assertSame( 'term', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', 'missing' );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', array( 'meta' ) );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', null );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', new stdClass() );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', 'base' );
		$this->assertSame( 'meta', bbp_engagements_strategy() );
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers ::bbp_engagements_strategy
	 */
	public function test_bbp_engagements_strategy_requires_a_supported_filtered_class() {
		$this->set_option( '_bbp_engagements_strategy', array( 'meta' ) );

		$filtered = null;
		$filter   = function( $integration, $default ) use ( &$filtered ) {
			$this->assertSame( 'meta', $integration );
			$this->assertSame( 'fallback', $default );

			return $filtered;
		};
		add_filter( 'bbp_engagements_strategy', $filter, 10, 2 );

		try {
			$filtered = array( 'meta' );
			$this->assertSame( 'meta', bbp_engagements_strategy( 'fallback' ) );

			$filtered = 'missing';
			$this->assertSame( 'meta', bbp_engagements_strategy( 'fallback' ) );

			$filtered = 'base';
			$this->assertSame( 'meta', bbp_engagements_strategy( 'fallback' ) );

			$filtered = 'term';
			$this->assertSame( 'term', bbp_engagements_strategy( 'fallback' ) );
		} finally {
			remove_filter( 'bbp_engagements_strategy', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers bbPress::setup_engagements
	 * @covers ::bbp_engagements_strategy
	 * @covers ::bbp_is_valid_engagements_strategy
	 */
	public function test_setup_engagements_uses_meta_for_an_invalid_strategy() {
		$engagements = bbpress()->engagements;
		$filtered    = null;
		$filter      = function() use ( &$filtered ) {
			return $filtered;
		};

		try {
			foreach ( array( array( 'meta' ), 'base', 'BASE' ) as $strategy ) {
				$this->set_option( '_bbp_engagements_strategy', $strategy );
				bbpress()->setup_engagements();
				$this->assertInstanceOf( 'BBP_User_Engagements_Meta', bbpress()->engagements );
			}

			$this->set_option( '_bbp_engagements_strategy', 'standalone' );
			bbpress()->setup_engagements();
			$this->assertInstanceOf( 'BBP_User_Engagements_Standalone', bbpress()->engagements );

			$this->set_option( '_bbp_engagements_strategy', 'meta' );
			add_filter( 'bbp_engagements_strategy', $filter );

			foreach ( array( new stdClass(), 'missing', 'base', 'BASE' ) as $filtered ) {
				bbpress()->setup_engagements();
				$this->assertInstanceOf( 'BBP_User_Engagements_Meta', bbpress()->engagements );
			}
		} finally {
			remove_filter( 'bbp_engagements_strategy', $filter );
			bbpress()->engagements = $engagements;
		}
	}
}

/**
 * Standalone test strategy for legacy duck-typing compatibility.
 */
class BBP_User_Engagements_Standalone {
}
