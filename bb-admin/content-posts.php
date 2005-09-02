<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die("Now how'd you get here?  And what did you think you'd being doing?"); //This should never happen.
	bb_add_filter( 'get_topic_where', 'no_where' );
	bb_add_filter( 'topic_link', 'make_link_deleted' );
	$bb_posts = get_deleted_posts( $page );
?>

<h2>Deleted Posts</h2>

<ol id="the-list">
<?php if ( $bb_posts ) : foreach ( $bb_posts as $bb_post ) : ?>
<li<?php alt_class('post'); ?>>
	<div class="threadauthor">
		<p><strong><?php post_author_link(); ?></strong><br />
		  <small><?php post_author_type(); ?></small></p>
	</div>

	<div class="threadpost">
		<div class="post"><?php post_text(); ?></div>
		<div class="poststuff">Posted: <?php bb_post_time(); ?> in <a href="<?php topic_link( $bb_post->topic_id ); ?>"><?php topic_title( $bb_post->topic_id ); ?></a> <?php post_ip_link(); ?> <?php post_edit_link(); ?> <?php post_delete_link(); ?></div>
	</div>
</li>
<?php endforeach; endif; ?>
</ol>

<?php $total = get_deleted_posts(0); echo get_page_number_links( $page, $total ); ?>

<?php bb_get_admin_footer(); ?>
