<?php
require_once('admin.php');
require_once( BB_PATH . BB_INC . 'functions.bb-statistics.php' );

$rn_forums = get_total_forums();
$rn_forums = sprintf(__ngettext('<span>%d</span> forum', '<span>%d</span> forums', $rn_forums), number_format( $rn_forums ) );

$rn_topics = get_total_topics();
$rn_topics = sprintf(__ngettext('<span>%d</span> topic', '<span>%d</span> topics', $rn_topics), number_format( $rn_topics ) );

$rn_posts = get_total_posts();
$rn_posts = sprintf(__ngettext('<span>%d</span> post', '<span>%d</span> posts', $rn_posts), number_format( $rn_posts ) );

$rn_users = bb_get_total_users();
$rn_users = sprintf(__ngettext('<span>%d</span> user', '<span>%d</span> users', $rn_users), number_format( $rn_users ) );

$rn_topic_tags = bb_get_total_topic_tags();
$rn_topic_tags = sprintf(__ngettext('<span>%d</span> tag', '<span>%d</span> tags', $rn_topic_tags), number_format( $rn_topic_tags ) );

$rn_topics_average = get_topics_per_day();
$rn_topics_average = sprintf(__ngettext('<span>%d</span> topic', '<span>%d</span> topics', $rn_topics_average), number_format( $rn_topics_average ) );

$rn_posts_average = get_posts_per_day();
$rn_posts_average = sprintf(__ngettext('<span>%d</span> post', '<span>%d</span> posts', $rn_posts_average), number_format( $rn_posts_average ) );

$rn_users_average = get_registrations_per_day();
$rn_users_average = sprintf(__ngettext('<span>%d</span> user', '<span>%d</span> users', $rn_users_average), number_format( $rn_users_average ) );

$rn_topic_tags_average = bb_get_topic_tags_per_day();
$rn_topic_tags_average = sprintf(__ngettext('<span>%d</span> tag', '<span>%d</span> tags', $rn_topic_tags_average), number_format( $rn_topic_tags_average ) );

$rn = apply_filters( 'bb_admin_right_now', array(
	'forums'     => array( $rn_forums, '-' ),
	'topics'     => array( $rn_topics, $rn_topics_average ),
	'posts'      => array( $rn_posts, $rn_posts_average ),
	'topic_tags' => array( $rn_topic_tags, $rn_topic_tags_average ),
	'users'      => array( $rn_users, $rn_users_average )
) );

$bb_admin_body_class = ' bb-admin-dashboard';

bb_get_admin_header();
?>

<div class="wrap">
	<h2><?php _e('Dashboard'); ?></h2>
	<?php do_action( 'bb_admin_notices' ); ?>

	<div id="dashboard-right-now" class="dashboard">
		<h3><?php _e('Right Now'); ?></h3>
		<div class="table">
			<table cellpadding="0" cellspacing="0">
				<thead>
					<tr>
						<th><?php _e( 'Totals' ); ?></th>
						<th><?php _e( 'Per Day' ); ?></th>
					</tr>
				</thead>
<?php
if ( !empty( $rn ) && is_array( $rn ) ) {
?>
				<tbody>
<?php
	foreach ( $rn as $rn_row ) {
?>
					<tr>
						<td><?php echo $rn_row[0]; ?></td>
						<td><?php echo $rn_row[1]; ?></td>
					</tr>
<?php
	}
?>
				</tbody>
<?php
}
?>
			</table>
		</div>

		<div class="versions">
			<p class="theme"><a class="button" href="<?php bb_uri( 'bb-admin/themes.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN ); ?>"><?php _e( 'Change Theme' ); ?></a><?php printf ( __( 'Theme <a href="%1$s">%2$s</a>' ), bb_get_uri( 'bb-admin/themes.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN ), bb_get_current_theme_data( 'Name' ) ); ?></p>
			<p class="bbpress"><?php printf( __( 'You are using <span class="b">bbPress %s</span>' ), bb_get_option( 'version' ) ); ?></p>
		</div>
	</div>

	<div id="dashboard-moderated" class="dashboard">
		<h3><?php _e('Recently Moderated Items'); ?></h3>
		<?php if ( $objects = bb_get_recently_moderated_objects() ) : ?>
		<ul class="posts">
			<?php add_filter( 'get_topic_where', 'bb_no_where' ); foreach ( $objects as $object ) : ?>
			<?php if ( 'post' == $object['type'] ) : global $bb_post; $bb_post = $object['data']; ?>
			<li>
<?php
					printf(
						__( '<a href="%1$s">Post</a> on <a href="%2$s">%3$s</a> by <a href="%4$s">%5$s</a>' ),
						esc_attr( add_query_arg( 'view', 'all', get_post_link() ) ),
						get_topic_link( $bb_post->topic_id ),
						get_topic_title( $bb_post->topic_id ),
						get_user_profile_link( $bb_post->poster_id ),
						get_post_author()
					);
?>
			</li>
			<?php elseif ( 'topic' == $object['type'] ) : global $topic; $topic = $object['data']; ?>
			<li>
<?php
					printf(
						__( 'Topic titled <a href="%1$s">%2$s</a> started by <a href="%3$s">%4$s</a>' ),
						esc_attr( add_query_arg( 'view', 'all', get_topic_link() ) ),
						get_topic_title(),
						get_user_profile_link( $topic->topic_poster ),
						get_topic_author()
					);
?>
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
