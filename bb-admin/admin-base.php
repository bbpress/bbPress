<?php
require_once('admin.php');

do_action( $bb_admin_page . '_pre_head' );

bb_get_admin_header(); 

if ( function_exists($bb_admin_page) ) : $bb_admin_page(); else : ?>

<p><?php _e('Nothing to see here.'); ?><p>

<?php endif; bb_get_admin_footer(); ?>
