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
		<h3 class="title"><?php _e( 'Forums', 'bbpress' ) ?></h3>
		<p><?php esc_html_e( 'bbPress provides the following tools to help you manage your forums:', 'bbpress' ); ?></p>

		<?php

		// Loop through tools and create links
		foreach ( $tools as $tool ) {

			// Skip if user cannot see this page
			if ( ! current_user_can( $tool['cap'] ) ) {
				continue;
			}

			// Add link to array
			$links[] = sprintf( '<a href="%s">%s</a>', get_admin_url( '', add_query_arg( array( 'page' => $tool['page'] ), 'tools.php' ) ), $tool['name'] );
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
		'description' => '',
		'callback'    => '',
		'priority'    => 0,
		'overhead'    => 'low',
		'components'  => array(),

		// @todo
		'success'     => esc_html__( 'The repair was completed successfully', 'bbpress' ),
		'failure'     => esc_html__( 'The repair was not successful',         'bbpress' )
	), 'register_repair_tool' );

	// Bail if missing required values
	if ( empty( $r['id'] ) || empty( $r['priority'] ) || empty( $r['description'] ) || empty( $r['callback'] ) ) {
		return;
	}

	// Add tool to the registered tools array
	bbpress()->admin->tools[ $r['id'] ] = array(
		'type'        => $r['type'],
		'description' => $r['description'],
		'priority'    => $r['priority'],
		'callback'    => $r['callback'],
		'overhead'    => $r['overhead'],
		'components'  => $r['components'],

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
		'description' => __( 'Recalculate parent topic for each reply', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_meta',
		'priority'    => 5,
		'overhead'    => 'low',
		'components'  => array( bbp_get_reply_post_type() )
	) );

	// Forum meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-forum-meta',
		'type'        => 'repair',
		'description' => __( 'Recalculate parent forum for each topic and reply', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_meta',
		'priority'    => 10,
		'overhead'    => 'low',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Forum visibility
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-forum-visibility',
		'type'        => 'repair',
		'description' => __( 'Recalculate private and hidden forums', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_visibility',
		'priority'    => 15,
		'overhead'    => 'low',
		'components'  => array( bbp_get_forum_post_type() )
	) );

	// Sync all topics in all forums
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-topics-forums',
		'type'        => 'repair',
		'description' => __( 'Recalculate last activity in each topic and forum', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_freshness',
		'priority'    => 20,
		'overhead'    => 'high',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Sync all sticky topics in all forums
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-topics-sticky',
		'type'        => 'repair',
		'description' => __( 'Recalculate sticky relationship of each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_sticky',
		'priority'    => 25,
		'overhead'    => 'low',
		'components'  => array( bbp_get_topic_post_type() )
	) );

	// Sync all hierarchical reply positions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-reply-positions',
		'type'        => 'repair',
		'description' => __( 'Recalculate the position of each reply', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_reply_menu_order',
		'priority'    => 30,
		'overhead'    => 'high',
		'components'  => array( bbp_get_reply_post_type() )
	) );

	// Sync all BuddyPress group forum relationships
	bbp_register_repair_tool( array(
		'id'          => 'bbp-group-forums',
		'type'        => 'repair',
		'description' => __( 'Repair BuddyPress Group Forum relationships', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_group_forum_relationship',
		'priority'    => 35,
		'overhead'    => 'low',
		'components'  => array( bbp_get_forum_post_type() )
	) );

	// Update closed topic counts
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-closed-topics',
		'type'        => 'repair',
		'description' => __( 'Repair closed topics', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_closed_topics',
		'priority'    => 40,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type() )
	) );

	// Count topics
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-topics',
		'type'        => 'repair',
		'description' => __( 'Recount topics in each forum', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_topic_count',
		'priority'    => 45,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_topic_post_type() )
	) );

	// Count topic tags
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-tags',
		'type'        => 'repair',
		'description' => __( 'Recount topics in each topic-tag', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_tag_count',
		'priority'    => 50,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_topic_tag_tax_id() )
	) );

	// Count forum replies
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-replies',
		'type'        => 'repair',
		'description' => __( 'Recount replies in each forum', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_reply_count',
		'priority'    => 55,
		'overhead'    => 'high',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_reply_post_type() )
	) );

	// Count topic replies
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-replies',
		'type'        => 'repair',
		'description' => __( 'Recount replies in each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_reply_count',
		'priority'    => 60,
		'overhead'    => 'high',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Count topic voices
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-voices',
		'type'        => 'repair',
		'description' => __( 'Recount voices in each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_voice_count',
		'priority'    => 65,
		'overhead'    => 'high',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Count non-published replies to each topic
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-hidden-replies',
		'type'        => 'repair',
		'description' => __( 'Recount pending, spammed, and trashed replies in each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_hidden_reply_count',
		'priority'    => 70,
		'overhead'    => 'high',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Recount topics for each user
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topics',
		'type'        => 'repair',
		'description' => __( 'Recount topics for each user', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_topic_count',
		'priority'    => 75,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Recount topics for each user
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-replies',
		'type'        => 'repair',
		'description' => __( 'Recount replies for each user', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_reply_count',
		'priority'    => 80,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_reply_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished topics from user favorites
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites',
		'type'        => 'repair',
		'description' => __( 'Remove unpublished topics from user favorites', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_favorites',
		'priority'    => 85,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished topics from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions',
		'type'        => 'repair',
		'description' => __( 'Remove unpublished topics from user subscriptions', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_topic_subscriptions',
		'priority'    => 90,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished forums from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions',
		'type'        => 'repair',
		'description' => __( 'Remove unpublished forums from user subscriptions', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_forum_subscriptions',
		'priority'    => 95,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_forum_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished forums from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-role-map',
		'type'        => 'repair',
		'description' => __( 'Remap existing users to default forum roles', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_roles',
		'priority'    => 100,
		'overhead'    => 'low',
		'components'  => array( bbp_get_user_rewrite_id() )
	) );

	// Migrate favorites from user-meta to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites-move',
		'type'        => 'upgrade',
		'description' => __( 'Upgrade user favorites', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_favorites',
		'priority'    => 105,
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_favorites_rewrite_id() )
	) );

	// Migrate topic subscriptions from user-meta to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions-move',
		'type'        => 'upgrade',
		'description' => __( 'Upgrade user topic subscriptions', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_topic_subscriptions',
		'priority'    => 110,
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Migrate forum subscriptions from user-meta to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions-move',
		'type'        => 'upgrade',
		'description' => __( 'Upgrade user forum subscriptions', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_forum_subscriptions',
		'priority'    => 115,
		'overhead'    => 'high',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Migrate topic engagements to post-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-engagements-move',
		'type'        => 'upgrade',
		'description' => __( 'Upgrade topic engagements', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_user_engagements',
		'priority'    => 120,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_engagements_rewrite_id() )
	) );

	// Remove favorites from user-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites-delete',
		'type'        => 'upgrade',
		'description' => __( 'Remove favorites from user-meta', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_remove_favorites_from_usermeta',
		'priority'    => 125,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_favorites_rewrite_id() )
	) );

	// Remove topic subscriptions from user-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions-delete',
		'type'        => 'upgrade',
		'description' => __( 'Remove topic subscriptions from user-meta', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta',
		'priority'    => 130,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
	) );

	// Remove forum subscriptions from user-meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions-delete',
		'type'        => 'upgrade',
		'description' => __( 'Remove forum subscriptions from user-meta', 'bbpress' ),
		'callback'    => 'bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta',
		'priority'    => 135,
		'overhead'    => 'medium',
		'components'  => array( bbp_get_user_rewrite_id(), bbp_get_user_subscriptions_rewrite_id() )
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
			$is_current = (bool) ( $tab['name'] == $active_tab );
			$tab_class  = $is_current ? $active_class : $idle_class;
			$tab_url    = get_admin_url( '', add_query_arg( array( 'page' => $tab['page'] ), 'tools.php' ) );
			$tabs_html .= '<a href="' . esc_url( $tab_url ) . '" class="' . esc_attr( $tab_class ) . '">' . esc_html( $tab['name'] ) . '</a>';
		}

		// Output the tabs
		return $tabs_html;
	}

