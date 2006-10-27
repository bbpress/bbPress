<?php bb_get_header(); ?>

<?php if ( $forums ) : ?>

<div id="hottags">
<h2><?php _e('Hot Tags'); ?></h2>
<p class="frontpageheatmap"><?php tag_heat_map(); ?></p>
</div>

<div id="discussions">
<?php if ( $topics || $super_stickies ) : ?>

<h2><?php _e('Latest Discussions'); ?></h2>

<table id="latest">
<tr>
	<th><?php _e('Topic'); ?> &#8212; <?php new_topic(); ?></th>
	<th><?php _e('Posts'); ?></th>
	<th><?php _e('Last Poster'); ?></th>
	<th><?php _e('Freshness'); ?></th>
</tr>

<?php if ( $super_stickies ) : foreach ( $super_stickies as $topic ) : ?>
<tr<?php topic_class(); ?>>
	<td><?php _e('Sticky:'); ?> <big><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></big></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
</tr>
<?php endforeach; endif; ?>

<?php if ( $topics ) : foreach ( $topics as $topic ) : ?>
<tr<?php topic_class(); ?>>
	<td><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
</tr>
<?php endforeach; endif; ?>
</table>
<?php endif; ?>

<h2><?php _e('Forums'); ?></h2>
<table id="forumlist">

<tr>
	<th><?php _e('Main Theme'); ?></th>
	<th><?php _e('Topics'); ?></th>
	<th><?php _e('Posts'); ?></th>
</tr>

<?php foreach ( $forums as $forum ) : ?>
<tr<?php alt_class('forum'); ?>>
	<td><a href="<?php forum_link(); ?>"><?php forum_name(); ?></a> &#8212; <small><?php forum_description(); ?></small></td>
	<td class="num"><?php forum_topics(); ?></td>
	<td class="num"><?php forum_posts(); ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ( $bb_current_user->ID ) : ?>
<div id="viewdiv">
<h2><?php _e('Views'); ?></h2>
<ul id="views">
<?php foreach ( get_views() as $view => $title ) : ?>
<li class="view"><a href="<?php view_link(); ?>"><?php view_name(); ?></a></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; else : // $forums ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a></h3>

<?php post_form(); endif; ?>
</div>

<?php bb_get_footer(); ?>
