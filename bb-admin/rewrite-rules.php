<?php
require('admin-action.php');

header('Content-Type: text/plain');

if ( !bb_current_user_can('manage_options') ) {
	wp_redirect( bb_get_option( 'uri' ) );
	exit();
}

?>

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase <?php bb_option( 'path' ); ?>

RewriteRule ^forum/([^/]+)/page/([0-9]+)/?$ <?php bb_option( 'path' ); ?>forum.php?id=$1&page=$2 [L,QSA]
RewriteRule ^forum/([^/]+)/?$ <?php bb_option( 'path' ); ?>forum.php?id=$1 [L,QSA]
RewriteRule ^topic/([^/]+)/page/([0-9]+)/?$ <?php bb_option( 'path' ); ?>topic.php?id=$1&page=$2 [L,QSA]
RewriteRule ^topic/([^/]+)/?$ <?php bb_option( 'path' ); ?>topic.php?id=$1 [L,QSA]
RewriteRule ^tags/([^/]+)/page/([0-9]+)/?$ <?php bb_option( 'path' ); ?>tags.php?tag=$1&page=$2 [L,QSA]
RewriteRule ^tags/([^/]+)/?$ <?php bb_option( 'path' ); ?>tags.php?tag=$1 [L,QSA]
RewriteRule ^tags/?$ <?php bb_option( 'path' ); ?>tags.php [L,QSA]
RewriteRule ^profile/([^/]+)/page/([0-9]+)/?$ <?php bb_option( 'path' ); ?>profile.php?id=$1&page=$2 [L,QSA]
RewriteRule ^profile/([^/]+)/([^/]+)/?$ <?php bb_option( 'path' ); ?>profile.php?id=$1&tab=$2 [L,QSA]
RewriteRule ^profile/([^/]+)/([^/]+)/page/([0-9]+)/?$ <?php bb_option( 'path' ); ?>profile.php?id=$1&tab=$2&page=$3 [L,QSA]
RewriteRule ^profile/([^/]+)/?$ <?php bb_option( 'path' ); ?>profile.php?id=$1 [L,QSA]
RewriteRule ^view/([^/]+)/page/([0-9]+)/?$ <?php bb_option( 'path' ); ?>view.php?view=$1&page=$2 [L,QSA]
RewriteRule ^view/([^/]+)/?$ <?php bb_option( 'path' ); ?>view.php?view=$1 [L,QSA]
RewriteRule ^rss/?$ <?php bb_option( 'path' ); ?>rss.php [L,QSA]
RewriteRule ^rss/forum/([^/]+)/?$ <?php bb_option( 'path' ); ?>rss.php?forum=$1 [L,QSA]
RewriteRule ^rss/topic/([^/]+)/?$ <?php bb_option( 'path' ); ?>rss.php?topic=$1 [L,QSA]
RewriteRule ^rss/tags/([^/]+)/?$ <?php bb_option( 'path' ); ?>rss.php?tag=$1 [L,QSA]
RewriteRule ^rss/profile/([^/]+)/?$ <?php bb_option( 'path' ); ?>rss.php?profile=$1 [L,QSA]
</IfModule>
