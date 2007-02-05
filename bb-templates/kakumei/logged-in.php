<p class="login">
	<?php printf('<p class="login">' . __('Welcome, %1$s!'), bb_get_current_user_info( 'name' ));?> <?php bb_profile_link(); ?>
	<small>(<?php bb_admin_link();?> | <?php bb_logout_link(); ?>)</small>
</p>
