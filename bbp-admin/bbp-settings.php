<?php

/**
 * bbPress Admin Settings
 *
 * @package bbPress
 * @subpackage Administration
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Start Main Section ********************************************************/

/**
 * Main settings section description for the settings page
 *
 * @since bbPress (r2786)
 */
function bbp_admin_setting_callback_main_section() {
?>

	<p><?php _e( 'Main settings for the bbPress plugin', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit lock setting field
 *
 * @since bbPress (r2737)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_editlock() {
?>

	<input name="_bbp_edit_lock" type="text" id="_bbp_edit_lock" value="<?php form_option( '_bbp_edit_lock' ); ?>" class="small-text" />
	<label for="_bbp_edit_lock"><?php _e( 'minutes', 'bbpress' ); ?></label>

<?php
}

/**
 * Throttle setting field
 *
 * @since bbPress (r2737)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_throttle() {
?>

	<input name="_bbp_throttle_time" type="text" id="_bbp_throttle_time" value="<?php form_option( '_bbp_throttle_time' ); ?>" class="small-text" />
	<label for="_bbp_throttle_time"><?php _e( 'seconds', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow favorites setting field
 *
 * @since bbPress (r2786)
 *
 * @uses checked() To display the checked attribute
 */
function bbp_admin_setting_callback_favorites() {
?>

	<input id="_bbp_enable_favorites" name="_bbp_enable_favorites" type="checkbox" id="_bbp_enable_favorites" value="1" <?php checked( true, bbp_is_favorites_active() ); ?> />
	<label for="_bbp_enable_favorites"><?php _e( 'Allow users to mark topics as favorites?', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow subscriptions setting field
 *
 * @since bbPress (r2737)
 *
 * @uses checked() To display the checked attribute
 */
function bbp_admin_setting_callback_subscriptions() {
?>

	<input id="_bbp_enable_subscriptions" name="_bbp_enable_subscriptions" type="checkbox" id="_bbp_enable_subscriptions" value="1" <?php checked( true, bbp_is_subscriptions_active() ); ?> />
	<label for="_bbp_enable_subscriptions"><?php _e( 'Allow users to subscribe to topics', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow anonymous posting setting field
 *
 * @since bbPress (r2737)
 *
 * @uses checked() To display the checked attribute
 */
function bbp_admin_setting_callback_anonymous() {
?>

	<input id="_bbp_allow_anonymous" name="_bbp_allow_anonymous" type="checkbox" id="_bbp_allow_anonymous" value="1" <?php checked( true, bbp_allow_anonymous() ); ?> />
	<label for="_bbp_allow_anonymous"><?php _e( 'Allow guest users without accounts to create topics and replies', 'bbpress' ); ?></label>

<?php
}

/** Start Per Page Section ****************************************************/

/**
 * Per page settings section description for the settings page
 *
 * @since bbPress (r2786)
 */
function bbp_admin_setting_callback_per_page_section() {
?>

	<p><?php _e( 'Per page settings for the bbPress plugin', 'bbpress' ); ?></p>

<?php
}

/**
 * Topics per page setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_topics_per_page() {
?>

	<input name="_bbp_topics_per_page" type="text" id="_bbp_topics_per_page" value="<?php form_option( '_bbp_topics_per_page' ); ?>" class="small-text" />
	<label for="_bbp_topics_per_page"><?php _e( 'per page', 'bbpress' ); ?></label>

<?php
}

/**
 * Replies per page setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_replies_per_page() {
?>

	<input name="_bbp_replies_per_page" type="text" id="_bbp_replies_per_page" value="<?php form_option( '_bbp_replies_per_page' ); ?>" class="small-text" />
	<label for="_bbp_replies_per_page"><?php _e( 'per page', 'bbpress' ); ?></label>

<?php
}

/** Start Per RSS Page Section ************************************************/

/**
 * Per page settings section description for the settings page
 *
 * @since bbPress (r2786)
 */
function bbp_admin_setting_callback_per_rss_page_section() {
?>

	<p><?php _e( 'Per RSS page settings for the bbPress plugin', 'bbpress' ); ?></p>

<?php
}

/**
 * Topics per RSS page setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_topics_per_rss_page() {
?>

	<input name="_bbp_topics_per_rss_page" type="text" id="_bbp_topics_per_rss_page" value="<?php form_option( '_bbp_topics_per_rss_page' ); ?>" class="small-text" />
	<label for="_bbp_topics_per_rss_page"><?php _e( 'per page', 'bbpress' ); ?></label>

<?php
}

/**
 * Replies per RSS page setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_replies_per_rss_page() {
?>

	<input name="_bbp_replies_per_rss_page" type="text" id="_bbp_replies_per_rss_page" value="<?php form_option( '_bbp_replies_per_rss_page' ); ?>" class="small-text" />
	<label for="_bbp_replies_per_rss_page"><?php _e( 'per page', 'bbpress' ); ?></label>

<?php
}

/** Start Slug Section ********************************************************/

/**
 * Slugs settings section description for the settings page
 *
 * @since bbPress (r2786)
 */
function bbp_admin_setting_callback_root_slug_section() {

	// Flush rewrite rules when this section is saved
	if ( isset( $_GET['settings-updated'] ) && isset( $_GET['page'] ) )
		flush_rewrite_rules(); ?>

	<p><?php printf( __( 'Include custom root slugs to prefix your forums and topics with. These can be partnered with WordPress pages to allow more flexibility.', 'bbpress' ), get_admin_url( null, 'options-permalink.php' ) ); ?></p>

<?php
}

/**
 * Root slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_root_slug() {
?>

		<input name="_bbp_root_slug" type="text" id="_bbp_root_slug" class="regular-text code" value="<?php form_option( '_bbp_root_slug' ); ?>" />

<?php
}

/**
 * Topic archive slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_topic_archive_slug() {
?>

	<input name="_bbp_topic_archive_slug" type="text" id="_bbp_topic_archive_slug" class="regular-text code" value="<?php form_option( '_bbp_topic_archive_slug' ); ?>" />

<?php
}

/** Single Slugs **************************************************************/

/**
 * Slugs settings section description for the settings page
 *
 * @since bbPress (r2786)
 */
function bbp_admin_setting_callback_single_slug_section() {

	// Flush rewrite rules when this section is saved
	if ( isset( $_GET['settings-updated'] ) && isset( $_GET['page'] ) )
		flush_rewrite_rules(); ?>

	<p><?php printf( __( 'You can enter custom slugs for your single forums, topics, replies, and tags URLs here. If you change these, existing permalinks will also change.', 'bbpress' ), get_admin_url( null, 'options-permalink.php' ) ); ?></p>

<?php
}

/**
 * Include root slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses checked() To display the checked attribute
 */
function bbp_admin_setting_callback_include_root() {
?>

	<input id="_bbp_include_root" name="_bbp_include_root" type="checkbox" id="_bbp_include_root" value="1" <?php checked( true, get_option( '_bbp_include_root', true ) ); ?> />
	<label for="_bbp_include_root"><?php _e( 'Incude the Forum Base slug in your single forum item links', 'bbpress' ); ?></label>

<?php
}

/**
 * Forum slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_forum_slug() {
?>

	<input name="_bbp_forum_slug" type="text" id="_bbp_forum_slug" class="regular-text code" value="<?php form_option( '_bbp_forum_slug' ); ?>" />

<?php
}

/**
 * Topic slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_topic_slug() {
?>

	<input name="_bbp_topic_slug" type="text" id="_bbp_topic_slug" class="regular-text code" value="<?php form_option( '_bbp_topic_slug' ); ?>" />

<?php
}

/**
 * Reply slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_reply_slug() {
?>

	<input name="_bbp_reply_slug" type="text" id="_bbp_reply_slug" class="regular-text code" value="<?php form_option( '_bbp_reply_slug' ); ?>" />

<?php
}

/**
 * Topic tag slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_topic_tag_slug() {
?>

	<input name="_bbp_topic_tag_slug" type="text" id="_bbp_topic_tag_slug" class="regular-text code" value="<?php form_option( '_bbp_topic_tag_slug' ); ?>" />

<?php
}

/** Other Slugs ***************************************************************/

/**
 * User slug setting field
 *
 * @since bbPress (r2786)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_user_slug() {
?>

	<input name="_bbp_user_slug" type="text" id="_bbp_user_slug" class="regular-text code" value="<?php form_option( '_bbp_user_slug' ); ?>" />

<?php
}

/**
 * View slug setting field
 *
 * @since bbPress (r2789)
 *
 * @uses form_option() To output the option value
 */
function bbp_admin_setting_callback_view_slug() {
?>

	<input name="_bbp_view_slug" type="text" id="_bbp_view_slug" class="regular-text code" value="<?php form_option( '_bbp_view_slug' ); ?>" />

<?php
}

/** Settings Page *************************************************************/

/**
 * The main settings page
 *
 * @since bbPress (r2643)
 *
 * @uses screen_icon() To display the screen icon
 * @uses settings_fields() To output the hidden fields for the form
 * @uses do_settings_sections() To output the settings sections
 */
function bbp_admin_settings() {
?>

	<div class="wrap">

		<?php screen_icon(); ?>

		<h2><?php _e( 'bbPress Settings', 'bbpress' ) ?></h2>

		<form action="options.php" method="post">

			<?php settings_fields( 'bbpress' ); ?>

			<?php do_settings_sections( 'bbpress' ); ?>

			<p class="submit">
				<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save Changes', 'bbpress' ); ?>" />
			</p>
		</form>
	</div>

<?php
}

/**
 * Contextual help for bbPress settings page
 *
 * @since bbPress (r3119)
 */
function bbp_admin_settings_help() {

	$bbp_contextual_help[] = __('This screen provides access to basic bbPress settings.', 'bbpress' );
	$bbp_contextual_help[] = __('In the Main Settings you have a number of options:',     'bbpress' );
	$bbp_contextual_help[] =
		'<ul>' .
			'<li>' . __( 'You can choose to lock a post after a certain number of minutes. "Locking post editing" will prevent the author from editing some amount of time after saving a post.',              'bbpress' ) . '</li>' .
			'<li>' . __( '"Throttle time" is the amount of time required between posts from a single author. The higher the throttle time, the longer a user will need to wait between posting to the forum.', 'bbpress' ) . '</li>' .
			'<li>' . __( 'You may choose to allow favorites, which are a way for users to save and later return to topics they favor. This is enabled by default.',                                            'bbpress' ) . '</li>' .
			'<li>' . __( 'You may choose to allow subscriptions, which allows users to subscribe for notifications to topics that interest them. This is enabled by default.',                                 'bbpress' ) . '</li>' .
			'<li>' . __( 'You may choose to allow "Anonymous Posting", which will allow guest users who do not have accounts on your site to both create topics as well as replies.',                          'bbpress' ) . '</li>' .
		'</ul>';

	$bbp_contextual_help[] = __( 'Per Page settings allow you to control the number of topics and replies will appear on each of those pages. This is comparable to the WordPress "Reading Settings" page, where you can set the number of posts that should show on blog pages and in feeds.', 'bbpress' );
	$bbp_contextual_help[] = __( 'The Forums section allows you to control the permalink structure for your forums. Each "base" is what will be displayed after your main URL and right before your permalink slug.', 'bbpress' );
	$bbp_contextual_help[] = __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.', 'bbpress' );
	$bbp_contextual_help[] = __( '<strong>For more information:</strong>', 'bbpress' );
	$bbp_contextual_help[] =
		'<ul>' .
			'<li>' . __( '<a href="http://bbpress.org/documentation/">bbPress Documentation</a>', 'bbpress' ) . '</li>' .
			'<li>' . __( '<a href="http://bbpress.org/forums/">bbPress Support Forums</a>',       'bbpress' ) . '</li>' .
		'</ul>' ;

	// Empty the default $contextual_help var
	$contextual_help = '';

	// Wrap each help item in paragraph tags
	foreach( $bbp_contextual_help as $paragraph )
		$contextual_help .= '<p>' . $paragraph . '</p>';

	// Add help
	add_contextual_help( 'settings_page_bbpress', $contextual_help );
}

?>
