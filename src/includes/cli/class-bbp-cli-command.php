<?php

/**
 * Small shared checks for bbPress WP-CLI commands.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Common command input checks. Public methods belong in the command families.
 *
 * @since 2.7.0 bbPress (r8552)
 */
abstract class BBP_CLI_Command {

	/**
	 * Require a capability from the selected --user.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access protected
	 *
	 * @param string $cap       Capability to check.
	 * @param int    $object_id Optional object ID for a meta capability.
	 * @return void
	 */
	protected function require_cap( $cap, $object_id = 0 ) {
		$allowed = $object_id
			? current_user_can( $cap, $object_id )
			: current_user_can( $cap );

		if ( ! $allowed ) {
			WP_CLI::error( __( 'You are not allowed to do that. Use --user with a permitted account.', 'bbpress' ) );
		}
	}

	/**
	 * Resolve an explicit post ID and reject another post type.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access protected
	 *
	 * @param int|string $value     Post ID supplied by WP-CLI.
	 * @param string     $post_type Expected post type.
	 * @return int Valid post ID.
	 */
	protected function post_id( $value, $post_type ) {
		if ( ! ctype_digit( (string) $value ) || ! (int) $value || get_post_type( (int) $value ) !== $post_type ) {
			WP_CLI::error( __( 'The requested forum item does not exist.', 'bbpress' ) );
		}

		return (int) $value;
	}

	/**
	 * Resolve an explicit user ID or login.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access protected
	 *
	 * @param int|string $value User ID or login supplied by WP-CLI.
	 * @return int Valid user ID.
	 */
	protected function user_id( $value ) {
		$user = ctype_digit( (string) $value )
			? get_user_by( 'id', (int) $value )
			: get_user_by( 'login', $value );

		if ( ! $user ) {
			WP_CLI::error( __( 'The requested user does not exist.', 'bbpress' ) );
		}

		return $user->ID;
	}

	/**
	 * Use the current user unless an author was explicitly supplied.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access protected
	 *
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @param string               $post_type  Plural post type for the edit-others capability.
	 * @return int Valid author ID.
	 */
	protected function author_id( $assoc_args, $post_type ) {
		$author_id = isset( $assoc_args['author'] )
			? $this->user_id( $assoc_args['author'] )
			: get_current_user_id();

		if ( ! $author_id ) {
			WP_CLI::error( __( 'Select an author with --user or --author.', 'bbpress' ) );
		}
		if ( get_current_user_id() !== $author_id ) {
			$this->require_cap( 'edit_others_' . $post_type );
		}

		return $author_id;
	}

	/**
	 * Run and report a bbPress status transition.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access protected
	 *
	 * @param int|string $value       Post ID supplied by WP-CLI.
	 * @param string     $post_type   Expected post type.
	 * @param string     $cap         Capability needed on the post.
	 * @param string     $callback    bbPress transition function.
	 * @param string     $success     Translated success message with an ID placeholder.
	 * @param string     $state_check Optional predicate for the current state.
	 * @return void
	 */
	protected function change( $value, $post_type, $cap, $callback, $success, $state_check = '' ) {
		$id = $this->post_id( $value, $post_type );
		$this->require_cap( $cap, $id );
		if ( $state_check && ! call_user_func( $state_check, $id ) ) {
			WP_CLI::error( __( 'The forum item is not in a valid state for this command.', 'bbpress' ) );
		}

		if ( ! call_user_func( $callback, $id ) ) {
			WP_CLI::error( __( 'The forum item could not be changed, or is already in that state.', 'bbpress' ) );
		}

		WP_CLI::success( sprintf( $success, $id ) );
	}

	/**
	 * Print IDs from bbPress relationship queries.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access protected
	 *
	 * @param array<int|string, int|string|WP_Post> $items Relationship objects or IDs.
	 * @return void
	 */
	protected function print_ids( $items ) {
		$ids = array_map(
			static function ( $item ) {
				return ( $item instanceof WP_Post ) ? $item->ID : absint( $item );
			},
			(array) $items
		);

		WP_CLI::line( implode( ' ', $ids ) );
	}
}
