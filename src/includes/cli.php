<?php

/**
 * Register bbPress WP-CLI commands.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/cli/class-bbp-cli-command.php';
require_once __DIR__ . '/admin/classes/class-bbp-tools-cli-command.php';
WP_CLI::add_command( 'bbp tool', 'BBP_Tools_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-forum-cli-command.php';
WP_CLI::add_command( 'bbp forum', 'BBP_Forum_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-topic-cli-command.php';
WP_CLI::add_command( 'bbp topic', 'BBP_Topic_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-reply-cli-command.php';
WP_CLI::add_command( 'bbp reply', 'BBP_Reply_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-favorite-cli-command.php';
WP_CLI::add_command( 'bbp favorite', 'BBP_Favorite_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-engagement-cli-command.php';
WP_CLI::add_command( 'bbp engagement', 'BBP_Engagement_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-subscription-cli-command.php';
WP_CLI::add_command( 'bbp subscription', 'BBP_Subscription_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-moderator-cli-command.php';
WP_CLI::add_command( 'bbp moderator', 'BBP_Moderator_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-user-cli-command.php';
WP_CLI::add_command( 'bbp user', 'BBP_User_CLI_Command' );

require_once __DIR__ . '/cli/class-bbp-reset-cli-command.php';
WP_CLI::add_command( 'bbp reset', 'BBP_Reset_CLI_Command' );
