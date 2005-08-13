<?php get_header(); ?>

<?php login_form(); ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <a href="<?php forum_link(); ?>"><?php forum_name(); ?></a></h3>
<div class="infobox">
<h2 class="topictitle<?php if ( $topic->topic_status ) echo ' deleted'; ?>"><?php topic_title(); ?></h2>

<?php topic_tags(); ?>

<ul class="topicmeta">
	<li>Topic started <?php topic_start_time(); ?> ago</li>
	<li><?php topic_posts(); ?> posts so far</li>
	<li><a href="<?php topic_last_post_link(); ?>">Latest reply</a> from <?php topic_last_poster(); ?></li>
	<li>This topic is <?php topic_resolved(); ?></li>
<?php if ( $current_user ) : ?>
	<li><?php user_favorites_link() ?> (<a href="<?php favorites_link(); ?>">?</a>)</li>
<?php endif; ?>
</ul>
<br clear="all" />
</div>
<?php bb_do_action('under_title', ''); ?>
<?php if ($posts) : ?>
<div class="nav">
<?php topic_pages(); ?>
</div>
<ol id="thread" start="<?php echo $list_start; ?>">

<?php foreach ($posts as $post) : $del_class = ( $post->post_status ) ? 'deleted' : ''; ?>
	<li id="post-<?php post_id(); ?>" <?php alt_class('post', $del_class); ?>>
	
		<div class="threadauthor">
			<p><strong><?php post_author_link(); ?></strong><br />
			  <small><?php post_author_type(); ?></small></p>
		</div>
		
		<div class="threadpost">
			<div class="post"><?php post_text(); ?></div>
			<div class="poststuff">Posted: <?php post_time(); ?> <a href="#post-<?php post_id(); ?>">#</a> <?php post_ip_link(); ?> <?php post_edit_link(); ?> <?php post_delete_link(); ?></div>
		</div>
	</li>
<?php endforeach; ?>

</ol>
<div class="clearit"><br style=" clear: both;" /></div>
<p><a href="<?php topic_rss_link(); ?>">RSS feed for this thread</a></p>
<div class="nav">
<?php topic_pages(); ?>
</div>
<?php endif; ?>
<?php if ( topic_is_open( $post->topic_id ) ) : ?>
<?php post_form(); ?>
<?php else : ?>
<h2>Topic Closed</h2>
<p>This topic has been closed to new replies.</p>
<?php endif; ?>
<div class="admin">
<?php topic_delete_link(); ?> <?php topic_close_link(); ?> <?php topic_sticky_link(); ?> <?php topic_show_all_link(); ?><br />
<?php topic_move_dropdown(); ?>
</div>
<?php get_footer(); ?>
