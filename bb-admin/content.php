<?php require_once('admin.php'); ?>
<?php bb_get_admin_header(); ?>

<?php
	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
	add_filter( 'topic_link', 'bb_make_link_view_all' );
	$topic_query_vars = array('topic_status' => 1, 'open' => 'all');
	if ( isset($_REQUEST['search']) )
		$topic_query_vars['post_status'] = 'all';
	$topic_query = new BB_Query_Form( 'topic', $topic_query_vars );
	$topics = $topic_query->results;
?>

<h2><?php
$h2_search = $topic_query->get( 'search' );
$h2_forum  = $topic_query->get( 'forum_id' );
$h2_tag    = $topic_query->get( 'tag_id' );
$h2_author = $topic_query->get( 'topic_author_id' );
$h2_status = $topic_query->get( 'topic_status' );
$h2_open   = $topic_query->get( 'open' );

$h2_search = $h2_search ? ' ' . sprintf( __('matching &#8220;%s&#8221;'), wp_specialchars( $h2_search ) ) : '';
$h2_forum  = $h2_forum  ? ' ' . sprintf( __('in &#8220;%s&#8221;')      , get_forum_name( $h2_forum ) ) : '';
$h2_tag    = $h2_tag    ? ' ' . sprintf( __('with tag &#8220;%s&#8221;'), wp_specialchars( get_tag_name( $h2_tag ) ) ) : '';
$h2_author = $h2_author ? ' ' . sprintf( __('by %s')                    , wp_specialchars( get_user_name( $h2_author ) ) ) : '';

$topic_stati = array( 0 => __('Normal') . ' ', 1 => __('Deleted') . ' ', 'all' => '' );
$topic_open  = array( 0 => __('Closed') . ' ', 1 => __('Open') . ' '   , 'all' => '' );

if ( 'all' == $h2_status && 'all' == $h2_open )
	$h2_noun = __('Topics');
else
	$h2_noun = sprintf( __( '%1$s%2$stopics'), $topic_stati[$h2_status], $topic_open[$h2_open] );

printf( __( '%1$s%2$s%3$s%4$s%5$s' ), $h2_noun, $h2_search, $h2_forum, $h2_tag, $h2_author );

?></h2>

<?php $topic_query->topic_search_form( array('tag' => true, 'author' => true, 'status' => true, 'open' => true, 'submit' => __('Filter &#187;')) ); ?>

<br class="clear" />

<table class="widefat">
<tr class="thead">
	<th><?php _e('Topic') ?></th>
	<th><?php _e('Last Poster') ?></th>
	<th><?php _e('Freshness') ?></th>
</tr>

<?php if ( $topics ) : foreach ( $topics as $topic ) : ?>
<tr<?php alt_class('topic'); ?>>
	<td><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
</tr>
<?php endforeach; else : ?>
<tr>
	<td colspan="3"><?php _e('No Topics Found'); ?></td>
</tr>
<?php endif; ?>
</table>

<?php echo get_page_number_links( $page, $topic_query->found_rows ); ?>

<?php bb_get_admin_footer(); ?>
