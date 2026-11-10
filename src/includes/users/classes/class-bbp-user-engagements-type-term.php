<?php

/**
 * bbPress User Engagements Term Type.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Engagement type for taxonomy terms.
 *
 * @since 2.7.0 bbPress (r8323)
 */
class BBP_User_Engagements_Type_Term extends BBP_User_Engagements_Type {

	/**
	 * Check whether a term supports an engagement.
	 *
	 * Terms may only be subscribed to when their taxonomy opts in with the
	 * `bbp_subscriptions` registration argument.
	 *
	 * @since 2.7.0 bbPress (r8323)
	 * @since 2.7.0 bbPress (r8330) Check the taxonomy subtype setting.
	 *
	 * @param string $engagement Favorite or subscription.
	 * @param int    $object_id  Term ID.
	 * @return bool Whether the term supports the engagement.
	 */
	public function supports( $engagement = '', $object_id = 0 ) {
		$term = $this->get_term( $object_id );

		if ( ! ( $term instanceof WP_Term ) ) {
			return false;
		}

		$taxonomy = get_taxonomy( $term->taxonomy );

		return ( 'subscription' === $engagement )
			&& ( $taxonomy instanceof WP_Taxonomy )
			&& ! empty( $taxonomy->bbp_subscriptions )
			&& bbp_is_subscription_subtype_active( 'term', $term->taxonomy );
	}

	/**
	 * Check whether the current user can read a term.
	 *
	 * Reading a term requires its taxonomy to be viewable, and the same access
	 * as reading forum content.
	 *
	 * @since 2.7.0 bbPress (r8323)
	 *
	 * @param int $object_id Term ID.
	 * @return bool Whether the current user can read the term.
	 */
	public function current_user_can_read( $object_id = 0 ) {
		$term = $this->get_term( $object_id );

		return ( $term instanceof WP_Term )
			&& is_taxonomy_viewable( $term->taxonomy )
			&& current_user_can( 'spectate' );
	}

	/**
	 * Check whether term queries support `tax_query`.
	 *
	 * @since 2.7.0 bbPress (r8323)
	 *
	 * @return bool False, because WP_Term_Query does not support `tax_query`.
	 */
	public function supports_tax_query() {
		return false;
	}

	/**
	 * Get query arguments that limit results to specific terms.
	 *
	 * @since 2.7.0 bbPress (r8323)
	 *
	 * @param array $object_ids Term IDs.
	 * @return array Query arguments.
	 */
	public function get_query_args( $object_ids = array() ) {
		return array(
			'include' => $object_ids
		);
	}

	/**
	 * Get query arguments that limit results to a taxonomy.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string $object_subtype Taxonomy name.
	 * @return array Query arguments.
	 */
	public function get_subtype_query_args( $object_subtype = '' ) {
		$object_subtype = is_string( $object_subtype )
			? sanitize_key( $object_subtype )
			: '';

		return ! empty( $object_subtype )
			? array(
				'taxonomy' => $object_subtype
			)
			: array();
	}

	/**
	 * Get the URL of a term.
	 *
	 * Topic tags use their own link, so its filter still applies.
	 *
	 * @since 2.7.0 bbPress (r8323)
	 *
	 * @param int $object_id Term ID.
	 * @return string|false Term archive URL, or false if the term does not exist.
	 */
	public function get_url( $object_id = 0 ) {
		$term = $this->get_term( $object_id );

		if ( ! ( $term instanceof WP_Term ) ) {
			return false;
		}

		// Topic tags use their own link
		$url = ( bbp_get_topic_tag_tax_id() === $term->taxonomy )
			? bbp_get_topic_tag_link( $term->slug )
			: get_term_link( $term );

		return ( ! empty( $url ) && ! is_wp_error( $url ) )
			? $url
			: false;
	}

	/**
	 * Get a term by ID.
	 *
	 * @since 2.7.0 bbPress (r8323)
	 *
	 * @param int $object_id Term ID.
	 * @return WP_Term|null|WP_Error The term, or nothing if it does not exist.
	 */
	private function get_term( $object_id = 0 ) {
		return ! empty( $object_id )
			? get_term( (int) $object_id )
			: null;
	}
}
