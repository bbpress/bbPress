<?php

/**
 * Tests for the phpBB converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_phpBB extends BBP_UnitTestCase {

	/**
	 * @var phpBB
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/phpBB.php';

		$reflection      = new ReflectionClass( 'phpBB' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers phpBB::callback_savepass
	 * @ticket BBP3683
	 */
	public function test_callback_savepass_normalizes_missing_salt() {
		$this->assertSame(
			array( 'hash' => 'hash', 'salt' => '' ),
			$this->converter->callback_savepass( 'hash', array() )
		);
	}

	/**
	 * @covers phpBB::callback_savepass
	 * @ticket BBP3683
	 */
	public function test_callback_savepass_survives_user_meta_storage() {
		$user_id  = $this->factory->user->create();
		$hash     = '$H\\2y$9abcdefgh$04\\abcdefghijklmnopqrstuu$zwZlBpR3wIKksRSWAqcp2Nu.yvp5IzW';
		$metadata = $this->converter->callback_savepass( $hash, array( 'user_form_salt' => "a\\b'" ) );

		update_user_meta( $user_id, '_bbp_password', $metadata );

		$this->assertSame(
			array( 'hash' => $hash, 'salt' => "a\\b'" ),
			get_user_meta( $user_id, '_bbp_password', true )
		);
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_bcrypt_hash() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '$2y$04$Bi6GofTIeap4FD70nDEUU.fQbNpCE3iWqSp6jXgP4oP1w0hPK/NMS';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_combined_phpass_and_bcrypt_hash() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '$H\\2y$9abcdefgh$04\\abcdefghijklmnopqrstuu$LVGmnMBN.mHLTBloiJQiHw2M3ahm4kK';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_combined_hash_from_phpbb2_migration() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '$H\\2y$9abcdefgh$04\\abcdefghijklmnopqrstuu$zwZlBpR3wIKksRSWAqcp2Nu.yvp5IzW';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_phpass_hash() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '$H$9abcdefghwldFXPgBejnqGWjxQpQKj0';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_md5_hash() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '5c8315e93cb86e3fcbf9a92673545161';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_argon2i_hash() {
		if ( ! defined( 'PASSWORD_ARGON2I' ) ) {
			$this->markTestSkipped( 'Argon2i is not supported by this PHP build.' );
		}

		$password = 'Correct Horse Battery Staple';
		$hash     = '$argon2i$v=19$m=8192,t=1,p=1$blV3UmJVTWJxV1JscG1QZQ$P473Mke3N59HdDKkv1TVjsvyXwc2FF+g9Ap//XLFc98';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_with_argon2id_hash() {
		if ( ! defined( 'PASSWORD_ARGON2ID' ) ) {
			$this->markTestSkipped( 'Argon2id is not supported by this PHP build.' );
		}

		$password = 'Correct Horse Battery Staple';
		$hash     = '$argon2id$v=19$m=8192,t=1,p=1$bWYubFBHS2RwSFJlaVdHeA$RTUSaxYrx7eiZMjd5MgL1Q6ADlGKxoyYceR6YybCEb0';
		$pass     = serialize( array( 'hash' => $hash, 'salt' => '' ) );

		$this->assertTrue( $this->converter->authenticate_pass( $password, $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array() ) ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers phpBB::authenticate_pass
	 * @ticket BBP3683
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$hash     = '$2y$04$Bi6GofTIeap4FD70nDEUU.fQbNpCE3iWqSp6jXgP4oP1w0hPK/NMS';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'phpbb-imported-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => $hash, 'salt' => '' ) );
		update_user_meta( $user_id, '_bbp_class', 'phpBB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'phpbb-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
