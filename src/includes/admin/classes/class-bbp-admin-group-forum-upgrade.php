<?php

/**
 * bbPress Admin Group Forum Upgrade.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Stage and validate converted relationships before publishing any mappings.
 *
 * Each forum and group claim is a separate, non-autoloaded temporary option.
 * Checkpoints contain only cursors, not the complete relationship graph.
 * Serialized metadata remains one native field, including modern group lists.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 */
class BBP_Admin_Group_Forum_Upgrade extends BBP_Admin_Batch_Worker {

	/**
	 * Saved checkpoint context version.
	 *
	 * @since 2.7.0 bbPress (r8338)
	 *
	 * @var int
	 */
	const CONTEXT_VERSION = 2;

	/**
	 * BuddyPress groups table name.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var string
	 */
	private $groups_table;

	/**
	 * BuddyPress group metadata table name.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var string
	 */
	private $groupmeta_table;

	/**
	 * Registered forum post type.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var string
	 */
	private $forum_post_type;

	/**
	 * In-memory migration plans created by a synchronous Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array
	 */
	private $preview_plans = array();

	/**
	 * Option names already inspected during cleanup.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array
	 */
	private $inspected_options = array();

	/**
	 * Whether a legacy value maps ambiguously to multiple objects.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var bool
	 */
	private $mapping_error = false;

	/**
	 * Estimated normal Run batches accumulated by a browser Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var int
	 */
	private $batches = 0;

	/**
	 * Aggregate migration changes keyed by operation type.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array
	 */
	private $counts = array(
		'groups'   => 0,
		'forums'   => 0,
		'statuses' => 0,
		'root'     => 0,
		'rename'   => 0,
		'roles'    => array(),
	);

	/**
	 * Set up the group-forum migration worker.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param bool $dry_run Whether to inspect the migration without changing data.
	 */
	public function __construct( $dry_run = false ) {
		$this->db              = bbp_db();
		$this->tool_id         = 'bbp-group-forums';
		$this->forum_post_type = bbp_get_forum_post_type();
		$this->dry_run         = $dry_run;
		$this->async           = $dry_run && bbp_admin_repair_tool_is_async_dry_run();
		$prefix                = apply_filters( 'bp_core_get_table_prefix', $this->db->base_prefix );
		$this->groups_table    = $prefix . 'bp_groups';
		$this->groupmeta_table = $prefix . 'bp_groups_groupmeta';
	}

	/**
	 * Run one bounded group-forum migration request.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 * @since 2.7.0 bbPress (r8331) Support complete browser Dry Runs across requests.
	 *
	 * @return array Migration result, continuation state, and aggregate totals.
	 */
	public function run() {
		list( $read, $saved ) = bbp_admin_repair_tool_get_checkpoint( $this->tool_id, $this->dry_run );
		$complete  = false;
		$restarted = false !== $saved && ! $this->valid_state( $saved );
		if ( ! $read ) {
			$this->fail( 'progress' );
		} elseif ( false === $saved || $restarted ) {
			$this->state = $this->new_state();
			// Publish the generation before prepare can clean up an abandoned run.
			if ( ! $this->error && ! $this->save() ) {
				$this->fail( 'progress' );
			}
		} else {
			$this->state = $saved;
		}
		if ( $this->async && $this->state ) {
			$preview_totals = array_merge( $this->counts, array( 'batches' => 0 ) );
			$data           = bbp_admin_repair_tool_restore_preview( $this->state, $preview_totals, array_keys( $preview_totals ) );
			foreach ( array_keys( $this->counts ) as $field ) {
				$this->counts[ $field ] = $preview_totals[ $field ];
			}
			$this->batches       = $preview_totals['batches'];
			$this->mapping_error = ! empty( $data['mapping_error'] );
		}
		$size = bbp_admin_repair_tool_batch_size( $this->tool_id );
		$work = 0;
		while ( ! $this->error && ! $complete && ( bbp_admin_repair_tool_continue_preview( $this->dry_run ) || $work < $size ) ) {
			$remaining = bbp_admin_repair_tool_continue_preview( $this->dry_run ) ? $size - ( $work % $size ) : $size - $work;
			$work     += $this->step( $remaining, $complete );
		}
		if ( $this->async && $work ) {
			++$this->batches;
		}
		if ( ( ! $this->dry_run || $this->async ) && $this->state && 'progress' !== $this->error ) {
			if ( in_array( $this->error, array( 'ambiguous-mapping', 'verify' ), true ) ) {
				// Rebuild invalid source plans. Previously written forum markers
				// disambiguate partial upgrades when legacy and current IDs overlap.
				$this->state = $this->new_state();
			} elseif ( $this->async ) {
				$preview_totals = array_merge( $this->counts, array( 'batches' => $this->batches ) );
				bbp_admin_repair_tool_store_preview( $this->state, $preview_totals, array_keys( $preview_totals ), array( 'mapping_error' => (bool) $this->mapping_error ) );
			}
			if ( $this->state && ! $this->save( ! $this->error && $complete ) ) {
				$this->fail( 'progress' );
			}
		}
		return $this->result(
			array_merge(
				$this->counts,
				array(
					'mapping_error' => $this->mapping_error,
					'restarted'     => $restarted,
					'no_data'       => $complete && empty( $this->state['legacy_forum_count'] ),
					'phase'         => isset( $this->state['phase'] ) ? $this->state['phase'] : 'prepare',
					'work'          => $work,
					'batches'       => $this->async ? $this->batches : max( 1, (int) ceil( $work / $size ) ),
				)
			),
			$complete
		);
	}

