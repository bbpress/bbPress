<?php

/**
 * User Mentions
 *
 * @package bbPress
 * @subpackage Theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! bbp_current_user_can_view_mentions() ) {
	return;
}

do_action( 'bbp_template_before_user_mentions' ); ?>

<div id="bbp-user-mentions" class="bbp-user-mentions">
	<h2 class="entry-title"><?php esc_html_e( 'Mentions', 'bbpress' ); ?></h2>
	<div class="bbp-user-section">
		<?php if ( bbp_get_user_mentions() ) : ?>
			<?php bbp_get_template_part( 'pagination', 'search' ); ?>
			<?php bbp_get_template_part( 'loop', 'search' ); ?>
			<?php bbp_get_template_part( 'pagination', 'search' ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No mentions found.', 'bbpress' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<?php do_action( 'bbp_template_after_user_mentions' );
