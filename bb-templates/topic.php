<?php get_header(); ?>

<?php login_form(); ?>

<h2><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <a href="<?php forum_link(); ?>"><?php forum_name(); ?></a></h2>
<h3><?php topic_title(); ?></h3>
<?php if ($posts) : ?>
<div class="nav">
<?php topic_pages(); ?>
</div>
<ol id="thread" start="<?php echo $list_start; ?>">

<?php foreach ($posts as $post) : ?>
	<li id="post-<?php post_id(); ?>" <?php alt_class('post'); ?>>
	
		<div class="threadauthor">
			<p><strong><?php post_author_link(); ?></strong><br />
			  <small><?php post_author_type(); ?></small></p>
		</div>
		
		<div class="threadpost">
			<div class="post"><?php post_text(); ?></div>
			<div class="poststuff">Posted: <?php post_time(); ?> {$viewIP} {$allowed} <a href="#post-<?php post_id(); ?>">#</a></div>
		</div>
	</li>
<?php endforeach; ?>

</ol>
<div class="clearit"><br style=" clear: both;" /></div>
<div class="nav">
<?php topic_pages(); ?>
</div>
<?php endif; ?>
<?php get_footer(); ?>