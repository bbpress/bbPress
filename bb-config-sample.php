<?php

// ** MySQL settings ** //
define('BBDB_NAME', 'bbpress');      // The name of the database
define('BBDB_USER', 'username');     // Your MySQL username
define('BBDB_PASSWORD', 'password'); // ...and password
define('BBDB_HOST', 'localhost');    // 99% chance you won't need to change these last few

define('BBDB_CHARSET', 'utf8');      // If you are *upgrading*, and your old bb-config.php does
define('BBDB_COLLATE', '');          // not have these two contstants in them, DO NOT define them
                                     // If you are installing for the first time, leave them here

// If you are running multiple bbPress installations in a single database,
// you will probably want to change this.
$bb_table_prefix = 'bb_'; // Only letters, numbers and underscores please!

// Change this to localize bbPress.  A corresponding MO file for the
// chosen language must be installed to bb-includes/languages.
// For example, install de.mo to bb-includes/languages and set BBLANG to 'de'
// to enable German language support.
define('BBLANG', '');

/* Stop editing */

if ( !defined('BBPATH') )
	define('BBPATH', dirname(__FILE__) . '/' );
require_once( BBPATH . 'bb-settings.php' );

?>
