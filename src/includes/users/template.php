<?php

/**
 * bbPress User Template Tags.
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Query Classes *************************************************************/

/**
 * Extension of WP_User_Query to allow easy looping.
 *
 * @since 2.6.0 bbPress (r6332)
 */
class BBP_User_Query extends WP_User_Query {

	/**
	 * The amount of users for the current query.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 * @var int
	 */
	public $user_count = 0;

	/**
	 * Index of the current item in the loop.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 * @var int
	 */
	public $current_user = -1;

	/**
	 * Whether the loop has started and the caller is in the loop.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 * @var bool
	 */
	public $in_the_loop = false;

	/**
	 * The current user.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 * @var WP_User
	 */
	public $user;

	/**
	 * PHP5 constructor.
	 *
	 * @since 2.6.0 bbPress (r6334)
	 * @access public
	 *
	 * @param null|string|array<string, mixed> $query Optional. The query variables.
	 */
	public function __construct( $query = null ) {
		if ( ! empty( $query ) ) {
			parent::__construct( $query );
			$this->user_count = count( $this->get_results() );
		}
	}

	/**
	 * Set up the next user and iterate current user index.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 *
	 * @return WP_User Next user.
	 */
	public function next_user() {
		++$this->current_user;
		$this->user = $this->get_results()[ $this->current_user ];

		return $this->user;
	}

	/**
	 * Sets up the current user.
	 *
	 * Retrieves the next user, sets up the user, sets the 'in the loop'
	 * property to true.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 *
	 * @return void
	 */
	public function the_user() {
		$this->in_the_loop = true;

		// loop has just started
		if ( -1 === $this->current_user ) {

			/**
			 * Fires once the loop is started.
			 *
			 * @since 2.6.0 bbPress (r6332)
			 *
			 * @param BBP_User_Query $query User query instance (passed by reference).
			 */
			do_action_ref_array( 'loop_start', array( &$this ) );
		}

		$this->next_user();
	}

	/**
	 * Determines whether there are more users available in the loop.
	 *
	 * Calls the {@see 'loop_end'} action when the loop is complete.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 *
	 * @return bool True if users are available, false if end of loop.
	 */
	public function have_users() {
		if ( ( $this->current_user + 1 ) < $this->user_count ) {
			return true;
		} elseif ( ( ( $this->current_user + 1 ) === $this->user_count ) && ( $this->user_count > 0 ) ) {

			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.6.0 bbPress (r6332)
			 *
			 * @param BBP_User_Query $query User query instance (passed by reference).
			 */
			do_action_ref_array( 'loop_end', array( &$this ) );

			// Do some cleaning up after the loop
			$this->rewind_users();
		}

		$this->in_the_loop = false;

		return false;
	}

	/**
	 * Rewind the users and reset user index.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 * @access public
	 *
	 * @return void
	 */
	public function rewind_users() {
		$this->current_user = -1;

		if ( $this->user_count > 0 ) {
			$this->user = $this->get_results()[0];
		}
	}
}

/**
 * Extension of WP_Term_Query to allow easy looping.
 *
 * @since 2.7.0 bbPress (r8335)
 */
class BBP_Term_Query extends WP_Term_Query {

	/**
	 * The amount of terms for the current query.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var int
	 */
	public $term_count = 0;

	/**
	 * Index of the current item in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var int
	 */
	public $current_term = -1;

	/**
	 * Whether the loop has started.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var bool
	 */
	public $in_the_loop = false;

	/**
	 * The current term.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var WP_Term|null
	 */
	public $term = null;

	/**
	 * Setup the term query.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string|array<string, mixed>|null $query Optional. Term query variables.
	 */
	public function __construct( $query = null ) {
		$this->terms = array();

		if ( ! empty( $query ) ) {
			parent::__construct( $query );
			$this->terms      = ! is_wp_error( $this->terms ) ? (array) $this->terms : array();
			$this->term_count = count( $this->terms );
		}
	}

	/**
	 * Set up the next term and iterate the current term index.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return WP_Term Next term.
	 */
	public function next_term() {
		++$this->current_term;
		$this->term = $this->terms[ $this->current_term ];

		return $this->term;
	}

	/**
	 * Set up the current term in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return void
	 */
	public function the_term() {
		$this->in_the_loop = true;

		if ( -1 === $this->current_term ) {
			do_action_ref_array( 'loop_start', array( &$this ) );
		}

		$this->next_term();
	}

	/**
	 * Determine whether there are more terms in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return bool Whether more terms are available.
	 */
	public function have_terms() {
		if ( ( $this->current_term + 1 ) < $this->term_count ) {
			return true;
		} elseif ( ( ( $this->current_term + 1 ) === $this->term_count ) && ( $this->term_count > 0 ) ) {
			do_action_ref_array( 'loop_end', array( &$this ) );
			$this->rewind_terms();
		}

		$this->in_the_loop = false;

		return false;
	}

	/**
	 * Rewind the terms and reset the term index.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return void
	 */
	public function rewind_terms() {
		$this->current_term = -1;

		if ( $this->term_count > 0 ) {
			$this->term = $this->terms[0];
		}
	}
}

/**
 * Query-like loop over registered taxonomies.
 *
 * @since 2.7.0 bbPress (r8335)
 */
class BBP_Taxonomy_Query {

	/**
	 * Taxonomies in the current query.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var list<WP_Taxonomy>
	 */
	public $taxonomies = array();

	/**
	 * The amount of taxonomies for the current query.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var int
	 */
	public $taxonomy_count = 0;

	/**
	 * Index of the current item in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var int
	 */
	public $current_taxonomy = -1;

	/**
	 * Whether the loop has started.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var bool
	 */
	public $in_the_loop = false;

	/**
	 * The current taxonomy.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @var WP_Taxonomy|null
	 */
	public $taxonomy = null;

	/**
	 * Setup the taxonomy query.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param array<array-key, string> $taxonomies Optional. Taxonomy names.
	 */
	public function __construct( $taxonomies = array() ) {
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy = get_taxonomy( $taxonomy );

			if ( $taxonomy instanceof WP_Taxonomy ) {
				$this->taxonomies[] = $taxonomy;
			}
		}

		$this->taxonomy_count = count( $this->taxonomies );
	}

	/**
	 * Set up the next taxonomy and iterate the current taxonomy index.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return WP_Taxonomy Next taxonomy.
	 */
	public function next_taxonomy() {
		++$this->current_taxonomy;
		$this->taxonomy = $this->taxonomies[ $this->current_taxonomy ];

		return $this->taxonomy;
	}

	/**
	 * Set up the current taxonomy in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return WP_Taxonomy Current taxonomy.
	 */
	public function the_taxonomy() {
		$this->in_the_loop = true;

		if ( -1 === $this->current_taxonomy ) {
			do_action_ref_array( 'loop_start', array( &$this ) );
		}

		return $this->next_taxonomy();
	}

	/**
	 * Determine whether there are more taxonomies in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return bool Whether more taxonomies are available.
	 */
	public function have_taxonomies() {
		if ( ( $this->current_taxonomy + 1 ) < $this->taxonomy_count ) {
			return true;
		} elseif ( ( ( $this->current_taxonomy + 1 ) === $this->taxonomy_count ) && ( $this->taxonomy_count > 0 ) ) {
			do_action_ref_array( 'loop_end', array( &$this ) );
			$this->rewind_taxonomies();
		}

		$this->in_the_loop = false;

		return false;
	}

	/**
	 * Rewind the taxonomies and reset the taxonomy index.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @return void
	 */
	public function rewind_taxonomies() {
		$this->current_taxonomy = -1;

		if ( $this->taxonomy_count > 0 ) {
			$this->taxonomy = $this->taxonomies[0];
		}
	}
}

/** User Loop *****************************************************************/

/**
 * The main user loop.
 *
 * @since 2.6.0 bbPress (r6332)
 *
 * @param array<string, mixed> $args All the arguments supported by {@link WP_User_Query}.
 * @return bool Whether users are available.
 */
function bbp_has_users( $args = array() ) {

	// Parse arguments with default user query for most circumstances
	$r = bbp_parse_args(
		$args,
		array(
			'include'     => array(),
			'orderby'     => 'login',
			'order'       => 'ASC',
			'count_total' => false,
			'fields'      => 'all',
		),
		'has_users'
	);

	// Run the query
	$bbp             = bbpress();
	$bbp->user_query = new BBP_User_Query( $r );

	/**
	 * Filters whether users are available.
	 *
	 * @since 2.6.0 bbPress (r6332)
	 *
	 * @param bool $value Whether users are available.
	 * @param BBP_User_Query $user_query Query object.
	 */
	return apply_filters( 'bbp_has_users', $bbp->user_query->have_users(), $bbp->user_query );
}

/**
 * Whether there are more users available in the loop.
 *
 * @since 2.6.0 bbPress (r6332)
 *
 * @return bool Whether another user is available.
 */
function bbp_users() {
	return bbpress()->user_query->have_users();
}

/**
 * Loads up the current user in the loop.
 *
 * @since 2.6.0 bbPress (r6332)
 *
 * @return void
 */
function bbp_the_user() {
	bbpress()->user_query->the_user();
}

/** Term Loop *****************************************************************/

/**
 * Start a taxonomy-term loop.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param array<string, mixed> $args Arguments supported by {@link WP_Term_Query}.
 * @return bool Whether the query has terms.
 */
function bbp_has_terms( $args = array() ) {
	$r = ! empty( $args )
		? bbp_parse_args(
			$args,
			array(
				'hide_empty' => false,
				'fields'     => 'all'
			),
			'has_terms'
		)
		: array();

	$bbp             = bbpress();
	$bbp->term_query = new BBP_Term_Query( $r );

	/**
	 * Filters whether the query has terms.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param bool $value Whether the query has terms.
	 * @param BBP_Term_Query $term_query Query object.
	 */
	return (bool) apply_filters( 'bbp_has_terms', $bbp->term_query->have_terms(), $bbp->term_query );
}

/**
 * Whether there are more terms available in the loop.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return bool Whether more terms are available.
 */
function bbp_terms() {
	return bbpress()->term_query->have_terms();
}

/**
 * Set up the current term in the loop.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return void
 */
function bbp_the_term() {
	bbpress()->term_query->the_term();
}

/** Users *********************************************************************/

/**
 * Output a validated user id.
 *
 * @since 2.0.0 bbPress (r2729)
 *
 * @param int|float|string|false $user_id                 Optional user ID, or false to use a fallback.
 * @param bool                   $displayed_user_fallback Whether to use the displayed user.
 * @param bool                   $current_user_fallback   Whether to use the current user.
 * @return void
 */
function bbp_user_id( $user_id = 0, $displayed_user_fallback = true, $current_user_fallback = false ) {
	echo bbp_get_user_id( $user_id, $displayed_user_fallback, $current_user_fallback );
}

/**
 * Return a validated user id.
 *
 * @since 2.0.0 bbPress (r2729)
 *
 * @param int|float|string|false $user_id                 Optional user ID, or false to use a fallback.
 * @param bool                   $displayed_user_fallback Whether to use the displayed user.
 * @param bool                   $current_user_fallback   Whether to use the current user.
 * @return int Validated user id.
 */
function bbp_get_user_id( $user_id = 0, $displayed_user_fallback = true, $current_user_fallback = false ) {
	$bbp = bbpress();

	// Easy empty checking
	if ( ! empty( $user_id ) && is_numeric( $user_id ) ) {
		$bbp_user_id = $user_id;

	// Currently inside a user loop
	} elseif ( ! empty( $bbp->user_query->in_the_loop ) && isset( $bbp->user_query->user->ID ) ) {
		$bbp_user_id = $bbp->user_query->user->ID;

	// Currently viewing or editing a user
	} elseif ( ( true === $displayed_user_fallback ) && ! empty( $bbp->displayed_user->ID ) ) {
		$bbp_user_id = $bbp->displayed_user->ID;

	// Maybe fallback on the current_user ID
	} elseif ( ( true === $current_user_fallback ) && ! empty( $bbp->current_user->ID ) ) {
		$bbp_user_id = $bbp->current_user->ID;

	// Failsafe
	} else {
		$bbp_user_id = 0;
	}

	/**
	 * Filters a validated user ID.
	 *
	 * @since 2.0.0 bbPress (r2729)
	 *
	 * @param int $bbp_user_id Validated user id.
	 * @param bool $displayed_user_fallback Whether to use the displayed user.
	 * @param bool $current_user_fallback Whether to use the current user.
	 */
	return (int) apply_filters( 'bbp_get_user_id', (int) $bbp_user_id, $displayed_user_fallback, $current_user_fallback );
}

/**
 * Output ID of current user.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return void
 */
function bbp_current_user_id() {
	echo bbp_get_current_user_id();
}

/**
 * Return ID of current user.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return int Current user id.
 */
function bbp_get_current_user_id() {

	/**
	 * Filters the current user ID.
	 *
	 * @since 2.0.0 bbPress (r2574)
	 *
	 * @param int $value Current user id.
	 */
	return (int) apply_filters( 'bbp_get_current_user_id', bbp_get_user_id( 0, false, true ) );
}

/**
 * Output ID of displayed user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return void
 */
function bbp_displayed_user_id() {
	echo bbp_get_displayed_user_id();
}

/**
 * Return ID of displayed user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return int Displayed user id.
 */
function bbp_get_displayed_user_id() {

	/**
	 * Filters the displayed user ID.
	 *
	 * @since 2.0.0 bbPress (r2688)
	 *
	 * @param int $value Displayed user id.
	 */
	return apply_filters( 'bbp_get_displayed_user_id', bbp_get_user_id( 0, true, false ) );
}

