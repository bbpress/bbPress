<?php

/**
 * Single User Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php do_action( 'bbp_template_notices' ); ?>

<?php

	// Profile details
	bbp_get_template_part( 'bbpress/user', 'details' );

	// Profile Edit
	if ( bbp_is_user_profile_edit() ) :

		// Subsciptions
		bbp_get_template_part( 'bbpress/form', 'user-edit' );

	// Profile Display
	else :

		// Subsciptions
		bbp_get_template_part( 'bbpress/user', 'subscriptions'  );

		// Favorite topics
		bbp_get_template_part( 'bbpress/user', 'favorites'      );

		// Topics created
		bbp_get_template_part( 'bbpress/user', 'topics-created' );

	endif;

?>
