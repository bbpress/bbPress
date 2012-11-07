<?php

/**
 * bbPress Users Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Users_Admin' ) ) :
/**
 * Loads bbPress users admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
 */
class BBP_Users_Admin {

	/**
	 * The bbPress users admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Users_Admin::setup_globals() Setup the globals needed
	 * @uses BBP_Users_Admin::setup_actions() Setup the hooks and actions
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since bbPress (r2646)
	 * @access private
	 *
	 * @uses add_action() To add various actions
	 */
	function setup_actions() {

		// Bail if in network admin
		if ( is_network_admin() )
			return;

		// User profile edit/display actions
		add_action( 'edit_user_profile', array( $this, 'secondary_role_display' ) );

		// WordPress user screen
		add_filter( 'manage_users_columns',       array( $this, 'user_role_column' )        );
		add_filter( 'manage_users_custom_column', array( $this, 'user_role_row'    ), 10, 3 );
	}

	/**
	 * Default interface for setting a forum role
	 *
	 * @since bbPress (r4285)
	 *
	 * @param WP_User $profileuser User data
	 * @return bool Always false
	 */
	public static function secondary_role_display( $profileuser ) {

		// Bail if current user cannot edit users
		if ( ! current_user_can( 'edit_user', $profileuser->ID ) )
			return; ?>

		<h3><?php _e( 'Forums', 'bbpress' ); ?></h3>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="bbp-forums-role"><?php _e( 'Forum Role', 'bbpress' ); ?></label></th>
					<td>

						<?php $user_role = bbp_get_user_role( $profileuser->ID ); ?>

						<select name="bbp-forums-role" id="bbp-forums-role">

							<?php if ( ! empty( $user_role ) ) : ?>

								<option value=""><?php _e( '&mdash; No role for this forum &mdash;', 'bbpress' ); ?></option>

							<?php else : ?>

								<option value="" selected="selected"><?php _e( '&mdash; No role for this forum &mdash;', 'bbpress' ); ?></option>

							<?php endif; ?>

							<?php foreach ( bbp_get_dynamic_roles() as $role => $details ) : ?>

								<option <?php selected( $user_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo translate_user_role( $details['name'] ); ?></option>

							<?php endforeach; ?>

						</select>
					</td>
				</tr>

			</tbody>
		</table>

		<?php
	}

	/**
	 * Add Forum Role column to the WordPress Users table, and change the
	 * core role title to "Site Role"
	 *
	 * @since bbPress (r4337)
	 *
	 * @param array $columns Users table columns
	 * @return array $columns
	 */
	public static function user_role_column( $columns = array() ) {
		$columns['role']          = __( 'Site Role',  'bbpress' );
    	$columns['bbp_user_role'] = __( 'Forum Role', 'bbpress' );

		return $columns;
	}

	/**
	 * Return user's forums role for display in the WordPress Users list table
	 *
	 * @since bbPress (r4337)
	 *
	 * @param string $retval
	 * @param string $column_name
	 * @param int $user_id
	 *
	 * @return string Displayable bbPress user role
	 */
	public static function user_role_row( $retval = '', $column_name = '', $user_id = 0 ) {

		// Only looking for bbPress's user role column
		if ( 'bbp_user_role' == $column_name ) {

			// Get the users role
			$user_role = bbp_get_user_role( $user_id );
			$retval    = false;

			// Translate user role for display
			if ( ! empty( $user_role ) ) {
				$roles  = bbp_get_dynamic_roles();
				$retval = translate_user_role( $roles[$user_role]['name'] );
			}
		}

		// Pass retval through
		return $retval;
	}
}
new BBP_Users_Admin();
endif; // class exists
