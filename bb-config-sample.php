<?php
// ** MySQL settings ** //
define('DB_NAME', 'bbpress');     // The name of the database
define('DB_USER', 'username');     // Your MySQL username
define('DB_PASSWORD', 'password'); // ...and password
define('DB_HOST', 'localhost');     // 99% chance you won't need to change this value

// Change the prefix if you want to have multiple forums in a single database.
$table_prefix  = 'minibb_';

$bb->domain = 'http://bbpress.example.com';
$bb->path   = '/support/';
$bb->name   = 'New bbPress Site';

$bb->mod_rewrite = false;
$bb->page_topics = 30;

// Number of minutes after posting a user can edit their post
$bb->edit_lock = 60;

/* Stop editing */

define('BBPATH', dirname(__FILE__) . '/' );
require_once( BBPATH . 'bb-settings.php' );
?>