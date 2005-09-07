<?php bb_get_header(); ?>

<?php login_form(); ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <a href="<?php forum_link(); ?>"><?php forum_name(); ?></a></h3>
<div class="infobox">
<h2 class="topictitle<?php if ( $topic->topic_status ) echo ' deleted'; ?>"><?php topic_title(); ?></h2>

<?php topic_tags(); ?>

<ul class="topicmeta">
	<li>Topic started <?php topic_start_time(); ?> ago</li>
	<li><?php topic_posts(); ?> posts so far</li>
	<li><a href="<?php topic_last_post_link(); ?>">Latest reply</a> from <?php topic_last_poster(); ?></li>
	<li id="resolutionflipper">This topic is <?php topic_resolved(); ?></li>
<?php if ( $bb_current_user ) : ?>
	<li id="favoritestoggle"><?php user_favorites_link() ?></li>
<?php endif; bb_do_action('topicmeta'); ?>
</ul>
<br clear="all" />
</div>
<?php bb_do_action('under_title', ''); ?>
<?php if ($posts) : ?>
<div class="nav">
<?php topic_pages(); ?>
</div>
<ol id="thread" start="<?php echo $list_start; ?>">

<?php foreach ($posts as $bb_post) : $del_class = post_del_class(); ?>
	<li id="post-<?php post_id(); ?>"<?php alt_class('post', $del_class); ?>>
<?php bb_post_template(); ?>
	</li>
<?php endforeach; ?>

</ol>
<div class="clearit"><br style=" clear: both;" /></div>
<p><a href="<?php topic_rss_link(); ?>">RSS feed for this thread</a></p>
<div class="nav">
<?php topic_pages(); ?>
</div>
<?php endif; ?>
<?php if ( topic_is_open( $bb_post->topic_id ) ) : ?>
<?php post_form(); ?>
<?php else : ?>
<h2>Topic Closed</h2>
<p>This topic has been closed to new replies.</p>
<?php endif; ?>
<div class="admin">
<?php topic_delete_link(); ?> <?php topic_close_link(); ?> <?php topic_sticky_link(); ?> <?php topic_show_all_link(); ?><br />
<?php topic_move_dropdown(); ?>
</div>
<?php bb_get_footer(); ?>
