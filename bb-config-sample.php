<?php

// ** MySQL settings ** //
define('BBDB_NAME', 'bbpress');      // The name of the database
define('BBDB_USER', 'username');     // Your MySQL username
define('BBDB_PASSWORD', 'password'); // ...and password
define('BBDB_HOST', 'localhost');    // 99% chance you won't need to change these last few

define('BBDB_CHARSET', 'utf8');      // If you are *upgrading*, and your old bb-config.php does
define('BBDB_COLLATE', '');          // not have these two constants in them, DO NOT define them
                                     // If you are installing for the first time, leave them here

// Change each KEY to a different unique phrase.  You won't have to remember the phrases later,
// so make them long and complicated.  You can visit https://www.grc.com/passwords.htm
// to get phrases generated for you, or just make something up.  Each key should have a different phrase.
// If you are integrating logins with WordPress, you will need to match each key to
// the value of their equivalent keys in the WordPress file wp-config.php
define('BB_AUTH_KEY', 'put your unique phrase here'); // Change this to a unique phrase.
define('BB_SECURE_AUTH_KEY', 'put your unique phrase here'); // Change this to a unique phrase.
define('BB_LOGGED_IN_KEY', 'put your unique phrase here'); // Change this to a unique phrase.
define('BB_NONCE_KEY', 'put your unique phrase here'); // Change this to a unique phrase.

// If you are running multiple bbPress installations in a single database,
// you will probably want to change this.
$bb_table_prefix = 'bb_'; // Only letters, numbers and underscores please!

// Change this to localize bbPress.  A corresponding MO file for the
// chosen language must be installed to bb-includes/languages.
// For example, install de.mo to bb-includes/languages and set BB_LANG to 'de'
// to enable German language support.
define('BB_LANG', '');

?>
