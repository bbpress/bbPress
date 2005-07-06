<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Profile</h3>
<h2><?php echo $user->user_login; ?></h2>

<?php if ( $updated ) : ?>
<div class="notice">
<p>Profile updated. <a href="<?php option('uri'); ?>profile-edit.php">Edit again &raquo;</a></p>
</div>
<?php elseif ( can_edit( $user_id ) ) : ?>
<p>This is how your profile appears to a fellow logged in member, you may <a href="<?php option('uri'); ?>profile-edit.php">edit this information</a>.
You can also <a href="<?php favorites_link(); ?>">manage your favorites</a> and subscribe to your favorites' <a href="<?php favorites_rss_link(); ?>"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>.</p>
<?php endif; ?>

<dl id="userinfo">
<dt>Member Since</dt>
<dd><?php echo gmdate('F j, Y', $reg_time); ?> (<?php echo bb_since($reg_time); ?>)</dd>
<?php
$USERINFO = '';
if ( $url = get_user_link( $user->ID ) ) :
        $USERINFO .= "<dt>Web address</dt>
<dd><a href='$url'>$url</a></dd>
";
endif;
if ($user->from) :
        $USERINFO .= "<dt>Where in the world?</dt>
<dd>$user->from</dd>
";
endif;
if ($user->occ) :
        $USERINFO .= "<dt>Occupation</dt>
<dd>$user->occ</dd>
";
endif;
if ($user->interest) :
        $USERINFO .= "<dt>Interests</dt>
<dd>$user->interest</dd>
";
endif;
echo $USERINFO;
?>
</dl>

<h2>User Activity</h2>

<div id="user-replies" class="user-recent"><h3>Recent Replies</h3>
<?php 
if ( $posts ) :
?>
<ol>
<?php foreach ($posts as $post) : $topic = get_topic( $post->topic_id ) ?>
<li><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a> User last replied: <?php post_time(); ?> ago.
<?php
if ( strtotime(get_post_time()) < strtotime(get_topic_time()) ) {
	echo ' <span class=freshness">Most recent reply: ';
	topic_time();
	echo ' ago.</span>';
} else {
	echo ' <span class="freshness">No replies since.</span>';
}
?>	
</li>
<?php endforeach; ?>
</ol>
<?php else : ?>
<p>No replies yet.</p>
<?php endif; ?>
</div>

<div id="user-threads" class="user-recent">
<h3>Threads Started</h3>
<?php if ( $threads ) : ?>
<ol>
<?php foreach ($threads as $topic) : ?>
<li><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a> Started: <?php topic_start_time(); ?> ago.
<?php
if ( strtotime(get_topic_start_time()) < strtotime(get_topic_time()) ) {
	echo ' <span class=freshness">Most recent reply: ';
	topic_time();
	echo ' ago.</span>';
} else {
	echo ' <span class="freshness">No replies.</span>';
}
?>	
</li>
<?php endforeach; ?>
</ol>
<?php else : ?>
<p>No topics posted yet.</p>
<?php endif; ?>
</div><br style="clear: both;" />

<?php get_footer(); ?>
