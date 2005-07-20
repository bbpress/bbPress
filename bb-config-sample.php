<?php
// ** MySQL settings ** //
define('DB_NAME', 'bbpress');     // The name of the database
define('DB_USER', 'username');     // Your MySQL username
define('DB_PASSWORD', 'password'); // ...and password
define('DB_HOST', 'localhost');     // 99% chance you won't need to change this value

// Change the prefix if you want to have multiple forums in a single database.
$table_prefix  = 'bb_';

$bb->domain = 'http://bbpress.example.com';	// There should be no trailing slash here.
$bb->path   = '/support/';			// There should be both a leading and trailing slash here. '/' is fine.
$bb->name   = 'New bbPress Site';

$bb->admin_email = 'you@example.com';
$bb->mod_rewrite = false;
$bb->page_topics = 30;

// Number of minutes after posting a user can edit their post
$bb->edit_lock = 60;

$bb->gmt_offset = 0;

/* Stop editing */

define('BBPATH', dirname(__FILE__) . '/' );
require_once( BBPATH . 'bb-settings.php' );
?>