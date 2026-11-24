<?php

/**
 * Single User Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php
	/** This action is documented in src/templates/default/bbpress/form-forum.php. */
	do_action( 'bbp_template_notices' ); ?>

	<?php
	/**
	 * Fires before the user wrapper template section.
	 *
	 * @since 2.6.0 bbPress (r5854)
	 */
	do_action( 'bbp_template_before_user_wrapper' ); ?>

	<div id="bbp-user-wrapper">

		<?php bbp_get_template_part( 'user', 'details' ); ?>

		<div id="bbp-user-body">
			<?php

			if ( bbp_is_favorites() ) :
				bbp_get_template_part( 'user', 'favorites' );
			endif;

			if ( bbp_is_subscriptions() ) :
				bbp_get_template_part( 'user', 'subscriptions' );
			endif;

			if ( bbp_is_single_user_engagements() ) :
				bbp_get_template_part( 'user', 'engagements' );
			endif;

			if ( bbp_is_single_user_mentions() ) :
				bbp_get_template_part( 'user', 'mentions' );
			endif;

			if ( bbp_is_single_user_topics() ) :
				bbp_get_template_part( 'user', 'topics-created'  );
			endif;

			if ( bbp_is_single_user_replies() ) :
				bbp_get_template_part( 'user', 'replies-created' );
			endif;

			if ( bbp_is_single_user_edit() ) :
				bbp_get_template_part( 'form', 'user-edit' );
			endif;

			if ( bbp_is_single_user_profile() ) :
				bbp_get_template_part( 'user', 'profile' );
			endif;

			?>
		</div>
	</div>

	<?php
	/**
	 * Fires after the user wrapper template section.
	 *
	 * @since 2.6.0 bbPress (r5854)
	 */
	do_action( 'bbp_template_after_user_wrapper' ); ?>

</div>
