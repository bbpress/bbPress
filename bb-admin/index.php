<?php require_once('admin.php'); require_once(BBPATH . 'bb-includes/statistics-functions.php'); ?>

<?php bb_get_admin_header(); ?>

<h2>Dashboard</h2>

<div id="zeitgeist">
<h2>Latest Activity</h2>
<h3>User Registrations</h3>
<ul class="users">
<?php if ( $users = get_recent_registrants() ) : foreach ( $users as $user ) : ?>
 <li><a href="<?php user_link( $user->ID ); ?>"><?php echo get_user_name( $user->ID ); ?></a> (<a href="<?php user_profile_link( $user->ID ); ?>">profile</a>) registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; endif; ?>
</ul>

<h3>Moderation</h3>
<ul class="posts">
<?php if ( $bb_posts = get_recently_moderated_posts() ) : foreach ( $bb_posts as $bb_post ) : ?>
 <li><a href="<?php echo bb_add_query_arg('view', 'deleted', get_post_link( $bb_post->post_id )); ?>">Post</a> on <a href="<?php topic_link( $bb_post->topic_id ); ?>"><?php topic_title( $bb_post->topic_id ); ?></a> by <a href="<?php user_profile_link( $bb_post->poster_id ); ?>"><?php post_author(); ?></a>.</li>
<?php endforeach; endif; ?>

<h3>Flagged</h3>
<h4>Posts</h4>
<ul class="posts">
<?php if ( false && $flagged = get_recently_flagged_posts() ) : foreach ( $flagged as $bb_post ) : ?>
 <li>Some stuff</li>
<?php endforeach; endif; ?>
</ul>

<h4>Users</h4>
<ul class="users">
<?php if ( false && $flagged = get_recently_flagged_users() ) : foreach ( $flagged as $bb_post ) : ?>
 <li>Some stuff</li>
<?php endforeach; endif; ?>
</ul>
</div>

<div id="bb-statistics">
<h3>Statistics</h3>
<ul>
 <li>Forums started <?php echo bb_since(get_inception()); ?> ago.</li>
 <li>Posts per day: <?php posts_per_day(); ?></li>
 <li>Topics per day: <?php topics_per_day(); ?></li>
 <li>Registrations per day: <?php registrations_per_day(); ?></li>
</ul>
</div>

<?php bb_get_admin_footer(); ?>
