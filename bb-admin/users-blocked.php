<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<h2><?php _e('Deactivated Users'); ?></h2>
<?php if ( $ids = get_ids_by_role( 'inactive' ) ) : ?>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li<?php alt_class('ina'); ?>><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>"><?php _e('profile'); ?></a>] <?php printf(__('registered %s ago'), bb_since(strtotime($user->user_registered))) ?> </li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p><?php _e('There are no inactive users.'); ?></p>
<?php endif; ?>

<h2><?php _e('Blocked Users'); ?></h2>
<?php if ( $ids = get_ids_by_role( 'blocked' ) ) : ?>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li<?php alt_class('blo'); ?>><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>"><?php _e('profile'); ?></a>] <?php printf(__('registered %s ago'), bb_since(strtotime($user->user_registered))) ?></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p><?php _e('There are no blocked users.'); ?></p>
<?php endif; ?>

<?php bb_get_admin_footer(); ?>
