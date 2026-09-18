<?php

bbp_setup_converter();

class BBP_Tests_Admin_Converters_Unserialize_Wakeup {
	public static $woke = false;

	public function __wakeup() {
		self::$woke = true;
	}
}

class BBP_Tests_Admin_Converters_Base_Converter extends BBP_Converter_Base {
	public function info() {
		return '';
	}

	protected function authenticate_pass( $password, $hash ) {
		return false;
	}

	public function get_pass_array( $value ) {
		return $this->unserialize_pass( $value );
	}
}

class BBP_Tests_Admin_Converters_Base_Source_Database {
	public $prefix      = '';
	public $connections = 0;

	public function db_connect( $allow_bail = true ) {
		++$this->connections;

		return true;
	}
}

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

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$source_db = new BBP_Tests_Admin_Converters_Base_Source_Database();
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

	/**
	 * @covers BBP_Converter_Base::unserialize_pass
	 * @ticket BBP3684
	 */
	public function test_unserialize_pass_validates_password_metadata() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$metadata  = array(
			'hash' => 'hash',
			'salt' => ';O:8:"stdClass":0:{}',
		);
		$object_metadata = array(
			'hash'   => 'hash',
			'object' => new BBP_Tests_Admin_Converters_Unserialize_Wakeup(),
		);
		BBP_Tests_Admin_Converters_Unserialize_Wakeup::$woke = false;

		$this->assertSame( $metadata, $converter->get_pass_array( serialize( $metadata ) ) );
		$this->assertFalse( $converter->get_pass_array( array() ) );
		$this->assertFalse( $converter->get_pass_array( 'not serialized' ) );
		$this->assertFalse( $converter->get_pass_array( serialize( 'not an array' ) ) );
		$this->assertFalse( $converter->get_pass_array( serialize( new stdClass() ) ) );
		$this->assertFalse( $converter->get_pass_array( serialize( $object_metadata ) ) );
		$this->assertFalse( $converter->get_pass_array( 'a:1:{s:4:"enum";E:3:"T:A";}' ) );
		$this->assertFalse( BBP_Tests_Admin_Converters_Unserialize_Wakeup::$woke );
	}
}