/**
 * Output a sanitized user field value.
 *
 * This function relies on the $filter parameter to decide how to sanitize
 * the field value that it finds. Since it uses the WP_User object's magic
 * __get() method, it can also be used to get user_meta values.
 *
 * @since 2.1.0 bbPress (r3634)
 *
 * @param string $field Field to get.
 * @param string $filter How to filter the field value (null|raw|db|display|edit).
 * @return void
 */
function bbp_displayed_user_field( $field = '', $filter = 'display' ) {
	echo bbp_get_displayed_user_field( $field, $filter );
}

/**
 * Return a sanitized user field value.
 *
 * This function relies on the $filter parameter to decide how to sanitize
 * the field value that it finds. Since it uses the WP_User object's magic
 * __get() method, it can also be used to get user_meta values.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @see WP_User::__get() for more on how the value is retrieved.
 * @see sanitize_user_field() for more on how the value is sanitized.
 *
 * @param string $field Field to get.
 * @param string $filter How to filter the field value (null|raw|db|display|edit).
 * @return string|bool Value of the field if it exists, else false.
 */
function bbp_get_displayed_user_field( $field = '', $filter = 'display' ) {

	// Get the displayed user
	$user         = bbpress()->displayed_user;

	// Juggle the user filter property because we don't want to muck up how
	// other code might interact with this object.
	$old_filter   = $user->filter;
	$user->filter = $filter;

	// Get the field value from the WP_User object. We don't need to perform
	// an isset() because the WP_User::__get() does it for us.
	$value        = $user->$field;

	// Put back the user filter property that was previously juggled above.
	$user->filter = $old_filter;

	/**
	 * Filters a sanitized user field value.
	 *
	 * @since 2.1.1 bbPress (r4094)
	 *
	 * @param string|bool $value Value of the field if it exists, else false.
	 * @param string $field Field to get.
	 * @param string $filter How to filter the field value (null|raw|db|display|edit).
	 */
	return apply_filters( 'bbp_get_displayed_user_field', $value, $field, $filter );
}

/**
 * Return a displayed user field as text for template output.
 *
	 * @since 2.7.0 bbPress (r8625)
 *
 * @param string $field Field to get.
 * @param string $filter How to filter the field value (null|raw|db|display|edit).
 * @return string Field value as text, or an empty string if unavailable.
 */
function bbp_get_displayed_user_field_text( $field = '', $filter = 'display' ) {
	$value = bbp_get_displayed_user_field( $field, $filter );

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Output name of current user.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return void
 */
function bbp_current_user_name() {
	echo esc_attr( bbp_get_current_user_name() );
}

/**
 * Return name of current user.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return string
 */
function bbp_get_current_user_name() {

	// Default current user name
	$default = bbp_get_fallback_display_name();

	// Check the $user_identity global
	$current_user_name = is_user_logged_in()
		? bbp_get_global_object( 'user_identity', false, $default )
		: $default;

	/**
	 * Filters the current user's name.
	 *
	 * @since 2.0.0 bbPress (r2574)
	 *
	 * @param string $current_user_name The current user's name.
	 */
	return apply_filters( 'bbp_get_current_user_name', $current_user_name );
}

/**
 * Output avatar of current user.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @param int $size Size of the avatar. Defaults to 40.
 * @return void
 */
function bbp_current_user_avatar( $size = 40 ) {
	echo bbp_get_current_user_avatar( $size );
}

/**
 * Return avatar of current user.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @param int $size Size of the avatar. Defaults to 40.
 * @return string Current user avatar.
 */
function bbp_get_current_user_avatar( $size = 40 ) {

	$user = bbp_get_current_user_id();
	if ( empty( $user ) ) {
		$user = bbp_get_current_anonymous_user_data( 'email' );
	}

	$avatar = get_avatar( $user, $size );

	/**
	 * Filters the current user's avatar.
	 *
	 * @since 2.0.0 bbPress (r2574)
	 *
	 * @param mixed $avatar Current user avatar, or false when unavailable.
	 * @param int $size Size of the avatar. Defaults to 40.
	 */
	return apply_filters( 'bbp_get_current_user_avatar', $avatar, $size );
}

/**
 * Output link to the profile page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_user_profile_link( $user_id = 0 ) {
	echo bbp_get_user_profile_link( $user_id );
}

/**
 * Return link to the profile page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int $user_id Optional. User id.
 * @return string|false User profile link, or false when unavailable.
 */
function bbp_get_user_profile_link( $user_id = 0 ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Get the user
	$user = get_userdata( $user_id );
	if ( empty( $user ) ) {
		return false;
	}

	// Display Name
	$name = ! empty( $user->display_name )
		? $user->display_name
		: bbp_get_fallback_display_name();

	// URL
	$url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );

	// Link
	$link = ( false !== $url )
		? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
		: esc_html( $name );

	/**
	 * Filters the link to the profile page of a user.
	 *
	 * @since 2.0.0 bbPress (r2688)
	 *
	 * @param string|false $link User profile link, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return (string) apply_filters( 'bbp_get_user_profile_link', $link, $user_id );
}

/**
 * Output a user's nicename.
 *
 * @since 2.3.0 bbPress (r4672)
 *
 * @param int   $user_id User ID whose nicename to get.
 * @param array<string, mixed> $args    Before, after, user ID, and forced nicename settings.
 * @return void
 */
function bbp_user_nicename( $user_id = 0, $args = array() ) {
	echo bbp_get_user_nicename( $user_id, $args );
}

/**
 * Return a user's nicename.
 *
 * @since 2.3.0 bbPress (r4672)
 *
 * @param int   $user_id User ID whose nicename to get.
 * @param array<string, mixed> $args    Before, after, user ID, and forced nicename settings.
 * @return string|false User nicename with optional wrapper, or false if no user ID exists.
 */
function bbp_get_user_nicename( $user_id = 0, $args = array() ) {

	// Bail if no user ID passed
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Parse default arguments
	$r = bbp_parse_args(
		$args,
		array(
			'user_id' => $user_id,
			'before'  => '',
			'after'   => '',
			'force'   => ''
		),
		'get_user_nicename'
	);

	$force     = bbp_get_template_string_arg( $r, 'force' );
	$before    = bbp_get_template_string_arg( $r, 'before' );
	$after     = bbp_get_template_string_arg( $r, 'after' );
	$lookup_id = is_numeric( $r['user_id'] ) ? (int) $r['user_id'] : 0;

	// Force the nicename (likely from a previous user query)
	if ( ! empty( $force ) ) {
		$nicename = $force;

	// Maybe fallback to getting the nicename from user data
	} elseif ( ! empty( $lookup_id ) ) {
		$user     = get_userdata( $lookup_id );
		$nicename = ! empty( $user )
			? $user->user_nicename
			: '';

	// Maybe fallback to empty string so filter still applies
	} else {
		$nicename = '';
	}

	// Maybe wrap the nicename
	$retval = ! empty( $nicename )
		? $before . esc_html( $nicename ) . $after
		: '';

	/**
	 * Filters a user's nicename.
	 *
	 * @since 2.3.0 bbPress (r4672)
	 *
	 * @param string|false $retval User nicename with optional wrapper, or false if no user ID exists.
	 * @param int $user_id User ID whose nicename to get.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Before, after, user ID, and forced nicename settings.
	 */
	return (string) apply_filters( 'bbp_get_user_nicename', $retval, $user_id, $r, $args );
}

/**
 * Output URL to the profile page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int    $user_id       Optional user ID.
 * @param string $user_nicename Optional user nicename.
 * @return void
 */
function bbp_user_profile_url( $user_id = 0, $user_nicename = '' ) {
	echo esc_url( bbp_get_user_profile_url( $user_id, $user_nicename ) );
}

/**
 * Return URL to the profile page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int    $user_id       Optional user ID.
 * @param string $user_nicename Optional user nicename.
 * @return string|false User profile URL, or false when unavailable.
 */
function bbp_get_user_profile_url( $user_id = 0, $user_nicename = '' ) {

	// Use displayed user ID if there is one, and one isn't requested
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_user_profile_url', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Pretty permalinks
	if ( bbp_use_pretty_urls() ) {

		// Get username if not passed
		if ( empty( $user_nicename ) ) {
			$user_nicename = bbp_get_user_nicename( $user_id );
		}

		// Run through home_url()
		$url = trailingslashit( bbp_get_root_url() . bbp_get_user_slug() ) . $user_nicename;
		$url = user_trailingslashit( $url );
		$url = home_url( $url );

	// Unpretty permalinks
	} else {
		$url = add_query_arg(
			array(
				bbp_get_user_rewrite_id() => $user_id
			),
			home_url( '/' )
		);
	}

	/**
	 * Filters the URL to a user profile.
	 *
	 * @since 2.0.0 bbPress (r2688)
	 *
	 * @param string|false $url User profile URL, or false when unavailable.
	 * @param int $user_id Optional user ID.
	 * @param string|false $user_nicename User nicename, or false when unavailable.
	 */
	return apply_filters( 'bbp_get_user_profile_url', $url, $user_id, $user_nicename );
}

/**
 * Output link to the profile edit page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_user_profile_edit_link( $user_id = 0 ) {
	echo bbp_get_user_profile_edit_link( $user_id );
}

/**
 * Return link to the profile edit page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int $user_id Optional. User id.
 * @return string|false User profile edit link, or false when unavailable.
 */
function bbp_get_user_profile_edit_link( $user_id = 0 ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Get the user
	$user = get_userdata( $user_id );
	if ( empty( $user ) ) {
		return false;
	}

	// Display Name
	$name = ! empty( $user->display_name )
		? $user->display_name
		: bbp_get_fallback_display_name();

	// URL
	$url = bbp_validate_url( bbp_get_user_profile_edit_url( $user_id ) );

	// Link
	$link = ( false !== $url )
		? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
		: esc_html( $name );

	/**
	 * Filters the link to edit a user profile.
	 *
	 * @since 2.0.0 bbPress (r2845)
	 *
	 * @param string|false $link User profile edit link, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return (string) apply_filters( 'bbp_get_user_profile_edit_link', $link, $user_id );
}

/**
 * Output URL to the profile edit page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int    $user_id       Optional user ID.
 * @param string $user_nicename Optional user nicename.
 * @return void
 */
function bbp_user_profile_edit_url( $user_id = 0, $user_nicename = '' ) {
	echo esc_url( bbp_get_user_profile_edit_url( $user_id, $user_nicename ) );
}

/**
 * Return URL to the profile edit page of a user.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int    $user_id       Optional user ID.
 * @param string $user_nicename Optional user nicename.
 * @return string|false User profile edit URL, or false when unavailable.
 */
function bbp_get_user_profile_edit_url( $user_id = 0, $user_nicename = '' ) {

	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_user_profile_edit_url', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get user profile URL
	$profile_url = bbp_validate_url( bbp_get_user_profile_url( $user_id, $user_nicename ) );
	$url         = false;

	// Pretty permalinks
	if ( false !== $profile_url && bbp_use_pretty_urls() ) {
		$url = trailingslashit( $profile_url ) . bbp_get_edit_slug();
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} elseif ( false !== $profile_url ) {
		$url = add_query_arg(
			array(
				bbp_get_edit_rewrite_id() => '1'
			),
			$profile_url
		);
	}

	/**
	 * Filters the URL to edit a user profile.
	 *
	 * @since 2.0.0 bbPress (r2688)
	 *
	 * @param string|false $url User profile edit URL, or false when unavailable.
	 * @param int $user_id Optional user ID.
	 * @param string $user_nicename Optional user nicename.
	 */
	return apply_filters( 'bbp_get_user_edit_profile_url', $url, $user_id, $user_nicename );
}

/**
 * Output a user's main role for display.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param int $user_id User id.
 * @return void
 */
function bbp_user_display_role( $user_id = 0 ) {
	echo esc_attr( bbp_get_user_display_role( $user_id ) );
}

/**
 * Return a user's main role for display.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param int $user_id User id.
 * @return string
 */
function bbp_get_user_display_role( $user_id = 0 ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id );

	// User is not registered
	if ( empty( $user_id ) ) {
		$role = esc_html__( 'Guest', 'bbpress' );

	// User is not active
	} elseif ( bbp_is_user_inactive( $user_id ) ) {
		$role = esc_html__( 'Inactive', 'bbpress' );

	// User have a role
	} else {
		$role_id = bbp_get_user_role( $user_id );
		$role    = bbp_get_dynamic_role_name( $role_id );
	}

	// No role found so default to generic "Member"
	if ( empty( $role ) ) {
		$role = esc_html__( 'Member', 'bbpress' );
	}

	/**
	 * Filters a user's main role for display.
	 *
	 * @since 2.1.0 bbPress (r3860)
	 *
	 * @param string $role A user's main role for display.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_user_display_role', $role, $user_id );
}

/**
 * Output the link to the admin section.
 *
 * @since 2.0.0 bbPress (r2827)
 *
 * @param array<string, mixed> $args Optional. See {@link bbp_get_admin_link()}.
 * @return void
 */
function bbp_admin_link( $args = array() ) {
	echo bbp_get_admin_link( $args );
}

/**
 * Return the link to the admin section.
 *
 * @since 2.0.0 bbPress (r2827)
 *
 * @param array<string, mixed>|string $args Link text and before/after wrapper settings.
 * @return string|null Admin link, or null without moderation capability.
 */
function bbp_get_admin_link( $args = array() ) {

	// Bail if user cannot globally moderate
	if ( ! current_user_can( 'moderate' ) ) {
		return null;
	}

	if ( ! empty( $args ) && is_string( $args ) && ( false === strpos( $args, '=' ) ) ) {
		$args = array( 'text' => $args );
	}

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'text'   => esc_html__( 'Admin', 'bbpress' ),
			'before' => '',
			'after'  => ''
		),
		'get_admin_link'
	);

	$before = bbp_get_template_string_arg( $r, 'before' );
	$after  = bbp_get_template_string_arg( $r, 'after' );
	$text   = bbp_get_template_string_arg( $r, 'text' );
	$retval = $before . '<a href="' . esc_url( admin_url() ) . '">' . $text . '</a>' . $after;

	/**
	 * Filters the link to the admin section.
	 *
	 * @since 2.0.0 bbPress (r2827)
	 *
	 * @param string|null $retval Admin link, or null without moderation capability.
	 * @param array $r Arguments.
	 * @param array<string, mixed>|string $args Link text and before/after wrapper settings.
	 */
	return apply_filters( 'bbp_get_admin_link', $retval, $r, $args );
}