	/**
	 * Validate a saved group-forum migration checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $state Saved checkpoint.
	 * @return bool Whether the checkpoint can be resumed.
	 */
	private function valid_state( $state ) {
		$valid = bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'legacy_forum_count', 'group_marker_offset' ) )
			&& isset( $state['phase'], $state['token'], $state['context'] )
			&& in_array( $state['phase'], array( 'prepare', 'index', 'plan', 'verify', 'apply', 'root', 'cleanup' ), true )
			&& is_string( $state['token'] ) && strlen( $state['token'] ) === 32 && ctype_xdigit( $state['token'] )
			&& array( $this->forum_post_type, $this->groups_table, $this->groupmeta_table, self::CONTEXT_VERSION ) === $state['context']
			&& ( $state['legacy_forum_count'] > 0 || ! in_array( $state['phase'], array( 'plan', 'verify', 'apply', 'root' ), true ) )
			&& $state['legacy_forum_count'] <= $state['upper_id'] && ( 'index' === $state['phase'] || 0 === $state['group_marker_offset'] );
		if ( ! $valid || ! isset( $state['_preview_state'] ) ) {
			return $valid;
		}
		if ( ! is_array( $state['_preview_state'] ) || ! isset( $state['_preview_state']['totals'], $state['_preview_state']['data'] )
			|| ! is_array( $state['_preview_state']['totals'] ) || ! is_array( $state['_preview_state']['data'] ) ) {
			return false;
		}

		$totals = $state['_preview_state']['totals'];
		$data   = $state['_preview_state']['data'];
		foreach ( array( 'groups', 'forums', 'statuses', 'root', 'rename', 'batches' ) as $field ) {
			if ( ! isset( $totals[ $field ] ) || ! is_int( $totals[ $field ] ) || $totals[ $field ] < 0 ) {
				return false;
			}
		}
		return isset( $totals['roles'], $data['mapping_error'] )
			&& is_array( $totals['roles'] )
			&& count( $totals['roles'] ) === count( array_filter( $totals['roles'], 'is_string' ) )
			&& ! array_diff( $totals['roles'], array( 'member', 'inactive', 'blocked', 'moderator', 'keymaster' ) )
			&& is_bool( $data['mapping_error'] );
	}

	/**
	 * Create a new group-forum migration checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @return array|false New checkpoint, or false after a database failure.
	 */
	protected function new_state() {
		$upper = (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type = %s", $this->forum_post_type ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'forums-read' );
			return false;
		}
		return array(
			'cursor'              => 0,
			'upper_id'            => $upper,
			'legacy_forum_count'  => 0,
			'group_marker_offset' => 0,
			'phase'               => 'prepare',
			'token'               => str_replace( '-', '', wp_generate_uuid4() ),
			'context'             => array( $this->forum_post_type, $this->groups_table, $this->groupmeta_table, self::CONTEXT_VERSION ),
		);
	}

	/**
	 * Return the option-name prefix for temporary migration plans.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @return string Migration plan prefix.
	 */
	private function prefix() {
		return bbp_admin_repair_tool_batch_option_name( $this->tool_id ) . '_groups_';
	}

	/**
	 * Return the option name for one migration plan record.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param string $key Plan record key.
	 * @return string Migration plan option name.
	 */
	private function option( $key ) {
		return $this->prefix() . $this->state['token'] . '_' . $key;
	}

	/**
	 * Read one temporary migration plan record.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param string $key Plan record key.
	 * @return mixed Stored plan value, or false when no value exists.
	 */
	private function read_plan( $key ) {
		$name = $this->option( $key );
		if ( array_key_exists( $name, $this->preview_plans ) ) {
			return $this->preview_plans[ $name ];
		}
		list( $read, $value ) = bbp_admin_repair_get_option( $name );
		if ( ! $read ) {
			$this->fail( 'progress' );
		}
		return $value;
	}

	/**
	 * Write one temporary migration plan record.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param string $key   Plan record key.
	 * @param mixed  $value Plan value.
	 */
	private function write_plan( $key, $value ) {
		$name = $this->option( $key );
		if ( $this->dry_run && ! $this->async ) {
			$this->preview_plans[ $name ] = $value;
		} elseif ( ! bbp_admin_repair_tool_save_option( $name, $value ) ) {
			$this->fail( 'progress' );
		}
	}

	/**
	 * Advance the checkpoint to another migration phase.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param string $phase Next migration phase.
	 */
	private function next_phase( $phase ) {
		$this->state['phase']  = $phase;
		$this->state['cursor'] = $this->state['group_marker_offset'] = 0;
	}

	/**
	 * Validate ID-list members before using WordPress's normal list parser.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param mixed $value Possible ID list.
	 * @return array|false Normalized IDs, or false when a member is invalid.
	 */
	private function parse_id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = wp_parse_list( $value );
		} elseif ( is_int( $value ) ) {
			$value = array( $value );
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( ( ! is_int( $item ) || $item < 0 ) && ( ! is_string( $item ) || ! ctype_digit( $item ) ) ) {
				return false;
			}
		}
		return array_values( array_filter( wp_parse_id_list( $value ) ) );
	}

	/**
	 * Read one logical relationship field with an unambiguous stored value.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param string $table      Metadata table name.
	 * @param string $column     Object ID column name.
	 * @param int    $id         Object ID.
	 * @param string $key        Metadata key.
	 * @param string $read_error Failure reason for a failed database read.
	 * @return array|false Stored field description, or false on failure.
	 */
	private function read_field( $table, $column, $id, $key, $read_error ) {
		$field = $this->db->get_row( $this->db->prepare( "SELECT COUNT(*) AS rows_found, MIN(meta_value) AS value, MIN(meta_key) AS field_key FROM `{$table}` WHERE `{$column}` = %d AND meta_key = %s", $id, $key ), ARRAY_A );
		if ( ! empty( $this->db->last_error ) || ! is_array( $field ) || ! isset( $field['rows_found'] ) || ! array_key_exists( 'value', $field ) || ! array_key_exists( 'field_key', $field ) ) {
			$this->fail( $read_error );
			return false;
		}
		$field['rows_found'] = (int) $field['rows_found'];
		if ( $field['rows_found'] > 1 ) {
			$this->fail( 'ambiguous-mapping' );
			return false;
		}
		if ( $field['rows_found'] && $field['field_key'] !== $key ) {
			$this->fail( 'verify' );
			return false;
		}
		return $field;
	}

	/**
	 * Read the legacy and current group markers for one forum.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param int $id Forum ID.
	 * @return array|false Forum migration source, or false on failure.
	 */
	private function read_forum_source( $id ) {
		$post = $this->db->get_row( $this->db->prepare( "SELECT ID, post_status FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $this->forum_post_type ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'forums-read' );
			return false;
		}
		if ( ! $post ) {
			return false;
		}
		$legacy = $this->read_field( $this->db->postmeta, 'post_id', $id, '_bbp_old_forum_id', 'forums-read' );
		$groups = array(
			'rows_found' => 0,
			'value'      => null,
			'field_key'  => null,
		);
		if ( $legacy && ! empty( $legacy['value'] ) ) {
			$groups = $this->read_field( $this->db->postmeta, 'post_id', $id, '_bbp_group_ids', 'forums-read' );
		}
		if ( false === $legacy || false === $groups ) {
			return false;
		}
		$ids = $groups['rows_found'] ? $this->parse_id_list( maybe_unserialize( $groups['value'] ) ) : array();
		if ( false === $ids ) {
			$this->fail( 'verify' );
			return false;
		}
		return array(
			'id'           => $id,
			'legacy'       => (string) $legacy['value'],
			'groups'       => $ids,
			'legacy_field' => $legacy,
			'groups_field' => $groups,
			'skip'         => count( $ids ) > 1,
			'owner'        => false,
		);
	}

	/**
	 * Read one group's forum marker and visibility source.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param int $id Group ID.
	 * @return array|false Group migration source, or false on failure.
	 */
	private function read_group_forum_source( $id ) {
		$field = $this->read_field( $this->groupmeta_table, 'group_id', $id, 'forum_id', 'groupmeta-read' );
		$group = $this->db->get_row( $this->db->prepare( "SELECT status FROM `{$this->groups_table}` WHERE id = %d", $id ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'groups-read' );
			return false;
		}
		return false === $field ? false : array(
			'id'     => $id,
			'exists' => is_object( $group ),
			'status' => $group ? $group->status : '',
			'forum'  => $field,
		);
	}

	/**
	 * Process one bounded migration step.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param int  $limit    Maximum work available to this step.
	 * @param bool $complete Whether the complete migration is finished, passed by reference.
	 * @return int Work units consumed.
	 */
	private function step( $limit, &$complete ) {
		$phase = $this->state['phase'];
		if ( in_array( $phase, array( 'prepare', 'cleanup' ), true ) ) {
			list( $success, $work, $finished ) = bbp_admin_repair_tool_cleanup_options( $this->prefix(), $limit, $this->dry_run && ! $this->async, $this->preview_plans, $this->inspected_options );
			if ( ! $success ) {
				$this->fail( 'progress' );
			} elseif ( $finished ) {
				if ( 'cleanup' === $phase ) {
					$complete = true;
				} else {
					$this->next_phase( 'index' );
				}
			}
			return $work;
		}
		if ( 'root' === $phase ) {
			$this->finalize_root();
			if ( ! $this->error ) {
				$this->next_phase( 'cleanup' );
			}
			return 1;
		}
		$id = (int) $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $this->forum_post_type, $this->state['cursor'], $this->state['upper_id'] ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'forums-read' );
			return 1;
		}
		if ( ! $id ) {
			$next = array(
				'index'  => 'plan',
				'plan'   => 'verify',
				'verify' => 'apply',
				'apply'  => 'root',
			);
			if ( 'verify' === $phase ) {
				$root = $this->read_root_source();
				if ( ! $this->error ) {
					$this->write_plan( 'root', $root );
				}
			}
			if ( ! $this->error ) {
				$this->next_phase( $this->state['legacy_forum_count'] ? $next[ $phase ] : 'cleanup' );
			}
			return 1;
		}
		if ( 'index' === $phase ) {
			$this->index_forum( $id );
		} else {
			$plan = $this->read_plan( 'forum_' . $id );
			if ( $this->error ) {
				return 1;
			}
			if ( ! $this->valid_plan( $plan, $id ) ) {
				$this->fail( 'verify' );
			} elseif ( in_array( $phase, array( 'plan', 'verify' ), true ) && ! $this->source_matches( $plan ) ) {
				$this->fail( 'verify' );
			} elseif ( 'verify' === $phase && ! empty( $plan['legacy'] ) && ! $plan['skip'] && ( ! isset( $plan['group'], $plan['update_group'] ) || ! is_int( $plan['group'] ) || ! is_bool( $plan['update_group'] ) ) ) {
				$this->fail( 'verify' );
			} elseif ( ! empty( $plan['legacy'] ) && ! $plan['skip'] ) {
				if ( in_array( $phase, array( 'plan', 'verify' ), true ) ) {
					if ( ! $this->plan_forum( $plan, 'verify' === $phase ) ) {
						return 1;
					}
				} elseif ( ! isset( $plan['group'], $plan['update_group'] ) || ! is_int( $plan['group'] ) || ! is_bool( $plan['update_group'] ) ) {
					$this->fail( 'verify' );
				} elseif ( $plan['group'] ) {
					$this->apply_plan( $plan );
				}
			}
			if ( ! $this->error ) {
				$this->state['cursor'] = $id;
			}
		}
		return 1;
	}

	/**
	 * Check whether a forum's source fields still match its migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $plan Forum migration plan.
	 * @return bool Whether the live source still matches.
	 */
	private function source_matches( $plan ) {
		$live = $this->read_forum_source( $plan['id'] );
		return $live && $live['legacy_field'] === $plan['legacy_field'] && $live['groups_field'] === $plan['groups_field'];
	}

	/**
	 * Validate one forum migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param mixed $plan Possible forum migration plan.
	 * @param int   $id   Expected forum ID.
	 * @return bool Whether the plan is valid.
	 */
	private function valid_plan( $plan, $id ) {
		if ( ! is_array( $plan ) || ! isset( $plan['id'], $plan['legacy'], $plan['groups'], $plan['legacy_field'], $plan['groups_field'], $plan['skip'], $plan['owner'] )
			|| $id !== $plan['id'] || ! is_string( $plan['legacy'] ) || ! is_bool( $plan['skip'] ) || ! is_bool( $plan['owner'] )
			|| ! is_array( $plan['groups'] ) || $this->parse_id_list( $plan['groups'] ) !== $plan['groups'] ) {
			return false;
		}
		foreach ( array( 'legacy_field', 'groups_field' ) as $key ) {
			$field = $plan[ $key ];
			if ( ! is_array( $field ) || ! isset( $field['rows_found'] ) || ! is_int( $field['rows_found'] )
				|| $field['rows_found'] < 0 || $field['rows_found'] > 1 || ! array_key_exists( 'value', $field )
				|| ( null !== $field['value'] && ! is_string( $field['value'] ) ) ) {
				return false;
			}
		}
		if ( isset( $plan['group'] ) && $plan['group'] ) {
			if ( ! isset( $plan['group_source'] ) || ! is_array( $plan['group_source'] )
				|| ! isset( $plan['group_source']['id'], $plan['group_source']['exists'], $plan['group_source']['forum'] )
				|| $plan['group_source']['id'] !== $plan['group'] || true !== $plan['group_source']['exists']
				|| ! is_array( $plan['group_source']['forum'] ) || ! array_key_exists( 'value', $plan['group_source']['forum'] ) ) {
				return false;
			}
		}

		if ( isset( $plan['scan'] ) ) {
			foreach ( array( 'legacy', 'current' ) as $mode ) {
				$key = $mode . '_matches';
				if ( ! isset( $plan[ $key ] ) || ! is_array( $plan[ $key ] ) || count( $plan[ $key ] ) > 1 || $this->parse_id_list( $plan[ $key ] ) !== $plan[ $key ] ) {
					return false;
				}
			}
			return in_array( $plan['scan'], array( 'legacy', 'current' ), true )
				&& isset( $plan['candidate_cursor'] ) && is_int( $plan['candidate_cursor'] ) && $plan['candidate_cursor'] >= 0;
		}
		return true;
	}

	/**
	 * Index one forum and its explicit group markers.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param int $id Forum ID.
	 */
	private function index_forum( $id ) {
		$plan = $this->state['group_marker_offset'] ? $this->read_plan( 'forum_' . $id ) : $this->read_forum_source( $id );
		if ( ! $this->valid_plan( $plan, $id ) || $this->state['group_marker_offset'] > count( $plan['groups'] ) ) {
			$this->fail( 'verify' );
			return;
		}
		if ( $this->state['group_marker_offset'] ) {
			$live = $this->read_forum_source( $id );
			if ( ! $live || $live['legacy_field'] !== $plan['legacy_field'] || $live['groups_field'] !== $plan['groups_field'] ) {
				$this->fail( 'verify' );
				return;
			}
		}
		$offset = $this->state['group_marker_offset'];
		if ( ! empty( $plan['legacy'] ) && isset( $plan['groups'][ $offset ] ) ) {
			$group      = $plan['groups'][ $offset ];
			$source     = $this->read_group_forum_source( $group );
			$value      = $source ? $source['forum']['value'] : null;
			$serialized = is_serialized( $value );
			$ids        = $this->parse_id_list( maybe_unserialize( $value ) );
			$owner      = ( ! empty( $value ) && false !== $ids && in_array( $id, $ids, true ) )
				|| ( ! $serialized && (string) $value === $plan['legacy'] );
			if ( $this->error ) {
				return;
			}
			if ( $owner ) {
				$this->write_plan( 'owner_' . $group, array( 'claimed' => true ) );
				$plan['owner'] = true;
			}
			if ( count( $plan['groups'] ) === 1 ) {
				$plan['marker'] = $source;
			}
			$plan['skip'] = $plan['skip'] || $serialized;
			++$this->state['group_marker_offset'];
		}
		$this->write_plan( 'forum_' . $id, $plan );
		if ( empty( $plan['legacy'] ) ) {
			$this->write_plan( 'native_' . $id, array( 'claimed' => true ) );
		}
		if ( empty( $plan['legacy'] ) || $this->state['group_marker_offset'] >= count( $plan['groups'] ) ) {
			$this->state['cursor']              = $id;
			$this->state['group_marker_offset'] = 0;
			$this->state['legacy_forum_count'] += (int) ! empty( $plan['legacy'] );
		}
	}

	/**
	 * Claim one legacy value or group for a forum migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param string $key Claim key.
	 * @param int    $id  Forum ID.
	 */
	private function claim( $key, $id ) {
		$claimed = $this->read_plan( $key );
		if ( false !== $claimed && ( ! is_array( $claimed ) || ! isset( $claimed['forum'] ) || $claimed['forum'] !== $id ) ) {
			if ( $this->fail( 'ambiguous-mapping' ) ) {
				$this->mapping_error = 0 === strpos( $key, 'legacy_' );
			}
		} elseif ( ! $this->error ) {
			$this->write_plan( $key, array( 'forum' => $id ) );
		}
	}

	/**
	 * Inspect one candidate per work unit without loading a complete group list.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $plan   Forum migration plan.
	 * @param bool  $verify Whether to verify a previously selected mapping.
	 * @return bool Whether candidate planning is complete for this forum.
	 */
	private function plan_forum( $plan, $verify = false ) {
		if ( $verify && ! isset( $plan['expected'] ) ) {
			$plan['expected'] = array(
				'group'        => $plan['group'],
				'update_group' => $plan['update_group'],
				'group_source' => isset( $plan['group_source'] ) ? $plan['group_source'] : false,
			);
		}
		$stored = $plan['groups'] ? reset( $plan['groups'] ) : 0;
		if ( $stored && ( ! array_key_exists( 'marker', $plan ) || $this->read_group_forum_source( $stored ) !== $plan['marker'] ) ) {
			$this->fail( 'verify' );
			return true;
		}
		if ( ! isset( $plan['scan'] ) ) {
			// The forum's explicit marker takes precedence over other candidates.
			$matches = $stored ? $this->db->get_row( $this->db->prepare( "SELECT MAX(meta_value = %s) AS legacy_match, MAX(meta_value = %d) AS current_match FROM `{$this->groupmeta_table}` WHERE group_id = %d AND meta_key = 'forum_id'", $plan['legacy'], $plan['id'], $stored ) ) : false;
			if ( ! empty( $this->db->last_error ) ) {
				$this->fail( 'groupmeta-read' );
				return true;
			}
			if ( $matches && ( $matches->legacy_match || $matches->current_match ) ) {
				return $this->finish_plan( $plan, $stored, ! empty( $matches->legacy_match ) );
			}
			$plan['scan']             = 'legacy';
			$plan['candidate_cursor'] = 0;
			$plan['legacy_matches']   = array();
			$plan['current_matches']  = array();
		}
		$legacy = 'legacy' === $plan['scan'];
		$slot   = $legacy ? '%s' : '%d';
		$value  = $legacy ? $plan['legacy'] : $plan['id'];
		$group  = (int) $this->db->get_var( $this->db->prepare( "SELECT group_id FROM `{$this->groupmeta_table}` WHERE meta_key = 'forum_id' AND meta_value = {$slot} AND group_id > %d ORDER BY group_id LIMIT 1", $value, $plan['candidate_cursor'] ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'groupmeta-read' );
			return true;
		}
		if ( $group ) {
			$plan['candidate_cursor'] = $group;
			$claimed                  = $this->read_plan( 'owner_' . $group );
			if ( false !== $claimed && array( 'claimed' => true ) !== $claimed ) {
				$this->fail( 'verify' );
			} elseif ( ! $claimed || ( $plan['owner'] && $stored === $group ) ) {
				$key = $plan['scan'] . '_matches';
				$plan[ $key ][] = $group;
				if ( count( $plan[ $key ] ) > 1 ) {
					$this->fail( 'ambiguous-mapping' );
				}
			}
		} elseif ( $legacy ) {
			$plan['scan']             = 'current';
			$plan['candidate_cursor'] = 0;
		} else {
			$group = $plan['legacy_matches'] ? reset( $plan['legacy_matches'] ) : ( $plan['current_matches'] ? reset( $plan['current_matches'] ) : 0 );
			return $this->finish_plan( $plan, $group, ! empty( $plan['legacy_matches'] ) );
		}
		if ( ! $this->error ) {
			$this->write_plan( 'forum_' . $plan['id'], $plan );
		}
		return false;
	}

	/**
	 * Finalize and persist one forum-to-group migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $plan   Forum migration plan.
	 * @param int   $group  Selected group ID, or zero when no group matches.
	 * @param bool  $update Whether the group's legacy marker needs updating.
	 * @return bool True after the plan has been finalized.
	 */
	private function finish_plan( $plan, $group, $update ) {
		$plan['group_source'] = false;
		$plan['group']        = $group;
		$plan['update_group'] = $update && $plan['legacy'] !== (string) $plan['id'];
		unset( $plan['scan'], $plan['candidate_cursor'], $plan['legacy_matches'], $plan['current_matches'] );
		if ( $group ) {
			if ( $plan['update_group'] ) {
				$this->claim( 'legacy_' . md5( $plan['legacy'] ), $plan['id'] );
				if ( (string) (int) $plan['legacy'] === $plan['legacy'] && $this->read_plan( 'native_' . $plan['legacy'] ) ) {
					if ( $this->fail( 'ambiguous-mapping' ) ) {
						$this->mapping_error = true;
					}
				}
			}
			if ( ! $this->error ) {
				$this->claim( 'group_' . $group, $plan['id'] );
			}
			$source = $this->read_group_forum_source( $group );
			// Candidate queries retain MySQL's comparison semantics for current IDs.
			if ( ! $this->error && ( ! $source || ! $source['exists'] || is_serialized( $source['forum']['value'] ) ) ) {
				$this->fail( 'verify' );
			}
			$plan['group_source'] = $source;
		}
		if ( isset( $plan['expected'] ) && ! $this->error ) {
			$actual = array(
				'group'        => $plan['group'],
				'update_group' => $plan['update_group'],
				'group_source' => isset( $plan['group_source'] ) ? $plan['group_source'] : false,
			);
			if ( $actual !== $plan['expected'] ) {
				$this->fail( 'verify' );
			}
			unset( $plan['expected'] );
		}
		if ( ! $this->error ) {
			$this->write_plan( 'forum_' . $plan['id'], $plan );
		}
		return true;
	}

	/**
	 * Map a BuddyPress group status to the target forum visibility.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $source Group migration source.
	 * @return string Target forum status, or an empty string when unchanged.
	 */
	private function group_visibility_target( $source ) {
		return 'public' === $source['status'] ? bbp_get_public_status_id() : ( in_array( $source['status'], array( 'private', 'hidden' ), true ) ? bbp_get_hidden_status_id() : '' );
	}

	/**
	 * Check one forum's post status and shared visibility lists.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param int         $id         Forum ID.
	 * @param string      $target     Expected forum status.
	 * @param string|null $visibility Current forum status, passed by reference.
	 * @return bool Whether all visibility state matches the target.
	 */
	private function visibility_matches( $id, $target, &$visibility = null ) {
		$visibility = $this->db->get_var( $this->db->prepare( "SELECT post_status FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $this->forum_post_type ) );
		if ( ! empty( $this->db->last_error ) || null === $visibility ) {
			$this->fail( 'visibility' );
			return false;
		}
		list( $hidden_read, $hidden )   = bbp_admin_repair_get_option( '_bbp_hidden_forums', array(), false );
		list( $private_read, $private ) = bbp_admin_repair_get_option( '_bbp_private_forums', array(), false );
		$hidden_ids  = $this->parse_id_list( $hidden );
		$private_ids = $this->parse_id_list( $private );
		if ( ! $hidden_read || ! $private_read || false === $hidden_ids || false === $private_ids ) {
			$this->fail( 'visibility' );
			return false;
		}
		return $visibility === $target
			&& ! in_array( $id, $private_ids, true )
			&& ( in_array( $id, $hidden_ids, true ) === ( bbp_get_hidden_status_id() === $target ) );
	}

	/**
	 * Apply one verified forum-to-group migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $plan Forum migration plan.
	 */
	private function apply_plan( $plan ) {
		$id       = $plan['id'];
		$group    = $plan['group'];
		$forum    = $this->read_forum_source( $id );
		$source   = $this->read_group_forum_source( $group );
		$value    = $source ? $source['forum']['value'] : null;
		$expected = $plan['group_source']['forum']['value'];
		$target   = $source ? $this->group_visibility_target( $source ) : '';
		$desired  = array( $group );
		if ( $this->error ) {
			return;
		}
		// Accept our own partial writes, but never overwrite a changed mapping.
		if ( ! $source || ! $source['exists'] || ! $forum || $forum['legacy_field'] !== $plan['legacy_field']
			|| ( $forum['groups'] !== $plan['groups'] && $forum['groups'] !== $desired )
			|| ( $value !== $expected && (string) $value !== (string) $id ) ) {
			$this->fail( 'verify' );
			return;
		}
		if ( $forum['groups'] !== $desired ) {
			if ( ! $this->dry_run ) {
				if ( ! bbp_admin_repair_metadata_update( 'post', $id, '_bbp_group_ids', $desired ) ) {
					$this->fail( 'forum-meta-write' );
					return;
				}
			}
			++$this->counts['forums'];
		}
		if ( $plan['update_group'] && (string) $value !== (string) $id ) {
			if ( ! $this->dry_run ) {
				$this->db->query( $this->db->prepare( "UPDATE `{$this->groupmeta_table}` SET `meta_value` = %d WHERE `group_id` = %d AND `meta_key` = %s AND BINARY `meta_value` = BINARY %s", $id, $group, 'forum_id', $expected ) );
				$failed = ! empty( $this->db->last_error );
				wp_cache_delete( $group, 'group_meta' );
				$stored = $this->read_group_forum_source( $group );
				if ( $failed || ! $stored || ! $stored['exists'] || (string) $stored['forum']['value'] !== (string) $id ) {
					$this->fail( 'groupmeta-write' );
					return;
				}
			}
			++$this->counts['groups'];
		}
		if ( $target && ! $this->visibility_matches( $id, $target, $current_visibility ) && ! $this->error ) {
			if ( ! $this->dry_run ) {
				if ( bbp_get_public_status_id() === $target ) {
					bbp_publicize_forum( $id, $current_visibility );
				} else {
					bbp_hide_forum( $id, $current_visibility );
				}
				if ( ! $this->visibility_matches( $id, $target ) ) {
					$this->fail( 'visibility' );
					return;
				}
			}
			++$this->counts['statuses'];
		}
	}

	/**
	 * Read the legacy root forum used by converted group forums.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @return array|false Root forum source, or false on failure.
	 */
	private function read_root_source() {
		$legacy_root_id = function_exists( 'bp_forums_parent_forum_id' ) ? bp_forums_parent_forum_id() : ( defined( 'BP_FORUMS_PARENT_FORUM_ID' ) ? (int) BP_FORUMS_PARENT_FORUM_ID : 1 );
		$posts          = $this->db->get_results( $this->db->prepare( "SELECT DISTINCT p.ID, p.post_type, p.post_parent, p.post_status, p.post_title, p.post_name FROM {$this->db->posts} p INNER JOIN {$this->db->postmeta} m ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key = '_bbp_old_forum_id' AND CAST(m.meta_value AS SIGNED) = %d ORDER BY p.ID LIMIT 2", $this->forum_post_type, $legacy_root_id ), ARRAY_A );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'forums-read' );
			return false;
		}
		if ( count( $posts ) > 1 ) {
			if ( $this->fail( 'ambiguous-mapping' ) ) {
				$this->mapping_error = true;
			}
			return false;
		}
		if ( ! $posts ) {
			return array( 'ID' => 0 );
		}
		$root                = reset( $posts );
		$root['ID']          = (int) $root['ID'];
		$root['post_parent'] = (int) $root['post_parent'];
		return $root;
	}

	/**
	 * Finalize the root forum and remove obsolete standalone roles.
	 *
	 * Fixed-size finalization runs only after every relationship plan succeeds.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 */
	private function finalize_root() {
		$planned = $this->read_plan( 'root' );
		$current = $this->read_root_source();
		if ( $this->error ) {
			return;
		}
		if ( ! is_array( $planned ) || ! isset( $planned['ID'] ) || ! is_int( $planned['ID'] ) || ! $current || $planned['ID'] !== $current['ID'] ) {
			$this->fail( 'verify' );
			return;
		}
		$id = (int) $current['ID'];
		if ( $id ) {
			foreach ( array( 'post_type', 'post_title', 'post_name', 'post_status' ) as $field ) {
				if ( ! isset( $planned[ $field ] ) || ! is_string( $planned[ $field ] ) ) {
					$this->fail( 'verify' );
					return;
				}
			}
			if ( 'Default Forum' === $planned['post_title'] ) {
				if ( ! isset( $planned['target_title'], $planned['target_slug'], $planned['rename_attempted'] ) ) {
					$planned['target_title']     = esc_html__( 'Group Forums', 'bbpress' );
					$planned['target_slug']      = wp_unique_post_slug( sanitize_title( esc_html__( 'group-forums', 'bbpress' ) ), $id, $current['post_status'], $this->forum_post_type, $current['post_parent'] );
					$planned['rename_attempted'] = false;
				}
				if ( ! is_string( $planned['target_title'] ) || ! is_string( $planned['target_slug'] ) || ! is_bool( $planned['rename_attempted'] ) ) {
					$this->fail( 'verify' );
					return;
				}
				$title = $planned['target_title'];
				$slug  = $planned['target_slug'];
				if ( ! $planned['rename_attempted'] && ( $current['post_title'] !== $planned['post_title'] || $current['post_name'] !== $planned['post_name'] ) ) {
					$this->fail( 'verify' );
					return;
				}
				$rename = $current['post_title'] !== $title || $current['post_name'] !== $slug;
				if ( ! $this->dry_run && $rename ) {
					$planned['rename_attempted'] = true;
					$this->write_plan( 'root', $planned );
					if ( $this->error ) {
						return;
					}
					$result = wp_update_post(
						array(
							'ID'         => $id,
							'post_title' => $title,
							'post_name'  => $slug,
						),
						true
					);
					$stored = $this->db->get_row( $this->db->prepare( "SELECT post_type, post_title, post_name FROM {$this->db->posts} WHERE ID = %d", $id ) );
					if ( ! empty( $this->db->last_error ) || is_wp_error( $result ) || $id !== $result || ! $stored || $stored->post_type !== $this->forum_post_type || $stored->post_title !== $title || $stored->post_name !== $slug ) {
						$this->fail( 'root-rename' );
						return;
					}
				}
				$this->counts['rename'] += (int) $rename;
			} elseif ( $planned['post_title'] !== $current['post_title'] || $planned['post_name'] !== $current['post_name'] ) {
				$this->fail( 'verify' );
				return;
			}
			if ( ! bbp_admin_repair_option_matches( '_bbp_group_forums_root_id', $id ) ) {
				if ( ! $this->dry_run && ! bbp_admin_repair_update_option( '_bbp_group_forums_root_id', $id ) ) {
					$this->fail( 'root-option' );
					return;
				}
				++$this->counts['root'];
			}
		}
		foreach ( array( 'member', 'inactive', 'blocked', 'moderator', 'keymaster' ) as $role ) {
			if ( get_role( $role ) ) {
				if ( ! $this->dry_run ) {
					$registry = wp_roles();
					$roles    = $registry->roles;
					$objects  = $registry->role_objects;
					$names    = $registry->role_names;
					remove_role( $role );
					if ( $registry->use_db && ! bbp_admin_repair_option_matches( $registry->role_key, $registry->roles ) ) {
						$registry->roles        = $roles;
						$registry->role_objects = $objects;
						$registry->role_names   = $names;
						wp_cache_delete_multiple( array( $registry->role_key, 'alloptions', 'notoptions' ), 'options' );
						$this->fail( 'roles' );
						return;
					}
				}
				$this->counts['roles'][] = $role;
			}
		}
	}
}
