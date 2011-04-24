<?php

/**
 * Single Forum Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php if ( bbp_is_forum_public( bbp_get_forum_id(), false ) || current_user_can( 'read_private_forums' ) ) : ?>

	<?php bbp_single_forum_description(); ?>

	<?php if ( bbp_get_forum_subforum_count() ) : ?>

		<?php while( bbp_has_forums() ) : ?>

			<?php get_template_part( 'bbpress/loop', 'forums' ); ?>

		<?php endwhile; ?>

	<?php endif; ?>

	<?php if ( !bbp_is_forum_category() ) : ?>

		<?php get_template_part( 'bbpress/pagination', 'topics' ); ?>

		<?php get_template_part( 'bbpress/loop',       'topics' ); ?>

		<?php get_template_part( 'bbpress/pagination', 'topics' ); ?>

		<?php get_template_part( 'bbpress/form',       'topic'  ); ?>

	<?php endif; ?>

<?php else : ?>

	<div id="forum-private" class="bbp-forum-info">
		<h1 class="entry-title"><?php _e( 'Private', 'bbpress' ); ?></h1>
		<div class="entry-content">

			<div class="bbp-template-notice info">
				<p><?php _e( 'You do not have permission to view this forum.', 'bbpress' ); ?></p>
			</div>

		</div>
	</div><!-- #forum-private -->

<?php endif; ?>
