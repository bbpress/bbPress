<?php

/**
 * Pagination for pages of replies (when viewing a topic)
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Fires before the pagination loop template section.
 *
 * @since 2.0.0 bbPress (r3311)
 */
do_action( 'bbp_template_before_pagination_loop' ); ?>

<div class="bbp-pagination">
	<div class="bbp-pagination-count"><?php bbp_topic_pagination_count(); ?></div>
	<div class="bbp-pagination-links"><?php bbp_topic_pagination_links(); ?></div>
</div>

<?php
/**
 * Fires after the pagination loop template section.
 *
 * @since 2.0.0 bbPress (r3311)
 */
do_action( 'bbp_template_after_pagination_loop' );
