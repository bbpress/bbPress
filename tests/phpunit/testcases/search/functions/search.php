<?php

/**
 * Tests for the search component search functions.
 *
 * @group search
 * @group functions
 */
class BBP_Tests_Search_Functions_Search extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_search_query
	 */
	public function test_bbp_search_query() {
		$old_search_query = bbpress()->search_query;
		$defaults         = function() {
			return array( 's' => 'bbp-3695-default-no-results', 'posts_per_page' => 10 );
		};

		add_filter( 'bbp_get_search_query_args', $defaults );
		try {
			$this->assertFalse( bbp_search_query() );
			$this->assertSame( 'bbp-3695-default-no-results', bbpress()->search_query->get( 's' ) );
			$this->assertSame( 10, bbpress()->search_query->get( 'posts_per_page' ) );

			$this->assertFalse( bbp_search_query( array( 's' => 'bbp-3695-no-results', 'posts_per_page' => 2 ) ) );
			$this->assertSame( 'bbp-3695-no-results', bbpress()->search_query->get( 's' ) );
			$this->assertSame( 2, bbpress()->search_query->get( 'posts_per_page' ) );
		} finally {
			remove_filter( 'bbp_get_search_query_args', $defaults );
			bbpress()->search_query = $old_search_query;
		}
	}

	/**
	 * @covers ::bbp_get_search_query_args
	 */
	public function test_bbp_get_search_query_args() {
		$terms = function() {
			return 'bbpress-needle';
		};
		$filtered = function( $args ) {
			$this->assertSame( array( 's' => 'bbpress-needle' ), $args );
			return array( 's' => 'filtered' );
		};

		$no_terms = function() {
			return false;
		};
		add_filter( 'bbp_get_search_terms', $no_terms );
		try {
			$this->assertSame( array(), bbp_get_search_query_args() );
		} finally {
			remove_filter( 'bbp_get_search_terms', $no_terms );
		}
		add_filter( 'bbp_get_search_terms', $terms );
		try {
			$this->assertSame( array( 's' => 'bbpress-needle' ), bbp_get_search_query_args() );
			add_filter( 'bbp_get_search_query_args', $filtered );
			try {
				$this->assertSame( array( 's' => 'filtered' ), bbp_get_search_query_args() );
			} finally {
				remove_filter( 'bbp_get_search_query_args', $filtered );
			}
		} finally {
			remove_filter( 'bbp_get_search_terms', $terms );
		}
	}

	/**
	 * @covers ::bbp_search_results_redirect
	 */
	public function test_bbp_search_results_redirect() {
		$action         = isset( $_GET['action'] ) ? $_GET['action'] : null;
		$pretty_calls   = 0;
		$url_calls      = 0;
		$pretty_filter  = function( $pretty ) use ( &$pretty_calls ) {
			$pretty_calls++;
			return $pretty;
		};
		$url_filter     = function() use ( &$url_calls ) {
			$url_calls++;
			return '';
		};

		add_filter( 'bbp_pretty_urls', $pretty_filter );
		add_filter( 'bbp_get_search_results_url', $url_filter );
		try {
			unset( $_GET['action'] );
			$this->assertNull( bbp_search_results_redirect() );
			$_GET['action'] = 'other';
			$this->assertNull( bbp_search_results_redirect() );
			$this->assertSame( 0, $pretty_calls );

			$_GET['action'] = 'bbp-search-request';
			$this->assertNull( bbp_search_results_redirect() );
			$this->assertSame( 1, $pretty_calls );
			$this->assertSame( 0, $url_calls );

			add_filter( 'bbp_pretty_urls', '__return_true', 20 );
			try {
				$this->assertNull( bbp_search_results_redirect() );
				$this->assertSame( 1, $url_calls );
			} finally {
				remove_filter( 'bbp_pretty_urls', '__return_true', 20 );
			}
		} finally {
			remove_filter( 'bbp_get_search_results_url', $url_filter );
			remove_filter( 'bbp_pretty_urls', $pretty_filter );
			if ( is_null( $action ) ) {
				unset( $_GET['action'] );
			} else {
				$_GET['action'] = $action;
			}
		}
	}

	/**
	 * @covers ::bbp_get_search_type_ids
	 */
	public function test_bbp_get_search_type_ids() {
		$this->assertSame( array( 's', 'fs', 'ts', 'rs' ), bbp_get_search_type_ids() );

		$filter = function() {
			return array( 'custom' );
		};
		add_filter( 'bbp_get_search_types', $filter );
		try {
			$this->assertSame( array( 'custom' ), bbp_get_search_type_ids() );
		} finally {
			remove_filter( 'bbp_get_search_types', $filter );
		}
	}

	/**
	 * @covers ::bbp_sanitize_search_request
	 */
	public function test_bbp_sanitize_search_request() {
		$request = $_REQUEST;
		$filter  = function( $terms, $query_arg ) {
			$this->assertSame( 's', $query_arg );
			$this->assertSame( 'alpha beta', $terms );
			return 'filtered';
		};

		try {
			$_REQUEST = array();
			$this->assertFalse( bbp_sanitize_search_request( 'invalid' ) );
			$this->assertFalse( bbp_sanitize_search_request() );

			$_REQUEST['s'] = ' <strong>alpha</strong> beta ';
			$this->assertSame( 'alpha beta', bbp_sanitize_search_request() );

			$_REQUEST['s'] = array( 'alpha', array( 'ignored' ), 'beta' );
			$this->assertSame( 'alpha beta', bbp_sanitize_search_request() );
			add_filter( 'bbp_sanitize_search_request', $filter, 10, 2 );
			try {
				$this->assertSame( 'filtered', bbp_sanitize_search_request() );
			} finally {
				remove_filter( 'bbp_sanitize_search_request', $filter );
			}
		} finally {
			$_REQUEST = $request;
		}
	}
}
