<?php

/**
 * Tests for the shared converter base.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Base extends BBP_UnitTestCase {

	/**
	 * @covers BBP_Converter_Base::convert_table
	 * @ticket BBP3684
	 */
	public function test_convert_table_connects_to_source_database() {
		bbp_setup_converter();

		$converter = new class() extends BBP_Converter_Base {
			public function info() {
				return '';
			}

			protected function authenticate_pass( $password, $hash ) {
				return false;
			}
		};
		$source_db = new class() {
			public $prefix      = '';
			public $connections = 0;

			public function db_connect( $allow_bail = true ) {
				++$this->connections;

				return true;
			}
		};
		$set_source_db = Closure::bind(
			function( $object, $database ) {
				$object->opdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_source_db( $converter, $source_db );

		$converter->convert_table( 'connection_probe', 1 );

		$this->assertSame( 1, $source_db->connections );
	}
}
