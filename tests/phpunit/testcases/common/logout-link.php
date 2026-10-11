<?php

/**
 * Tests for logout link markup.
 *
 * @group common
 * @group login
 * @group widgets
 */
class BBP_Tests_Common_Logout_Link extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_logout_link
	 */
	public function test_get_logout_link_uses_default_classes() {
		$link = bbp_get_logout_link();

		$this->assertStringContainsString( 'class="button logout-link"', $link );
	}

	/**
	 * @covers ::bbp_get_logout_link
	 */
	public function test_get_logout_link_accepts_custom_classes() {
		$link = bbp_get_logout_link( '', array( 'logout-link' ) );

		$this->assertStringContainsString( 'class="logout-link"', $link );
		$this->assertStringNotContainsString( 'class="button logout-link"', $link );
	}

	/**
	 * @covers BBP_Login_Widget::widget
	 */
	public function test_login_widget_uses_plain_logout_link() {
		$this->set_current_user( $this->factory->user->create() );

		$widget = new BBP_Login_Widget();
		$args   = array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
		);

		ob_start();
		$widget->widget( $args, array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="logout-link"', $output );
		$this->assertStringNotContainsString( 'class="button logout-link"', $output );
	}
}
