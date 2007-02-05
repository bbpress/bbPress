<?php
require_once('admin.php');

do_action( $bb_admin_page . '_pre_head' );

bb_get_admin_header(); 

if ( is_callable($bb_admin_page) ) : call_user_func( $bb_admin_page ); else : ?>

<p><?php _e('Nothing to see here.'); ?><p>

<?php endif; bb_get_admin_footer(); ?>
