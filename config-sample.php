<?php

// ** MySQL settings ** //
define('BBDB_NAME', 'bbpress');     // The name of the database
define('BBDB_USER', 'username');     // Your MySQL username
define('BBDB_PASSWORD', 'password'); // ...and password
define('BBDB_HOST', 'localhost');     // 99% chance you won't need to change this value

$bb->domain = 'http://bbpress.example.com';	// There should be no trailing slash here.
// There should be both a leading and trailing slash here. '/' is fine if the site is in root.
$bb->path   = '/support/';
$bb->name   = 'New bbPress Site';

$bb->admin_email = 'you@example.com';
$bb->mod_rewrite = false;
$bb->page_topics = 30;

// Number of minutes after posting a user can edit their post
$bb->edit_lock = 60;

$bb->gmt_offset = 0;

// Change the prefix if you want to have multiple forums in a single database.
$bb_table_prefix  = 'bb_';

// If you want to integrate bbPress with a WordPress installation in the same database,
// put WordPress' table prefix here.
$bb->wp_table_prefix = false;  // 'wp_';

// Akismet Key: http://wordpress.com/api-keys/
$bb->akismet_key = false;

/* Stop editing */

define('BBPATH', dirname(__FILE__) . '/' );
require_once( BBPATH . 'bb-settings.php' );

?>
