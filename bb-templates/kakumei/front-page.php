<?php bb_get_header(); ?>

<?php if ( $forums ) : ?>

<div id="hottags">
<h2><?php _e('Hot Tags'); ?></h2>
<p class="frontpageheatmap"><?php bb_tag_heat_map(); ?></p>
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
	<td><?php bb_topic_labels(); ?> <big><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></big></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><a href="<?php topic_last_post_link(); ?>"><?php topic_time(); ?></a></td>
</tr>
<?php endforeach; endif; // $super_stickies ?>

<?php if ( $topics ) : foreach ( $topics as $topic ) : ?>
<tr<?php topic_class(); ?>>
	<td><?php bb_topic_labels(); ?> <a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><a href="<?php topic_last_post_link(); ?>"><?php topic_time(); ?></a></td>
</tr>
<?php endforeach; endif; // $topics ?>
</table>
<?php endif; // $topics or $super_stickies ?>

<?php if ( bb_forums() ) : ?>
<h2><?php _e('Forums'); ?></h2>
<table id="forumlist">

<tr>
	<th><?php _e('Main Theme'); ?></th>
	<th><?php _e('Topics'); ?></th>
	<th><?php _e('Posts'); ?></th>
</tr>
<?php while ( bb_forum() ) : ?>
<?php if (bb_get_forum_is_category()) : ?>
<tr<?php bb_forum_class('bb-category'); ?>>
	<td colspan="3"><?php bb_forum_pad( '<div class="nest">' ); ?><a href="<?php forum_link(); ?>"><?php forum_name(); ?></a><small><?php forum_description(); ?></small><?php bb_forum_pad( '</div>' ); ?></td>
</tr>
<?php continue; endif; ?>
<tr<?php bb_forum_class(); ?>>
	<td><?php bb_forum_pad( '<div class="nest">' ); ?><a href="<?php forum_link(); ?>"><?php forum_name(); ?></a><small><?php forum_description(); ?></small><?php bb_forum_pad( '</div>' ); ?></td>
	<td class="num"><?php forum_topics(); ?></td>
	<td class="num"><?php forum_posts(); ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; // bb_forums() ?>

<?php if ( bb_is_user_logged_in() ) : ?>
<div id="viewdiv">
<h2><?php _e('Views'); ?></h2>
<ul id="views">
<?php foreach ( bb_get_views() as $the_view => $title ) : ?>
<li class="view"><a href="<?php view_link( $the_view ); ?>"><?php view_name( $the_view ); ?></a></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; // bb_is_user_logged_in() ?>

</div>

<?php else : // $forums ?>

<h3 class="bbcrumb"><a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a></h3>

<?php post_form(); endif; // $forums ?>

<?php bb_get_footer(); ?>
