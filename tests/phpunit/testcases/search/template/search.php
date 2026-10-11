<?php

/**
 * Tests for the search component search template functions.
 *
 * @group search
 * @group template
 */
class BBP_Tests_Search_Template_Search extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_search_results
	 */
	public function test_bbp_has_search_results() {
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'UniqueSearchNeedle' ) );

		$this->assertTrue( bbp_has_search_results( array( 's' => 'UniqueSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) ) );
		$this->assertSame( $topic_id, bbpress()->search_query->posts[0]->ID );
		$this->assertFalse( bbp_has_search_results( array( 's' => 'NoMatchingSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) ) );
	}

	/**
	 * @covers ::bbp_search_results
	 */
	public function test_bbp_search_results() {
		$this->factory->topic->create( array( 'post_title' => 'UniqueSearchNeedle' ) );
		bbp_has_search_results( array( 's' => 'UniqueSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) );

		$this->assertTrue( bbp_search_results() );
		bbp_the_search_result();
		$this->assertFalse( bbp_search_results() );
	}

	/**
	 * @covers ::bbp_the_search_result
	 */
	public function test_bbp_the_search_result() {
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'UniqueSearchNeedle' ) );
		bbp_has_search_results( array( 's' => 'UniqueSearchNeedle', 'post_type' => bbp_get_topic_post_type() ) );

		$this->assertTrue( bbp_search_results() );
		bbp_the_search_result();
		$this->assertSame( $topic_id, get_the_ID() );
		$this->assertSame( $topic_id, bbpress()->current_topic_id );
		$this->assertFalse( bbp_search_results() );
	}

	/**
	 * @covers ::bbp_search_title
	 * @covers ::bbp_get_search_title
	 */
	public function test_bbp_get_search_title() {
		$this->assertSame( 'Search', bbp_get_search_title() );
		$filter = static function() {
			return 'sample query';
		};
		add_filter( 'bbp_get_search_terms', $filter );
		try {
			$title = bbp_get_search_title();
			$this->assertStringContainsString( 'sample query', $title );
			$this->expectOutputString( $title );
			bbp_search_title();
		} finally {
			remove_filter( 'bbp_get_search_terms', $filter );
		}
	}

	/**
	 * @covers ::bbp_search_url
	 * @covers ::bbp_get_search_url
	 */
	public function test_bbp_get_search_url() {
		$url = bbp_get_search_url();

		$this->assertStringContainsString( bbp_get_search_rewrite_id(), $url );
		$this->expectOutputString( esc_url( $url ) );
		bbp_search_url();
	}

	/**
	 * @covers ::bbp_search_results_url
	 * @covers ::bbp_get_search_results_url
	 */
	public function test_bbp_get_search_results_url() {
		$filter = static function() {
			return 'sample query';
		};
		add_filter( 'bbp_get_search_terms', $filter );
		try {
			$url = bbp_get_search_results_url();
			$this->assertStringContainsString( 'sample', $url );
			$this->expectOutputString( esc_url( $url ) );
			bbp_search_results_url();
		} finally {
			remove_filter( 'bbp_get_search_terms', $filter );
		}
	}

	/**
	 * @covers ::bbp_search_terms
	 * @covers ::bbp_get_search_terms
	 */
	public function test_bbp_get_search_terms() {
		$this->assertFalse( bbp_get_search_terms() );
		$this->assertSame( 'sample-query', bbp_get_search_terms( 'Sample Query' ) );
		$this->expectOutputString( 'sample-query' );
		bbp_search_terms( 'Sample Query' );
	}

	/**
	 * @covers ::bbp_get_search_pagination_base
	 */
	public function test_bbp_get_search_pagination_base_with_ugly_permalinks() {
		global $wp_query;

		$old_permalinks = get_option( 'permalink_structure' );
		$old_request_uri = $_SERVER['REQUEST_URI'];
		$old_singular    = $wp_query->is_singular;

		try {
			$this->set_permalink_structure();
			$_SERVER['REQUEST_URI'] = '/?bbp_search=sample-query';

			$this->assertSame( '/?bbp_search=sample-query&paged=%#%', bbp_get_search_pagination_base() );

			$wp_query->is_singular = true;
			$this->assertSame( '/?bbp_search=sample-query&paged=%#%', bbp_get_search_pagination_base() );
		} finally {
			$_SERVER['REQUEST_URI'] = $old_request_uri;
			$wp_query->is_singular  = $old_singular;
			$this->set_permalink_structure( $old_permalinks );
		}
	}

	/**
	 * @covers ::bbp_get_search_pagination_base
	 */
	public function test_bbp_get_search_pagination_base_with_pretty_permalinks() {
		$old_permalinks = get_option( 'permalink_structure' );
		$filter         = static function() {
			return home_url( '/forums/search/sample-query/' );
		};

		add_filter( 'bbp_get_search_results_url', $filter );

		try {
			$this->set_permalink_structure( '/%postname%/' );

			$this->assertFalse( is_singular() );
			$this->assertSame(
				home_url( '/forums/search/sample-query/page/%#%/' ),
				bbp_get_search_pagination_base()
			);
		} finally {
			remove_filter( 'bbp_get_search_results_url', $filter );
			$this->set_permalink_structure( $old_permalinks );
		}
	}

	/**
	 * @covers ::bbp_get_search_pagination_base
	 */
	public function test_bbp_get_search_pagination_base_respects_permalink_trailing_slashes() {
		$old_permalinks = get_option( 'permalink_structure' );
		$filter         = static function() {
			return home_url( '/forums/search/sample-query' );
		};

		add_filter( 'bbp_get_search_results_url', $filter );

		try {
			$this->set_permalink_structure( '/%postname%' );

			$this->assertSame(
				home_url( '/forums/search/sample-query/page/%#%' ),
				bbp_get_search_pagination_base()
			);
		} finally {
			remove_filter( 'bbp_get_search_results_url', $filter );
			$this->set_permalink_structure( $old_permalinks );
		}
	}

	/**
	 * @covers ::bbp_get_search_pagination_base
	 */
	public function test_bbp_get_search_pagination_base_for_singular_content() {
		global $post, $wp_query;

		$old_permalinks = get_option( 'permalink_structure' );
		$old_post       = $post;
		$old_singular   = $wp_query->is_singular;
		$post_id        = self::factory()->post->create( array( 'post_name' => 'search-shortcode' ) );

		try {
			$this->set_permalink_structure( '/%postname%/' );
			$post                  = get_post( $post_id );
			$wp_query->is_singular = true;

			$this->assertTrue( is_singular() );
			$this->assertSame(
				home_url( '/search-shortcode/page/%#%/' ),
				bbp_get_search_pagination_base()
			);
		} finally {
			$post                  = $old_post;
			$wp_query->is_singular = $old_singular;
			$this->set_permalink_structure( $old_permalinks );
		}
	}

	/**
	 * @covers ::bbp_get_search_pagination_base
	 */
	public function test_bbp_get_search_pagination_base_is_filterable() {
		$old_request_uri = $_SERVER['REQUEST_URI'];
		$received_base   = null;
		$filter          = static function( $base ) use ( &$received_base ) {
			$received_base = $base;

			return 'https://example.org/filtered/';
		};

		add_filter( 'bbp_get_search_pagination_base', $filter );

		try {
			$_SERVER['REQUEST_URI'] = '/?bbp_search=filter-base';

			$this->assertSame( 'https://example.org/filtered/', bbp_get_search_pagination_base() );
			$this->assertSame( '/?bbp_search=filter-base&paged=%#%', $received_base );
		} finally {
			$_SERVER['REQUEST_URI'] = $old_request_uri;
			remove_filter( 'bbp_get_search_pagination_base', $filter );
		}
	}

	/**
	 * @covers ::bbp_search_pagination_count
	 * @covers ::bbp_get_search_pagination_count
	 */
	public function test_bbp_get_search_pagination_count() {
		$this->factory->topic->create_many( 2, array( 'post_title' => 'SearchPageNeedle' ) );
		bbp_has_search_results( array(
			's'              => 'SearchPageNeedle',
			'post_type'      => bbp_get_topic_post_type(),
			'posts_per_page' => 1,
		) );
		$count = bbp_get_search_pagination_count();

		$this->assertStringContainsString( 'Viewing', $count );
		$this->assertStringContainsString( '2 total', $count );
		$this->expectOutputString( $count );
		bbp_search_pagination_count();
	}

	/**
	 * @covers ::bbp_search_pagination_links
	 * @covers ::bbp_get_search_pagination_links
	 */
	public function test_bbp_get_search_pagination_links() {
		$this->factory->topic->create_many( 2, array( 'post_title' => 'SearchPageNeedle' ) );
		bbp_has_search_results( array(
			's'              => 'SearchPageNeedle',
			'post_type'      => bbp_get_topic_post_type(),
			'posts_per_page' => 1,
		) );
		$links = bbp_get_search_pagination_links();

		$this->assertNotEmpty( $links );
		$this->assertStringContainsString( 'page-numbers', $links );
		$this->expectOutputString( $links );
		bbp_search_pagination_links();
	}
}
