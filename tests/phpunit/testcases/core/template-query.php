<?php

/**
 * Tests for parsing bbPress query state.
 *
 * @group core
 * @group template_functions
 */
class BBP_Tests_Core_Template_Query extends BBP_UnitTestCase {

	/**
	 * Original main query.
	 *
	 * @var WP_Query
	 */
	private $original_wp_the_query;

	/**
	 * Original current query.
	 *
	 * @var WP_Query
	 */
	private $original_wp_query;

	/**
	 * Original displayed user.
	 *
	 * @var mixed
	 */
	private $original_displayed_user;

	public function setUp(): void {
		parent::setUp();

		$this->original_wp_the_query   = $GLOBALS['wp_the_query'];
		$this->original_wp_query       = $GLOBALS['wp_query'];
		$this->original_displayed_user = isset( bbpress()->displayed_user ) ? bbpress()->displayed_user : null;
	}

	public function tearDown(): void {
		$GLOBALS['wp_the_query'] = $this->original_wp_the_query;
		$GLOBALS['wp_query']     = $this->original_wp_query;

		if ( is_null( $this->original_displayed_user ) ) {
			unset( bbpress()->displayed_user );
		} else {
			bbpress()->displayed_user = $this->original_displayed_user;
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_clears_singular_state_on_user_routes() {
		$user_id = $this->factory->user->create();
		$cases   = array(
			'page ID'   => array( 'page_id', PHP_INT_MAX ),
			'page name' => array( 'pagename', 'missing-page' ),
			'post ID'   => array( 'p', PHP_INT_MAX ),
			'post name' => array( 'name', 'missing-post' ),
		);

		foreach ( $cases as $case => $query_var ) {
			$query = $this->set_main_query();
			$query->parse_query(
				array(
					bbp_get_user_rewrite_id()         => $user_id,
					bbp_get_user_replies_rewrite_id() => 1,
					$query_var[0]                      => $query_var[1],
				)
			);

			$this->assertTrue( $query->bbp_is_single_user, $case );
			$this->assertTrue( $query->bbp_is_single_user_replies, $case );
			$this->assertFalse( $query->is_page, $case );
			$this->assertFalse( $query->is_single, $case );
			$this->assertFalse( $query->is_singular, $case );
		}
	}

	/**
	 * Set and return a new main query.
	 *
	 * @return WP_Query Main query.
	 */
	private function set_main_query() {
		$query = new WP_Query();

		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['wp_query']     = $query;

		return $query;
	}
}
