<?php

/**
 * bbPress Converter Database
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Converter_DB' ) && class_exists( 'wpdb' ) ) :
/**
 * bbPress Converter Database Access Abstraction Object
 *
 * @since 2.6.0 bbPress (r6784)
 */
class BBP_Converter_DB extends wpdb {

	/**
	 * Sets up the credentials used to connect to the database server, but does
	 * not actually connect to the database on construct.
	 *
	 * @since 2.6.0 bbPress (r6784)
	 *
	 * @param string $dbuser     MySQL database user
	 * @param string $dbpassword MySQL database password
	 * @param string $dbname     MySQL database name
	 * @param string $dbhost     MySQL database host
	 */
	public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		register_shutdown_function( array( $this, '__destruct' ) );

		if ( WP_DEBUG && WP_DEBUG_DISPLAY ) {
			$this->show_errors();
		}

		// Use ext/mysqli if it exists unless WP_USE_EXT_MYSQL is defined as true
		if ( function_exists( 'mysqli_connect' ) ) {
			$this->use_mysqli = true;

			if ( defined( 'WP_USE_EXT_MYSQL' ) ) {
				$this->use_mysqli = ! WP_USE_EXT_MYSQL;
			}
		}

		// Setup credentials
		$this->dbuser     = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname     = $dbname;
		$this->dbhost     = $dbhost;

		// Normally wpdb would try to connect here, but we don't want to do that
		// until we are good and ready, so instead we do nothing.
	}
}
endif;
