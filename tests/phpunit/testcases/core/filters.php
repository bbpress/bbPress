<?php

/**
 * Tests for the core filters.
 *
 * @group core
 * @group filters
 */
class BBP_Tests_Core_Filters extends BBP_UnitTestCase {

	/**
	 * Verify empty route query variables are removed before feed handling.
	 */
	public function test_request_filters_normalize_route_query_vars_first() {
		$this->assertSame( 5, has_filter( 'bbp_request', 'bbp_request_remove_empty_query_vars' ) );
		$this->assertSame( 10, has_filter( 'bbp_request', 'bbp_request_feed_trap' ) );
	}

	/**
	 * @covers ::_bbp_filter_locale
	 * @covers ::bbp_plugin_locale
	 */
	public function test_deprecated_locale_filter_runs_only_for_the_bbpress_domain() {
		$filtered = array();
		$filter   = function ( $locale, $domain ) use ( &$filtered ) {
			$filtered[] = array( $locale, $domain );

			return 'bbpress-' . $locale;
		};

		add_filter( 'bbpress_locale', $filter, 10, 2 );
		try {
			$this->assertSame( 'en_US', apply_filters( 'plugin_locale', 'en_US', 'other-domain' ) );
			$this->assertSame( 'bbpress-en_US', apply_filters( 'plugin_locale', 'en_US', 'bbpress' ) );
			$this->assertSame( array( array( 'en_US', 'bbpress' ) ), $filtered );
		} finally {
			remove_filter( 'bbpress_locale', $filter, 10 );
		}
	}
}
