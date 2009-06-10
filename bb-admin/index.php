<?php
require_once('admin.php');
require_once( BB_PATH . BB_INC . 'functions.bb-statistics.php' );

$bb_admin_body_class = ' bb-admin-dashboard';

bb_get_admin_header();
?>

<div class="wrap">
	<h2><?php _e('Dashboard'); ?></h2>
	<?php do_action( 'bb_admin_notices' ); ?>

	<div id="dashboard-right-now" class="dashboard">
		<h3><?php _e('Right Now'); ?></h3>
<?php
$forums = get_total_forums();
$forums = sprintf(__ngettext('%d forum', '%d forums', $forums), $forums);
$topics = get_total_topics();
$topics = sprintf(__ngettext('is %d topic', 'are %d topics', $topics), $topics);
$posts = get_total_posts();
$posts = sprintf(__ngettext('%d post', '%d posts', $posts), $posts);
$users = get_total_users();
$users = sprintf(__ngettext('%d user', '%d users', $users), $users);
$topics_average = get_topics_per_day();
$topics_average = sprintf(__ngettext('%d topic', '%d topics', $topics_average), $topics_average);
$posts_average = get_posts_per_day();
$posts_average = sprintf(__ngettext('%d post', '%d posts', $posts_average), $posts_average);
$users_average = get_registrations_per_day();
$users_average = sprintf(__ngettext('%d user', '%d users', $users_average), $users_average);
?>
		<p><?php printf(__('You have %1$s. There %2$s with %3$s by %4$s.'), $forums, $topics, $posts, $users); ?></p>
		<p><?php printf(__('That\'s an average of %1$s, %2$s and %3$s per day since your forums were started %4$s ago.'), $topics_average, $posts_average, $users_average, bb_get_inception()); ?></p>
		<p><?php printf(__('You are using the theme <a href="%1$s">%2$s</a>. This is bbPress version %3$s'), bb_get_uri('bb-admin/themes.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN), bb_get_current_theme_data('Name'), bb_get_option('version')); ?></p>
	</div>

	<div id="dashboard-recent-user-registrations" class="dashboard left">
		<?php if ( $users = get_recent_registrants() ) : ?>
		<h3><?php _e('Recent User Registrations'); ?></h3>
		<ul class="users">
			<?php foreach ( $users as $user ) : ?>
			<li>
				<?php full_user_link( $user->ID ); ?>
				(<a href="<?php user_profile_link( $user->ID ); ?>"><?php echo get_user_name( $user->ID ); ?></a>)
				<?php printf(__('registered %s ago'), bb_since( $user->user_registered )) ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>

	<div id="dashboard-moderated" class="dashboard right">
		<h3><?php _e('Recently Moderated Items'); ?></h3>
		<?php if ( $objects = bb_get_recently_moderated_objects() ) : ?>
		<ul class="posts">
			<?php add_filter( 'get_topic_where', 'bb_no_where' ); foreach ( $objects as $object ) : ?>
			<?php if ( 'post' == $object['type'] ) : global $bb_post; $bb_post = $object['data']; ?>
			<li>
				<a href="<?php echo esc_attr( add_query_arg( 'view', 'all', get_post_link() ) ); ?>"><?php _e('Post'); ?></a>
				<?php _e('on'); ?>
				<a href="<?php topic_link( $bb_post->topic_id ); ?>"><?php topic_title( $bb_post->topic_id ); ?></a>
				<?php _e('by'); ?>
				<a href="<?php user_profile_link( $bb_post->poster_id ); ?>"><?php post_author(); ?></a>.
			</li>
			<?php elseif ( 'topic' == $object['type'] ) : global $topic; $topic = $object['data']; ?>
			<li>
				<?php _e('Topic titled'); ?>
				<a href="<?php echo esc_attr( add_query_arg( 'view', 'all', get_topic_link() ) ); ?>"><?php topic_title(); ?></a>
				<?php _e('started by'); ?>
				<a href="<?php user_profile_link( $topic->topic_poster ); ?>"><?php topic_author(); ?></a>.
			</li>
			<?php endif; endforeach; remove_filter( 'get_topic_where', 'bb_no_where' ); ?>
		</ul>
		<?php else : ?>
		<p>
			<?php _e('No moderated posts or topics&#8230; you must have very well behaved members.'); ?>
		</p>
		<?php endif; ?>
	</div>
	<div class="clear"></div>
</div>

<?php bb_get_admin_footer(); ?>
