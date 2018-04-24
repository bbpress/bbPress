<?php

/**
 * bbPress Users Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Users_Admin' ) ) :
/**
 * Loads bbPress users admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since 2.0.0 bbPress (r2464)
 */
class BBP_Users_Admin {

	/**
	 * The bbPress users admin loader
	 *
	 * @since 2.0.0 bbPress (r2515)
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since 2.0.0 bbPress (r2646)
	 *
	 * @access private
	 */
	function setup_actions() {

		// Bail if in network admin
		if ( is_network_admin() ) {
			return;
		}

		// User profile edit/display actions
		add_action( 'edit_user_profile', array( $this, 'secondary_role_display' ) );

		// WordPress user screen
		// Remvove the bottom list table "change forum role" dropdown from WordPress < 4.6.
		// See https://bbpress.trac.wordpress.org/ticket/2906.
		if ( bbp_get_major_wp_version() < 4.6 ) {
			add_action( 'restrict_manage_users',  array( __CLASS__, 'user_role_bulk_dropdown' )    );
		} else {
			add_action( 'restrict_manage_users',  array( $this, 'user_role_bulk_dropdown' ), 10, 1 );
		}
		add_filter( 'manage_users_columns',       array( $this, 'user_role_column'        ), 10, 1 );
		add_filter( 'manage_users_custom_column', array( $this, 'user_role_row'           ), 10, 3 );

		// Only list bbPress roles under Forum Role, remove from WordPress' > 4.4 Site Role list.
		if ( bbp_get_major_wp_version() >= 4.4 ) {
			add_filter( 'get_role_list',          array( $this, 'user_role_list_filter'   ), 10, 2 );
		}

		// User List Table
		add_action( 'load-users.php',   array( $this, 'user_role_bulk_change' ), 10, 1 );
		add_action( 'user_row_actions', array( $this, 'user_row_actions'      ), 10, 2 );
	}

