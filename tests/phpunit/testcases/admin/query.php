<?php

/**
 * Tests for administration query filters.
 *
 * @group admin
 */
class BBP_Tests_Admin_Query extends BBP_UnitTestCase {

	protected $old_get;

	public function setUp(): void {
		parent::setUp();

		$this->old_get = $_GET;
	}

	public function tearDown(): void {
		$_GET = $this->old_get;

		parent::tearDown();
	}

	/**
	 * @dataProvider get_admin_classes
	 */
	public function test_filter_post_rows_casts_forum_id_to_integer( $class_name, $class_file ) {
		require_once BBP_PLUGIN_DIR . $class_file;

		$_GET['bbp_forum_id'] = '123-invalid';

		$reflection = new ReflectionClass( $class_name );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$query_vars = $admin->filter_post_rows( array() );

		$this->assertSame( '_bbp_forum_id', $query_vars['meta_key'] );
		$this->assertSame( 'NUMERIC', $query_vars['meta_type'] );
		$this->assertSame( 123, $query_vars['meta_value'] );
	}

	public function get_admin_classes() {
		return array(
			'replies' => array( 'BBP_Replies_Admin', 'includes/admin/replies.php' ),
			'topics'  => array( 'BBP_Topics_Admin',  'includes/admin/topics.php'  ),
		);
	}
}
