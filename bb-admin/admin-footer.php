<div id="footer"><p><a href="http://bbpress.org/"><img src="../bb-images/bbpress.png" alt="bbPress" /></a><br />
<?php option('version'); ?> <br /> 
<a href="http://bbpress.org/documentation/"><?php _e('Documentation'); ?></a> &#8212; <a href="http://bbpress.automattic.com/"><?php _e('Development'); ?></a> <br />
<?php printf(__('%s seconds'), number_format(bb_timer_stop(), 2)); ?>
</p>

</div>

<?php bb_do_action('bb_admin_footer', ''); ?>

</body>
</html>
