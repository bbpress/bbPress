<?php require_once('admin.php'); ?>
<?php bb_get_admin_header(); ?>

<?php	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?")); //This should never happen.
	add_filter( 'topic_link', 'bb_make_link_view_all' );
	$topic_query = new BB_Query('topic', array('topic_status' => 1) );
	$topics = $topic_query->results;

?>

<h2><?php _e('Deleted Topics') ?></h2>

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
<?php endforeach; endif; ?>
</table>

<?php echo get_page_number_links( $page, $topic_query->row_count ); ?>

<?php bb_get_admin_footer(); ?>
