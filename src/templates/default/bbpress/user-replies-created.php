<?php

/**
 * User Replies Created
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_user_replies' ); ?>

<div id="bbp-user-replies-created" class="bbp-user-replies-created">

	<?php bbp_get_template_part( 'form', 'reply-search' ); ?>

	<h2 class="entry-title"><?php esc_html_e( 'Forum Replies Created', 'bbpress' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_replies_created() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'replies' ); ?>

			<?php bbp_get_template_part( 'loop',       'replies' ); ?>

			<?php bbp_get_template_part( 'pagination', 'replies' ); ?>

		<?php else : ?>

			<?php bbp_get_template_part( 'feedback', 'no-replies' ); ?>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-replies-created -->

<?php do_action( 'bbp_template_after_user_replies' );
