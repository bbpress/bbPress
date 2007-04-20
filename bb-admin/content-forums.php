<?php
require_once('admin.php');

$forums = get_forums();
$forums_count = $forums ? count($forums) : 0;

if ( 'delete' == $_GET['action'] ) {
	$forum_to_delete = (int) $_GET['id'];
	$deleted_forum = get_forum( $forum_to_delete );
	if ( !$deleted_forum || $forums_count < 2 || !bb_current_user_can( 'delete_forum', $forum_to_delete ) )
		wp_redirect( add_query_arg( array('action' => false, 'id' => false) ) );
}

if ( isset($_GET['message']) ) {
	switch ( $_GET['message'] ) :
	case 'updated' :
		bb_admin_notice( __('Forum Updated.') );
		break;
	case 'deleted' :
		bb_admin_notice( sprintf(__('Forum deleted.  You should have bbPress <a href="%s">recount your site information</a>.'), bb_get_option( 'uri' ) . 'bb-admin/site.php') );
		break;
	endswitch;
}

if ( !isset($_GET['action']) )
	bb_enqueue_script( 'content-forums' );

bb_get_admin_header();
?>

<h2><?php _e('Forum Management'); ?></h2>
<?php switch ( $_GET['action'] ) : ?>
<?php case 'edit' : ?>
<h3><?php _e('Update Forum'); ?></h3>
<?php bb_forum_form( (int) $_GET['id'] ); ?>
<?php break; case 'delete' : ?>
<div class="ays narrow">
	<p><big><?php printf(__('Are you sure you want to delete the "<strong>%s</strong>" forum?'), $deleted_forum->forum_name); ?></big></p>
	<p>This forum contains</p>
	<ul>
		<li><?php printf(__ngettext('%d topic', '%d topics', $deleted_forum->topics), $deleted_forum->topics); ?></li>
		<li><?php printf(__ngettext('%d post', '%d posts', $deleted_forum->posts), $deleted_forum->posts); ?></li>
	</ul>

	<form method="post" id="delete-forums" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
		<p>
			<label for="move-topics-delete"><input type="radio" name="move_topics" id="move-topics-delete" value="delete" /> <?php _e('Delete all topics and posts in this forum. <em>This can never be undone.</em>'); ?></label><br />
			<label for="move-topics-move"><input type="radio" name="move_topics" id="move-topics-move" value="move" checked="checked" /> <?php _e('Move topics from this forum into'); ?></label>
			<?php $forums = get_forums( 'strcmp', array($deleted_forum->forum_id) ); ?>
			<select name="move_topics_forum" id="move-topics-forum">
<?php foreach ($forums as $forum ) : ?>
				<option value="<?php forum_id(); ?>"><?php forum_name(); ?></option>
<?php endforeach; ?>
			</select>
			
		</p>
		<p class="submit alignright">
			<input class="delete" name="Submit" type="submit" value="<?php _e('Delete forum &raquo;'); ?>" tabindex="10" />
			<input type="hidden" name="action" value="delete" />
			<input type="hidden" name="forum_id" value="<?php echo $deleted_forum->forum_id; ?>" />
		</p>
		<?php bb_nonce_field( 'delete-forums' ); ?>
	</form>
	<form method="get" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
		<p class="submit alignleft">
			<input type="submit" value="<?php _e('&laquo; Go back'); ?>" tabindex="10" />
		</p>
	</form>
</div>
<?php break; default : ?>

<?php if ( $forums ) : ?>

<ul id="the-list" class="list-block holder">
	<li class="thead list-block"><div class="list-block">Name &#8212; Description</div></li>
<?php
bb_forum_adminlistitems($forums);
?>
</ul>
<?php endif; // $forums ?>

<h3><?php _e('Add Forum'); ?></h3>
<?php bb_forum_form(); ?>

<?php break; endswitch; // action ?>

<div id="ajax-response"></div>

<?php bb_get_admin_footer(); ?>