/** User IP *******************************************************************/

/**
 * Output the author IP address of a post.
 *
 * @since 2.0.0 bbPress (r3120)
 *
 * @param array<string, mixed>|int $args Optional. Arguments or a post ID.
 * @return void
 */
function bbp_author_ip( $args = array() ) {
	echo bbp_get_author_ip( $args );
}

/**
 * Return the author IP address of a post.
 *
 * @since 2.0.0 bbPress (r3120)
 *
 * @param array<string, mixed>|int $args Optional. Arguments or a post ID.
 * @return string Author IP address with optional markup, or an empty string when unavailable.
 */
function bbp_get_author_ip( $args = array() ) {

	// Used as post id
	$post_id = is_numeric( $args ) ? (int) $args : 0;

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'post_id' => $post_id,
			'before'  => '<span class="bbp-author-ip">(',
			'after'   => ')</span>'
		),
		'get_author_ip'
	);

	$before_html = bbp_get_template_string_arg( $r, 'before' );
	$after_html  = bbp_get_template_string_arg( $r, 'after' );

	// Get the author IP meta value
	$author_ip = get_post_meta( $r['post_id'], '_bbp_author_ip', true );
	$author_ip = ! empty( $author_ip )
		? $before_html . esc_html( $author_ip ) . $after_html
		: '';

	/**
	 * Filters the author IP address of a post.
	 *
	 * @since 2.0.0 bbPress (r3120)
	 *
	 * @param string $author_ip Author IP address with optional markup, or an empty string when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed>|int $args Arguments or a post ID.
	 */
	return apply_filters( 'bbp_get_author_ip', $author_ip, $r, $args );
}

/** Anonymous Fields **********************************************************/

/**
 * Get the default name that's displayed when a user cannot be identified.
 *
 * This might happen if a user was deleted but their content was retained, or
 * if something went wrong during saving anonymous user data to the database.
 *
 * @since 2.6.0 bbPress (r6562)
 *
 * @param int $object_id For additional context only, usually a post ID.
 *
 * @return string
 */
function bbp_get_fallback_display_name( $object_id = 0 ) {
	$name = esc_html__( 'Anonymous', 'bbpress' );

	/**
	 * Filters the default name that's displayed when a user cannot be identified.
	 *
	 * @since 2.6.0 bbPress (r6562)
	 *
	 * @param string $name The default name that's displayed when a user cannot be identified.
	 * @param int $object_id For additional context only, usually a post ID.
	 */
	return apply_filters( 'bbp_get_anonymous_name', $name, $object_id );
}

/**
 * Output the author display-name of a topic or reply.
 *
 * Convenience function to ensure proper template functions are called
 * and correct filters are executed. Used primarily to display topic
 * and reply author information in the anonymous form template-part.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @param int $post_id Post id.
 * @return void
 */
function bbp_author_display_name( $post_id = 0 ) {
	echo esc_attr( bbp_get_author_display_name( $post_id ) );
}

/**
 * Return the author display-name of a topic or reply.
 *
 * Convenience function to ensure proper template functions are called
 * and correct filters are executed. Used primarily to display topic
 * and reply author information in the anonymous form template-part.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @param int $post_id Post id.
 *
 * @return string The name of the author.
 */
function bbp_get_author_display_name( $post_id = 0 ) {

	// Define local variable(s)
	$retval = '';

	// Topic edit
	if ( bbp_is_topic_edit() ) {
		$retval = bbp_get_topic_author_display_name( $post_id );

	// Reply edit
	} elseif ( bbp_is_reply_edit() ) {
		$retval = bbp_get_reply_author_display_name( $post_id );

	// Not an edit, so rely on current user cookie data
	} else {
		$retval = bbp_get_current_anonymous_user_data( 'name' );
	}

	/**
	 * Filters the author display-name of a topic or reply.
	 *
	 * @since 2.5.0 bbPress (r5120)
	 *
	 * @param mixed $retval Author name, or anonymous author data.
	 * @param int $post_id Post id.
	 */
	return apply_filters( 'bbp_get_author_display_name', $retval, $post_id );
}

/**
 * Output the author email of a topic or reply.
 *
 * Convenience function to ensure proper template functions are called
 * and correct filters are executed. Used primarily to display topic
 * and reply author information in the anonymous user form template-part.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @param int $post_id Post id.
 * @return void
 */
function bbp_author_email( $post_id = 0 ) {
	echo esc_attr( bbp_get_author_email( $post_id ) );
}

/**
 * Return the author email of a topic or reply.
 *
 * Convenience function to ensure proper template functions are called
 * and correct filters are executed. Used primarily to display topic
 * and reply author information in the anonymous user form template-part.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @param int $post_id Post id.
*
 * @return string The email of the author.
 */
function bbp_get_author_email( $post_id = 0 ) {

	// Define local variable(s)
	$retval = '';

	// Topic edit
	if ( bbp_is_topic_edit() ) {
		$retval = bbp_get_topic_author_email( $post_id );

	// Reply edit
	} elseif ( bbp_is_reply_edit() ) {
		$retval = bbp_get_reply_author_email( $post_id );

	// Not an edit, so rely on current user cookie data
	} else {
		$retval = bbp_get_current_anonymous_user_data( 'email' );
	}

	/**
	 * Filters the author email of a topic or reply.
	 *
	 * @since 2.5.0 bbPress (r5120)
	 *
	 * @param mixed $retval Author email, or anonymous author data.
	 * @param int $post_id Post id.
	 */
	return apply_filters( 'bbp_get_author_email', $retval, $post_id );
}

/**
 * Output the author url of a topic or reply.
 *
 * Convenience function to ensure proper template functions are called
 * and correct filters are executed. Used primarily to display topic
 * and reply author information in the anonymous user form template-part.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @param int $post_id Post id.
 * @return void
 */
function bbp_author_url( $post_id = 0 ) {
	echo esc_attr( bbp_get_author_url( $post_id ) );
}

/**
 * Return the author url of a topic or reply.
 *
 * Convenience function to ensure proper template functions are called
 * and correct filters are executed. Used primarily to display topic
 * and reply author information in the anonymous user form template-part.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @param int $post_id Post id.
 *
 * @return string The url of the author.
 */
function bbp_get_author_url( $post_id = 0 ) {

	// Define local variable(s)
	$retval = '';

	// Topic edit
	if ( bbp_is_topic_edit() ) {
		$retval = bbp_get_topic_author_url( $post_id );

	// Reply edit
	} elseif ( bbp_is_reply_edit() ) {
		$retval = bbp_get_reply_author_url( $post_id );

	// Not an edit, so rely on current user cookie data
	} else {
		$retval = bbp_get_current_anonymous_user_data( 'url' );
	}

	/**
	 * Filters the URL of a topic or reply author.
	 *
	 * @since 2.5.0 bbPress (r5120)
	 *
	 * @param mixed $retval Author URL, or anonymous author data.
	 * @param int $post_id Post id.
	 */
	return apply_filters( 'bbp_get_author_url', $retval, $post_id );
}

/** Favorites *****************************************************************/

/**
 * Output the link to the user's favorites page (profile page).
 *
 * @since 2.0.0 bbPress (r2660)
 * @since 2.6.0 bbPress (r6308) Add pagination if in the loop
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_favorites_permalink( $user_id = 0 ) {
	echo esc_url( bbp_get_favorites_permalink( $user_id ) );
}

/**
 * Return the link to the user's favorites page (profile page).
 *
 * @since 2.0.0 bbPress (r2660)
 * @since 2.6.0 bbPress (r6308) Add pagination if in the loop
 *
 * @param int $user_id Optional. User id.
 * @return string|false URL for the user favorites page, or false when unavailable.
 */
function bbp_get_favorites_permalink( $user_id = 0 ) {

	// Use displayed user ID if there is one, and one isn't requested
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_favorites_permalink', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get user profile URL & page
	$profile_url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
	$url         = false;
	$page        = (int) bbpress()->topic_query->paged;
	$paged       = (bool) bbpress()->topic_query->in_the_loop;

	// Pretty permalinks
	if ( false !== $profile_url && bbp_use_pretty_urls() ) {

		// Base URL
		$url = trailingslashit( $profile_url ) . bbp_get_user_favorites_slug();

		// Add page
		if ( ( true === $paged ) && ( $page > 1 ) ) {
			$url = trailingslashit( $url ) . bbp_get_paged_slug() . '/' . $page;
		}

		// Ensure correct trailing slash
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} elseif ( false !== $profile_url ) {

		// Base arguments
		$args = array(
			bbp_get_user_favorites_rewrite_id() => bbp_get_user_favorites_slug(),
		);

		// Add page
		if ( ( true === $paged ) && ( $page > 1 ) ) {
			$args['page'] = $page;
		}

		// Add arguments
		$url = add_query_arg( $args, $profile_url );
	}

	/**
	 * Filters the link to the user's favorites page (profile page).
	 *
	 * @since 2.0.0 bbPress (r2660)
	 *
	 * @param string|false $url URL for the user favorites page, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_favorites_permalink', $url, $user_id );
}

/**
 * Output the link to make a topic favorite/remove a topic from favorites.
 *
 * @since 2.0.0 bbPress (r2652)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args See {@link bbp_get_user_favorites_link()}.
 * @param int   $user_id Legacy user ID parameter; use $args['user_id'] instead.
 * @param bool  $wrap    Whether to wrap the link in a span.
 * @return void
 */
function bbp_user_favorites_link( $args = array(), $user_id = 0, $wrap = true ) {
	echo bbp_get_user_favorites_link( $args, $user_id, $wrap );
}

/**
 * User favorites link.
 *
 * Return the link to make a topic favorite/remove a topic from
 * favorites.
 *
 * @since 2.0.0 bbPress (r2652)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args    Favorite labels, object ID/type, user ID, wrapper, and redirect settings.
 * @param int   $user_id Legacy user ID parameter; use $args['user_id'] instead.
 * @param bool  $wrap    Whether to wrap the link in a span.
 * @return string|false Favorites link, or false when unavailable.
 */
function bbp_get_user_favorites_link( $args = array(), $user_id = 0, $wrap = true ) {

	// Bail if favorites are inactive
	if ( ! bbp_is_favorites_active() ) {
		return false;
	}

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'favorite'    => esc_html__( 'Favorite',   'bbpress' ),
			'favorited'   => esc_html__( 'Unfavorite', 'bbpress' ),
			'user_id'     => 0,
			'object_id'   => 0,
			'object_type' => 'post',
			'before'      => '',
			'after'       => '',
			'redirect_to' => '',

			// Deprecated. Use object_id.
			'forum_id'    => 0,
			'topic_id'    => 0
		),
		'get_user_favorites_link'
	);

	// Use renderable strings for labels and link arguments
	$favorite    = bbp_get_template_string_arg( $r, 'favorite' );
	$favorited   = bbp_get_template_string_arg( $r, 'favorited' );
	$object_type = bbp_get_template_string_arg( $r, 'object_type' );
	$before      = bbp_get_template_string_arg( $r, 'before' );
	$after       = bbp_get_template_string_arg( $r, 'after' );
	$redirect_to = bbp_get_template_string_arg( $r, 'redirect_to' );

	// Validate user and object ID's
	$user_arg    = $r['user_id'];
	$user_arg    = is_numeric( $user_arg ) ? $user_arg : false;
	$user_id     = bbp_get_user_id( $user_arg, true, true );
	$object_type = sanitize_key( $object_type );

	// Back-compat for deprecated arguments
	if ( ! empty( $r['topic_id'] ) ) {
		$object_arg = $r['topic_id'];
	} elseif ( ! empty( $r['forum_id'] ) ) {
		$object_arg = $r['forum_id'];
	} else {
		$object_arg = $r['object_id'];
	}
	$object_id = is_numeric( $object_arg ) ? absint( $object_arg ) : 0;

	// Validate the object before checking its favorite state
	if ( empty( $user_id ) || empty( $object_id ) || ! bbp_current_user_can_toggle_engagement( $object_id, $object_type, 'favorite', 'remove', $user_id ) ) {
		return false;
	}

	// Decide which link to show
	$is_fav = bbp_is_user_favorite( $user_id, $object_id );
	$toggle_action = ! empty( $is_fav ) ? 'remove' : 'add';
	if ( ! bbp_current_user_can_toggle_engagement( $object_id, $object_type, 'favorite', $toggle_action, $user_id ) ) {
		return false;
	}

	if ( ! empty( $is_fav ) ) {
		$text   = $favorited;
		$q_args = array(
			'action'    => 'bbp_favorite_remove',
			'object_id' => $object_id
		);
	} else {
		$text   = $favorite;
		$q_args = array(
			'action'    => 'bbp_favorite_add',
			'object_id' => $object_id
		);
	}

	// Custom redirect
	if ( ! empty( $redirect_to ) ) {
		$q_args['redirect_to'] = urlencode( $redirect_to );
	}

	// URL
	$url  = esc_url( wp_nonce_url( add_query_arg( $q_args ), 'toggle-favorite_' . $object_id ) );
	$sub  = $is_fav ? ' class="is-favorite"' : '';
	$html = sprintf( '%s<span id="favorite-%d"  %s><a href="%s" class="favorite-toggle" data-bbp-object-id="%d" data-bbp-object-type="%s" data-bbp-nonce="%s">%s</a></span>%s', $before, $object_id, $sub, $url, $object_id, $object_type, wp_create_nonce( 'toggle-favorite_' . $object_id ), $text, $after );

	// Initial output is wrapped in a span, ajax output is hooked to this
	if ( ! empty( $wrap ) && ( bbp_get_current_user_id() === $user_id ) ) {
		$html = '<span id="favorite-toggle">' . $html . '</span>';
	}

	/**
	 * Filters the favorites link.
	 *
	 * @since 2.0.0 bbPress (r2652)
	 *
	 * @param string|false $html Favorites link, or false when unavailable.
	 * @param array $r Arguments.
	 * @param int $user_id Legacy user ID parameter; use $args['user_id'] instead.
	 * @param int $object_id Object id.
	 */
	return apply_filters( 'bbp_get_user_favorites_link', $html, $r, $user_id, $object_id );
}

