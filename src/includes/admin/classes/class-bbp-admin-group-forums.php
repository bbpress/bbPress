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
class BBP_Admin_Group_Forums {

	/**
	 * WordPress database connection.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var wpdb
	 */
	private $db;

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
	 * Registered upgrade tool ID.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var string
	 */
	private $tool_id = 'bbp-group-forums';

	/**
	 * Current migration checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array|false
	 */
	private $state;

	/**
	 * Whether this run only previews changes.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var bool
	 */
	private $dry_run;

	/**
	 * Whether this is one request in a browser Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var bool
	 */
	private $async;

	/**
	 * In-memory migration plans created by a synchronous Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array
	 */
	private $preview = array();

	/**
	 * Option names already inspected during cleanup.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array
	 */
	private $cleaned = array();

	/**
	 * Current migration error code.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var int
	 */
	private $code = 0;

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
			$this->code = 11;
		} elseif ( false === $saved || $restarted ) {
			$this->state = $this->new_state();
			// Publish the generation before prepare can clean up an abandoned run.
			if ( ! $this->code && ! $this->save() ) {
				$this->code = 11;
			}
		} else {
			$this->state = $saved;
		}
		if ( $this->async && $this->state ) {
			$preview = array_merge( $this->counts, array( 'batches' => 0 ) );
			$data    = bbp_admin_repair_tool_restore_preview( $this->state, $preview, array_keys( $preview ) );
			foreach ( array_keys( $this->counts ) as $field ) {
				$this->counts[ $field ] = $preview[ $field ];
			}
			$this->batches       = $preview['batches'];
			$this->mapping_error = ! empty( $data['mapping_error'] );
		}
		$size = bbp_admin_repair_tool_batch_size( $this->tool_id );
		$work = 0;
		while ( ! $this->code && ! $complete && ( bbp_admin_repair_tool_continue_preview( $this->dry_run ) || $work < $size ) ) {
			$remaining = bbp_admin_repair_tool_continue_preview( $this->dry_run ) ? $size - ( $work % $size ) : $size - $work;
			$work     += $this->step( $remaining, $complete );
		}
		if ( $this->async && $work ) {
			++$this->batches;
		}
		if ( ( ! $this->dry_run || $this->async ) && $this->state && 11 !== $this->code ) {
			if ( in_array( $this->code, array( 7, 12 ), true ) ) {
				// Rebuild invalid source plans. Previously written forum markers
				// disambiguate partial upgrades when legacy and current IDs overlap.
				$this->state = $this->new_state();
			} elseif ( $this->async ) {
				$preview = array_merge( $this->counts, array( 'batches' => $this->batches ) );
				bbp_admin_repair_tool_store_preview( $this->state, $preview, array_keys( $preview ), array( 'mapping_error' => (bool) $this->mapping_error ) );
			}
			if ( $this->state && ! $this->save( ! $this->code && $complete ) ) {
				$this->code = 11;
			}
		}
		return array_merge(
			$this->counts,
			array(
				'code'          => $this->code,
				'mapping_error' => $this->mapping_error,
				'complete'      => ! $this->code && $complete,
				'restarted'     => $restarted,
				'no_data'       => $complete && empty( $this->state['legacy_forum_count'] ),
				'phase'         => isset( $this->state['phase'] ) ? $this->state['phase'] : 'prepare',
				'work'          => $work,
				'batches'       => $this->async ? $this->batches : max( 1, (int) ceil( $work / $size ) ),
			)
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
			&& array( $this->forum_post_type, $this->groups_table, $this->groupmeta_table, 2 ) === $state['context']
			&& ( $state['legacy_forum_count'] > 0 || ! in_array( $state['phase'], array( 'plan', 'verify', 'apply', 'root' ), true ) )
			&& $state['legacy_forum_count'] <= $state['upper_id'] && ( 'index' === $state['phase'] || 0 === $state['group_marker_offset'] );
		if ( ! $valid || ! isset( $state['_preview_state'] ) ) {
			return $valid;
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
	private function new_state() {
		$upper = (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type = %s", $this->forum_post_type ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 2;
			return false;
		}
		return array(
			'cursor'              => 0,
			'upper_id'            => $upper,
			'legacy_forum_count'  => 0,
			'group_marker_offset' => 0,
			'phase'               => 'prepare',
			'token'               => str_replace( '-', '', wp_generate_uuid4() ),
			'context'             => array( $this->forum_post_type, $this->groups_table, $this->groupmeta_table, 2 ),
		);
	}

	/**
	 * Save or delete the current migration checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param bool $complete Whether the migration completed successfully.
	 * @return bool Whether the checkpoint operation succeeded.
	 */
	private function save( $complete = false ) {
		return bbp_admin_repair_tool_save_checkpoint( $this->tool_id, $this->state, $complete, $this->dry_run );
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
		if ( array_key_exists( $name, $this->preview ) ) {
			return $this->preview[ $name ];
		}
		list( $read, $value ) = bbp_admin_repair_get_option( $name );
		if ( ! $read ) {
			$this->code = 11;
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
			$this->preview[ $name ] = $value;
		} elseif ( ! bbp_admin_repair_tool_save_option( $name, $value ) ) {
			$this->code = 11;
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
	private function ids( $value ) {
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
	 * @param string $table  Metadata table name.
	 * @param string $column Object ID column name.
	 * @param int    $id     Object ID.
	 * @param string $key    Metadata key.
	 * @param int    $error  Error code for a failed database read.
	 * @return array|false Stored field description, or false on failure.
	 */
	private function read_field( $table, $column, $id, $key, $error ) {
		$field = $this->db->get_row( $this->db->prepare( "SELECT COUNT(*) AS rows_found, MIN(meta_value) AS value, MIN(meta_key) AS field_key FROM `{$table}` WHERE `{$column}` = %d AND meta_key = %s", $id, $key ), ARRAY_A );
		if ( ! empty( $this->db->last_error ) || ! is_array( $field ) || ! isset( $field['rows_found'] ) || ! array_key_exists( 'value', $field ) || ! array_key_exists( 'field_key', $field ) ) {
			$this->code = $error;
			return false;
		}
		$field['rows_found'] = (int) $field['rows_found'];
		if ( $field['rows_found'] > 1 ) {
			$this->code = 7;
			return false;
		}
		if ( $field['rows_found'] && $field['field_key'] !== $key ) {
			$this->code = 12;
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
			$this->code = 2;
			return false;
		}
		if ( ! $post ) {
			return false;
		}
		$legacy = $this->read_field( $this->db->postmeta, 'post_id', $id, '_bbp_old_forum_id', 2 );
		$groups = array(
			'rows_found' => 0,
			'value'      => null,
			'field_key'  => null,
		);
		if ( $legacy && ! empty( $legacy['value'] ) ) {
			$groups = $this->read_field( $this->db->postmeta, 'post_id', $id, '_bbp_group_ids', 2 );
		}
		if ( false === $legacy || false === $groups ) {
			return false;
		}
		$ids = $groups['rows_found'] ? $this->ids( maybe_unserialize( $groups['value'] ) ) : array();
		if ( false === $ids ) {
			$this->code = 12;
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
		$field = $this->read_field( $this->groupmeta_table, 'group_id', $id, 'forum_id', 3 );
		$group = $this->db->get_row( $this->db->prepare( "SELECT status FROM `{$this->groups_table}` WHERE id = %d", $id ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 4;
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
			list( $success, $work, $finished ) = bbp_admin_repair_tool_cleanup_options( $this->prefix(), $limit, $this->dry_run && ! $this->async, $this->preview, $this->cleaned );
			if ( ! $success ) {
				$this->code = 11;
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
			$this->root();
			if ( ! $this->code ) {
				$this->next_phase( 'cleanup' );
			}
			return 1;
		}
		$id = (int) $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $this->forum_post_type, $this->state['cursor'], $this->state['upper_id'] ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 2;
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
				if ( ! $this->code ) {
					$this->write_plan( 'root', $root );
				}
			}
			if ( ! $this->code ) {
				$this->next_phase( $this->state['legacy_forum_count'] ? $next[ $phase ] : 'cleanup' );
			}
			return 1;
		}
		if ( 'index' === $phase ) {
			$this->index( $id );
		} else {
			$node = $this->read_plan( 'forum_' . $id );
			if ( $this->code ) {
				return 1;
			}
			if ( ! $this->valid_node( $node, $id ) ) {
				$this->code = 12;
			} elseif ( in_array( $phase, array( 'plan', 'verify' ), true ) && ! $this->source_matches( $node ) ) {
				$this->code = $this->code ? $this->code : 12;
			} elseif ( 'verify' === $phase && ! empty( $node['legacy'] ) && ! $node['skip'] && ( ! isset( $node['group'], $node['update_group'] ) || ! is_int( $node['group'] ) || ! is_bool( $node['update_group'] ) ) ) {
				$this->code = 12;
			} elseif ( ! empty( $node['legacy'] ) && ! $node['skip'] ) {
				if ( in_array( $phase, array( 'plan', 'verify' ), true ) ) {
					if ( ! $this->plan( $node, 'verify' === $phase ) ) {
						return 1;
					}
				} elseif ( ! isset( $node['group'], $node['update_group'] ) || ! is_int( $node['group'] ) || ! is_bool( $node['update_group'] ) ) {
					$this->code = 12;
				} elseif ( $node['group'] ) {
					$this->apply( $node );
				}
			}
			if ( ! $this->code ) {
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
	 * @param array $node Forum migration plan.
	 * @return bool Whether the live source still matches.
	 */
	private function source_matches( $node ) {
		$live = $this->read_forum_source( $node['id'] );
		return $live && $live['legacy_field'] === $node['legacy_field'] && $live['groups_field'] === $node['groups_field'];
	}

	/**
	 * Validate one forum migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param mixed $node Possible forum migration plan.
	 * @param int   $id   Expected forum ID.
	 * @return bool Whether the plan is valid.
	 */
	private function valid_node( $node, $id ) {
		if ( ! is_array( $node ) || ! isset( $node['id'], $node['legacy'], $node['groups'], $node['legacy_field'], $node['groups_field'], $node['skip'], $node['owner'] )
			|| $id !== $node['id'] || ! is_string( $node['legacy'] ) || ! is_bool( $node['skip'] ) || ! is_bool( $node['owner'] )
			|| ! is_array( $node['groups'] ) || $this->ids( $node['groups'] ) !== $node['groups'] ) {
			return false;
		}
		foreach ( array( 'legacy_field', 'groups_field' ) as $key ) {
			$field = $node[ $key ];
			if ( ! is_array( $field ) || ! isset( $field['rows_found'] ) || ! is_int( $field['rows_found'] )
				|| $field['rows_found'] < 0 || $field['rows_found'] > 1 || ! array_key_exists( 'value', $field )
				|| ( null !== $field['value'] && ! is_string( $field['value'] ) ) ) {
				return false;
			}
		}
		if ( isset( $node['group'] ) && $node['group'] ) {
			if ( ! isset( $node['group_source'] ) || ! is_array( $node['group_source'] )
				|| ! isset( $node['group_source']['id'], $node['group_source']['exists'], $node['group_source']['forum'] )
				|| $node['group_source']['id'] !== $node['group'] || true !== $node['group_source']['exists']
				|| ! is_array( $node['group_source']['forum'] ) || ! array_key_exists( 'value', $node['group_source']['forum'] ) ) {
				return false;
			}
		}

		if ( isset( $node['scan'] ) ) {
			foreach ( array( 'legacy', 'current' ) as $mode ) {
				$key = $mode . '_matches';
				if ( ! isset( $node[ $key ] ) || ! is_array( $node[ $key ] ) || count( $node[ $key ] ) > 1 || $this->ids( $node[ $key ] ) !== $node[ $key ] ) {
					return false;
				}
			}
			return in_array( $node['scan'], array( 'legacy', 'current' ), true )
				&& isset( $node['candidate_cursor'] ) && is_int( $node['candidate_cursor'] ) && $node['candidate_cursor'] >= 0;
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
	private function index( $id ) {
		$node = $this->state['group_marker_offset'] ? $this->read_plan( 'forum_' . $id ) : $this->read_forum_source( $id );
		if ( ! $this->valid_node( $node, $id ) || $this->state['group_marker_offset'] > count( $node['groups'] ) ) {
			$this->code = $this->code ? $this->code : 12;
			return;
		}
		if ( $this->state['group_marker_offset'] ) {
			$live = $this->read_forum_source( $id );
			if ( ! $live || $live['legacy_field'] !== $node['legacy_field'] || $live['groups_field'] !== $node['groups_field'] ) {
				$this->code = $this->code ? $this->code : 12;
				return;
			}
		}
		$offset = $this->state['group_marker_offset'];
		if ( ! empty( $node['legacy'] ) && isset( $node['groups'][ $offset ] ) ) {
			$group      = $node['groups'][ $offset ];
			$source     = $this->read_group_forum_source( $group );
			$value      = $source ? $source['forum']['value'] : null;
			$serialized = is_serialized( $value );
			$ids        = $this->ids( maybe_unserialize( $value ) );
			$owner      = ( ! empty( $value ) && false !== $ids && in_array( $id, $ids, true ) )
				|| ( ! $serialized && (string) $value === $node['legacy'] );
			if ( $this->code ) {
				return;
			}
			if ( $owner ) {
				$this->write_plan( 'owner_' . $group, array( 'claimed' => true ) );
				$node['owner'] = true;
			}
			if ( count( $node['groups'] ) === 1 ) {
				$node['marker'] = $source;
			}
			$node['skip'] = $node['skip'] || $serialized;
			++$this->state['group_marker_offset'];
		}
		$this->write_plan( 'forum_' . $id, $node );
		if ( empty( $node['legacy'] ) ) {
			$this->write_plan( 'native_' . $id, array( 'claimed' => true ) );
		}
		if ( empty( $node['legacy'] ) || $this->state['group_marker_offset'] >= count( $node['groups'] ) ) {
			$this->state['cursor']              = $id;
			$this->state['group_marker_offset'] = 0;
			$this->state['legacy_forum_count'] += (int) ! empty( $node['legacy'] );
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
			$this->code          = 7;
			$this->mapping_error = 0 === strpos( $key, 'legacy_' );
		} elseif ( ! $this->code ) {
			$this->write_plan( $key, array( 'forum' => $id ) );
		}
	}

	/**
	 * Inspect one candidate per work unit without loading a complete group list.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $node   Forum migration plan.
	 * @param bool  $verify Whether to verify a previously selected mapping.
	 * @return bool Whether candidate planning is complete for this forum.
	 */
	private function plan( $node, $verify = false ) {
		if ( $verify && ! isset( $node['expected'] ) ) {
			$node['expected'] = array(
				'group'        => $node['group'],
				'update_group' => $node['update_group'],
				'group_source' => isset( $node['group_source'] ) ? $node['group_source'] : false,
			);
		}
		$stored = $node['groups'] ? reset( $node['groups'] ) : 0;
		if ( $stored && ( ! array_key_exists( 'marker', $node ) || $this->read_group_forum_source( $stored ) !== $node['marker'] ) ) {
			$this->code = $this->code ? $this->code : 12;
			return true;
		}
		if ( ! isset( $node['scan'] ) ) {
			// The forum's explicit marker takes precedence over other candidates.
			$matches = $stored ? $this->db->get_row( $this->db->prepare( "SELECT MAX(meta_value = %s) AS legacy_match, MAX(meta_value = %d) AS current_match FROM `{$this->groupmeta_table}` WHERE group_id = %d AND meta_key = 'forum_id'", $node['legacy'], $node['id'], $stored ) ) : false;
			if ( ! empty( $this->db->last_error ) ) {
				$this->code = 3;
				return true;
			}
			if ( $matches && ( $matches->legacy_match || $matches->current_match ) ) {
				return $this->finish_plan( $node, $stored, ! empty( $matches->legacy_match ) );
			}
			$node['scan']             = 'legacy';
			$node['candidate_cursor'] = 0;
			$node['legacy_matches']   = $node['current_matches'] = array();
		}
		$legacy = 'legacy' === $node['scan'];
		$slot   = $legacy ? '%s' : '%d';
		$value  = $legacy ? $node['legacy'] : $node['id'];
		$group  = (int) $this->db->get_var( $this->db->prepare( "SELECT group_id FROM `{$this->groupmeta_table}` WHERE meta_key = 'forum_id' AND meta_value = {$slot} AND group_id > %d ORDER BY group_id LIMIT 1", $value, $node['candidate_cursor'] ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 3;
			return true;
		}
		if ( $group ) {
			$node['candidate_cursor'] = $group;
			$claimed                  = $this->read_plan( 'owner_' . $group );
			if ( false !== $claimed && array( 'claimed' => true ) !== $claimed ) {
				$this->code = 12;
			} elseif ( ! $claimed || ( $node['owner'] && $stored === $group ) ) {
				$key = $node['scan'] . '_matches';
				$node[ $key ][] = $group;
				if ( count( $node[ $key ] ) > 1 ) {
					$this->code = 7;
				}
			}
		} elseif ( $legacy ) {
			$node['scan']             = 'current';
			$node['candidate_cursor'] = 0;
		} else {
			$group = $node['legacy_matches'] ? reset( $node['legacy_matches'] ) : ( $node['current_matches'] ? reset( $node['current_matches'] ) : 0 );
			return $this->finish_plan( $node, $group, ! empty( $node['legacy_matches'] ) );
		}
		if ( ! $this->code ) {
			$this->write_plan( 'forum_' . $node['id'], $node );
		}
		return false;
	}

	/**
	 * Finalize and persist one forum-to-group migration plan.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $node   Forum migration plan.
	 * @param int   $group  Selected group ID, or zero when no group matches.
	 * @param bool  $update Whether the group's legacy marker needs updating.
	 * @return bool True after the plan has been finalized.
	 */
	private function finish_plan( $node, $group, $update ) {
		$node['group_source'] = false;
		$node['group']        = $group;
		$node['update_group'] = $update && $node['legacy'] !== (string) $node['id'];
		unset( $node['scan'], $node['candidate_cursor'], $node['legacy_matches'], $node['current_matches'] );
		if ( $group ) {
			if ( $node['update_group'] ) {
				$this->claim( 'legacy_' . md5( $node['legacy'] ), $node['id'] );
				if ( (string) (int) $node['legacy'] === $node['legacy'] && $this->read_plan( 'native_' . $node['legacy'] ) ) {
					$this->code          = 7;
					$this->mapping_error = true;
				}
			}
			if ( ! $this->code ) {
				$this->claim( 'group_' . $group, $node['id'] );
			}
			$source = $this->read_group_forum_source( $group );
			// Candidate queries retain MySQL's comparison semantics for current IDs.
			if ( ! $this->code && ( ! $source || ! $source['exists'] || is_serialized( $source['forum']['value'] ) ) ) {
				$this->code = 12;
			}
			$node['group_source'] = $source;
		}
		if ( isset( $node['expected'] ) && ! $this->code ) {
			$actual = array(
				'group'        => $node['group'],
				'update_group' => $node['update_group'],
				'group_source' => isset( $node['group_source'] ) ? $node['group_source'] : false,
			);
			if ( $actual !== $node['expected'] ) {
				$this->code = 12;
			}
			unset( $node['expected'] );
		}
		if ( ! $this->code ) {
			$this->write_plan( 'forum_' . $node['id'], $node );
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
			$this->code = 9;
			return false;
		}
		list( $hidden_read, $hidden )   = bbp_admin_repair_get_option( '_bbp_hidden_forums', array(), false );
		list( $private_read, $private ) = bbp_admin_repair_get_option( '_bbp_private_forums', array(), false );
		$hidden_ids  = $this->ids( $hidden );
		$private_ids = $this->ids( $private );
		if ( ! $hidden_read || ! $private_read || false === $hidden_ids || false === $private_ids ) {
			$this->code = 9;
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
	 * @param array $node Forum migration plan.
	 */
	private function apply( $node ) {
		$id       = $node['id'];
		$group    = $node['group'];
		$forum    = $this->read_forum_source( $id );
		$source   = $this->read_group_forum_source( $group );
		$value    = $source ? $source['forum']['value'] : null;
		$expected = $node['group_source']['forum']['value'];
		$target   = $source ? $this->group_visibility_target( $source ) : '';
		$desired  = array( $group );
		if ( $this->code ) {
			return;
		}
		// Accept our own partial writes, but never overwrite a changed mapping.
		if ( ! $source || ! $source['exists'] || ! $forum || $forum['legacy_field'] !== $node['legacy_field']
			|| ( $forum['groups'] !== $node['groups'] && $forum['groups'] !== $desired )
			|| ( $value !== $expected && (string) $value !== (string) $id ) ) {
			$this->code = 12;
			return;
		}
		if ( $forum['groups'] !== $desired ) {
			if ( ! $this->dry_run ) {
				if ( ! bbp_admin_repair_metadata_update( 'post', $id, '_bbp_group_ids', $desired ) ) {
					$this->code = 8;
					return;
				}
			}
			++$this->counts['forums'];
		}
		if ( $node['update_group'] && (string) $value !== (string) $id ) {
			if ( ! $this->dry_run ) {
				$this->db->query( $this->db->prepare( "UPDATE `{$this->groupmeta_table}` SET `meta_value` = %d WHERE `group_id` = %d AND `meta_key` = %s AND BINARY `meta_value` = BINARY %s", $id, $group, 'forum_id', $expected ) );
				$failed = ! empty( $this->db->last_error );
				wp_cache_delete( $group, 'group_meta' );
				$stored = $this->read_group_forum_source( $group );
				if ( $failed || ! $stored || ! $stored['exists'] || (string) $stored['forum']['value'] !== (string) $id ) {
					$this->code = 5;
					return;
				}
			}
			++$this->counts['groups'];
		}
		if ( $target && ! $this->visibility_matches( $id, $target, $current_visibility ) && ! $this->code ) {
			if ( ! $this->dry_run ) {
				if ( bbp_get_public_status_id() === $target ) {
					bbp_publicize_forum( $id, $current_visibility );
				} else {
					bbp_hide_forum( $id, $current_visibility );
				}
				if ( ! $this->visibility_matches( $id, $target ) ) {
					$this->code = 9;
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
		$old   = function_exists( 'bp_forums_parent_forum_id' ) ? bp_forums_parent_forum_id() : ( defined( 'BP_FORUMS_PARENT_FORUM_ID' ) ? (int) BP_FORUMS_PARENT_FORUM_ID : 1 );
		$posts = $this->db->get_results( $this->db->prepare( "SELECT DISTINCT p.ID, p.post_type, p.post_parent, p.post_status, p.post_title, p.post_name FROM {$this->db->posts} p INNER JOIN {$this->db->postmeta} m ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key = '_bbp_old_forum_id' AND CAST(m.meta_value AS SIGNED) = %d ORDER BY p.ID LIMIT 2", $this->forum_post_type, $old ), ARRAY_A );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 2;
			return false;
		}
		if ( count( $posts ) > 1 ) {
			$this->code          = 7;
			$this->mapping_error = true;
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
	private function root() {
		$planned = $this->read_plan( 'root' );
		$current = $this->read_root_source();
		if ( $this->code ) {
			return;
		}
		if ( ! is_array( $planned ) || ! isset( $planned['ID'] ) || ! is_int( $planned['ID'] ) || ! $current || $planned['ID'] !== $current['ID'] ) {
			$this->code = 12;
			return;
		}
		$id = (int) $current['ID'];
		if ( $id ) {
			foreach ( array( 'post_type', 'post_title', 'post_name', 'post_status' ) as $field ) {
				if ( ! isset( $planned[ $field ] ) || ! is_string( $planned[ $field ] ) ) {
					$this->code = 12;
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
					$this->code = 12;
					return;
				}
				$title = $planned['target_title'];
				$slug  = $planned['target_slug'];
				if ( ! $planned['rename_attempted'] && ( $current['post_title'] !== $planned['post_title'] || $current['post_name'] !== $planned['post_name'] ) ) {
					$this->code = 12;
					return;
				}
				$rename = $current['post_title'] !== $title || $current['post_name'] !== $slug;
				if ( ! $this->dry_run && $rename ) {
					$planned['rename_attempted'] = true;
					$this->write_plan( 'root', $planned );
					if ( $this->code ) {
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
						$this->code = 6;
						return;
					}
				}
				$this->counts['rename'] += (int) $rename;
			} elseif ( $planned['post_title'] !== $current['post_title'] || $planned['post_name'] !== $current['post_name'] ) {
				$this->code = 12;
				return;
			}
			if ( ! bbp_admin_repair_option_matches( '_bbp_group_forums_root_id', $id ) ) {
				if ( ! $this->dry_run && ! bbp_admin_repair_update_option( '_bbp_group_forums_root_id', $id ) ) {
					$this->code = 10;
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
						$this->code = 13;
						return;
					}
				}
				$this->counts['roles'][] = $role;
			}
		}
	}
}
