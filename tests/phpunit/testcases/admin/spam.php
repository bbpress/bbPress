<?php

/**
 * Tests for the administration Empty Spam controls.
 *
 * @group admin
 * @group topics
 * @group replies
 */
class BBP_Tests_Admin_Spam extends BBP_UnitTestCase {

	private $get;

	public function setUp(): void {
		parent::setUp();

		$this->get = $_GET;

		if ( ! class_exists( 'BBP_Topics_Admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/topics.php';
		}

		if ( ! class_exists( 'BBP_Replies_Admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/replies.php';
		}
	}

	public function tearDown(): void {
		$_GET = $this->get;

		parent::tearDown();
	}

	private function get_admin_without_hooks( $class_name, $post_type ) {
		$reflection = new ReflectionClass( $class_name );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$property   = $reflection->getProperty( 'post_type' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $admin, $post_type );

		return $admin;
	}

	private function capture_empty_spam( $admin ) {
		ob_start();
		$admin->filter_empty_spam();

		return ob_get_clean();
	}

	/**
	 * @covers BBP_Topics_Admin::filter_empty_spam
	 * @covers BBP_Replies_Admin::filter_empty_spam
	 * @ticket 3725
	 */
	public function test_empty_spam_controls_require_spam_view_and_moderation_capability() {
		$admins = array(
			$this->get_admin_without_hooks( 'BBP_Topics_Admin', bbp_get_topic_post_type() ),
			$this->get_admin_without_hooks( 'BBP_Replies_Admin', bbp_get_reply_post_type() ),
		);

		foreach ( $admins as $admin ) {
			$this->set_current_user( 0 );

			foreach ( array( null, bbp_get_public_status_id(), bbp_get_spam_status_id() ) as $post_status ) {
				$_GET = is_null( $post_status )
					? array()
					: array( 'post_status' => $post_status );
				$this->assertSame( '', $this->capture_empty_spam( $admin ) );
			}

			$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
			bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
			$this->set_current_user( $user_id );
			$_GET = array( 'post_status' => bbp_get_public_status_id() );
			$this->assertSame( '', $this->capture_empty_spam( $admin ) );

			$_GET = array( 'post_status' => bbp_get_spam_status_id() );
			$output = $this->capture_empty_spam( $admin );
			$this->assertStringContainsString( 'Empty Spam', $output );
			$this->assertStringContainsString( '_destroy_nonce', $output );
		}
	}
}