/** Subscriptions *************************************************************/

/**
 * Output the link to the user's subscriptions page (profile page).
 *
 * @since 2.0.0 bbPress (r2688)
 * @since 2.6.0 bbPress (r6308) Add pagination if in the loop
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_subscriptions_permalink( $user_id = 0 ) {
	echo esc_url( bbp_get_subscriptions_permalink( $user_id ) );
}

/**
 * Return the link to the user's subscriptions page (profile page).
 *
 * @since 2.0.0 bbPress (r2688)
 * @since 2.6.0 bbPress (r6308) Add pagination if in the loop
 *
 * @param int $user_id Optional. User id.
 * @return string|false URL for the user subscriptions page, or false when unavailable.
 */
function bbp_get_subscriptions_permalink( $user_id = 0 ) {

	// Use displayed user ID if there is one, and one isn't requested
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_subscriptions_permalink', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get user profile URL
	$profile_url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
	$url         = false;
	$page        = 0;
	$paged       = false;

	// Get pagination data
	if ( bbpress()->topic_query->in_the_loop ) {
		$page  = (int) bbpress()->topic_query->paged;
		$paged = (bool) bbpress()->topic_query->in_the_loop;

	} elseif ( bbpress()->forum_query->in_the_loop ) {
		$page  = (int) bbpress()->forum_query->paged;
		$paged = (bool) bbpress()->forum_query->in_the_loop;
	}

	// Pretty permalinks
	if ( false !== $profile_url && bbp_use_pretty_urls() ) {

		// Base URL
		$url = trailingslashit( $profile_url ) . bbp_get_user_subscriptions_slug();

		// Add page
		if ( ( true === $paged ) && ( $page > 1 ) ) {
			$url = trailingslashit( $url ) . bbp_get_paged_slug() . '/' . $page;
		}

		// Ensure correct trailing slash
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} elseif ( false !== $profile_url ) {

		// Base arguments
		$args = array(
			bbp_get_user_subscriptions_rewrite_id() => bbp_get_user_subscriptions_slug(),
		);

		// Add page
		if ( ( true === $paged ) && ( $page > 1 ) ) {
			$args['page'] = $page;
		}

		// Add arguments
		$url = add_query_arg( $args, $profile_url );
	}

	/**
	 * Filters the link to the user's subscriptions page (profile page).
	 *
	 * @since 2.0.0 bbPress (r2849)
	 *
	 * @param string|false $url URL for the user subscriptions page, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_subscriptions_permalink', $url, $user_id );
}

/**
 * Whether the current request is a subscription-unsubscribe request.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @param string $status Optional. Status to compare.
 * @return bool Whether this is an unsubscribe request with the requested status.
 */
function bbp_is_subscription_unsubscribe( $status = '' ) {
	$current = get_query_var( 'bbp_subscription_unsubscribe' );

	// Compare the current result when a status was requested
	if ( ! empty( $status ) ) {
		$retval = ( $status === $current );

	// Otherwise check the main query and rewrite variable
	} else {
		$wp_query   = bbp_get_wp_query();
		$rewrite_id = bbp_get_subscription_unsubscribe_rewrite_id();
		$retval     = ! empty( $current );

		if ( empty( $retval ) && isset( $wp_query->bbp_is_subscription_unsubscribe ) ) {
			$retval = ( true === $wp_query->bbp_is_subscription_unsubscribe );
		}

		if ( empty( $retval ) && ! empty( get_query_var( $rewrite_id ) ) ) {
			$retval = true;
		}
	}

	/**
	 * Filters whether this is an unsubscribe request with the requested status.
	 *
	 * @since 2.7.0 bbPress (r8349)
	 *
	 * @param bool $retval Whether this is an unsubscribe request with the requested status.
	 * @param string $status Status to compare.
	 * @param string $current Current unsubscribe request status.
	 */
	return (bool) apply_filters( 'bbp_is_subscription_unsubscribe', $retval, $status, $current );
}

/**
 * Get the title for the current subscription-unsubscribe request.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @return string Unsubscribe page title.
 */
function bbp_get_subscription_unsubscribe_title() {
	$status = get_query_var( 'bbp_subscription_unsubscribe' );

	if ( 'confirm' === $status ) {
		$title = __( 'Confirm Unsubscribe', 'bbpress' );
	} elseif ( 'success' === $status ) {
		$title = __( 'Unsubscribed', 'bbpress' );
	} else {
		$title = __( 'Unable to Unsubscribe', 'bbpress' );
	}

	/**
	 * Filters the title for the current subscription-unsubscribe request.
	 *
	 * @since 2.7.0 bbPress (r8349)
	 *
	 * @param string $title Unsubscribe page title.
	 * @param string $status Unsubscribe request status.
	 */
	return apply_filters( 'bbp_get_subscription_unsubscribe_title', $title, $status );
}

/**
 * Output the feedback class for the current subscription-unsubscribe request.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @return void
 */
function bbp_subscription_unsubscribe_feedback_class() {
	echo esc_attr( bbp_get_subscription_unsubscribe_feedback_class() );
}

/**
 * Get the feedback class for the current subscription-unsubscribe request.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @return string Feedback class.
 */
function bbp_get_subscription_unsubscribe_feedback_class() {
	$status = get_query_var( 'bbp_subscription_unsubscribe' );
	$class  = ( 'success' === $status )
		? 'info'
		: 'error';

	/**
	 * Filters the feedback class for the current subscription-unsubscribe request.
	 *
	 * @since 2.7.0 bbPress (r8349)
	 *
	 * @param string $class Feedback class.
	 * @param string $status Unsubscribe request status.
	 */
	return apply_filters( 'bbp_get_subscription_unsubscribe_feedback_class', $class, $status );
}

/**
 * Output feedback for the current subscription-unsubscribe request.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @return void
 */
function bbp_subscription_unsubscribe_feedback() {
	echo esc_html( bbp_get_subscription_unsubscribe_feedback() );
}

/**
 * Get feedback for the current subscription-unsubscribe request.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @return string Feedback message.
 */
function bbp_get_subscription_unsubscribe_feedback() {
	$status = get_query_var( 'bbp_subscription_unsubscribe' );

	if ( 'success' === $status ) {
		$feedback = __( 'You have been unsubscribed.', 'bbpress' );
	} elseif ( 'failed' === $status ) {
		$feedback = __( 'There was a problem unsubscribing.', 'bbpress' );
	} else {
		$feedback = __( 'This unsubscribe link is invalid or has expired.', 'bbpress' );
	}

	/**
	 * Filters the feedback for the current subscription-unsubscribe request.
	 *
	 * @since 2.7.0 bbPress (r8349)
	 *
	 * @param string $feedback Feedback message.
	 * @param string $status Unsubscribe request status.
	 */
	return apply_filters( 'bbp_get_subscription_unsubscribe_feedback', $feedback, $status );
}

/**
 * Output a user's subscribed taxonomy terms.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return void
 */
function bbp_user_term_subscriptions() {
	bbp_get_template_part( 'user', 'term-subscriptions' );
}

/**
 * Start the term subscription taxonomy loop.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return bool Whether there are taxonomies in the loop.
 */
function bbp_has_term_subscription_taxonomies() {
	$bbp                 = bbpress();
	$bbp->taxonomy_query = new BBP_Taxonomy_Query( bbp_get_topic_subscription_taxonomies() );

	/**
	 * Filters whether there are taxonomies in the loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param bool $value Whether there are taxonomies in the loop.
	 * @param BBP_Taxonomy_Query $taxonomy_query Query object.
	 */
	return (bool) apply_filters( 'bbp_has_term_subscription_taxonomies', $bbp->taxonomy_query->have_taxonomies(), $bbp->taxonomy_query );
}

/**
 * Whether there are more term subscription taxonomies in the loop.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return bool Whether more taxonomies are available.
 */
function bbp_term_subscription_taxonomies() {
	return bbpress()->taxonomy_query->have_taxonomies();
}

/**
 * Set up the current term subscription taxonomy.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return WP_Taxonomy|false Current taxonomy object, or false when unavailable.
 */
function bbp_the_term_subscription_taxonomy() {
	return bbpress()->taxonomy_query->the_taxonomy();
}

/**
 * Get the current term subscription taxonomy.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return WP_Taxonomy|false Current taxonomy object, or false when unavailable.
 */
function bbp_get_term_subscription_taxonomy() {
	$query    = bbpress()->taxonomy_query;
	$taxonomy = $query->taxonomy;
	$retval   = ! empty( $query->in_the_loop ) && ( $taxonomy instanceof WP_Taxonomy )
		? $taxonomy
		: false;

	/**
	 * Filters the current term subscription taxonomy.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param WP_Taxonomy|false $retval Current taxonomy object, or false when unavailable.
	 * @param string $taxonomy Taxonomy name.
	 */
	return apply_filters( 'bbp_get_term_subscription_taxonomy', $retval, $taxonomy );
}

/**
 * Output the current term subscription taxonomy ID.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return void
 */
function bbp_term_subscription_taxonomy_id() {
	echo esc_attr( bbp_get_term_subscription_taxonomy_id() );
}

/**
 * Get the current term subscription taxonomy ID.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return string Taxonomy ID.
 */
function bbp_get_term_subscription_taxonomy_id() {
	$taxonomy = bbp_get_term_subscription_taxonomy();
	$retval   = ( $taxonomy instanceof WP_Taxonomy )
		? $taxonomy->name
		: '';

	/**
	 * Filters the current term subscription taxonomy ID.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string $retval Taxonomy ID.
	 * @param WP_Taxonomy|false $taxonomy Subscription taxonomy object, or false when unavailable.
	 */
	return (string) apply_filters( 'bbp_get_term_subscription_taxonomy_id', $retval, $taxonomy );
}

/**
 * Get the current term subscription taxonomy label.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return string Taxonomy plural label.
 */
function bbp_get_term_subscription_taxonomy_label() {
	$taxonomy = bbp_get_term_subscription_taxonomy();
	$retval   = ( $taxonomy instanceof WP_Taxonomy )
		? $taxonomy->labels->name
		: '';

	/**
	 * Filters the current term subscription taxonomy label.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string $retval Taxonomy plural label.
	 * @param WP_Taxonomy|false $taxonomy Subscription taxonomy object, or false when unavailable.
	 */
	return (string) apply_filters( 'bbp_get_term_subscription_taxonomy_label', $retval, $taxonomy );
}

/**
 * Get the current term subscription from the term loop.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param int|WP_Term $term Optional. Term ID or object.
 * @return WP_Term|false Term object, or false when unavailable.
 */
function bbp_get_term_subscription( $term = 0 ) {
	if ( $term instanceof WP_Term ) {
		$retval = $term;
	} elseif ( ! empty( $term ) ) {
		$retval = get_term( $term );
	} elseif ( ! empty( bbpress()->term_query->in_the_loop ) && ( bbpress()->term_query->term instanceof WP_Term ) ) {
		$retval = bbpress()->term_query->term;
	} else {
		$retval = false;
	}

	/**
	 * Filters the current term subscription from the term loop.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param mixed $retval Term object, WP_Error, false, or null when unavailable.
	 * @param int|WP_Term $term Term ID or object.
	 */
	return apply_filters( 'bbp_get_term_subscription', $retval, $term );
}

/**
 * Output the current term subscription name.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param int|WP_Term $term Optional. Term ID or object.
 * @return void
 */
function bbp_term_subscription_name( $term = 0 ) {
	echo esc_html( bbp_get_term_subscription_name( $term ) );
}

/**
 * Get the current term subscription name.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param int|WP_Term $term Optional. Term ID or object.
 * @return string Term name.
 */
function bbp_get_term_subscription_name( $term = 0 ) {
	$term = bbp_get_term_subscription( $term );
	$name = ( $term instanceof WP_Term )
		? $term->name
		: '';

	/**
	 * Filters the current term subscription name.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string $name Term name.
	 * @param WP_Term|false $term Resolved subscription term, or false when unavailable.
	 */
	return (string) apply_filters( 'bbp_get_term_subscription_name', $name, $term );
}

/**
 * Output the current term subscription permalink.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param int|WP_Term $term Optional. Term ID or object.
 * @return void
 */
function bbp_term_subscription_permalink( $term = 0 ) {
	echo esc_url( bbp_get_term_subscription_permalink( $term ) );
}

/**
 * Get the current term subscription permalink.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param int|WP_Term $term Optional. Term ID or object.
 * @return string Term permalink.
 */
function bbp_get_term_subscription_permalink( $term = 0 ) {
	$term = bbp_get_term_subscription( $term );
	$type = bbp_get_engagement_type( 'term' );
	$url  = ( $term instanceof WP_Term ) && ! empty( $type )
		? $type->get_url( $term->term_id )
		: '';

	/**
	 * Filters the current term subscription permalink.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string|false|null $url Term permalink, or false or null when unavailable.
	 * @param WP_Term|false $term Resolved subscription term, or false when unavailable.
	 */
	return (string) apply_filters( 'bbp_get_term_subscription_permalink', $url, $term );
}

/**
 * Output the current term subscription toggle link.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param array<string, mixed> $args Optional. Subscription-link arguments.
 * @return void
 */
function bbp_term_subscription_toggle_link( $args = array() ) {
	echo bbp_get_term_subscription_toggle_link( $args );
}

/**
 * Get the current term subscription toggle link.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param array<string, mixed> $args Optional. Subscription-link arguments.
 * @return string|false Subscription link, or false when unavailable.
 */
function bbp_get_term_subscription_toggle_link( $args = array() ) {
	$term    = bbp_get_term_subscription();
	$user_id = bbp_get_user_id( 0, true, true );

	$r = bbp_parse_args(
		$args,
		array(
			'user_id'     => $user_id,
			'object_id'   => ( $term instanceof WP_Term ) ? $term->term_id : 0,
			'object_type' => 'term',
			'before'      => ' (',
			'after'       => ')',
			'redirect_to' => bbp_get_subscriptions_permalink( $user_id )
		),
		'get_term_subscription_toggle_link'
	);

	/**
	 * Filters the current term subscription toggle link.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param mixed $value Subscription link, or false or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Subscription-link arguments.
	 */
	return apply_filters( 'bbp_get_term_subscription_toggle_link', bbp_get_user_subscribe_link( $r, $user_id, false ), $r, $args );
}

/**
 * Output the link to subscribe/unsubscribe from a topic.
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args See {@link bbp_get_user_subscribe_link()}.
 * @param int   $user_id Legacy user ID parameter; use $args['user_id'] instead.
 * @param bool  $wrap    Whether to wrap the link in a span.
 * @return void
 */
function bbp_user_subscribe_link( $args = array(), $user_id = 0, $wrap = true ) {
	echo bbp_get_user_subscribe_link( $args, $user_id, $wrap );
}

/**
 * Return the link to subscribe/unsubscribe from a forum or topic.
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 * @since 2.7.0 bbPress (r8323) Scope the nonce to the object type for non-posts.
 * @since 2.7.0 bbPress (r8335) Scope element IDs to the object type for non-posts.
 *
 * @param array<string, mixed> $args    Subscription labels, object ID/type, user ID, wrapper, and redirect settings.
 * @param int   $user_id Legacy user ID parameter; use $args['user_id'] instead.
 * @param bool  $wrap    Whether to wrap the link in a span.
 * @return string|false|null Subscription link, false when unavailable, or null when subscriptions are inactive.
 */
function bbp_get_user_subscribe_link( $args = array(), $user_id = 0, $wrap = true ) {

	// Bail if subscriptions are inactive
	if ( ! bbp_is_subscriptions_active() ) {
		return null;
	}

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'subscribe'   => esc_html__( 'Subscribe',   'bbpress' ),
			'unsubscribe' => esc_html__( 'Unsubscribe', 'bbpress' ),
			'user_id'     => 0,
			'object_id'   => 0,
			'object_type' => 'post',
			'before'      => '',
			'after'       => '',
			'redirect_to' => '',

			// Deprecated. Use object_id.
			'forum_id'    => 0,
			'topic_id'    => 0
		),
		'get_user_subscribe_link'
	);

	// Use renderable strings for labels and link arguments
	$subscribe   = bbp_get_template_string_arg( $r, 'subscribe' );
	$unsubscribe = bbp_get_template_string_arg( $r, 'unsubscribe' );
	$object_type = bbp_get_template_string_arg( $r, 'object_type' );
	$before      = bbp_get_template_string_arg( $r, 'before' );
	$after       = bbp_get_template_string_arg( $r, 'after' );
	$redirect_to = bbp_get_template_string_arg( $r, 'redirect_to' );

	// Validate user
	$user_arg    = $r['user_id'];
	$user_arg    = is_numeric( $user_arg ) ? $user_arg : false;
	$user_id     = bbp_get_user_id( $user_arg, true, true );
	$object_type = sanitize_key( $object_type );

	// Back-compat for deprecated arguments
	if ( ! empty( $r['topic_id'] ) ) {
		$object_arg = $r['topic_id'];
	} elseif ( ! empty( $r['forum_id'] ) ) {
		$object_arg = $r['forum_id'];
	} else {
		$object_arg = $r['object_id'];
	}
	$object_id = is_numeric( $object_arg ) ? absint( $object_arg ) : 0;

	// Validate the object before checking its subscription state
	if ( empty( $user_id ) || empty( $object_id ) || ! bbp_current_user_can_toggle_engagement( $object_id, $object_type, 'subscription', 'remove', $user_id ) ) {
		return false;
	}

	// Decide which link to show
	$is_subscribed = bbp_is_user_subscribed( $user_id, $object_id, $object_type );
	$toggle_action = ! empty( $is_subscribed ) ? 'remove' : 'add';
	if ( ! bbp_current_user_can_toggle_engagement( $object_id, $object_type, 'subscription', $toggle_action, $user_id ) ) {
		return false;
	}

	if ( ! empty( $is_subscribed ) ) {
		$text   = $unsubscribe;
		$q_args = array(
			'action'      => 'bbp_unsubscribe',
			'object_id'   => $object_id,
			'object_type' => $object_type
		);
	} else {
		$text   = $subscribe;
		$q_args = array(
			'action'      => 'bbp_subscribe',
			'object_id'   => $object_id,
			'object_type' => $object_type
		);
	}

	// Custom redirect
	if ( ! empty( $redirect_to ) ) {
		$q_args['redirect_to'] = urlencode( $redirect_to );
	}

	// Posts keep the original nonce action for third-party theme package handlers.
	$nonce_action = ( 'post' === $object_type )
		? 'toggle-subscription_' . $object_id
		: 'toggle-subscription_' . $object_type . '_' . $object_id;
	$element_id   = ( 'post' === $object_type )
		? 'subscribe-' . $object_id
		: 'subscribe-' . $object_type . '-' . $object_id;
	$url          = esc_url( wp_nonce_url( add_query_arg( $q_args ), $nonce_action ) );
	$sub          = $is_subscribed ? ' class="is-subscribed"' : '';
	$html         = sprintf( '%s<span id="%s"  %s><a href="%s" class="subscription-toggle" data-bbp-object-id="%d" data-bbp-object-type="%s" data-bbp-nonce="%s">%s</a></span>%s', $before, $element_id, $sub, $url, $object_id, $object_type, wp_create_nonce( $nonce_action ), $text, $after );

	// Initial output is wrapped in a span, ajax output is hooked to this
	if ( ! empty( $wrap ) && ( bbp_get_current_user_id() === $user_id ) ) {
		$html = '<span id="subscription-toggle">' . $html . '</span>';
	}

	/**
	 * Filters a forum or topic subscription link.
	 *
	 * @since 2.0.0 bbPress (r2668)
	 *
	 * @param string|false|null $html Subscription link, false when unavailable, or null when subscriptions are inactive.
	 * @param array $r Arguments.
	 * @param int $user_id Legacy user ID parameter; use $args['user_id'] instead.
	 * @param int $object_id Object id.
	 */
	return apply_filters( 'bbp_get_user_subscribe_link', $html, $r, $user_id, $object_id );
}

