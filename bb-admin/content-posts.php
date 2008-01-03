<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
	add_filter( 'get_topic_where', 'no_where' );
	add_filter( 'get_topic_link', 'bb_make_link_view_all' );
	$post_query = new BB_Query_Form( 'post', array( 'post_status' => 1, 'count' => true ) );
	$bb_posts =& $post_query->results;
	$total = $post_query->found_rows;
?>

<h2><?php
$h2_search = $post_query->get( 'post_text' );
$h2_forum  = $post_query->get( 'forum_id' );
$h2_tag    = $post_query->get( 'tag_id' );
$h2_author = $post_query->get( 'post_author_id' );
$h2_status = $post_query->get( 'post_status' );

$h2_search = $h2_search ? ' ' . sprintf( __('matching &#8220;%s&#8221;'), wp_specialchars( $h2_search ) ) : '';
$h2_forum  = $h2_forum  ? ' ' . sprintf( __('in &#8220;%s&#8221;')      , get_forum_name( $h2_forum ) ) : '';
$h2_tag    = $h2_tag    ? ' ' . sprintf( __('with tag &#8220;%s&#8221;'), wp_specialchars( bb_get_tag_name( $h2_tag ) ) ) : '';
$h2_author = $h2_author ? ' ' . sprintf( __('by %s')                    , wp_specialchars( get_user_name( $h2_author ) ) ) : '';

$stati = array( 0 => __('Normal') . ' ', 1 => __('Deleted') . ' ', 'all' => '' );

if ( 'all' == $h2_status )
	$h2_noun = __('Posts');
else
	$h2_noun = sprintf( __( '%1$sposts'), $stati[$h2_status], $topic_open[$h2_open] );

printf( __( '%1$s%2$s%3$s%4$s%5$s' ), $h2_noun, $h2_search, $h2_forum, $h2_tag, $h2_author );

?></h2>

<?php $post_query->form( array('tag' => true, 'post_author' => true, 'post_status' => true, 'submit' => __('Filter &#187;')) ); ?>

<br class="clear" />

<ol id="the-list">
<?php bb_admin_list_posts(); ?>
</ol>

<?php echo get_page_number_links( $page, $total ); ?>

<?php bb_get_admin_footer(); ?>
