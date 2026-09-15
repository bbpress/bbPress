<?php

/**
 * User Roles Profile Edit Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<?php if ( bbp_current_user_can_edit_user_field( 'site_role', bbp_get_displayed_user_id() ) ) : ?>
	<div>
		<label for="role"><?php esc_html_e( 'Blog Role', 'bbpress' ); ?></label>

		<?php bbp_edit_user_blog_role(); ?>

	</div>
<?php endif; ?>

<?php if ( bbp_current_user_can_edit_user_field( 'forum_role', bbp_get_displayed_user_id() ) ) : ?>
	<div>
		<label for="forum-role"><?php esc_html_e( 'Forum Role', 'bbpress' ); ?></label>

		<?php bbp_edit_user_forums_role(); ?>

	</div>
<?php endif; ?>