	/**
	 * Default interface for setting a forum role
	 *
	 * @since 2.2.0 bbPress (r4285)
	 *
	 * @param WP_User $profileuser User data
	 * @return bool Always false
	 */
	public static function secondary_role_display( $profileuser ) {

		// Bail if current user cannot edit users
		if ( ! current_user_can( 'edit_user', $profileuser->ID ) ) {
			return;
		}

		// Get the roles
		$dynamic_roles = bbp_get_dynamic_roles();

		// Only keymasters can set other keymasters
		if ( ! bbp_is_user_keymaster() ) {
			unset( $dynamic_roles[ bbp_get_keymaster_role() ] );
		} ?>

		<h2><?php esc_html_e( 'Forums', 'bbpress' ); ?></h2>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="bbp-forums-role"><?php esc_html_e( 'Forum Role', 'bbpress' ); ?></label></th>
					<td>

						<?php $user_role = bbp_get_user_role( $profileuser->ID ); ?>

						<select name="bbp-forums-role" id="bbp-forums-role">

							<?php if ( ! empty( $user_role ) ) : ?>

								<option value=""><?php esc_html_e( '&mdash; No role for these forums &mdash;', 'bbpress' ); ?></option>

							<?php else : ?>

								<option value="" selected="selected"><?php esc_html_e( '&mdash; No role for these forums &mdash;', 'bbpress' ); ?></option>

							<?php endif; ?>

							<?php foreach ( $dynamic_roles as $role => $details ) : ?>

								<option <?php selected( $user_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo bbp_translate_user_role( $details['name'] ); ?></option>

							<?php endforeach; ?>

						</select>
					</td>
				</tr>

			</tbody>
		</table>

		<?php
	}

	/**
	 * Add bulk forums role dropdown to the WordPress users table
	 *
	 * @since 2.2.0 bbPress (r4360)
	 * @since 2.6.0 bbPress (r6055) Introduced the `$which` parameter.
	 *
	 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
	 */
	public static function user_role_bulk_dropdown( $which ) {

		// Remove the bottom list table "change forum role" dropdown from WordPress < 4.6.
		// See https://bbpress.trac.wordpress.org/ticket/2906.
		if ( bbp_get_major_wp_version() < 4.6 ) {
			remove_action( 'restrict_manage_users', array( __CLASS__, 'user_role_bulk_dropdown' ) );
		}

		// Bail if current user cannot promote users
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		// Get the roles
		$dynamic_roles = bbp_get_dynamic_roles();

		// Only keymasters can set other keymasters
		if ( ! bbp_is_user_keymaster() ) {
			unset( $dynamic_roles[ bbp_get_keymaster_role() ] );
		}

		$select_id = 'bottom' === $which ? 'bbp-new-role2' : 'bbp-new-role';
		$button_id = 'bottom' === $which ? 'bbp-change-role2' : 'bbp-change-role';
		?>

		<label class="screen-reader-text" for="<?php echo $select_id; ?>"><?php esc_html_e( 'Change forum role to&hellip;', 'bbpress' ) ?></label>
		<select name="<?php echo $select_id; ?>" id="<?php echo $select_id; ?>" style="display:inline-block; float:none;">
			<option value=''><?php esc_html_e( 'Change forum role to&hellip;', 'bbpress' ) ?></option>
			<?php foreach ( $dynamic_roles as $role => $details ) : ?>
				<option value="<?php echo esc_attr( $role ); ?>"><?php echo bbp_translate_user_role( $details['name'] ); ?></option>
			<?php endforeach; ?>
		</select><?php submit_button( esc_html__( 'Change', 'bbpress' ), 'secondary', $button_id, false );

		wp_nonce_field( 'bbp-bulk-users', 'bbp-bulk-users-nonce' );
	}

	/**
	 * Process bulk dropdown form submission from the WordPress Users
	 * Table
	 *
	 * @since 2.2.0 bbPress (r4365)
	 *
	 * @return bool Always false
	 */
	public function user_role_bulk_change() {

		// Bail if no users specified
		if ( empty( $_REQUEST['users'] ) ) {
			return;
		}

		// Bail if this isn't a bbPress action
		if ( ( empty( $_REQUEST['bbp-new-role'] ) && empty( $_REQUEST['bbp-new-role2'] ) ) || ( empty( $_REQUEST['bbp-change-role'] ) && empty( $_REQUEST['bbp-change-role2'] ) ) ) {
			return;
		}

		$new_role = false;
		if ( ! empty( $_REQUEST['bbp-change-role2'] ) && ! empty( $_REQUEST['bbp-new-role2'] ) ) {
			$new_role = $_REQUEST['bbp-new-role2'];
		} elseif ( ! empty( $_REQUEST['bbp-change-role'] ) && ! empty( $_REQUEST['bbp-new-role'] ) ) {
			$new_role = $_REQUEST['bbp-new-role'];
		}

		// Check that the new role exists
		$dynamic_roles = bbp_get_dynamic_roles();
		if ( ! $new_role || empty( $dynamic_roles[ $new_role ] ) ) {
			return;
		}

		// Bail if nonce check fails
		check_admin_referer( 'bbp-bulk-users', 'bbp-bulk-users-nonce' );

		// Bail if current user cannot promote users
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		// Get the current user ID
		$current_user_id = (int) bbp_get_current_user_id();

		// Run through user ids
		foreach ( (array) $_REQUEST['users'] as $user_id ) {
			$user_id = (int) $user_id;

			// Don't let a user change their own role
			if ( $user_id === $current_user_id ) {
				continue;
			}

			// Set up user and role data
			$user_role = bbp_get_user_role( $user_id );
			$new_role  = sanitize_text_field( $new_role );

			// Only keymasters can set other keymasters
			if ( in_array( bbp_get_keymaster_role(), array( $user_role, $new_role ), true ) && ! bbp_is_user_keymaster() ) {
				continue;
			}

			// Set the new forums role
			if ( $new_role !== $user_role ) {
				bbp_set_user_role( $user_id, $new_role );
			}
		}
	}

	/**
	 * Add a "View" link for each user
	 *
	 * @since 2.6.0 bbPress (r6502)
	 *
	 * @param array   $actions
	 * @param WP_User $user
	 *
	 * @return array Actions with 'view' link added to them
	 */
	public function user_row_actions( $actions = array(), $user = false ) {

		// Reverse
		$actions = array_reverse( $actions );

		// Add the view action link
		$actions['view'] = '<a href="' . esc_url( bbp_get_user_profile_url( $user->ID ) ) . '" class="bbp-user-profile-link">' . esc_html__( 'View', 'bbpress' ) . '</a>';

		// Re-reverse
		return array_reverse( $actions );
	}

	/**
	 * Add Forum Role column to the WordPress Users table, and change the
	 * core role title to "Site Role"
	 *
	 * @since 2.2.0 bbPress (r4337)
	 *
	 * @param array $columns Users table columns
	 * @return array $columns
	 */
	public static function user_role_column( $columns = array() ) {

		// New title for old Role column
		$columns['role'] = esc_html__( 'Site Role',  'bbpress' );

		// New column
		$bbp_user_role = array(
			'bbp_user_role' => esc_html__( 'Forum Role', 'bbpress' )
		);

		// Make sure role columns are next to each other
		$role_pos = array_search( 'role', array_keys( $columns ), true );
		$result   = array_slice( $columns, 0, $role_pos + 1 );
		$result   = array_merge( $result, $bbp_user_role );

		// Merge and return
		return array_merge( $result, array_slice( $columns, $role_pos ) );
	}

	/**
	 * Return user's forums role for display in the WordPress Users list table
	 *
	 * @since 2.2.0 bbPress (r4337)
	 *
	 * @param string $retval
	 * @param string $column_name
	 * @param int $user_id
	 *
	 * @return string Displayable bbPress user role
	 */
	public static function user_role_row( $retval = '', $column_name = '', $user_id = 0 ) {

		// User role column
		if ( 'bbp_user_role' === $column_name ) {

			// Get the users role
			$user_role = bbp_get_user_role( $user_id );
			$retval    = false;

			// Translate user role for display
			if ( ! empty( $user_role ) ) {
				$roles  = bbp_get_dynamic_roles();
				$retval = bbp_translate_user_role( $roles[ $user_role ]['name'] );
			}
		}

		// Pass retval through
		return $retval;
	}

	/**
	 * Filter the list of roles included in the WordPress site role list
	 *
	 * Ensures forum roles are only displayed under the Forum Role list in the
	 * WordPress Users list table
	 *
	 * @since 2.6.0 bbPress (r6051)
	 *
	 * @return array $roles
	 */
	public static function user_role_list_filter( $roles, $user ) {

		// Get the users role
		$user_role = bbp_get_user_role( $user->ID );

		if ( ! empty( $user_role ) ) {
			unset( $roles[ $user_role ] );
		}

		return $roles;
	}
}
new BBP_Users_Admin();
endif; // class exists
