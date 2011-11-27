<?php

/**
 * User Favorites
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

	<?php bbp_set_query_name( 'bbp_user_profile_favorites' ); ?>

	<div id="bbp-author-favorites" class="bbp-author-favorites">
		<h2 class="entry-title"><?php _e( 'Favorite Forum Topics', 'bbpress' ); ?></h2>
		<div class="bbp-user-section">

			<?php if ( bbp_get_user_favorites() ) : ?>

				<?php bbp_get_template_part( 'bbpress/pagination', 'topics' ); ?>

				<?php bbp_get_template_part( 'bbpress/loop',       'topics' ); ?>

				<?php bbp_get_template_part( 'bbpress/pagination', 'topics' ); ?>

			<?php else : ?>

				<p><?php bbp_is_user_home() ? _e( 'You currently have no favorite topics.', 'bbpress' ) : _e( 'This user has no favorite topics.', 'bbpress' ); ?></p>

			<?php endif; ?>

		</div>
	</div><!-- #bbp-author-favorites -->

	<?php bbp_reset_query_name(); ?>
