<?php

/**
 * Term Subscription Loop - Single Term
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<li>
	<?php if ( bbp_is_subscriptions() && bbp_current_user_can_manage_engagements( 'subscription' ) ) : ?>

		<span class="bbp-row-actions">

			<?php do_action( 'bbp_theme_before_term_subscription_action' ); ?>

			<?php
			bbp_term_subscription_toggle_link(
				array(
					'before'      => '',
					'after'       => '',
					'subscribe'   => '+',
					'unsubscribe' => '&times;'
				)
			);
			?>

			<?php do_action( 'bbp_theme_after_term_subscription_action' ); ?>

		</span>

	<?php endif; ?>

	<?php do_action( 'bbp_theme_before_term_subscription_name' ); ?>

	<?php if ( bbp_get_term_subscription_permalink() ) : ?>
		<a href="<?php bbp_term_subscription_permalink(); ?>"><?php bbp_term_subscription_name(); ?></a>
	<?php else : ?>
		<?php bbp_term_subscription_name(); ?>
	<?php endif; ?>

	<?php do_action( 'bbp_theme_after_term_subscription_name' ); ?>
</li>
