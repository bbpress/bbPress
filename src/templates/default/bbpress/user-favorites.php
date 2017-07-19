<?php

/**
 * User Favorites
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_user_favorites' ); ?>

<div id="bbp-user-favorites" class="bbp-user-favorites">

	<?php bbp_get_template_part( 'form', 'topic-search' ); ?>

	<h2 class="entry-title"><?php esc_html_e( 'Favorite Forum Topics', 'bbpress' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_favorites() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

			<?php bbp_get_template_part( 'loop',       'topics' ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

		<?php else : ?>

			<?php bbp_get_template_part( 'feedback', 'no-topics' ); ?>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-favorites -->

<?php do_action( 'bbp_template_after_user_favorites' );
