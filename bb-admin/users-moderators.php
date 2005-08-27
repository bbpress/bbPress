<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php if ( $ids = get_ids_by_role( 'keymaster' ) ) : ?>
<h2>Key masters</h2>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>">profile</a>] registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ( $ids = get_ids_by_role( 'administrator' ) ) : ?>
<h2>Adminstrators</h2>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>">profile</a>] registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ( $ids = get_ids_by_role( 'moderator' ) ) : ?>
<h2>Moderators</h2>
<ul class="users">
<?php foreach ( $ids as $id ) : $user = bb_get_user( $id ) ;?>
 <li><?php full_user_link( $id ); ?> [<a href="<?php user_profile_link( $id ); ?>">profile</a>] registered <?php echo bb_since(strtotime($user->user_registered)); ?> ago.</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php bb_get_admin_footer(); ?>
