<?php

/**
 * Tests for the example converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Example extends BBP_UnitTestCase {

	/**
	 * @var Example
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Example.php';

		$reflection      = new ReflectionClass( 'Example' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers Example::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_malformed_values() {
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash', 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 123, 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'salt' => 123 ) ) ) );
	}
}
