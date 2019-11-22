<?php

/**
 * bbPress Admin Tools Page
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Output a bbPress specific tools box
 *
 * @since 2.6.0 bbPress (r6273)
 */
function bbp_admin_tools_box() {

	// Bail if user cannot access tools page
	if ( ! current_user_can( 'bbp_tools_page' ) ) {
		return;
	}

	// Get the tools pages
	$links = array();
	$tools = bbp_get_tools_admin_pages(); ?>

	<div class="card">
		<h3 class="title"><?php esc_html_e( 'Forums', 'bbpress' ) ?></h3>
		<p><?php esc_html_e( 'bbPress provides the following tools to help you manage your forums:', 'bbpress' ); ?></p>

		<?php

		// Loop through tools and create links
		foreach ( $tools as $tool ) {

			// Skip if user cannot see this page
			if ( ! current_user_can( $tool['cap'] ) ) {
				continue;
			}

			// Add link to array
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( array( 'page' => $tool['page'] ), admin_url( 'tools.php' ) ) ), $tool['name'] );
		}

		// Output links
		echo '<p class="bbp-tools-links">' . implode( ' &middot; ', $links ) . '</p>';

	?></div>

<?php
}

/**
 * Register an admin area repair tool
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 * @return
 */
function bbp_register_repair_tool( $args = array() ) {

	// Parse arguments
	$r = bbp_parse_args( $args, array(
		'id'          => '',
		'type'        => '',
		'title'       => '',
		'description' => '',
		'callback'    => '',
		'priority'    => 0,
		'overhead'    => 'low',
		'version'     => '',
		'components'  => array(),

		// @todo
		'success'     => esc_html__( 'The repair was completed successfully', 'bbpress' ),
		'failure'     => esc_html__( 'The repair was not successful',         'bbpress' )
	), 'register_repair_tool' );

	// Bail if missing required values
	if ( empty( $r['id'] ) || empty( $r['priority'] ) || empty( $r['title'] ) || empty( $r['callback'] ) ) {
		return;
	}

	// Add tool to the registered tools array
	bbp_admin()->tools[ $r['id'] ] = array(
		'type'        => $r['type'],
		'title'       => $r['title'],
		'description' => $r['description'],
		'priority'    => $r['priority'],
		'callback'    => $r['callback'],
		'overhead'    => $r['overhead'],
		'components'  => $r['components'],
		'version'     => $r['version'],

		// @todo
		'success'     => $r['success'],
		'failure'     => $r['failure'],
	);
}

/**
 * Register the default repair tools
 *
 * @since 2.6.0 bbPress (r5885)
 */
