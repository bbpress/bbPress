<?php get_header(); ?>

<h2><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Statistics</h2>

<dl>
	<dt>Registered Users</dt>
	<dd><strong><?php total_users(); ?></strong></dd>
	<dt>Posts</dt>
	<dd><strong><?php total_posts(); ?></strong></dd>
</dl>

<?php if ($popular) : ?>
<h3>Most Popular Topics</h3>
<ol>
<?php foreach ($popular as $topic) : ?>
<li><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a> &#8212; <?php topic_posts(); ?> posts</li>
<?php endforeach; ?>

<?php endif; ?>
</ol>
<?php get_footer(); ?>