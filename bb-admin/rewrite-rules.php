<?php
require('admin-action.php');

header('Content-type: text/plain');

if ( !bb_current_user_can('manage_options') ) {
	wp_redirect( bb_get_option( 'uri' ) );
	exit();
}

?>

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase <?php echo $bb->path; ?>

RewriteRule ^forum/([0-9]+)/page/([0-9]+)$ <?php echo $bb->path; ?>forum.php?id=$1&page=$2 [L,QSA]
RewriteRule ^forum/([0-9]+)$ <?php echo $bb->path; ?>forum.php?id=$1 [L,QSA]
RewriteRule ^topic/([0-9]+)/page/([0-9]+)$ <?php echo $bb->path; ?>topic.php?id=$1&page=$2 [L,QSA]
RewriteRule ^topic/([0-9]+)$ <?php echo $bb->path; ?>topic.php?id=$1 [L,QSA]
RewriteRule ^tags/(.+)/page/([0-9]+)$ <?php echo $bb->path; ?>ags.php?tag=$1&page=$2 [L,QSA]
RewriteRule ^tags/(.+)/?$ <?php echo $bb->path; ?>tags.php?tag=$1 [L,QSA]
RewriteRule ^tags/? <?php echo $bb->path; ?>tags.php [L,QSA]
RewriteRule ^profile/([0-9]+)/page/([0-9]+)$ <?php echo $bb->path; ?>profile.php?id=$1&page=$2 [L,QSA]
RewriteRule ^profile/([0-9]+)/([a-z]+)$ <?php echo $bb->path; ?>profile.php?id=$1&tab=$2 [L,QSA]
RewriteRule ^profile/([0-9]+)/([a-z]+)/page/([0-9]+)$ <?php echo $bb->path; ?>profile.php?id=$1&tab=$2&page=$3 [L,QSA]
RewriteRule ^profile/([0-9]+)$ <?php echo $bb->path; ?>profile.php?id=$1 [L,QSA]
RewriteRule ^view/([a-z-]+)/page/([0-9]+)$ <?php echo $bb->path; ?>view.php?view=$1&page=$2 [L,QSA]
RewriteRule ^view/([a-z-]+)$ <?php echo $bb->path; ?>view.php?view=$1 [L,QSA]
RewriteRule ^rss/forum/([0-9]+)$ <?php echo $bb->path; ?>rss.php?forum=$1 [L,QSA]
RewriteRule ^rss/topic/([0-9]+)$ <?php echo $bb->path; ?>rss.php?topic=$1 [L,QSA]
RewriteRule ^rss/tags/([a-z]+)$ <?php echo $bb->path; ?>rss.php?tag=$1 [L,QSA]
RewriteRule ^rss/profile/([0-9]+)$ <?php echo $bb->path; ?>rss.php?profile=$1 [L,QSA]
</IfModule>
