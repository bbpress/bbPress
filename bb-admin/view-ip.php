<?php
require('admin.php');

if ( !bb_current_user_can('view_by_ip') ) {
	wp_redirect( bb_get_uri(null, null, BB_URI_CONTEXT_HEADER) );
	exit();
}

$ip = preg_replace('/[^0-9\.]/', '', $_GET['ip']);

$post_query = new BB_Query( 'post', array( 'ip' => $ip, 'per_page' => 30 ) );

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('IP Information'); ?></h2>

<h3><?php _e('Last 30 posts'); ?></h3>

<?php if ($post_query->results) : ?>

<div class="nav">
<?php topic_pages(); ?>
</div>

<ol id="thread">

<?php foreach ($post_query->results as $bb_post) : ?>
	<li id="post-<?php post_id(); ?>" <?php alt_class('post'); ?>>
	
		<div class="threadauthor">
			<p><strong><?php post_author_link(); ?></strong><br />
			  <small><?php post_author_title_link(); ?></small></p>
		</div>
		
		<div class="threadpost">
			<div class="post"><?php post_text(); ?></div>
			<div class="poststuff"><?php _e('Posted:'); ?> <?php bb_post_time(); ?> <a href="#post-<?php post_id(); ?>">#</a> <?php post_ip(); ?></div>
		</div>
	</li>
<?php endforeach; ?>

</ol>

<div class="clearit"><br style=" clear: both;" /></div>

<div class="nav">
<?php topic_pages(); ?>
</div>

<?php endif; ?>

</div>

<?php bb_get_admin_footer(); ?>
