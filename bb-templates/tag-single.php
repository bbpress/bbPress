<?php get_header(); ?>

<?php login_form(); ?>

<h2><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <a href="<?php tag_page_link(); ?>">Tags</a> &raquo; <?php tag_name(); ?></h2>

<?php if ( $topics ) : ?>

<table id="latest">
<tr>
	<th>Topic</th>
	<th>Posts</th>
	<th>Last Poster</th>
	<th>Freshness</th>
</tr>

<?php foreach ( $topics as $topic ) : ?>
<tr<?php alt_class('topic'); ?>>
	<td><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
</tr>
<?php endforeach; ?>
</table>
<div class="nav">
<?php forum_pages(); ?>
</div>
<?php endif; ?>

<h2>Add New Topic</h2>

<?php post_form(); ?>

<?php get_footer(); ?>