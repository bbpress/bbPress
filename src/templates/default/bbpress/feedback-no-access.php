<?php

/**
 * No Access Feedback Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="forum-private" class="bbp-forum-content">
	<h1 class="entry-title"><?php esc_html_e( 'Private', 'bbpress' ); ?></h1>
	<div class="entry-content">
		<div class="bbp-template-notice info">
			<ul>
				<li><?php esc_html_e( 'You do not have permission to view this forum.', 'bbpress' ); ?></li>
			</ul>
		</div>
	</div>
</div><!-- #forum-private -->