/** Edit User *****************************************************************/

/**
 * Display profile edit success notice on user edit page.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return void
 */
function bbp_notice_edit_user_success() {

	// Bail if no updated argument
	if ( empty( $_GET['updated'] ) ) {
		return;
	}

	// Bail if not on users own profile
	if ( ! bbp_is_single_user_edit() ) {
		return;
	} ?>

	<div class="bbp-template-notice">
		<ul>
			<li><?php esc_html_e( 'User updated.', 'bbpress' ); ?></li>
		</ul>
	</div>

	<?php
}

/**
 * Display pending email change notice on user edit page.
 *
 * @since 2.6.0 bbPress (r5661)
 *
 * @return void
 */
function bbp_notice_edit_user_pending_email() {

	$user_id = bbp_get_displayed_user_id();

	// Show the notice only to users who may edit the email address
	if ( ! bbp_is_user_home_edit() && ( ! bbp_is_single_user_edit() || ! bbp_current_user_can_edit_user_field( 'email', $user_id ) ) ) {
		return;
	}

	// Check for pending email address change
	$key       = '_new_email';
	$new_email = get_user_meta( $user_id, $key, true );

	// Bail if no pending email address change
	if ( empty( $new_email['newemail'] ) ) {
		return;
	}

	// Build the nonced URL to dismiss the pending change
	$user_url = bbp_validate_url( bbp_get_user_profile_edit_url( $user_id ) );
	$nonce    = "dismiss-{$user_id}{$key}";
	$args     = array(
		'action'  => 'bbp-update-user-email',
		'dismiss' => "{$user_id}{$key}"
	);

	// Build the variables to pass into printf()
	if ( false !== $user_url ) {
		$dismiss_url  = wp_nonce_url( add_query_arg( $args, $user_url ), $nonce );
		$dismiss_link = '<a href="' . esc_url( $dismiss_url ) . '">' . esc_html_x( 'Cancel', 'Dismiss pending user email address change', 'bbpress' ) . '</a>';
		/* translators: 1: New email address, 2: Dismiss change link */
		$notice = esc_html__( 'There is a pending email address change to %1$s. %2$s', 'bbpress' );
	} else {
		$dismiss_link = '';
		/* translators: %s: New email address */
		$notice = esc_html__( 'There is a pending email address change to %1$s.', 'bbpress' );
	}
	$coded_email = '<code>' . esc_html( $new_email['newemail'] ) . '</code>'; ?>

	<div class="bbp-template-notice info">
		<ul>
			<li>
				<?php
				printf(
					$notice,
					$coded_email,
					$dismiss_link
				);
				?></li>
		</ul>
	</div>

	<?php
}

/**
 * Super admin privileges notice.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return void
 */
function bbp_notice_edit_user_is_super_admin() {
	if ( is_multisite() && ( bbp_is_single_user() || bbp_is_single_user_edit() ) && current_user_can( 'manage_network_options' ) && is_super_admin( bbp_get_displayed_user_id() ) ) : ?>

	<div class="bbp-template-notice important">
		<ul>
			<li><?php bbp_is_user_home() || bbp_is_user_home_edit() ? esc_html_e( 'You have super admin privileges.', 'bbpress' ) : esc_html_e( 'This user has super admin privileges.', 'bbpress' ); ?></li>
		</ul>
	</div>

<?php endif;
}

/**
 * Drop down for selecting the user's display name.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return void
 */
function bbp_edit_user_display_name() {
	$bbp            = bbpress();
	$public_display = array();
	$public_display['display_username'] = $bbp->displayed_user->user_login;

	if ( ! empty( $bbp->displayed_user->nickname ) ) {
		$public_display['display_nickname']  = $bbp->displayed_user->nickname;
	}

	if ( ! empty( $bbp->displayed_user->first_name ) ) {
		$public_display['display_firstname'] = $bbp->displayed_user->first_name;
	}

	if ( ! empty( $bbp->displayed_user->last_name ) ) {
		$public_display['display_lastname']  = $bbp->displayed_user->last_name;
	}

	if ( ! empty( $bbp->displayed_user->first_name ) && ! empty( $bbp->displayed_user->last_name ) ) {
		$public_display['display_firstlast'] = $bbp->displayed_user->first_name . ' ' . $bbp->displayed_user->last_name;
		$public_display['display_lastfirst'] = $bbp->displayed_user->last_name  . ' ' . $bbp->displayed_user->first_name;
	}

	// Only add this if it isn't duplicated elsewhere
	if ( ! in_array( $bbp->displayed_user->display_name, $public_display, true ) ) {
		$public_display = array( 'display_displayname' => $bbp->displayed_user->display_name ) + $public_display;
	}

	$public_display = array_map( 'trim', $public_display );
	$public_display = array_unique( $public_display ); ?>

	<select name="display_name" id="display_name">

	<?php foreach ( $public_display as $id => $item ) : ?>

		<option id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $item ); ?>"<?php selected( $bbp->displayed_user->display_name, $item ); ?>><?php echo esc_html( $item ); ?></option>

	<?php endforeach; ?>

	</select>

<?php
}

/**
 * Output blog role selector (for user edit).
 *
 * @since 2.2.0 bbPress (r4288)
 *
 * @return void
 */
