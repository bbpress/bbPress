<?php

/**
 * User Topics Created
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Fires before the user topics created template section.
 *
 * @since 2.1.0 bbPress (r3807)
 */
do_action( 'bbp_template_before_user_topics_created' ); ?>

<div id="bbp-user-topics-started" class="bbp-user-topics-started">

	<?php bbp_get_template_part( 'form', 'topic-search' ); ?>

	<h2 class="entry-title"><?php esc_html_e( 'Forum Topics Started', 'bbpress' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_topics_started() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

			<?php bbp_get_template_part( 'loop',       'topics' ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

		<?php else : ?>

			<?php bbp_get_template_part( 'feedback', 'no-topics' ); ?>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-topics-started -->

<?php
/**
 * Fires after the user topics created template section.
 *
 * @since 2.1.0 bbPress (r3807)
 */
do_action( 'bbp_template_after_user_topics_created' );
