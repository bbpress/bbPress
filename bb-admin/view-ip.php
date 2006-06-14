<?php
require('admin-action.php');

if ( !bb_current_user_can('view_by_ip') ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

$ip = preg_replace('/[^0-9\.]/', '', $_GET['ip']);

$posts = $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE poster_ip = '$ip' ORDER BY post_time DESC LIMIT 30");

require('head.php');
?>
<h2><?php _e('IP Information'); ?></h2>
<h3><?php _e('Last 30 posts'); ?></h3>
<?php if ($posts) : ?>
<div class="nav">
<?php topic_pages(); ?>
</div>
<ol id="thread">

<?php foreach ($posts as $bb_post) : ?>
	<li id="post-<?php post_id(); ?>" <?php alt_class('post'); ?>>
	
		<div class="threadauthor">
			<p><strong><?php post_author_link(); ?></strong><br />
			  <small><?php post_author_type(); ?></small></p>
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
<?php require('foot.php'); ?>
