<?php

/**
 * WP-CLI symbols for PHPStan. bbPress loads these commands only in WP-CLI.
 *
 * PHPStan scans this file without executing it.
 *
 * @package bbPress
 */

namespace {
	class WP_CLI {

		/**
		 * @param string $name Command name.
		 * @param string $command Command class name.
		 * @return bool
		 */
		public static function add_command( $name, $command ) {}

		/**
		 * @param string $message Error message.
		 * @return never
		 */
		public static function error( $message ) {}

		/**
		 * @param string $message Success message.
		 * @return void
		 */
		public static function success( $message ) {}

		/**
		 * @param string|int $message Output line.
		 * @return void
		 */
		public static function line( $message ) {}

		/**
		 * @param string $message Output line.
		 * @return void
		 */
		public static function log( $message ) {}

		/**
		 * @param string               $message    Confirmation prompt.
		 * @param array<string, mixed> $assoc_args Named command arguments.
		 * @return void
		 */
		public static function confirm( $message, $assoc_args = array() ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param array<string, mixed> $assoc_args Named command arguments.
	 * @param string               $flag       Flag name.
	 * @param mixed                $default    Default value.
	 * @return mixed
	 */
	function get_flag_value( $assoc_args, $flag, $default = null ) {}

	/**
	 * @param string                             $format Output format.
	 * @param array<int, array<string, mixed>>  $items  Output rows.
	 * @param array<int, string>                $fields Output columns.
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {}
}
