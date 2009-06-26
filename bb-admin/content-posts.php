<?php
require_once('admin.php');

$bb_admin_body_class = ' bb-admin-posts';

bb_get_admin_header();

if ( !bb_current_user_can('browse_deleted') )
	die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
add_filter( 'get_topic_where', 'bb_no_where' );
add_filter( 'get_topic_link', 'bb_make_link_view_all' );
add_filter( 'post_edit_uri', 'bb_make_link_view_all' );
$post_query = new BB_Query_Form( 'post', array( 'post_status' => 'normal', 'count' => true, 'per_page' => 20 ) );
$bb_posts =& $post_query->results;
$total = $post_query->found_rows;
?>

<div class="wrap">

<h2><?php _e( 'Posts' ); ?>
<?php
$h2_search = $post_query->get( 'post_text' );
$h2_forum  = $post_query->get( 'forum_id' );
$h2_tag    = $post_query->get( 'tag_id' );
$h2_author = $post_query->get( 'post_author_id' );

$h2_search = $h2_search ? ' ' . sprintf( __('containing &#8220;%s&#8221;'), esc_html( $h2_search ) ) : '';
$h2_forum  = $h2_forum  ? ' ' . sprintf( __('in &#8220;%s&#8221;')      , get_forum_name( $h2_forum ) ) : '';
$h2_tag    = $h2_tag    ? ' ' . sprintf( __('with tag &#8220;%s&#8221;'), esc_html( bb_get_tag_name( $h2_tag ) ) ) : '';
$h2_author = $h2_author ? ' ' . sprintf( __('by %s')                    , esc_html( get_user_name( $h2_author ) ) ) : '';

if ( $h2_search || $h2_forum || $h2_tag || $h2_author ) {
	echo '<span class="subtitle">';
	
	printf( __( '%1$s%2$s%3$s%4$s' ), $h2_search, $h2_forum, $h2_tag, $h2_author );
	
	echo '</span>';
}
?>
</h2>
<?php do_action( 'bb_admin_notices' ); ?>

<?php $post_query->form( array('tag' => true, 'post_author' => true, 'post_status' => true, 'submit' => __('Filter')) ); ?>

<div class="tablenav">
<?php if ( $total ) : ?>
	<div class="tablenav-pages">
		<span class="displaying-num"><?php echo $displaying_num = sprintf(
			__( '%1$s to %2$s of %3$s' ),
			bb_number_format_i18n( ( $page - 1 ) * $post_query->get( 'per_page' ) + 1 ),
			$page * $post_query->get( 'per_page' ) < $total ? bb_number_format_i18n( $page * $post_query->get( 'per_page' ) ) : '<span class="total-type-count">' . bb_number_format_i18n( $total ) . '</span>',
			'<span class="total-type-count">' . bb_number_format_i18n( $total ) . '</span>'
		); ?></span><span class="displaying-pages">
<?php
$_page_link_args = array(
	'page' => $page,
	'total' => $total,
	'per_page' => 4,//$post_query->get( 'per_page' ),
	'mod_rewrite' => false,
	'prev_text' => __( '&laquo;' ),
	'next_text' => __( '&raquo;' )
);
echo $page_number_links = get_page_number_links( $_page_link_args );
?></span>
		<div class="clear"></div>
	</div>
<?php endif; ?>
</div>
<div class="clear"></div>

<?php bb_admin_list_posts(); ?>

<div class="tablenav">
<?php if ( $total ) : ?>
	<div class="tablenav-pages bottom">
		<span class="displaying-pages"><?php echo $page_number_links; ?></span>
		<div class="clear"></div>
	</div>
<?php endif; ?>
</div>
<div class="clear"></div>

</div>

<?php bb_get_admin_footer(); ?>
