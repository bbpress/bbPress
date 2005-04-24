<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Profile</h3>
<h2><?php echo $user->username; ?></h2>

<?php if ( $updated ) : ?>
<div class="notice">
<p>Profile updated. <a href="<?php option('uri'); ?>profile-edit.php">Edit again &raquo;</a></p>
</div>
<?php elseif ( can_edit( $user_id ) ) : ?>
<p>This is how your profile appears to a fellow logged in member, you may <a href="<?php option('uri'); ?>profile-edit.php">edit this information</a>.</p>
<?php endif; ?>

<dl id="userinfo">
<dt>Member Since</dt>
<dd><?php echo gmdate('F j, Y', $ts); ?> (<?php echo bb_since($ts); ?>)</dd>
<?php
$USERINFO = '';
if ($user->user_website) :
        $USERINFO .= "<dt>Web address</dt>
<dd><a href='$user->user_website'>$user->user_website</a></dd>
";
endif;
if ($user->user_from) :
        $USERINFO .= "<dt>Where in the world?</dt>
<dd>$user->user_from</dd>
";
endif;
if ($user->user_occ) :
        $USERINFO .= "<dt>Occupation</dt>
<dd>$user->user_occ</dd>
";
endif;
if ($user->user_interest) :
        $USERINFO .= "<dt>Interests</dt>
<dd>$user->user_interest</dd>
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
<?php foreach ($posts as $post) : ?>
<li><a href="<?php topic_link( $post->topic_id ); ?>"><?php topic_title( $post->topic_id ); ?></a> <?php post_time(); ?> ago</li>
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
<li><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a> <?php topic_time(); ?> ago</li>
<?php endforeach; ?>
</ol>
<?php else : ?>
<p>No topics posted yet.</p>
<?php endif; ?>
</div><br style="clear: both;" />

<?php get_footer(); ?>