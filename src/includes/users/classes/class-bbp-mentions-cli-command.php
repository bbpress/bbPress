<?php

/**
 * bbPress Mention WP-CLI Commands.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Index historical mentions without sending email.
 *
 * @since 2.7.0 bbPress (r8264)
 */
class BBP_Mentions_CLI_Command {

	/**
	 * Backfill mentions in resumable post-ID batches.
	 *
	 * Shares progress with the forum upgrade and repair tools. Run the same command
	 * again after interruption. Use --url to select one site on multisite.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<number>]
	 * : Maximum posts examined per batch, from 1 to 1000.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--max-batches=<number>]
	 * : Stop after this many batches. Zero runs until complete.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--restart]
	 * : Start again at post ID zero without clearing notification history.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbp mentions backfill --batch-size=100 --max-batches=10
	 *     wp bbp mentions backfill
	 *     wp bbp mentions backfill --restart
	 *
	 * @since 2.7.0 bbPress (r8264)
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function backfill( $args, $assoc_args ) {
		$batch_size  = isset( $assoc_args['batch-size'] ) ? $assoc_args['batch-size'] : 100;
		$max_batches = isset( $assoc_args['max-batches'] ) ? $assoc_args['max-batches'] : 0;
		$restart     = isset( $assoc_args['restart'] );
		if ( ! ctype_digit( (string) $batch_size ) || ! ctype_digit( (string) $max_batches ) ) {
			WP_CLI::error( __( 'Batch sizes and limits must be whole numbers.', 'bbpress' ) );
		}
		$flush_runtime = function_exists( 'wp_cache_flush_runtime' ) && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_runtime' );
		if ( wp_using_ext_object_cache() && ! $flush_runtime && ( 1 !== (int) $max_batches ) ) {
			WP_CLI::error( __( 'This object cache cannot clear process-local data. Use --max-batches=1 and repeat the command to keep memory bounded.', 'bbpress' ) );
		}

		for ( $batch = 0; ; ++$batch ) {
			$state = bbp_backfill_mentions( (int) $batch_size, $restart && ( 0 === $batch ) );
			if ( is_wp_error( $state ) ) {
				WP_CLI::error( $state );
			}

			$message = sprintf(
				/* translators: 1: Last post ID examined, 2: Upper post ID, 3: Number of topics and replies processed */
				__( 'Post ID %1$d of %2$d; %3$d topics and replies processed.', 'bbpress' ),
				$state['cursor'],
				$state['upper'],
				$state['processed']
			);
			WP_CLI::log( $message );

			// Bound process-local cache growth without flushing shared caches.
			if ( $flush_runtime ) {
				wp_cache_flush_runtime();
			} elseif ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}

			if ( $state['complete'] ) {
				WP_CLI::success( __( 'Mention backfill complete. No historical mention emails were queued.', 'bbpress' ) );
				break;
			}
			if ( $max_batches && ( ( $batch + 1 ) >= (int) $max_batches ) ) {
				WP_CLI::success( __( 'Mention backfill progress saved. Run the same command to resume.', 'bbpress' ) );
				break;
			}
		}
	}

	/**
	 * Show this site's saved mention backfill progress as JSON.
	 *
	 * @since 2.7.0 bbPress (r8264)
	 */
	public function status() {
		$state = bbp_get_mention_backfill_status();
		unset( $state['token'] );
		WP_CLI::line( wp_json_encode( $state ) );
	}
}

WP_CLI::add_command( 'bbp mentions', 'BBP_Mentions_CLI_Command' );
