<?php require_once('admin.php'); ?>
<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
	add_filter( 'get_latest_topics_where', 'deleted_topics' );
	add_filter( 'topic_link', 'make_link_view_all' );
	$topics = get_latest_topics( 0, $page );
?>

<h2><?php _e('Deleted Topics') ?></h2>

<table>
<tr>
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
<?php endforeach; endif; ?>
</table>

<?php $total = get_deleted_topics_count(); echo get_page_number_links( $page, $total ); ?>

<?php bb_get_admin_footer(); ?>
