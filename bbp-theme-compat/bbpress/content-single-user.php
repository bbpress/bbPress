<?php

/**
 * Single User Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<div id="bbpress-forums">

	<?php do_action( 'bbp_template_notices' ); ?>

	<?php
	
		bbp_get_template_part( 'user', 'details' );

		if ( bbp_is_favorites() ) :
			bbp_get_template_part( 'user', 'favorites' );

		elseif ( bbp_is_subscriptions() ) :
			bbp_get_template_part( 'user', 'subscriptions' );

		else :
			bbp_get_template_part( 'user', 'topics-created' );

		endif;
	?>

</div>
