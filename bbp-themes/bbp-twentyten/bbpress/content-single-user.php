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

	<?php bbp_get_template_part( 'bbpress/user', 'details' ); ?>

	<hr />

	<?php bbp_get_template_part( 'bbpress/user', 'subscriptions' ); ?>

	<hr />

	<?php bbp_get_template_part( 'bbpress/user', 'favorites' ); ?>

	<hr />

	<?php bbp_get_template_part( 'bbpress/user', 'topics-created' ); ?>

</div>
