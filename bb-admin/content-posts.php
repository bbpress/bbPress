<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
	add_filter( 'get_topic_where', 'no_where' );
	add_filter( 'topic_link', 'make_link_view_all' );
	$bb_posts = get_deleted_posts( $page );
?>

<h2><?php _e('Deleted Posts'); ?></h2>

<ol id="the-list">
<?php bb_admin_list_posts(); ?>
</ol>

<?php $total = get_deleted_posts(0); echo get_page_number_links( $page, $total ); ?>

<?php bb_get_admin_footer(); ?>
