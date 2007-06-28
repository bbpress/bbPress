<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
	add_filter( 'get_topic_where', 'no_where' );
	add_filter( 'get_topic_link', 'bb_make_link_view_all' );
	$post_query = new BB_Query( 'post', array( 'post_status' => 1 ) );
	$bb_posts =& $post_query->results;
	$total = $post_query->found_rows;
?>

<h2><?php _e('Deleted Posts'); ?></h2>

<ol id="the-list">
<?php bb_admin_list_posts(); ?>
</ol>

<?php echo get_page_number_links( $page, $total ); ?>

<?php bb_get_admin_footer(); ?>
