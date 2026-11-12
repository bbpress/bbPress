<?php

/**
 * bbPress Admin Batch Worker.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Shared state and checkpoint helpers for resumable admin workers.
 *
 * @since 2.7.0 bbPress (r8338)
 * @access private
 */
abstract class BBP_Admin_Batch_Worker {

	/**
	 * Generic tool callback failure status.
	 *
	 * Zero is success and one is reserved for a successful no-op, so every
	 * worker failure uses the first shared failure value.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @var int
	 */
	const RESULT_FAILED = 2;

	/**
	 * WordPress database connection.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8338) Moved to the shared batch worker.
	 *
	 * @var wpdb
	 */
	protected $db;

	/**
	 * Registered repair or upgrade tool ID.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 * @since 2.7.0 bbPress (r8338) Moved to the shared batch worker.
	 *
	 * @var string
	 */
	protected $tool_id;

	/**
	 * Current worker checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8338) Moved to the shared batch worker.
	 *
	 * @var array|false
	 */
	protected $state;

	/**
	 * Whether this run only previews changes.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8338) Moved to the shared batch worker.
	 *
	 * @var bool
	 */
	protected $dry_run;

	/**
	 * Whether this is one request in a browser Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 * @since 2.7.0 bbPress (r8338) Moved to the shared batch worker.
	 *
	 * @var bool
	 */
	protected $async = false;

	/**
	 * First semantic failure reason reported by the worker.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @var string
	 */
	protected $error = '';

	/**
	 * Record the first failure encountered by this worker.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @param string $reason Semantic failure reason.
	 * @return bool Whether this call recorded the failure.
	 */
	protected function fail( $reason ) {
		if ( ! $this->error && $reason ) {
			$this->error = (string) $reason;
			return true;
		}
		return false;
	}

	/**
	 * Return the normalized callback result code.
	 *
	 * Worker-specific numeric failures are intentionally not part of the tools
	 * API. Semantic failure reasons are returned separately for diagnostics and
	 * user-facing messages.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @return int Zero on success, or two on failure.
	 */
	protected function result_code() {
		return $this->error ? self::RESULT_FAILED : 0;
	}

	/**
	 * Assemble a worker result with the shared failure contract.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @param array $result   Worker-specific result fields.
	 * @param bool  $complete Whether the operation completed.
	 * @return array Worker result with normalized status fields.
	 */
	protected function result( $result, $complete ) {
		return array_merge(
			$result,
			array(
				'code'     => $this->result_code(),
				'error'    => $this->error,
				'complete' => ! $this->error && $complete,
			)
		);
	}

	/**
	 * Save or delete the current worker checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8338) Moved to the shared batch worker.
	 *
	 * @param bool $complete Whether the operation completed successfully.
	 * @return bool Whether the checkpoint operation succeeded.
	 */
	protected function save( $complete = false ) {
		return bbp_admin_repair_tool_save_checkpoint( $this->tool_id, $this->state, $complete, $this->dry_run );
	}

	/**
	 * Create a new worker checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @return array|false New checkpoint, or false after a failure.
	 */
	abstract protected function new_state();
}
