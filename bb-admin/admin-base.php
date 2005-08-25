<?php require_once('admin.php'); ?>

<?php bb_get_admin_header(); ?>

<?php if ( function_exists($bb_admin_page) ) : $bb_admin_page(); else : ?>

<p>Nothing to see here.<p>

<?php endif; bb_get_admin_footer(); ?>