/**
 * Return possible tools pages
 *
 * @since 2.6.0 (r6273)
 *
 * @return array
 */
function bbp_get_tools_admin_pages() {

	// Filter & return
	return (array) apply_filters( 'bbp_tools_admin_tabs', array(
		array(
			'page' => 'bbp-repair',
			'func' => 'bbp_admin_repair_page',
			'cap'  => 'bbp_tools_repair_page',
			'name' => esc_html__( 'Repair Forums', 'bbpress' ),

			// Deprecated 2.6.0
			'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-repair'    ), 'tools.php' ) )
		),
		array(
			'page' => 'bbp-upgrade',
			'func' => 'bbp_admin_upgrade_page',
			'cap'  => 'bbp_tools_upgrade_page',
			'name' => esc_html__( 'Upgrade Forums', 'bbpress' ),

			// Deprecated 2.6.0
			'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-upgrade' ), 'tools.php' ) )
		),
		array(
			'page' => 'bbp-converter',
			'func' => 'bbp_converter_settings_page',
			'cap'  => 'bbp_tools_import_page',
			'name' => esc_html__( 'Import Forums', 'bbpress' ),

			// Deprecated 2.6.0
			'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-converter' ), 'tools.php' ) )
		),
		array(
			'page' => 'bbp-reset',
			'func' => 'bbp_admin_reset_page',
			'cap'  => 'bbp_tools_reset_page',
			'name' => esc_html__( 'Reset Forums', 'bbpress' ),

			// Deprecated 2.6.0
			'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-reset'     ), 'tools.php' ) )
		)
	) );
}