function bbp_register_default_repair_tools() {

	// Topic meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-topic-meta',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate parent topic for each reply', 'bbpress' ),
		'description' => esc_html__( 'Run this if replies appear in the wrong topics.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_meta',
		'priority'    => 5,
		'overhead'    => 'low',
		'components'  => array( bbp_get_reply_post_type() )
	) );

	// Forum meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-forum-meta',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate parent forum for each topic and reply', 'bbpress' ),
		'description' => esc_html__( 'Run this if topics or replies appear in the wrong forums.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_meta',
		'priority'    => 10,
		'overhead'    => 'low',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Forum visibility
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-forum-visibility',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate private and hidden forums', 'bbpress' ),
		'description' => esc_html__( 'Run this if non-public forums are publicly visible.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_visibility',
		'priority'    => 15,
		'overhead'    => 'low',
		'components'  => array( bbp_get_forum_post_type() )
	) );

	// Sync all topics in all forums
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-topics-forums',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate last activity in each topic and forum', 'bbpress' ),
		'description' => esc_html__( 'Run this if freshness appears incorrectly.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_freshness',
		'priority'    => 20,
		'overhead'    => 'high',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Sync all sticky topics in all forums
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-topics-sticky',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate sticky relationship of each topic', 'bbpress' ),
		'description' => esc_html__( 'Run this if sticky topics appear incorrectly.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_sticky',
		'priority'    => 25,
		'overhead'    => 'low',
		'components'  => array( bbp_get_topic_post_type() )
	) );

	// Sync all hierarchical reply positions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-reply-positions',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate position of each reply in each topic', 'bbpress' ),
		'description' => esc_html__( 'Run this if replies appear in the wrong order.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_reply_menu_order',
		'priority'    => 30,
		'overhead'    => 'high',
		'components'  => array( bbp_get_reply_post_type() )
	) );

	// Sync all topic engagements for all users
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-engagements',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recalculate engagements in each topic for each user', 'bbpress' ),
		'description' => esc_html__( 'Run this if voices appear incorrectly.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_voice_count',
		'priority'    => 35,
		'overhead'    => 'high',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Update closed topic counts
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-closed-topics',
		'type'        => 'repair',
		'title'       => esc_html__( 'Repair closed topic statuses', 'bbpress' ),
		'description' => esc_html__( 'Run this if closed topics appear incorrectly.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_closed_topics',
		'priority'    => 40,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type() )
	) );

	// Count topics
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-topics',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount topics in each forum', 'bbpress' ),
		'description' => esc_html__( 'Run this if the number of topics in any forums are incorrect.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_topic_count',
		'priority'    => 45,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_topic_post_type() )
	) );

	// Count topic tags
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-tags',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount topics in each topic-tag', 'bbpress' ),
		'description' => esc_html__( 'Run this if the number of topics in any topic-tags are incorrect.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_tag_count',
		'priority'    => 50,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_topic_tag_tax_id() )
	) );

	// Count forum replies
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-replies',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount replies in each forum', 'bbpress' ),
		'description' => esc_html__( 'Run this if the number of replies in any forums are incorrect.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_reply_count',
		'priority'    => 55,
		'overhead'    => 'high',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_reply_post_type() )
	) );

	// Count non-published replies to each forum
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-hidden-replies',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount pending, spammed, and trashed replies in each forum', 'bbpress' ),
		'description' => esc_html__( 'Run this if non-public replies display incorrectly in forums.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_hidden_reply_count',
		'priority'    => 60,
		'overhead'    => 'high',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_reply_post_type() )
	) );

	// Count topic replies
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-replies',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount replies in each topic', 'bbpress' ),
		'description' => esc_html__( 'Run this if the number of replies in any topics are incorrect.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_reply_count',
		'priority'    => 65,
		'overhead'    => 'high',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Count non-published replies to each topic
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-hidden-replies',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount pending, spammed, and trashed replies in each topic', 'bbpress' ),
		'description' => esc_html__( 'Run this if non-public replies display incorrectly in topics.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_hidden_reply_count',
		'priority'    => 70,
		'overhead'    => 'high',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Recount topics for each user
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topics',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount topics for each user', 'bbpress' ),
		'description' => esc_html__( 'Run this to get fresh topic counts for all users.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_topic_count',
		'priority'    => 75,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Recount topics for each user
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-replies',
		'type'        => 'repair',
		'title'       => esc_html__( 'Recount replies for each user', 'bbpress' ),
		'description' => esc_html__( 'Run this to get fresh reply counts for all users.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_reply_count',
		'priority'    => 80,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_reply_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished topics from user favorites
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites',
		'type'        => 'repair',
		'title'       => esc_html__( 'Remove unpublished topics from user favorites', 'bbpress' ),
		'description' => esc_html__( 'Run this to remove trashed or deleted topics from all user favorites.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_favorites',
		'priority'    => 85,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished topics from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions',
		'type'        => 'repair',
		'title'       => esc_html__( 'Remove unpublished topics from user subscriptions', 'bbpress' ),
		'description' => esc_html__( 'Run this to remove trashed or deleted topics from all user subscriptions.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_topic_subscriptions',
		'priority'    => 90,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished forums from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions',
		'type'        => 'repair',
		'title'       => esc_html__( 'Remove unpublished forums from user subscriptions', 'bbpress' ),
		'description' => esc_html__( 'Run this to remove trashed or deleted forums from all user subscriptions.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_forum_subscriptions',
		'priority'    => 95,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished forums from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-role-map',
		'type'        => 'repair',
		'title'       => esc_html__( 'Remap all users to default forum roles', 'bbpress' ),
		'description' => esc_html__( 'Run this if users have issues accessing the forums.', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_roles',
		'priority'    => 100,
		'overhead'    => 'low',
		'components'  => array( bbp_get_user_rewrite_id() )
	) );

	// Migrate topic engagements to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-engagements-move',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Upgrade user topic engagements', 'bbpress' ),
		'description' => esc_html__( 'Copies engagements from user meta to topic meta.', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_engagements',
		'priority'    => 105,
		'version'     => '2.6.0',
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_engagements_rewrite_id() )
	) );

	// Migrate favorites from user-meta to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites-move',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Upgrade user topic favorites', 'bbpress' ),
		'description' => esc_html__( 'Copies favorites from user meta to topic meta.', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_favorites',
		'priority'    => 110,
		'version'     => '2.6.0',
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_favorites_rewrite_id() )
	) );

	// Migrate topic subscriptions from user-meta to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions-move',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Upgrade user topic subscriptions', 'bbpress' ),
		'description' => esc_html__( 'Copies topic subscriptions from user meta to topic meta.', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_topic_subscriptions',
		'priority'    => 115,
		'version'     => '2.6.0',
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Migrate forum subscriptions from user-meta to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions-move',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Upgrade user forum subscriptions', 'bbpress' ),
		'description' => esc_html__( 'Copies forum subscriptions from user meta to forum meta.', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_forum_subscriptions',
		'priority'    => 120,
		'version'     => '2.6.0',
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Remove favorites from user-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites-delete',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Remove favorites from user-meta', 'bbpress' ),
		'description' => esc_html__( 'Run this to delete old data (after confirming successful favorites upgrade)', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_remove_favorites_from_usermeta',
		'priority'    => 125,
		'version'     => '2.6.1',
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_favorites_rewrite_id() )
	) );

	// Remove topic subscriptions from user-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions-delete',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Remove topic subscriptions from user-meta', 'bbpress' ),
		'description' => esc_html__( 'Run this to delete old data (after confirming successful topic subscriptions upgrade)', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta',
		'priority'    => 130,
		'version'     => '2.6.1',
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Remove forum subscriptions from user-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions-delete',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Remove forum subscriptions from user-meta', 'bbpress' ),
		'description' => esc_html__( 'Run this to delete old data (after confirming successful forum subscriptions upgrade)', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta',
		'priority'    => 135,
		'version'     => '2.6.1',
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Sync all BuddyPress group forum relationships
	bbp_register_repair_tool( array(
		'id'          => 'bbp-group-forums',
		'type'        => 'upgrade',
		'title'       => esc_html__( 'Upgrade BuddyPress Group Forum relationships', 'bbpress' ),
		'description' => esc_html__( 'Run this if you just upgraded BuddyPress Forums from Legacy.', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_group_forum_relationship',
		'priority'    => 140,
		'version'     => esc_html__( 'Any', 'bbpress' ),
		'overhead'    => 'low',
		'components'  => array( bbp_get_forum_post_type() )
	) );
}

/**
 * Output the tabs in the admin area
 *
 * @since 2.1.0 bbPress (r3872)
 *
 * @param string $active_tab Name of the tab that is active
 */
function bbp_tools_admin_tabs( $active_tab = '' ) {
	echo bbp_get_tools_admin_tabs( $active_tab );
}

	/**
	 * Output the tabs in the admin area
	 *
	 * @since 2.1.0 bbPress (r3872)
	 *
	 * @param string $active_tab Name of the tab that is active
	 */
	function bbp_get_tools_admin_tabs( $active_tab = '' ) {

		// Declare local variables
		$tabs_html    = '';
		$idle_class   = 'nav-tab';
		$active_class = 'nav-tab nav-tab-active';

		// Setup core admin tabs
		$tabs = bbp_get_tools_admin_pages();

		// Loop through tabs and build navigation
		foreach ( $tabs as $tab ) {

			// Skip if user cannot visit page
			if ( ! current_user_can( $tab['cap'] ) ) {
				continue;
			}

			// Setup tab HTML
			$is_current = (bool) ( $tab['page'] === $active_tab );
			$tab_class  = $is_current ? $active_class : $idle_class;
			$tab_url    = add_query_arg( array( 'page' => $tab['page'] ), admin_url( 'tools.php' ) );

			// Tab name is not escaped - may contain HTML
			$tabs_html .= '<a href="' . esc_url( $tab_url ) . '" class="' . esc_attr( $tab_class ) . '">' . $tab['name'] . '</a>';
		}

		// Output the tabs
		return $tabs_html;
	}

/**
 * Return possible tools pages
 *
 * @since 2.6.0 bbPress (r6273)
 *
 * @return array
 */
function bbp_get_tools_admin_pages() {

	// Get tools URL one time & use in each tab
	$tools_url = admin_url( 'tools.php' );

	// Filter & return
	return (array) apply_filters( 'bbp_tools_admin_tabs', array(
		array(
			'page' => 'bbp-repair',
			'func' => 'bbp_admin_repair_page',
			'cap'  => 'bbp_tools_repair_page',
			'name' => bbp_maybe_append_pending_upgrade_count( esc_html__( 'Repair Forums', 'bbpress' ), 'repair' ),

			// Deprecated 2.6.0
			'href' => add_query_arg( array( 'page' => 'bbp-repair' ), $tools_url )
		),
		array(
			'page' => 'bbp-upgrade',
			'func' => 'bbp_admin_upgrade_page',
			'cap'  => 'bbp_tools_upgrade_page',
			'name' => bbp_maybe_append_pending_upgrade_count( esc_html__( 'Upgrade Forums', 'bbpress' ), 'upgrade' ),

			// Deprecated 2.6.0
			'href' => add_query_arg( array( 'page' => 'bbp-upgrade' ), $tools_url )
		),
		array(
			'page' => 'bbp-converter',
			'func' => 'bbp_converter_settings_page',
			'cap'  => 'bbp_tools_import_page',
			'name' => esc_html__( 'Import Forums', 'bbpress' ),

			// Deprecated 2.6.0
			'href' => add_query_arg( array( 'page' => 'bbp-converter' ), $tools_url )
		),
		array(
			'page' => 'bbp-reset',
			'func' => 'bbp_admin_reset_page',
			'cap'  => 'bbp_tools_reset_page',
			'name' => esc_html__( 'Reset Forums', 'bbpress' ),

			// Deprecated 2.6.0
			'href' => add_query_arg( array( 'page' => 'bbp-reset' ), $tools_url )
		)
	) );
}
