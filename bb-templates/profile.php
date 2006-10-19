<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php _e('Profile') ?></h3>
<h2 id="userlogin"><?php echo get_user_name( $user->ID ); ?></h2>

<?php if ( $updated ) : ?>
<div class="notice">
<p><?php _e('Profile updated'); ?>. <a href="<?php profile_tab_link( $user_id, 'edit' ); ?>"><?php _e('Edit again &raquo;'); ?></a></p>
</div>
<?php elseif ( $user_id == $bb_current_user->ID ) : ?>
<p><?php printf(__('This is how your profile appears to a fellow logged in member, you may <a href="%1$s">edit this information</a>. You can also <a href="%2$s">manage your favorites</a> and subscribe to your favorites&#8217; <a href="%3$s"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>'), get_profile_tab_link( $user_id, 'edit' ), get_favorites_link(), get_favorites_rss_link()) ?></p>
<?php endif; ?>

<?php bb_profile_data(); ?>

<h3 id="useractivity"><?php _e('User Activity') ?></h3>

<div id="user-replies" class="user-recent"><h4><?php _e('Recent Replies'); ?></h4>
<?php if ( $posts ) : ?>
<ol>
<?php foreach ($posts as $bb_post) : $topic = get_topic( $bb_post->topic_id ) ?>
<li<?php alt_class('replies'); ?>>
	<a href="<?php topic_link(); ?>"><?php topic_title(); ?></a>
	<?php if ( $user->ID == $bb_current_user->ID ) printf(__('You last replied: %s ago.'), bb_since( bb_get_post_time() )); else printf(__('User last replied: %s ago.'), bb_since( bb_get_post_time() )); ?>

	<span class="freshness"><?php
		if ( strtotime(bb_get_post_time()) < strtotime(get_topic_time()) )
			printf(__('Most recent reply: %s ago'), bb_since( get_topic_time() ));
		else
			_e('No replies since.');
	?></span>
</li>
<?php endforeach; ?>
</ol>
<?php else : if ( $page ) : ?>
<p><?php _e('No more replies.') ?></p>
<?php else : ?>
<p><?php _e('No replies yet.') ?></p>
<?php endif; endif; ?>
</div>

<div id="user-threads" class="user-recent">
<h4><?php _e('Threads Started') ?></h4>
<?php if ( $threads ) : ?>
<ol>
<?php foreach ($threads as $topic) : ?>
<li<?php alt_class('threads'); ?>>
	<a href="<?php topic_link(); ?>"><?php topic_title(); ?></a>
	<?php printf(__('Started: %s ago'), get_topic_start_time()); ?>

	<span class="freshness"><?php
		if ( strtotime(get_topic_start_time()) < strtotime(get_topic_time()) )
			printf(__('Most recent reply: %s ago.'), bb_since( get_topic_time() ));
		else
			_e('No replies.');
	?></span>
</li>
<?php endforeach; ?>
</ol>
<?php else : if ( $page ) : ?>
<p><?php _e('No more topics posted.') ?></p>
<?php else : ?>
<p><?php _e('No topics posted yet.') ?></p>
<?php endif; endif;?>
</div><br style="clear: both;" />

<?php profile_pages(); ?>

<?php bb_get_footer(); ?>
