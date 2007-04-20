<?php require_once('admin.php'); require_once(BBPATH . BBINC . 'statistics-functions.php'); ?>
<?php bb_get_admin_header(); ?>

<h2><?php _e('Dashboard'); ?></h2>

<div id="zeitgeist">
<h2><?php _e('Latest Activity'); ?></h2>
<h3><?php _e('User Registrations'); ?></h3>
<ul class="users">
<?php if ( $users = get_recent_registrants() ) : foreach ( $users as $user ) : ?>
 <li><?php full_user_link( $user->ID ); ?> [<a href="<?php user_profile_link( $user->ID ); ?>"><?php _e('profile') ?></a>] <?php printf(__('registered %s ago'), bb_since( $user->user_registered )) ?></li>
<?php endforeach; endif; ?>
</ul>

<h3><?php _e('Recently Moderated'); ?></h3>
<ul class="posts">
<?php if ( $objects = bb_get_recently_moderated_objects() ) : add_filter( 'get_topic_where', 'no_where' ); foreach ( $objects as $object ) : ?>
<?php if ( 'post' == $object['type'] ) : global $bb_post; $bb_post = $object['data']; ?>
	<li><a href="<?php echo attribute_escape( add_query_arg( 'view', 'all', get_post_link() ) ); ?>"><?php _e('Post'); ?></a> <?php _e('on'); ?> <a href="<?php topic_link( $bb_post->topic_id ); ?>"><?php topic_title( $bb_post->topic_id ); ?></a> <?php _e('by'); ?> <a href="<?php user_profile_link( $bb_post->poster_id ); ?>"><?php post_author(); ?></a>.</li>
<?php elseif ( 'topic' == $object['type'] ) : global $topic; $topic = $object['data']; ?>
	<li><?php _e('Topic titled'); ?> <a href="<?php echo attribute_escape( add_query_arg( 'view', 'all', get_topic_link() ) ); ?>"><?php topic_title(); ?></a> <?php _e('started by'); ?> <a href="<?php user_profile_link( $topic->topic_poster ); ?>"><?php topic_author(); ?></a>.</li>
<?php endif; endforeach; remove_filter( 'get_topic_where', 'no_where' ); endif; ?>
</ul>
</div>

<div id="bb-statistics">
<h3><?php _e('Statistics'); ?></h3>
<ul>
 <li><?php _e('Posts per day'); ?>: <?php posts_per_day(); ?></li>
 <li><?php _e('Topics per day'); ?>: <?php topics_per_day(); ?></li>
 <li><?php _e('Registrations per day'); ?>: <?php registrations_per_day(); ?></li>
 <li><?php printf(__('Forums started %s ago.'), bb_get_inception()); ?></li>
</ul>
</div>

<?php bb_get_admin_footer(); ?>
