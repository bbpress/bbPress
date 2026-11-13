<?php

/**
 * Subscription Unsubscribe Feedback
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div class="bbp-template-notice <?php bbp_subscription_unsubscribe_feedback_class(); ?>">
	<ul>
		<li><?php bbp_subscription_unsubscribe_feedback(); ?></li>
	</ul>
</div>
