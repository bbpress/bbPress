<?php

/**
 * Subscription Unsubscribe Content
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php if ( bbp_is_subscription_unsubscribe( 'confirm' ) ) : ?>

		<?php bbp_get_template_part( 'form', 'subscription-unsubscribe' ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback', 'subscription-unsubscribe' ); ?>

	<?php endif; ?>

</div>
