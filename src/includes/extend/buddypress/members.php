<?php

/**
 * bbPress BuddyPress Members Class.
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Forums_Members' ) ) :
/**
 * Member profile modifications.
 *
 * @since 2.2.0 bbPress (r4397)
 * @since 2.6.0 bbPress (r6320) Add engagements support
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_BuddyPress_Members {

	/**
	 * Main constructor for modifying bbPress profile links.
	 *
	 * @since 2.2.0 bbPress (r4397)
	 */
	public function __construct() {
		$this->setup_actions();
		$this->setup_filters();
		$this->fully_loaded();
	}

	/**
	 * Setup the actions.
	 *
	 * @since 2.2.0 bbPress (r4397)
	 *
	 * @access private
	 */
	private function setup_actions() {

		// Allow unsubscribe/unfavorite links to work
		add_action( 'bp_template_redirect', array( $this, 'set_member_forum_query_vars' ) );
		add_action( 'bp_notification_settings', array( $this, 'notification_settings' ) );
		add_action( 'bp_core_notification_settings_after_save', array( $this, 'save_notification_settings' ) );

		/** Engagements *******************************************************/

		// Move handlers to 'bp_actions' - BuddyPress bypasses template_loader
		remove_action( 'bbp_get_request', 'bbp_favorites_handler', 1 );
		remove_action( 'bbp_get_request', 'bbp_subscriptions_handler', 1 );
		add_action(    'bp_actions',      array( $this, 'engagements_handler' ), 1 );
	}

	/** Actions ***************************************************************/

	/**
	 * Handle favorites and subscriptions from BuddyPress member pages.
	 *
	 * BuddyPress does not pass the requested action to its `bp_actions` hook,
	 * so get it directly from the request before passing it to the bbPress
	 * handlers.
	 *
	 * @since 2.6.20 bbPress (r7933)
	 * @since 2.6.20 bbPress (r8024) Set member query variables before handling engagements.
	 */
	public function engagements_handler() {

		// Bail if no action, or if not a string (arrays not supported)
		if ( empty( $_GET['action'] ) || ! is_string( $_GET['action'] ) ) {
			return;
		}

		// Sanitize the GET action
		$action = sanitize_key( $_GET['action'] );

		// Set the displayed member context before handlers select a user
		$this->set_member_forum_query_vars();

		// Pass the action to both handlers, which validate supported actions
		bbp_favorites_handler( $action );
		bbp_subscriptions_handler( $action );
	}

	/**
	 * Setup the filters.
	 *
	 * @since 2.2.0 bbPress (r4397)
	 * @since 2.6.0 bbPress (r6320) Add engagements support
	 *
	 * @access private
	 */
	private function setup_filters() {
		add_filter( 'bbp_pre_get_user_profile_url',         array( $this, 'get_user_profile_url'        ) );
		add_filter( 'bbp_pre_get_user_topics_created_url',  array( $this, 'get_topics_created_url'      ) );
		add_filter( 'bbp_pre_get_user_replies_created_url', array( $this, 'get_replies_created_url'     ) );
		add_filter( 'bbp_pre_get_user_engagements_url',     array( $this, 'get_engagements_permalink'   ) );
		add_filter( 'bbp_pre_get_user_mentions_url',        array( $this, 'get_mentions_permalink'      ) );
		add_filter( 'bbp_pre_get_favorites_permalink',      array( $this, 'get_favorites_permalink'     ) );
		add_filter( 'bbp_pre_get_subscriptions_permalink',  array( $this, 'get_subscriptions_permalink' ) );
		add_filter( 'bbp_mention_email_settings_url',       array( $this, 'mention_email_settings_url'  ), 10, 2 );
		add_filter( 'bbp_get_user_notification_inherited_preference', array( $this, 'get_mention_email_preference' ), 10, 3 );
	}

	/**
	 * Allow the variables, actions, and filters to be modified by third party
	 * plugins and themes.
	 *
	 * @since 2.6.0 bbPress (r6808)
	 */
	private function fully_loaded() {
		do_action_ref_array( 'bbp_buddypress_members_loaded', array( $this ) );
	}

	/** Filters ***************************************************************/

	/**
	 * Override bbPress profile URL with BuddyPress profile URL.
	 *
	 * @since 2.6.0 bbPress (r6804)
	 * @since 2.6.0 bbPress (r6320) Add engagements support
	 *
	 * @param int $user_id
	 * @return string
	 */
	public function get_user_profile_url( $user_id = 0 ) {
		return $this->get_profile_url( $user_id );
	}

	/**
	 * Override bbPress topics created URL with BuddyPress profile URL.
	 *
	 * @since 2.6.0 bbPress (r6804)
	 * @since 2.6.0 bbPress (r6804) Use private method
	 *
	 * @param int $user_id
	 * @return string
	 */
	public function get_topics_created_url( $user_id = 0 ) {
		return $this->get_profile_url( $user_id, bbp_get_topic_archive_slug() );
	}

	/**
	 * Override bbPress replies created URL with BuddyPress profile URL.
	 *
	 * @since 2.6.0 bbPress (r6804)
	 * @since 2.6.0 bbPress (r6804) Use private method
	 *
	 * @param int $user_id
	 * @return string
	 */
	public function get_replies_created_url( $user_id = 0 ) {
		return $this->get_profile_url( $user_id, bbp_get_reply_archive_slug() );
	}

	/**
	 * Override bbPress favorites URL with BuddyPress profile URL.
	 *
	 * @since 2.2.0 bbPress (r4397)
	 * @since 2.6.0 bbPress (r6804) Use private method
	 *
	 * @param int $user_id
	 * @return string
	 */
	public function get_favorites_permalink( $user_id = 0 ) {
		return $this->get_profile_url( $user_id, bbp_get_user_favorites_slug() );
	}

	/**
	 * Override bbPress subscriptions URL with BuddyPress profile URL.
	 *
	 * @since 2.2.0 bbPress (r4397)
	 * @since 2.6.0 bbPress (r6804) Use private method
	 *
	 * @param int $user_id
	 * @return string
	 */
	public function get_subscriptions_permalink( $user_id = 0 ) {
		return $this->get_profile_url( $user_id, bbp_get_user_subscriptions_slug() );
	}

	/**
	 * Override bbPress engagements URL with BuddyPress profile URL.
	 *
	 * @since 2.6.0 bbPress (r6321)
	 *
	 * @param int $user_id
	 * @return string
	 */
	public function get_engagements_permalink( $user_id = 0 ) {
		return $this->get_profile_url( $user_id, bbp_get_user_engagements_slug() );
	}

	/**
	 * Return the BuddyPress member's forum mention URL.
	 *
	 * @since 2.7.0 bbPress (r8264)
	 *
	 * @param int $user_id Profile owner ID.
	 * @return string|false
	 */
	public function get_mentions_permalink( $user_id = 0 ) {
		return $this->get_profile_url( $user_id, bbp_get_user_mentions_slug() );
	}

	/**
	 * Display bbPress preferences in BuddyPress notification settings.
	 *
	 * @since 2.7.0 bbPress (r8363)
	 */
	public function notification_settings() {
		$user_id = bp_displayed_user_id();
		if ( ! bbp_current_user_can_edit_user_field( 'email', $user_id ) ) {
			return;
		}

		$types = array();
		foreach ( bbp_get_notification_types() as $type => $args ) {
			if ( is_string( $type ) && is_array( $args ) && ! empty( $args['label'] ) && is_string( $args['label'] ) ) {
				$types[ $type ] = $args;
			}
		}

		if ( empty( $types ) ) {
			return;
		}
		?>

		<table class="notification-settings" id="bbpress-notification-settings">
			<thead>
				<tr>
					<th class="icon">&nbsp;</th>
					<th class="title"><?php esc_html_e( 'Forums', 'bbpress' ); ?></th>
					<th class="yes default"><?php esc_html_e( 'Default', 'bbpress' ); ?></th>
					<th class="yes"><?php esc_html_e( 'Yes', 'bbpress' ); ?></th>
					<th class="no"><?php esc_html_e( 'No', 'bbpress' ); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ( $types as $type => $args ) : ?>
					<?php $choice = bbp_get_user_notification_preference_choice( $user_id, $type ); ?>
					<tr id="bbpress-notification-settings-<?php echo esc_attr( $type ); ?>">
						<td>&nbsp;</td>
						<td><?php echo esc_html( $args['label'] ); ?></td>
						<td class="yes default">
							<input type="radio" name="bbp_notification_preferences[<?php echo esc_attr( $type ); ?>]" id="bbp-notification-<?php echo esc_attr( $type ); ?>-default" value="default" <?php checked( $choice, 'default' ); ?> />
							<label for="bbp-notification-<?php echo esc_attr( $type ); ?>-default" class="bp-screen-reader-text"><?php esc_html_e( 'Use the default setting', 'bbpress' ); ?></label>
						</td>
						<td class="yes">
							<input type="radio" name="bbp_notification_preferences[<?php echo esc_attr( $type ); ?>]" id="bbp-notification-<?php echo esc_attr( $type ); ?>-yes" value="yes" <?php checked( $choice, 'yes' ); ?> />
							<label for="bbp-notification-<?php echo esc_attr( $type ); ?>-yes" class="bp-screen-reader-text"><?php esc_html_e( 'Yes, send email', 'bbpress' ); ?></label>
						</td>
						<td class="no">
							<input type="radio" name="bbp_notification_preferences[<?php echo esc_attr( $type ); ?>]" id="bbp-notification-<?php echo esc_attr( $type ); ?>-no" value="no" <?php checked( $choice, 'no' ); ?> />
							<label for="bbp-notification-<?php echo esc_attr( $type ); ?>-no" class="bp-screen-reader-text"><?php esc_html_e( 'No, do not send email', 'bbpress' ); ?></label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php wp_nonce_field( 'bbp-notification-preferences-' . $user_id, '_bbp_notification_nonce', false ); ?>

		<?php
	}

	/**
	 * Save independently authorized bbPress email preferences.
	 *
	 * @since 2.7.0 bbPress (r8363)
	 */
	public function save_notification_settings() {
		bbp_save_user_notification_preferences( bp_displayed_user_id() );
	}

	/**
	 * Inherit the BuddyPress mention setting when bbPress has no explicit choice.
	 *
	 * @since 2.7.0 bbPress (r8363)
	 *
	 * @param bool|null $preference Inherited preference from another integration.
	 * @param int       $user_id    Recipient ID.
	 * @param string    $type       Notification type.
	 * @return bool|null Inherited preference, if available.
	 */
	public function get_mention_email_preference( $preference, $user_id, $type ) {
		if ( 'mentions' === $type ) {
			$preference = ( 'yes' === bp_get_user_meta( $user_id, 'notification_activity_new_mention', true ) );
		}

		return $preference;
	}

	/**
	 * Point mention email recipients to BuddyPress notification settings.
	 *
	 * @since 2.7.0 bbPress (r8264)
	 *
	 * @param string $url     Native settings URL.
	 * @param int    $user_id Recipient ID.
	 * @return string
	 */
	public function mention_email_settings_url( $url, $user_id ) {
		$profile = $this->get_profile_url( $user_id );
		if ( $profile && bp_is_active( 'settings' ) ) {
			$url = trailingslashit( $profile ) . bp_get_settings_slug() . '/notifications/';
		}

		return $url;
	}

	/**
	 * Set favorites and subscriptions query variables if viewing member profile
	 * pages.
	 *
	 * @since 2.3.0 bbPress (r4616)
	 * @since 2.6.0 bbPress (r6320) Support all profile sections
	 * @since 2.6.20 bbPress (r7925) Support all displayed profiles
	 *
	 * @return void
	 */
	public function set_member_forum_query_vars() {

		// Special handling for forum component
		if ( ! bp_is_user() ) {
			return;
		}

		// Get the main query object
		$wp_query = bbp_get_wp_query();

		// 'topics' action
		if ( bp_is_current_action( bbp_get_topic_archive_slug() ) ) {
			$wp_query->bbp_is_single_user_topics = true;

		// 'replies' action
		} elseif ( bp_is_current_action( bbp_get_reply_archive_slug() ) ) {
			$wp_query->bbp_is_single_user_replies = true;

		// 'favorites' action
		} elseif ( bbp_is_favorites_active() && bp_is_current_action( bbp_get_user_favorites_slug() ) ) {
			$wp_query->bbp_is_single_user_favs = true;

		// 'subscriptions' action
		} elseif ( bbp_is_subscriptions_active() && bp_is_current_action( bbp_get_user_subscriptions_slug() ) ) {
			$wp_query->bbp_is_single_user_subs = true;

		// 'engagements' action
		} elseif ( bbp_is_engagements_active() && bp_is_current_action( bbp_get_user_engagements_slug() ) ) {
			$wp_query->bbp_is_single_user_engagements = true;

		// 'mentions' action
		} elseif ( bp_is_current_action( bbp_get_user_mentions_slug() ) && bbp_current_user_can_view_mentions( bp_displayed_user_id() ) ) {
			$wp_query->bbp_is_single_user_mentions = true;

			// BuddyPress routes page numbers as action variables.
			$page = bp_action_variable( 1 );
			if ( bp_is_action_variable( bbp_get_paged_slug(), 0 ) && ctype_digit( (string) $page ) ) {
				$wp_query->set( 'paged', max( 1, (int) $page ) );
			}
		}
	}

	/** Private Methods *******************************************************/

	/**
	 * Private method used to concatenate user IDs and slugs into URLs.
	 *
	 * @since 2.6.0 bbPress (r6804)
	 *
	 * @param int    $user_id
	 * @param string $slug
	 *
	 * @return string
	 */
	private function get_profile_url( $user_id = 0, $slug = '' ) {

		// Do not filter if not on BuddyPress root blog
		if ( empty( $user_id ) || ! bp_is_root_blog() ) {
			return false;
		}

		// Default URL value
		$url = array();

		// BuddyPress 12.0 and newer (rewrite rules)
		if ( function_exists( 'bp_members_get_user_url' ) ) {
			$url = array( bp_members_get_user_url( $user_id ) );

		// BuddyPress before 12.0 (deprecated code is intentionally included)
		} elseif ( function_exists( 'bp_core_get_user_domain' ) ) {
			$url = array( bp_core_get_user_domain( $user_id ) );
		}

		// Maybe push slug to end of URL array
		if ( ! empty( $slug ) ) {
			array_push( $url, bbpress()->extend->buddypress->slug );
			array_push( $url, $slug );
		}

		// Return
		return implode( '', array_map( 'trailingslashit', $url ) );
	}
}
endif;
