<?php get_header(); ?>

<?php login_form(); ?>

<?php tag_heat_map(); ?>

<?php if ( $forums ) : ?>
<table id="forumlist">

<tr>
	<th>Main Theme</th>
	<th>Topics</th>
	<th>Posts</th>
</tr>

<?php foreach ( $forums as $forum ) : ?>
<tr<?php alt_class('forum'); ?>>
	<td><a href="<?php forum_link(); ?>"><?php forum_name(); ?></a> &#8212; <small><?php forum_description(); ?></small></td>
	<td class="num"><?php forum_topics(); ?></td>
	<td class="num"><?php forum_posts(); ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Latest Discussions</h2>

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
<?php endif; ?>

<?php get_footer(); ?>