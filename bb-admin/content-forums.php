<?php
require_once('admin.php');

$forums = get_forums();
$forums_count = $forums ? count($forums) : 0;

if ( isset($_GET['action']) && 'delete' == $_GET['action'] ) {
	$forum_to_delete = (int) $_GET['id'];
	$deleted_forum = get_forum( $forum_to_delete );
	if ( !$deleted_forum || $forums_count < 2 || !bb_current_user_can( 'delete_forum', $forum_to_delete ) ) {
		bb_safe_redirect( add_query_arg( array('action' => false, 'id' => false) ) );
		exit;
	}
}

if ( isset($_GET['message']) ) {
	switch ( $_GET['message'] ) :
	case 'updated' :
		bb_admin_notice( __('Forum Updated.') );
		break;
	case 'deleted' :
		bb_admin_notice( sprintf(
			__('Forum deleted.  You should have bbPress <a href="%s">recount your site information</a>.'),
			bb_get_uri('bb-admin/site.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN)
		) );
		break;
	endswitch;
}

if ( !isset($_GET['action']) )
	wp_enqueue_script( 'content-forums' );

$bb_admin_body_class = ' bb-admin-forums';

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('Edit Forums'); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>
<?php switch ( @$_GET['action'] ) : ?>
<?php case 'edit' : ?>
<h3><?php _e('Update Forum'); ?></h3>
<?php bb_forum_form( (int) $_GET['id'] ); ?>
<?php break; case 'delete' : ?>
<div class="ays narrow">
	<p><big><?php printf(__('Are you sure you want to delete the "<strong>%s</strong>" forum?'), $deleted_forum->forum_name); ?></big></p>
	<p><?php _e('This forum contains'); ?></p>
	<ul>
		<li><?php printf(__ngettext('%d topic', '%d topics', $deleted_forum->topics), $deleted_forum->topics); ?></li>
		<li><?php printf(__ngettext('%d post', '%d posts', $deleted_forum->posts), $deleted_forum->posts); ?></li>
	</ul>

	<form method="post" id="delete-forums" action="<?php bb_uri('bb-admin/bb-forum.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
		<p>
			<label for="move-topics-delete"><input type="radio" name="move_topics" id="move-topics-delete" value="delete" /> <?php _e('Delete all topics and posts in this forum. <em>This can never be undone.</em>'); ?></label><br />
			<label for="move-topics-move"><input type="radio" name="move_topics" id="move-topics-move" value="move" checked="checked" /> <?php _e('Move topics from this forum into'); ?></label>
			<?php bb_forum_dropdown( array('id' => 'move_topics_forum', 'callback' => 'strcmp', 'callback_args' => array($deleted_forum->forum_id), 'selected' => $deleted_forum->forum_parent) ); ?>
		</p>
		<p class="submit alignright">
			<input class="delete" name="Submit" type="submit" value="<?php _e('Delete forum &raquo;'); ?>" tabindex="10" />
			<input type="hidden" name="action" value="delete" />
			<input type="hidden" name="forum_id" value="<?php echo $deleted_forum->forum_id; ?>" />
		</p>
		<?php wp_nonce_field( 'delete-forums' ); ?>
	</form>
	<form method="get" action="<?php bb_uri('bb-admin/bb-forum.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
		<p class="submit alignleft">
			<input type="submit" value="<?php _e('&laquo; Go back'); ?>" tabindex="10" />
		</p>
	</form>
</div>
<?php break; default : ?>


<?php if ( bb_forums( 'type=list&walker=BB_Walker_ForumAdminlistitems' ) ) : ?>
<ul id="forum-list" class="list:forum list-block holder widefat">
	<li class="thead list-block"><div class="list-block"><?php _e('Name &#8212; Description'); ?></div></li>
<?php while ( bb_forum() ) : ?>
<?php bb_forum_row(); ?>
<?php endwhile; ?>
<?php endif; // bb_forums() ?>
</ul>

<h3><?php _e('Add Forum'); ?></h3>
<?php bb_forum_form(); ?>

<?php break; endswitch; // action ?>

<div id="ajax-response"></div>

</div>

<?php bb_get_admin_footer(); ?>
