<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<h2>Deactivated Users</h2>
<?php if ( $ids = get_ids_by_role( 'inactive' ) ) : ?>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>">profile</a>] registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p>There are no inactive users.</p>
<?php endif; ?>

<h2>Blocked Users</h2>
<?php if ( $ids = get_ids_by_role( 'blocked' ) ) : ?>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>">profile</a>] registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p>There are no blocked users.</p>
<?php endif; ?>

<?php bb_get_admin_footer(); ?>
