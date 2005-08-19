<?php
require('admin-header.php');

header('Content-type: text/plain');

if ( !bb_current_user_can('manage_options') ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

?>

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase <?php echo $bb->path; ?> 
RewriteRule ^forum/(0-9+)$ <?php echo $bb->path; ?>forum.php?id=$1 [QSA]
RewriteRule ^topic/(0-9+)$ <?php echo $bb->path; ?>topic.php?id=$1 [QSA]
</IfModule>