function bbp_edit_user_blog_role() {

	// Bail if no user is being edited
	if ( ! bbp_is_single_user_edit() || ! bbp_current_user_can_edit_user_field( 'site_role', bbp_get_displayed_user_id() ) ) {
		return;
	}

	// Get users current blog role
	$user_role  = bbp_get_user_blog_role( bbp_get_displayed_user_id() );

	// Get the blog roles
	$blog_roles = bbp_get_blog_roles();
	if ( ! is_array( $blog_roles ) ) {
		$blog_roles = array();
	}
	?>

	<select name="role" id="role">
		<option value=""><?php esc_html_e( '&mdash; No role for this site &mdash;', 'bbpress' ); ?></option>

		<?php foreach ( $blog_roles as $role => $details ) : ?>

			<option <?php selected( $user_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo bbp_translate_user_role( $details['name'] ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php
}

/**
 * Output the automatic forum role assignment exclusion fields.
 *
 * @since 2.7.0 bbPress (r8160)
 *
 * @param int $user_id User ID.
 * @return void
 */
function bbp_user_auto_role_exclusion_form( $user_id = 0 ) {
	$user_id              = bbp_get_user_id( $user_id, false, false );
	$scope                = bbp_get_user_auto_role_exclusion_scope( $user_id );
	$is_multisite         = is_multisite();
	$can_manage_all_sites = $is_multisite && current_user_can( 'manage_network_users' );
	$is_locked            = ( 'all_sites' === $scope ) && ! $can_manage_all_sites;
	$selected_scope       = $scope ? $scope : 'site';

	if ( empty( $user_id ) ) {
		return;
	}
	?>

	<?php if ( ! $is_locked ) : ?>
		<input type="hidden" name="bbp-auto-role-exclusion-present" value="1" />
	<?php endif; ?>

	<label for="bbp-auto-role-exclusion">
		<input type="checkbox" name="bbp-auto-role-exclusion" id="bbp-auto-role-exclusion" value="1" <?php checked( false !== $scope ); ?> <?php disabled( $is_locked ); ?> />
		<?php
		if ( $can_manage_all_sites ) {
			esc_html_e( 'Skip automatic role assignment for this user on:', 'bbpress' );
		} else {
			esc_html_e( 'Skip automatic role assignment for this user', 'bbpress' );
		}
		?>
	</label>

	<?php if ( $can_manage_all_sites ) : ?>
		<label class="screen-reader-text" for="bbp-auto-role-exclusion-scope"><?php esc_html_e( 'Automatic role assignment exclusion scope', 'bbpress' ); ?></label>
		<select name="bbp-auto-role-exclusion-scope" id="bbp-auto-role-exclusion-scope">
			<option value="site" <?php selected( 'site', $selected_scope ); ?>><?php esc_html_e( 'This site', 'bbpress' ); ?></option>
			<option value="all_sites" <?php selected( 'all_sites', $selected_scope ); ?>><?php esc_html_e( 'All sites', 'bbpress' ); ?></option>
		</select>
	<?php endif; ?>

	<?php if ( $is_locked ) : ?>
		<p class="description"><?php esc_html_e( 'Set for all sites by a network administrator.', 'bbpress' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Existing and assigned forum roles are unaffected.', 'bbpress' ); ?></p>
	<?php endif; ?>

	<?php
}

/**
 * Output the automatic forum role assignment exclusion for administrators editing a user.
 *
 * @since 2.7.0 bbPress (r8160)
 *
 * @return void
 */
function bbp_edit_user_auto_role_exclusion() {
	$user_id = bbp_get_displayed_user_id();

	if (
		! bbp_is_single_user_edit()
		|| bbp_is_user_home_edit()
		|| ! current_user_can( 'manage_options' )
		|| ! bbp_current_user_can_edit_user_field( 'forum_role', $user_id )
	) {
		return;
	}
	?>

	<div>
		<?php bbp_user_auto_role_exclusion_form( $user_id ); ?>
	</div>

	<?php
}

/**
 * Output forum role selector (for user edit).
 *
 * @since 2.2.0 bbPress (r4288)
 *
 * @return void
 */
function bbp_edit_user_forums_role() {

	// Bail if no user is being edited
	if ( ! bbp_is_single_user_edit() || ! bbp_current_user_can_edit_user_field( 'forum_role', bbp_get_displayed_user_id() ) ) {
		return;
	}

	// Get the user's current forum role
	$user_role     = bbp_get_user_role( bbp_get_displayed_user_id() );
	$all_roles     = bbp_get_dynamic_roles();
	$current_name  = isset( $all_roles[ $user_role ]['name'] )
		? $all_roles[ $user_role ]['name']
		: '';

	// Get the folum roles
	$dynamic_roles = bbp_get_user_editable_forum_roles( bbp_get_displayed_user_id() ); ?>

	<select name="bbp-forums-role" id="bbp-forums-role">
		<?php if ( ! empty( $user_role ) && ! isset( $dynamic_roles[ $user_role ] ) && ! empty( $current_name ) ) : ?>

			<option value="<?php echo esc_attr( $user_role ); ?>" selected="selected"><?php echo esc_html( bbp_translate_user_role( $current_name ) ); ?></option>

		<?php endif; ?>
		<option value=""><?php esc_html_e( '&mdash; No role for these forums &mdash;', 'bbpress' ); ?></option>

		<?php foreach ( $dynamic_roles as $role => $details ) : ?>

			<option <?php selected( $user_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo bbp_translate_user_role( $details['name'] ); ?></option>

		<?php endforeach; ?>

	</select>
	<p class="description"><?php esc_html_e( 'No role removes the assigned role but does not prevent automatic assignment.', 'bbpress' ); ?></p>

	<?php
}

/**
 * Filter whether password fields are displayed on a bbPress user profile.
 *
 * @since 2.6.17 bbPress (r7513)
 *
 * @param bool    $show         Whether to show the password fields.
 * @param WP_User $profile_user User being edited.
 * @return bool Whether to show the password fields.
 */
function bbp_filter_user_edit_password_fields( $show, $profile_user ) {
	if ( $show && ! is_admin() && bbp_is_single_user_edit() ) {
		$show = bbp_current_user_can_edit_user_field( 'password', $profile_user->ID );
	}

	return $show;
}

/**
 * Return user contact methods select box.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return array<string, string> User contact methods.
 */
function bbp_edit_user_contact_methods() {

	// Get the core WordPress contact methods
	$contact_methods = wp_get_user_contact_methods( bbpress()->displayed_user );

	/**
	 * Filters the user contact methods select box.
	 *
	 * @since 2.0.0 bbPress (r2970)
	 *
	 * @param array<string, string> $contact_methods User contact methods.
	 */
	return (array) apply_filters( 'bbp_edit_user_contact_methods', $contact_methods );
}

/**
 * Output the language chooser (for user edit).
 *
 * @since 2.6.0 bbPress (r6489)
 *
 * @param array<string, mixed> $args See wp_dropdown_languages().
 * @return void
 */
function bbp_edit_user_language( $args = array() ) {

	// Bail if no user is being edited
	if ( ! bbp_is_single_user_edit() ) {
		return;
	}

	// Output the dropdown
	bbp_user_languages_dropdown( $args );
}

/** Requests ******************************************************************/

/**
 * Verify if a POST request came from a user profile edit form.
 *
 * Used to avoid cross-site request forgeries when checking posted user profile
 * form content.
 *
 * @since 2.6.15 bbPress (r7399)
 *
 * @param int $user_id User id.
 *
 * @return bool True if this is a user profile post request with valid nonce.
 */
function bbp_is_user_profile_form_post_request( $user_id = 0 ) {

	// Bail if no user ID was passed.
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if not a post request.
	if ( ! bbp_is_post_request() ) {
		return false;
	}

	// Build the nonce action string.
	$action = 'update-user_' . $user_id;

	// User role updates rely on the user profile nonce.
	if ( bbp_verify_nonce_request( $action ) ) {
		return true;
	}

	// Fallback for admin profile requests where URL validation can vary.
	if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], $action ) ) {
		return true;
	}

	return false;
}

/** Topics Created ************************************************************/

/**
 * Output the link to the user's topics.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_user_topics_created_url( $user_id = 0 ) {
	echo esc_url( bbp_get_user_topics_created_url( $user_id ) );
}

/**
 * Return the link to the user's topics.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @param int $user_id Optional. User id.
 * @return string|false URL for the user topics page, or false when unavailable.
 */
function bbp_get_user_topics_created_url( $user_id = 0 ) {

	// Use displayed user ID if there is one, and one isn't requested
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_user_topics_created_url', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get user profile URL
	$profile_url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
	$url         = false;

	// Pretty permalinks
	if ( false !== $profile_url && bbp_use_pretty_urls() ) {
		$url = trailingslashit( $profile_url ) . bbp_get_topic_archive_slug();
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} elseif ( false !== $profile_url ) {
		$url = add_query_arg(
			array(
				bbp_get_user_topics_rewrite_id() => '1',
			),
			$profile_url
		);
	}

	/**
	 * Filters the link to the user's topics.
	 *
	 * @since 2.2.0 bbPress (r4228)
	 *
	 * @param string|false $url URL for the user topics page, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_user_topics_created_url', $url, $user_id );
}

/** Replies Created ************************************************************/

/**
 * Output the link to the user's replies.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_user_replies_created_url( $user_id = 0 ) {
	echo esc_url( bbp_get_user_replies_created_url( $user_id ) );
}

/**
 * Return the link to the user's replies.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @param int $user_id Optional. User id.
 * @return string|false URL for the user replies page, or false when unavailable.
 */
function bbp_get_user_replies_created_url( $user_id = 0 ) {

	// Use displayed user ID if there is one, and one isn't requested
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_user_replies_created_url', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get user profile URL
	$profile_url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
	$url         = false;

	// Pretty permalinks
	if ( false !== $profile_url && bbp_use_pretty_urls() ) {
		$url = trailingslashit( $profile_url ) . bbp_get_reply_archive_slug();
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} elseif ( false !== $profile_url ) {
		$url = add_query_arg(
			array(
				bbp_get_user_replies_rewrite_id() => '1',
			),
			$profile_url
		);
	}

	/**
	 * Filters the link to the user's replies.
	 *
	 * @since 2.2.0 bbPress (r4322)
	 *
	 * @param string|false $url URL for the user replies page, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_user_replies_created_url', $url, $user_id );
}

/** Engagements ***************************************************************/

/**
 * Output the link to the user's engagements.
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id.
 * @return void
 */
function bbp_user_engagements_url( $user_id = 0 ) {
	echo esc_url( bbp_get_user_engagements_url( $user_id ) );
}

/**
 * Return the link to the user's engagements.
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id.
 * @return string|false URL for the user engagements page, or false when unavailable.
 */
function bbp_get_user_engagements_url( $user_id = 0 ) {

	// Use displayed user ID if there is one, and one isn't requested
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( 'bbp_pre_get_user_engagements_url', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get user profile URL
	$profile_url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
	$url         = false;

	// Pretty permalinks
	if ( false !== $profile_url && bbp_use_pretty_urls() ) {
		$url = trailingslashit( $profile_url ) . bbp_get_user_engagements_slug();
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} elseif ( false !== $profile_url ) {
		$url = add_query_arg(
			array(
				bbp_get_user_engagements_rewrite_id() => '1',
			),
			$profile_url
		);
	}

	/**
	 * Filters the link to the user's engagements.
	 *
	 * @since 2.6.0 bbPress (r6320)
	 *
	 * @param string|false $url URL for the user engagements page, or false when unavailable.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_user_engagements_url', $url, $user_id );
}

/** Mentions ******************************************************************/

/**
 * Return a user's mention history URL.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $user_id Profile owner ID.
 * @return string|false
 */
function bbp_get_user_mentions_url( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id );
	if ( ! $user_id ) {
		return false;
	}

	$intercept = bbp_maybe_intercept( 'bbp_pre_get_user_mentions_url', func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	$url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
	if ( false !== $url ) {
		$url = bbp_use_pretty_urls()
			? user_trailingslashit( trailingslashit( $url ) . bbp_get_user_mentions_slug() )
			: add_query_arg( bbp_get_user_mentions_rewrite_id(), '1', $url );
	}

	/**
	 * Filters the URL for a user's mentions.
	 *
	 * @since 2.7.0 bbPress (r8264)
	 *
	 * @param string|false $url The URL for a user's mentions.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_get_user_mentions_url', $url, $user_id );
}

/**
 * Display notification preferences on front-end and administration profiles.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param WP_User|null $user User being edited, or the displayed user.
 * @return void
 */
function bbp_user_notification_settings( $user = null ) {
	// bbPress also invokes the WordPress profile hooks on its front-end form.
	if ( ! is_admin() && in_array( current_filter(), array( 'show_user_profile', 'edit_user_profile' ), true ) ) {
		return;
	}

	$user_id = ( $user instanceof WP_User ) ? $user->ID : bbp_get_displayed_user_id();
	if ( ! bbp_current_user_can_edit_user_field( 'email', $user_id ) ) {
		return;
	}

	$template = bbp_locate_template( 'form-user-notification-preferences.php' );

	if ( ! empty( $template ) ) {
		$displayed_user           = bbpress()->displayed_user;
		bbpress()->displayed_user = get_userdata( $user_id );

		try {
			load_template( $template, false );
		} finally {
			bbpress()->displayed_user = $displayed_user;
		}
	}
}

/**
 * Get the saved choice for a notification preference select.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param int    $user_id User ID.
 * @param string $type    Notification type.
 * @return string Default, yes, or no.
 */
function bbp_get_user_notification_preference_choice( $user_id = 0, $type = '' ) {
	$preference = bbp_get_user_notification_preference( $user_id, $type );

	if ( null === $preference ) {
		return 'default';
	}

	return $preference ? 'yes' : 'no';
}

/**
 * Output a notification preference select for a user.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param int    $user_id User ID.
 * @param string $type    Notification type.
 * @return void
 */
function bbp_user_notification_preference_field( $user_id = 0, $type = '' ) {
	$types = bbp_get_notification_types();

	if ( ! is_string( $type ) || ! isset( $types[ $type ] ) || ! is_array( $types[ $type ] ) || empty( $types[ $type ]['label'] ) || ! is_string( $types[ $type ]['label'] ) ) {
		return;
	}

	$label  = $types[ $type ]['label'];
	$choice = bbp_get_user_notification_preference_choice( $user_id, $type );
	$default = bbp_get_user_notification_default( $user_id, $type );
	?>

	<div>
		<label for="bbp-notification-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></label>
		<select name="bbp_notification_preferences[<?php echo esc_attr( $type ); ?>]" id="bbp-notification-<?php echo esc_attr( $type ); ?>">
			<option value="default" <?php selected( $choice, 'default' ); ?>><?php echo esc_html( $default ? __( 'Default (emails on)', 'bbpress' ) : __( 'Default (emails off)', 'bbpress' ) ); ?></option>
			<option value="yes" <?php selected( $choice, 'yes' ); ?>><?php esc_html_e( 'Send emails', 'bbpress' ); ?></option>
			<option value="no" <?php selected( $choice, 'no' ); ?>><?php esc_html_e( 'No emails', 'bbpress' ); ?></option>
		</select>
	</div>

	<?php
}

/**
 * Output notification preference fields for the displayed user.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @return void
 */
function bbp_user_notification_preference_fields() {
	$user_id = bbp_get_displayed_user_id();

	foreach ( array_keys( bbp_get_notification_types() ) as $type ) {
		bbp_user_notification_preference_field( $user_id, $type );
	}
}

/**
 * Output the displayed user's notification preference nonce.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @return void
 */
function bbp_user_notification_preferences_nonce() {
	wp_nonce_field( 'bbp-notification-preferences-' . bbp_get_displayed_user_id(), '_bbp_notification_nonce', false );
}

/** Language ******************************************************************/

/**
 * Output the select element used to save a user's language.
 *
 * @since 2.6.0 bbPress (r6489)
 *
 * @param array<string, mixed> $args See wp_dropdown_languages().
 * @return void
 */
function bbp_user_languages_dropdown( $args = array() ) {
	echo bbp_get_user_languages_dropdown( $args );
}

/**
 * Return the select element used to save a user's language.
 *
 * @since 2.6.0 bbPress (r6489)
 *
 * @param array<string, mixed> $args See wp_dropdown_languages().
 * @return string
 */
function bbp_get_user_languages_dropdown( $args = array() ) {

	// Get user language
	$user_id = ! empty( $args['user_id'] )
		? bbp_get_user_id( $args['user_id'], false, false )
		: bbp_get_displayed_user_id();

	// Get user locale
	$user        = ! empty( $user_id ) ? get_userdata( $user_id ) : false;
	$user_locale = ( $user instanceof WP_User ) ? $user->locale : 'site-default';

	// Get all languages
	$languages = get_available_languages();

	// No locale for English
	if ( 'en_US' === $user_locale ) {
		$user_locale = '';

	// Fallback to site-default if there is a mismatch
	} elseif ( '' === $user_locale || ! in_array( $user_locale, $languages, true ) ) {
		$user_locale = 'site-default';
	}

	// Don't pass user ID in
	unset( $args['user_id'] );

	// Parse arguments
	$r = bbp_parse_args(
		$args,
		array(
			'name'                        => 'locale',
			'id'                          => 'locale',
			'selected'                    => $user_locale,
			'languages'                   => $languages,
			'echo'                        => false,
			'show_available_translations' => false,
			'show_option_site_default'    => true
		),
		'user_languages_dropdown'
	);

	// Get the markup for the languages drop-down
	$retval = wp_dropdown_languages( $r );

	/**
	 * Filters the select element used to save a user's language.
	 *
	 * @since 2.6.0 bbPress (r6489)
	 *
	 * @param mixed $retval Language selector markup, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args See wp_dropdown_languages().
	 */
	return apply_filters( 'bbp_get_user_languages_dropdown', $retval, $r, $args );
}

/** Login *********************************************************************/

/**
 * Handle the login and registration template notices.
 *
 * @since 2.0.0 bbPress (r2970)
 *
 * @return void
 */
function bbp_login_notices() {

	// loggedout was passed
	if ( ! empty( $_GET['loggedout'] ) && ( 'true' === $_GET['loggedout'] ) ) {
		bbp_add_error( 'loggedout', esc_html__( 'You are now logged out.', 'bbpress' ), 'message' );

	// registration is disabled
	} elseif ( ! empty( $_GET['registration'] ) && ( 'disabled' === $_GET['registration'] ) ) {
		bbp_add_error( 'registerdisabled', esc_html__( 'New user registration is currently not allowed.', 'bbpress' ) );

	// Prompt user to check their email
	} elseif ( ! empty( $_GET['checkemail'] ) && in_array( $_GET['checkemail'], array( 'confirm', 'newpass', 'registered' ), true ) ) {

		switch ( $_GET['checkemail'] ) {

			// Email needs confirmation
			case 'confirm' :
				bbp_add_error( 'confirm',    esc_html__( 'Check your e-mail for the confirmation link.',     'bbpress' ), 'message' );
				break;

			// User requested a new password
			case 'newpass' :
				bbp_add_error( 'newpass',    esc_html__( 'Check your e-mail for your new password.',         'bbpress' ), 'message' );
				break;

			// User is newly registered
			case 'registered' :
				bbp_add_error( 'registered', esc_html__( 'Registration complete. Please check your e-mail.', 'bbpress' ), 'message' );
				break;
		}
	}
}

/**
 * Redirect a user back to their profile if they are already logged in.
 *
 * This should be used before {@link get_header()} is called in template files
 * where the user should never have access to the contents of that file.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function bbp_logged_in_redirect( $url = '' ) {

	// Bail if user is not logged in
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Setup the profile page to redirect to
	$redirect_to = ! empty( $url ) ? $url : bbp_get_user_profile_url( bbp_get_current_user_id() );

	// Do a safe redirect
	bbp_redirect( $redirect_to );
}

/**
 * Output the required hidden fields when logging in.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @return void
 */
function bbp_user_login_fields() {
?>

	<input type="hidden" name="user-cookie" value="1" />

	<?php

	// Allow custom login redirection

	/**
	 * Filters the redirect URL for the login form.
	 *
	 * @since 2.0.0 bbPress (r3450)
	 *
	 * @param string $value The redirect URL for the login form.
	 */
	$redirect_to = apply_filters( 'bbp_user_login_redirect_to', '' );
	bbp_redirect_to_field( $redirect_to );

	// Prevent intention hi-jacking of log-in form
	wp_nonce_field( 'bbp-user-login' );
}

/** Register ******************************************************************/

/**
 * Output the required hidden fields when registering.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @return void
 */
function bbp_user_register_fields() {
?>

	<input type="hidden" name="action"      value="register" />
	<input type="hidden" name="user-cookie" value="1" />

	<?php

	// Allow custom registration redirection

	/**
	 * Filters the redirect URL for the registration form.
	 *
	 * @since 2.0.0 bbPress (r3450)
	 *
	 * @param string $value The redirect URL for the registration form.
	 */
	$redirect_to = apply_filters( 'bbp_user_register_redirect_to', '' );
	bbp_redirect_to_field( add_query_arg( array( 'checkemail' => 'registered' ), $redirect_to ) );

	// Prevent intention hi-jacking of sign-up form
	wp_nonce_field( 'bbp-user-register' );
}

/** Lost Password *************************************************************/

/**
 * Output the required hidden fields when user lost password.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @return void
 */
function bbp_user_lost_pass_fields() {
?>

	<input type="hidden" name="user-cookie" value="1" />

	<?php

	// Allow custom lost pass redirection

	/**
	 * Filters the redirect URL for the lost password form.
	 *
	 * @since 2.0.0 bbPress (r3450)
	 *
	 * @param string|false $value The redirect URL for the lost password form.
	 */
	$redirect_to = apply_filters( 'bbp_user_lost_pass_redirect_to', get_permalink() );
	bbp_redirect_to_field( add_query_arg( array( 'checkemail' => 'confirm' ), $redirect_to ) );

	// Prevent intention hi-jacking of lost pass form
	wp_nonce_field( 'bbp-user-lost-pass' );
}

/** Author Avatar *************************************************************/

/**
 * Output the author link of a post.
 *
 * @since 2.0.0 bbPress (r2874)
 *
 * @param array<string, mixed>|int $args Optional. Arguments or a post ID.
 * @return void
 */
function bbp_author_link( $args = array() ) {
	echo bbp_get_author_link( $args );
}

/**
 * Return the author link of the post.
 *
 * @since 2.0.0 bbPress (r2874)
 * @since 2.6.21 bbPress (r8080) Added the `user_id` argument for non-topic and non-reply posts.
 *
 * @param array<string, mixed>|int $args Optional. Arguments or a post ID.
 * @return string Author link, placeholder, or an empty string.
 */
function bbp_get_author_link( $args = array() ) {

	$post_id = is_numeric( $args ) ? (int) $args : 0;

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'post_id'    => $post_id,
			'user_id'    => 0,
			'link_title' => '',
			'type'       => 'both',
			'size'       => 80,
			'sep'        => ''
		),
		'get_author_link'
	);

	$post_arg = $r['post_id'];
	if ( $post_arg instanceof WP_Post ) {
		$post_id = $post_arg->ID;
	} elseif ( is_numeric( $post_arg ) ) {
		$post_id = (int) $post_arg;
	} else {
		$post_id = 0;
	}

	$explicit_user_id = is_numeric( $r['user_id'] ) ? (int) $r['user_id'] : 0;
	$title_arg        = bbp_get_template_string_arg( $r, 'link_title' );
	$sep              = bbp_get_template_string_arg( $r, 'sep' );
	$size             = is_numeric( $r['size'] ) ? (int) $r['size'] : 80;

	// An explicitly empty post ID must not fall back to the current loop post
	if ( ( is_numeric( $args ) || ( is_array( $args ) && array_key_exists( 'post_id', $args ) ) ) && empty( $post_id ) ) {
		/** This filter is documented in src/includes/users/template.php. */
		return apply_filters( 'bbp_get_author_link', '-', $r, $args );
	}

	// Confirmed topic
	if ( bbp_is_topic( $post_id ) ) {
		return bbp_get_topic_author_link( $r );

	// Confirmed reply
	} elseif ( bbp_is_reply( $post_id ) ) {
		return bbp_get_reply_author_link( $r );
	}

	// Default return value
	$author_link = '';

	// Neither a reply nor a topic, so could be a revision or explicit user
	if ( ! empty( $post_id ) || ! empty( $explicit_user_id ) ) {

		// Get some useful author information
		if ( ! empty( $explicit_user_id ) ) {
			$user_id = $explicit_user_id;
		} else {
			$post_author = get_post_field( 'post_author', $post_id );
			$user_id     = (int) $post_author;
		}
		$author_url = bbp_validate_url( bbp_get_user_profile_url( $user_id ) );
		$anonymous  = empty( $explicit_user_id ) && ! empty( $post_id ) && bbp_is_reply_anonymous( $post_id );

		// Generate title with the display name of the author
		if ( empty( $title_arg ) ) {
			$author = get_the_author_meta( 'display_name', $user_id );
			$title  = empty( $anonymous )
				/* translators: %s: Author's display name */
				? esc_attr__( "View %s's profile",  'bbpress' )
				/* translators: %s: Author's display name */
				: esc_attr__( "Visit %s's website", 'bbpress' );

			$r['link_title'] = sprintf( $title, $author );
			$title_arg       = $r['link_title'];
		}

		// Setup title and author_links array
		$author_links = array();
		$link_title   = ! empty( $title_arg )
			? ' title="' . esc_attr( $title_arg ) . '"'
			: '';

		// Get avatar (unescaped, because HTML)
		if ( ( 'avatar' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
			$author_links['avatar'] = get_avatar( $user_id, $size );
		}

		// Get display name (escaped, because never HTML)
		if ( ( 'name' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
			$author_links['name'] = esc_html( get_the_author_meta( 'display_name', $user_id ) );
		}

		// Empty array
		$links  = array();
		$sprint = '<span%1$s>%2$s</span>';

		// Wrap each link
		foreach ( $author_links as $link => $link_text ) {
			$link_class = ' class="bbp-author-' . esc_attr( $link ) . '"';
			$links[]    = sprintf( $sprint, $link_class, $link_text );
		}

		// Juggle
		$author_links = $links;
		unset( $links );

		// Filter sections

		/**
		 * Filters the author links before combining them.
		 *
		 * @since 2.6.0 bbPress (r6736)
		 *
		 * @param array $author_links The author links before combining them.
		 * @param array $r Parsed arguments.
		 * @param array<string, mixed>|int $args Original arguments.
		 */
		$sections    = apply_filters( 'bbp_get_author_links', $author_links, $r, $args );

		// Assemble sections into author link
		$author_link = implode( $sep, $sections );

		// Only wrap in link if profile exists
		if ( empty( $anonymous ) && bbp_user_has_profile( $user_id ) && ( false !== $author_url ) ) {
			$author_link = sprintf( '<a href="%1$s"%2$s%3$s>%4$s</a>', esc_url( $author_url ), $link_title, ' class="bbp-author-link"', $author_link );
		}
	}

	/**
	 * Filters the author link of the post.
	 *
	 * @since 2.0.0 bbPress (r2894)
	 *
	 * @param string $author_link Author link, placeholder, or an empty string.
	 * @param array $r Arguments.
	 * @param array<string, mixed>|int $args Arguments or a post ID.
	 */
	return apply_filters( 'bbp_get_author_link', $author_link, $r, $args );
}

/** Capabilities **************************************************************/

/**
 * Check if the user can access a specific forum.
 *
 * @since 2.0.0 bbPress (r3057)
 * @since 2.6.19 bbPress (r7675) Check forum ancestors by default.
 *
 * @param array<string, mixed> $args User ID, forum ID, and ancestor check settings.
 * @return bool
 */
function bbp_user_can_view_forum( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'user_id'         => bbp_get_current_user_id(),
			'forum_id'        => bbp_get_forum_id(),
			'check_ancestors' => true
		),
		'user_can_view_forum'
	);

	// Validate parsed values
	$user_id  = bbp_get_user_id( $r['user_id'], false, false );
	$forum_id = bbp_get_forum_id( $r['forum_id'] );
	$retval   = false;

	// User is a keymaster
	if ( ! empty( $user_id ) && bbp_is_user_keymaster( $user_id ) ) {
		$retval = true;

	// Forum is public, and user can read forums or is not logged in
	} elseif ( bbp_is_forum_public( $forum_id, $r['check_ancestors'] ) ) {
		$retval = true;

	// Forum is private, and user can see it
	} elseif ( bbp_is_forum_private( $forum_id, $r['check_ancestors'] ) && user_can( $user_id, 'read_forum', $forum_id ) ) {
		$retval = true;

	// Forum is hidden, and user can see it
	} elseif ( bbp_is_forum_hidden( $forum_id, $r['check_ancestors'] ) && user_can( $user_id, 'read_forum', $forum_id  ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the user can access a specific forum.
	 *
	 * @since 2.0.0 bbPress (r3057)
	 *
	 * @param bool $retval Whether the user can access a specific forum.
	 * @param int $forum_id Forum id.
	 * @param int $user_id User id.
	 */
	return apply_filters( 'bbp_user_can_view_forum', $retval, $forum_id, $user_id );
}

/**
 * Check if the current user can publish topics.
 *
 * @since 2.0.0 bbPress (r3366)
 *
 * @return bool
 */
function bbp_current_user_can_publish_topics() {

	// Users need to earn access
	$retval = false;

	// Always allow keymasters
	if ( bbp_is_user_keymaster() ) {
		$retval = true;

	// Do not allow anonymous if not enabled
	} elseif ( ! is_user_logged_in() && bbp_allow_anonymous() ) {
		$retval = true;

	// User is logged in
	} elseif ( current_user_can( 'publish_topics' ) ) {
		$retval = true;
	}

	// The site-wide status limits the default publishing permission.
	if ( ! bbp_current_user_can_post_in_forums() ) {
		$retval = false;
	}

	/**
	 * Filters whether the current user can publish topics.
	 *
	 * @since 2.0.0 bbPress (r3366)
	 *
	 * @param bool $retval Whether the current user can publish topics.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_publish_topics', $retval );
}

/**
 * Check if the current user can publish forums.
 *
 * @since 2.1.0 bbPress (r3549)
 *
 * @return bool
 */
function bbp_current_user_can_publish_forums() {

	// Users need to earn access
	$retval = false;

	// Always allow keymasters
	if ( bbp_is_user_keymaster() ) {
		$retval = true;

	// User is logged in
	} elseif ( current_user_can( 'publish_forums' ) ) {
		$retval = true;
	}

	// Only BuddyPress Group Forums can be created while forums are frozen.
	if ( bbp_is_forums_status( 'frozen' ) ) {
		$retval = false;
	}

	/**
	 * Filters whether the current user can publish forums.
	 *
	 * @since 2.1.0 bbPress (r3549)
	 *
	 * @param bool $retval Whether the current user can publish forums.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_publish_forums', $retval );
}

/**
 * Check if the current user can publish replies.
 *
 * @since 2.0.0 bbPress (r3366)
 *
 * @return bool
 */
function bbp_current_user_can_publish_replies() {

	// Users need to earn access
	$retval = false;

	// Always allow keymasters
	if ( bbp_is_user_keymaster() ) {
		$retval = true;

	// Do not allow anonymous if not enabled
	} elseif ( ! is_user_logged_in() && bbp_allow_anonymous() ) {
		$retval = true;

	// User is logged in
	} elseif ( current_user_can( 'publish_replies' ) ) {
		$retval = true;
	}

	// The site-wide status limits the default publishing permission.
	if ( ! bbp_current_user_can_post_in_forums() ) {
		$retval = false;
	}

	/**
	 * Filters whether the current user can publish replies.
	 *
	 * @since 2.0.0 bbPress (r3366)
	 *
	 * @param bool $retval Whether the current user can publish replies.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_publish_replies', $retval );
}

/** Forms *********************************************************************/

/**
 * The following functions should be turned into mapped meta capabilities in a
 * future version. They exist only to remove complex logistical capability
 * checks from within template parts.
 */

/**
 * Get the forums the current user has the ability to see and post to.
 *
 * @since 2.0.0 bbPress (r3127)
 *
 * @param array<string, mixed> $args Forum query arguments.
 *
 * @return array<array-key, WP_Post>|false Forums, or false when none are available.
 */
function bbp_get_forums_for_current_user( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'post_type'    => bbp_get_forum_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post__not_in' => bbp_exclude_forum_ids( 'array' ),
			'numberposts'  => -1
		),
		'get_forums_for_current_user'
	);

	// Get the forums
	$forums = get_posts( $r );

	// No availabe forums
	if ( empty( $forums ) ) {
		$forums = false;
	}

	/**
	 * Filters the forums the current user has the ability to see and post to.
	 *
	 * @since 2.0.0 bbPress (r3127)
	 *
	 * @param mixed $forums Forum IDs or posts, or false when none are available.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Forum query arguments.
	 */
	return apply_filters( 'bbp_get_forums_for_current_user', $forums, $r, $args );
}

/**
 * Performs a series of checks to ensure the current user can create forums.
 *
 * @since 2.1.0 bbPress (r3549)
 *
 * @return bool
 */
function bbp_current_user_can_access_create_forum_form() {

	// Users need to earn access
	$retval = false;

	// Always allow keymasters
	if ( bbp_is_user_keymaster() ) {
		$retval = true;

	// Looking at a single forum & forum is open
	} elseif ( is_singular() && bbp_is_forum_open() ) {
		$retval = bbp_current_user_can_publish_forums();

	// User can edit this forum
	} else {
		$retval = current_user_can( 'edit_forum', bbp_get_forum_id() );
	}

	// Editing an existing forum remains available to capable users.
	if ( ! bbp_is_forum_edit() && bbp_is_forums_status( 'frozen' ) ) {
		$retval = false;
	}

	/**
	 * Filters whether the current user can access the new forum form.
	 *
	 * @since 2.1.0 bbPress (r3549)
	 *
	 * @param bool $retval Whether the current user can access the new forum form.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_access_create_forum_form', (bool) $retval );
}

/**
 * Performs a series of checks to ensure the current user can create topics.
 *
 * @since 2.0.0 bbPress (r3127)
 *
 * @return bool
 */
function bbp_current_user_can_access_create_topic_form() {

	// Users need to earn access
	$retval = false;

	// Always allow keymasters
	if ( bbp_is_user_keymaster() ) {
		$retval = true;

	// Looking at a single forum & forum is open
	} elseif ( ( bbp_is_single_forum() || is_singular() ) && bbp_is_forum_open() ) {
		$retval = bbp_current_user_can_publish_topics();

	// User can edit this topic
	} else {
		$retval = current_user_can( 'edit_topic', bbp_get_topic_id() );
	}

	// A new topic needs the site's posting permission; editing does not.
	if ( ! bbp_is_topic_edit() && ! bbp_current_user_can_post_in_forums() ) {
		$retval = false;
	}

	// Check the current password before opening an existing topic for editing.
	if ( $retval && bbp_is_topic_edit() ) {
		$topic_id = bbp_get_topic_id();
		if ( ! current_user_can( 'moderate', $topic_id ) && bbp_get_password_required_id( $topic_id ) ) {
			$retval = false;
		}
	}

	/**
	 * Filters whether the current user can access the new topic form.
	 *
	 * @since 2.0.0 bbPress (r3159)
	 *
	 * @param bool $retval Whether the current user can access the new topic form.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_access_create_topic_form', (bool) $retval );
}

/**
 * Performs a series of checks to ensure the current user can create replies.
 *
 * @since 2.0.0 bbPress (r3366)
 *
 * @return bool
 */
function bbp_current_user_can_access_create_reply_form() {

	// Users need to earn access
	$retval = false;

	// Always allow keymasters
	if ( bbp_is_user_keymaster() ) {
		$retval = true;

	// Looking at single topic (and singulars), topic is open, and forum is open
	} elseif ( ( bbp_is_single_topic() || is_singular() ) && bbp_is_topic_open() && bbp_is_forum_open() && bbp_is_topic_published() ) {
		$retval = bbp_current_user_can_publish_replies();

	// User can edit this reply
	} elseif ( bbp_get_reply_id() ) {
		$retval = current_user_can( 'edit_reply', bbp_get_reply_id() );

	// User can edit this topic
	} elseif ( bbp_get_topic_id() ) {
		$retval = current_user_can( 'edit_topic', bbp_get_topic_id() );
	}

	// A new reply needs the site's posting permission; editing does not.
	if ( ! bbp_is_reply_edit() && ! bbp_current_user_can_post_in_forums() ) {
		$retval = false;
	}

	// Check the current password before opening an existing reply for editing.
	if ( $retval && bbp_is_reply_edit() ) {
		$reply_id = bbp_get_reply_id();
		if ( ! current_user_can( 'moderate', $reply_id ) && bbp_get_password_required_id( $reply_id ) ) {
			$retval = false;
		}
	}

	/**
	 * Filters whether the current user can access the new reply form.
	 *
	 * @since 2.1.0 bbPress (r3549)
	 *
	 * @param bool $retval Whether the current user can access the new reply form.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_access_create_reply_form', (bool) $retval );
}

/**
 * Check if the current user can access structural topic moderation actions.
 *
 * This covers topic merges, topic splits, and reply moves. Their handlers
 * perform the same capability checks independently when processing a request.
 *
 * @since 2.6.19 bbPress (r7617)
 *
 * @param int $topic_id Optional. Topic ID.
 * @return bool True if the current user can moderate and edit the topic.
 */
function bbp_current_user_can_access_topic_moderation( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$retval   = ! empty( $topic_id )
		&& current_user_can( 'moderate', $topic_id )
		&& current_user_can( 'edit_topic', $topic_id );

	/**
	 * Filters whether the current user can access structural topic moderation actions.
	 *
	 * @since 2.7.0 bbPress (r7617)
	 *
	 * @param bool $retval True if the current user can moderate and edit the topic.
	 * @param int $topic_id Topic ID.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_access_topic_moderation', $retval, $topic_id );
}

/**
 * Performs a series of checks to ensure the current user should see the
 * anonymous user form fields.
 *
 * @since 2.5.0 bbPress (r5120)
 *
 * @return bool
 */
function bbp_current_user_can_access_anonymous_user_form() {

	// Users need to earn access
	$retval = false;

	// User is not logged in, and anonymous posting is allowed
	if ( bbp_is_anonymous() ) {
		$retval = true;

	// User is editing a topic, and topic is authored by anonymous user
	} elseif ( bbp_is_topic_edit() && bbp_is_topic_anonymous() ) {
		$retval = true;

	// User is editing a reply, and reply is authored by anonymous user
	} elseif ( bbp_is_reply_edit() && bbp_is_reply_anonymous() ) {
		$retval = true;
	}

	/**
	 * Filters whether the current user can access the anonymous user form.
	 *
	 * @since 2.5.0 bbPress (r5120)
	 *
	 * @param bool $retval Whether the current user can access the anonymous user form.
	 */
	return (bool) apply_filters( 'bbp_current_user_can_access_anonymous_user_form', (bool) $retval );
}

/** Moderators ****************************************************************/

/**
 * Output the moderators of a forum.
 *
 * @since 2.6.0 bbPress (r6056)
 *
 * @param int   $forum_id Optional. Topic id.
 * @param array<string, mixed> $args     See {@link bbp_get_moderator_list()}.
 * @return void
 */
function bbp_moderator_list( $forum_id = 0, $args = array() ) {
	echo bbp_get_moderator_list( $forum_id, $args );
}

/**
 * Return the moderators for an object.
 *
 * @since 2.6.0 bbPress (r6056)
 *
 * @param int   $object_id Optional. Object id.
 * @param array<string, mixed> $args      Before, separator, after, and empty-list markup.
 *
 * @return string Moderator list of the object.
 */
function bbp_get_moderator_list( $object_id = 0, $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'before' => '<div class="bbp-moderators"><p>' . esc_html__( 'Moderators:', 'bbpress' ) . '&nbsp;',
			'sep'    => ', ',
			'after'  => '</p></div>',
			'none'   => ''
		),
		'get_moderator_list'
	);

	$before = bbp_get_template_string_arg( $r, 'before' );
	$after  = bbp_get_template_string_arg( $r, 'after' );
	$sep    = bbp_get_template_string_arg( $r, 'sep' );
	$none   = bbp_get_template_string_arg( $r, 'none' );

	// Get forum moderators
	$user_ids = bbp_get_moderator_ids( $object_id );
	if ( ! empty( $user_ids ) ) {

		// In admin, use nicenames
		if ( is_admin() ) {
			$users = bbp_get_user_nicenames_from_ids( $user_ids );

		// In theme, use display names & profile links
		} else {
			foreach ( $user_ids as $user_id ) {
				$users[] = bbp_get_user_profile_link( $user_id );
			}
		}

		$retval = $before . implode( $sep, $users ) . $after;

	// No forum moderators
	} else {
		$retval = $none;
	}

	/**
	 * Filters the moderators for an object.
	 *
	 * @since 2.6.0 bbPress (r6056)
	 *
	 * @param string $retval Moderator list of the object.
	 */
	return apply_filters( 'bbp_get_moderator_list', $retval );
}
