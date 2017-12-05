<?php

/**
 * bbPress Admin Tools Reset
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin reset page
 *
 * @since 2.0.0 bbPress (r2613)
 *
 */
function bbp_admin_reset_page() {
?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( esc_html__( 'Reset Forums', 'bbpress' ) ); ?></h2>
		<p><?php esc_html_e( 'Revert your forums back to a brand new installation, as if bbPress were never installed. This process cannot be undone.', 'bbpress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'The following data will be removed:', 'bbpress' ) ?></th>
						<td>
							<?php esc_html_e( 'All Forums',           'bbpress' ); ?><br />
							<?php esc_html_e( 'All Topics',           'bbpress' ); ?><br />
							<?php esc_html_e( 'All Replies',          'bbpress' ); ?><br />
							<?php esc_html_e( 'All Topic Tags',       'bbpress' ); ?><br />
							<?php esc_html_e( 'All Meta Data',        'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum Settings',       'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum Activity',       'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum User Roles',     'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum Moderators',     'bbpress' ); ?><br />
							<?php esc_html_e( 'Importer Helper Data', 'bbpress' ); ?><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Delete imported users?', 'bbpress' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( "Say it ain't so!", 'bbpress' ); ?></span></legend>
								<label><input type="checkbox" class="checkbox" name="bbpress-delete-imported-users" id="bbpress-delete-imported-users" value="1" /> <?php esc_html_e( 'This option will delete all previously imported users, and cannot be undone.', 'bbpress' ); ?></label>
								<p class="description"><?php esc_html_e( 'Proceeding without this checked removes the meta-data necessary to delete these users later.', 'bbpress' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Do you really want to do this?', 'bbpress' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( "Say it ain't so!", 'bbpress' ); ?></span></legend>
								<label><input type="checkbox" class="checkbox" name="bbpress-are-you-sure" id="bbpress-are-you-sure" value="1" /> <?php esc_html_e( 'This process cannot be undone.', 'bbpress' ); ?></label>
								<p class="description"><?php esc_html_e( 'Backup your database before proceeding.', 'bbpress' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php esc_attr_e( 'Reset bbPress', 'bbpress' ); ?>" />
				<?php wp_nonce_field( 'bbpress-reset' ); ?>
			</fieldset>
		</form>
	</div>

<?php
}

/**
 * Handle a bbPress admin area reset request.
 *
 * @since 2.0.0 bbPress (r2613)
 */
function bbp_admin_reset_handler() {

	// Bail if not resetting.
	if ( ! bbp_is_post_request() || empty( $_POST['bbpress-are-you-sure'] ) ) {
		return;
	}

	// Only keymasters can proceed.
	if ( ! bbp_is_user_keymaster() ) {
		return;
	}

	// Bail if not referred from resetter
	check_admin_referer( 'bbpress-reset' );

	// Reset all of bbPress
	bbp_admin_reset_database();
}

/**
 * Perform a bbPress database reset.
 *
 * @since 2.6.0 bbPress
 */
function bbp_admin_reset_database() {

	// Stores messages.
	$messages = array();
	$failed   = esc_html__( 'Failed!',  'bbpress' );
	$success  = esc_html__( 'Success!', 'bbpress' );

	// Flush the cache; things are about to get ugly.
	wp_cache_flush();

	/** Posts *****************************************************************/

	// Post types and status.
	$fpt = bbp_get_forum_post_type();
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();

	// Define variables.
	$bbp_db     = bbp_db();
	$statement  = esc_html__( 'Deleting Posts&hellip; %s', 'bbpress' );

	$sql_posts  = $bbp_db->get_results( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` IN ('{$fpt}', '{$tpt}', '{$rpt}')", OBJECT_K );
	$sql_delete = "DELETE FROM `{$bbp_db->posts}` WHERE `post_type` IN ('{$fpt}', '{$tpt}', '{$rpt}')";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** Post Meta *************************************************************/

	if ( ! empty( $sql_posts ) ) {
		$sql_meta = array();
		foreach ( $sql_posts as $key => $value ) {
			$sql_meta[] = $key;
		}
		$statement  = esc_html__( 'Deleting Post Meta&hellip; %s', 'bbpress' );
		$sql_meta   = implode( "', '", $sql_meta );
		$sql_delete = "DELETE FROM `{$bbp_db->postmeta}` WHERE `post_id` IN ('{$sql_meta}')";
		$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
		$messages[] = sprintf( $statement, $result );
	}

	/** Post Revisions ********************************************************/

	if ( ! empty( $sql_posts ) ) {
		$sql_meta = array();
		foreach ( $sql_posts as $key => $value ) {
			$sql_meta[] = $key;
		}
		$statement  = esc_html__( 'Deleting Post Revisions&hellip; %s', 'bbpress' );
		$sql_meta   = implode( "', '", $sql_meta );
		$sql_delete = "DELETE FROM `{$bbp_db->posts}` WHERE `post_parent` IN ('{$sql_meta}') AND `post_type` = 'revision'";
		$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
		$messages[] = sprintf( $statement, $result );
	}

	/** Forum moderators ******************************************************/

	$statement  = esc_html__( 'Deleting Forum Moderators&hellip; %s', 'bbpress' );
	$sql_delete = "DELETE a,b,c FROM `{$bbp_db->terms}` AS a LEFT JOIN `{$bbp_db->term_taxonomy}` AS c ON a.term_id = c.term_id LEFT JOIN `{$bbp_db->term_relationships}` AS b ON b.term_taxonomy_id = c.term_taxonomy_id WHERE c.taxonomy = 'forum-mod'";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** Topic Tags ************************************************************/

	$statement  = esc_html__( 'Deleting Topic Tags&hellip; %s', 'bbpress' );
	$sql_delete = "DELETE a,b,c FROM `{$bbp_db->terms}` AS a LEFT JOIN `{$bbp_db->term_taxonomy}` AS c ON a.term_id = c.term_id LEFT JOIN `{$bbp_db->term_relationships}` AS b ON b.term_taxonomy_id = c.term_taxonomy_id WHERE c.taxonomy = 'topic-tag'";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** User ******************************************************************/

	// First, if we're deleting previously imported users, delete them now
	if ( ! empty( $_POST['bbpress-delete-imported-users'] ) ) {
		$sql_users  = $bbp_db->get_results( "SELECT `user_id` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '_bbp_old_user_id'", OBJECT_K );
		if ( ! empty( $sql_users ) ) {
			$sql_meta = array();
			foreach ( $sql_users as $key => $value ) {
				$sql_meta[] = $key;
			}
			$statement  = esc_html__( 'Deleting Imported Users&hellip; %s', 'bbpress' );
			$sql_meta   = implode( "', '", $sql_meta );
			$sql_delete = "DELETE FROM `{$bbp_db->users}` WHERE `ID` IN ('{$sql_meta}')";
			$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
			$messages[] = sprintf( $statement, $result );
			$statement  = esc_html__( 'Deleting Imported User Meta&hellip; %s', 'bbpress' );
			$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `user_id` IN ('{$sql_meta}')";
			$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
			$messages[] = sprintf( $statement, $result );
		}
	}

	// Next, if we still have users that were not imported delete that meta data
	$statement  = esc_html__( 'Deleting User Meta&hellip; %s', 'bbpress' );
	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` LIKE '%%_bbp_%%'";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** Converter *************************************************************/

	$statement  = esc_html__( 'Deleting Conversion Table&hellip; %s', 'bbpress' );
	$table_name = $bbp_db->prefix . 'bbp_converter_translator';
	if ( $bbp_db->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
		$bbp_db->query( "DROP TABLE {$table_name}" );
		$result = $success;
	} else {
		$result = $failed;
	}
	$messages[] = sprintf( $statement, $result );

	/** Options ***************************************************************/

	$statement  = esc_html__( 'Deleting Settings&hellip; %s', 'bbpress' );
	bbp_delete_options();
	$messages[] = sprintf( $statement, $success );

	/** Roles *****************************************************************/

	$statement  = esc_html__( 'Deleting Roles and Capabilities&hellip; %s', 'bbpress' );
	bbp_remove_roles();
	bbp_remove_caps();
	$messages[] = sprintf( $statement, $success );

	/** Output ****************************************************************/

	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			bbp_admin_tools_feedback( $message );
		}
	}
}
