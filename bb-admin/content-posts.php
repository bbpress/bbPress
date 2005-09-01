<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die("Now how'd you get here?  And what did you think you'd being doing?"); //This should never happen.
	bb_add_filter( 'bb_post_time', 'strtotime' );
	bb_add_filter( 'bb_post_time', 'bb_since' );
	bb_add_filter( 'get_topic_where', 'no_where' );
	bb_add_filter( 'topic_link', 'make_link_deleted' );
	$bb_posts = get_deleted_posts( $page );
?>

<h2>Deleted Posts</h2>

<table>
<tr>
	<th>Poster</th>
	<th>Topic</th>
	<th>Freshness</th>
</tr>

<?php if ( $bb_posts ) : foreach ( $bb_posts as $bb_post ) : ?>
<tr<?php alt_class('topic'); ?>>
	<td><?php full_user_link( $bb_post->poster_id ); ?> [<a href="<?php user_profile_link( $id ); ?>">profile</a>]</td>
	<td><a href="<?php topic_link( $bb_post->topic_id ); ?>"><?php topic_title( $bb_post->topic_id ); ?></a></td>
	<td class="num"><small><?php bb_post_time(); ?></small></td>
</tr>
<?php endforeach; endif; ?>
</table>

<?php echo get_page_number_links( $page, -1 ); ?>

<?php bb_get_admin_footer(); ?>
