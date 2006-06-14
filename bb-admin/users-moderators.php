<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php if ( $ids = get_ids_by_role( 'keymaster' ) ) : ?>
<h2><?php _e('Key masters'); ?></h2>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li<?php alt_class('key'); ?>><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>"><?php _e('profile'); ?></a>] <?php printf(__('registered %s ago'), bb_since(strtotime($user->user_registered))) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ( $ids = get_ids_by_role( 'administrator' ) ) : ?>
<h2><?php _e('Adminstrators'); ?></h2>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li<?php alt_class('adm'); ?>><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>"><?php _e('profile'); ?></a>] <?php printf(__('registered %s ago'), bb_since(strtotime($user->user_registered))) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ( $ids = get_ids_by_role( 'moderator' ) ) : ?>
<h2><?php _e('Moderators'); ?></h2>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li<?php alt_class('mod'); ?>><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>"><?php _e('profile'); ?></a>] <?php printf(__('registered %s ago'), bb_since(strtotime($user->user_registered))) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php bb_get_admin_footer(); ?>
