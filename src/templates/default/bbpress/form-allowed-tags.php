<?php

/**
 * Allowed HTML Tags
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! ( bbp_use_wp_editor() || current_user_can( 'unfiltered_html' ) ) ) : ?>

	<p class="form-allowed-tags">
		<label>
			<?php
			printf(
				/* translators: %s: Literally the "HTML" string */
				esc_html__( 'You may use these %s tags and attributes:', 'bbpress' ),
				'<abbr title="HyperText Markup Language">HTML</abbr>'
			);
			?>
		</label>
		<br />
		<code><?php bbp_allowed_tags(); ?></code>
	</p>

<?php endif;
