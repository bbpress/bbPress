<?php

/**
 * User Term Subscriptions
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( bbp_get_user_id() && bbp_has_term_subscription_taxonomies() ) :

	while ( bbp_term_subscription_taxonomies() ) : bbp_the_term_subscription_taxonomy(); ?>

		<h2 class="entry-title">
			<?php
			printf(
				/* translators: %s: Taxonomy plural label. */
				esc_html__( 'Subscribed %s', 'bbpress' ),
				esc_html( bbp_get_term_subscription_taxonomy_label() )
			);
			?>
		</h2>
		<div class="bbp-user-section">

			<?php if ( bbp_get_user_term_subscriptions( array( 'taxonomy' => bbp_get_term_subscription_taxonomy_id() ) ) ) : ?>

				<?php bbp_get_template_part( 'loop', 'term-subscriptions' ); ?>

			<?php else : ?>

				<?php bbp_get_template_part( 'feedback', 'no-term-subscriptions' ); ?>

			<?php endif; ?>

		</div>

	<?php endwhile; ?>

<?php endif;
