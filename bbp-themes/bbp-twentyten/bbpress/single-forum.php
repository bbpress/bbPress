<?php

/**
 * Single Forum Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php if ( bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) ) : ?>

	<?php bbp_single_forum_description(); ?>

	<?php if ( bbp_get_forum_subforum_count() ) : ?>

		<?php if ( bbp_has_forums() ) : ?>

			<?php bbp_get_template_part( 'bbpress/loop', 'forums' ); ?>

		<?php endif; ?>

	<?php endif; ?>

	<?php if ( !bbp_is_forum_category() && bbp_has_topics() ) : ?>

		<?php bbp_get_template_part( 'bbpress/pagination', 'topics' ); ?>

		<?php bbp_get_template_part( 'bbpress/loop',       'topics' ); ?>

		<?php bbp_get_template_part( 'bbpress/pagination', 'topics' ); ?>

		<?php bbp_get_template_part( 'bbpress/form',       'topic'  ); ?>

	<?php endif; ?>

<?php elseif ( bbp_is_forum_private( bbp_get_forum_id(), false ) ) : ?>

	<?php bbp_get_template_part( 'bbpress/no', 'access'); ?>

<?php endif; ?>
