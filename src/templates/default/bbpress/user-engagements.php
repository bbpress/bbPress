<?php

/**
 * User Engagements
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_user_engagements' ); ?>

<div id="bbp-user-engagements" class="bbp-user-engagements">
	<h2 class="entry-title"><?php esc_html_e( 'Topics Engaged In', 'bbpress' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_engagements() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

			<?php bbp_get_template_part( 'loop',       'topics' ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

		<?php else : ?>

			<p><?php bbp_is_user_home()
				? esc_html_e( 'You have not engaged in any topics.',      'bbpress' )
				: esc_html_e( 'This user has not engaged in any topics.', 'bbpress' );
			?></p>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-engagements -->

<?php do_action( 'bbp_template_after_user_engagements' );
