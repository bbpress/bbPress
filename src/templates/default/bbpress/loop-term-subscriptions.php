<?php

/**
 * Term Subscriptions Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_term_subscriptions_loop' ); ?>

<ul id="bbp-term-subscriptions-<?php bbp_term_subscription_taxonomy_id(); ?>" class="bbp-term-subscriptions">

	<?php while ( bbp_terms() ) : bbp_the_term(); ?>

		<?php bbp_get_template_part( 'loop', 'single-term-subscription' ); ?>

	<?php endwhile; ?>

</ul>

<?php do_action( 'bbp_template_after_term_subscriptions_loop' );
