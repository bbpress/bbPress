<?php require_once('admin.php'); require_once(BBPATH . 'bb-includes/statistics-functions.php'); ?>

<?php bb_get_admin_header(); ?>

<h2>Dashboard</h2>

<div id="zeitgeist">
<h2>Latest Activity</h2>
<h3>User Registrations</h3>
<ul class="users">
<?php if ( $users = get_recent_registrants() ) : foreach ( $users as $user ) : ?>
 <li><?php full_user_link( $user->ID ); ?> [<a href="<?php user_profile_link( $user->ID ); ?>">profile</a>] registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; endif; ?>
</ul>

<h3>Recently Moderated</h3>
<ul class="posts">
<?php if ( $objects = get_recently_moderated_objects() ) : foreach ( $objects as $object ) : if ( 'post' == $object['type'] ) : global $bb_post; $bb_post = $object['data']; ?>
 <li><a href="<?php echo bb_add_query_arg( 'view', 'all', get_post_link() ); ?>">Post</a> on <a href="<?php topic_link( $bb_post->topic_id ); ?>"><?php topic_title( $bb_post->topic_id ); ?></a> by <a href="<?php user_profile_link( $bb_post->poster_id ); ?>"><?php post_author(); ?></a>.</li>
<?php elseif ( 'topic' == $object['type'] ) : global $topic; $topic = $object['data']; ?>
 <li>Topic titled <a href="<?php echo bb_add_query_arg( 'view', 'all', get_topic_link() ); ?>"><?php topic_title(); ?></a> started by <a href="<?php user_profile_link( $topic->topic_poster ); ?>"><?php echo $topic->topic_poster_name; ?></a>.</li>
<?php endif; endforeach; endif; ?>
</ul>
</div>

<div id="bb-statistics">
<h3>Statistics</h3>
<ul>
 <li>Posts per day: <?php posts_per_day(); ?></li>
 <li>Topics per day: <?php topics_per_day(); ?></li>
 <li>Registrations per day: <?php registrations_per_day(); ?></li>
 <li>Forums started <?php echo bb_since(get_inception()); ?> ago.</li>
</ul>
</div>

<?php bb_get_admin_footer(); ?>
