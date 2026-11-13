<?php

/**
 * bbPress Admin Forum Repair Traversal.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Resumable, post-order forum traversal shared by repair tools.
 *
 * Nodes live in non-autoloaded options so a request need not load the complete
 * hierarchy. Default child discovery is paginated. A custom child-list filter
 * still receives its complete list, as required by that existing API.
 *
 * @since 2.7.0 bbPress (r8299)
 * @since 2.7.0 bbPress (r8315) Revalidate shared source identity before writes and child merges.
 * @access private
 */
abstract class BBP_Admin_Forum_Repair extends BBP_Admin_Batch_Worker {

	/**
	 * Maximum synchronous Dry Run restarts in one request.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @var int
	 */
	const SYNCHRONOUS_RESTART_LIMIT = 3;

	/**
	 * Repair operation type.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Ordered metadata keys written for each node.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @var array
	 */
	protected $meta_keys;

	/**
	 * Source types, statuses, and filter state captured for this run.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var array
	 */
	protected $context;

	/**
	 * Whether the current checkpoint was loaded from storage.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var bool
	 */
	protected $persisted = false;

	/**
	 * Traversal nodes loaded during the current request, keyed by post ID.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var array
	 */
	protected $nodes = array();

	/**
	 * Option names created by a synchronous Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var array
	 */
	protected $created_options = array();

	/**
	 * Option names already inspected during cleanup.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var array
	 */
	protected $inspected_options = array();

	/**
	 * Changed node IDs recorded during the current request.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var array
	 */
	protected $changed_ids = array();

	/**
	 * In-progress metadata cleanup state for a browser Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var array|null
	 */
	protected $preview_metadata = null;

	/**
	 * Whether malformed saved progress was restarted.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var bool
	 */
	protected $restarted = false;

	/**
	 * Whether the saved traversal tree is malformed or stale.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var bool
	 */
	protected $corrupt = false;

	/**
	 * Whether the current request should stop at a safe boundary.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var bool
	 */
	protected $stop = false;

	/**
	 * Whether preview batch estimation reached a semantic phase boundary.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var bool
	 */
	protected $phase_boundary = false;

	/**
	 * Aggregate number of repaired nodes in a browser Dry Run.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var int
	 */
	protected $preview_repaired = 0;

	/**
	 * Run one bounded request, preserving a replayable path through the tree.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array Repair result and continuation state.
	 */
	public function run() {
		$complete       = false;
		$initial        = true;
		$batches        = 0;
		$estimated_work = 0;
		$restarts       = 0;
		$this->async = $this->dry_run && bbp_admin_repair_tool_is_async_dry_run();
		if ( ! $this->valid_type() ) {
			$this->fail( 'source-invalid' );
		} else {
			list( $read, $saved ) = bbp_admin_repair_tool_get_checkpoint( $this->tool_id, $this->dry_run );
			if ( ! $read ) {
				$this->fail( 'progress' );
				return $this->result(
					array(
						'repaired'  => 0,
						'initial'   => false,
						'restarted' => false,
						'batches'   => 0,
					),
					false
				);
			}
			if ( ( false !== $saved ) && ! $this->valid_state( $saved ) ) {
				$saved           = false;
				$this->restarted = true;
			}
			$initial         = false === $saved;
			$this->persisted = ! $initial;
			$this->state     = $initial ? $this->new_state() : $saved;
			if ( ! $this->error && $this->async ) {
				$preview = array(
					'initial'        => true,
					'batches'        => 0,
					'estimated_work' => 0,
					'repaired'       => 0,
				);
				$data = bbp_admin_repair_tool_restore_preview( $this->state, $preview, array_keys( $preview ) );
				$initial                = $preview['initial'];
				$batches                = $preview['batches'];
				$estimated_work         = $preview['estimated_work'];
				$this->preview_repaired = $preview['repaired'];
				$this->preview_metadata = isset( $data['metadata'] ) ? $data['metadata'] : null;
				$this->load_preview_state( $data );
			}
			if ( ! $this->error && ( ! $this->dry_run || $this->async ) && $initial && ! $this->save() ) {
				$this->fail( 'progress' );
			}
			$batch_size = bbp_admin_repair_tool_batch_size( $this->tool_id );
			$total_work = 0;
			$batch_work = 0;
			// Direct Dry Runs remain synchronous; browser Dry Runs stop at one request boundary.
			while ( ! $this->error && ! $complete && ! $this->stop && ( ( $this->dry_run && ! $this->async ) || $batch_work < $batch_size ) ) {
				$phase       = isset( $this->state['phase'] ) ? $this->state['phase'] : '';
				$remaining   = $batch_size - $batch_work;
				$step_work   = max( 1, $this->step( $remaining, $complete ) );
				$total_work += $step_work;
				$batch_work += $step_work;
				if ( $this->async ) {
					$estimated_work += $this->preview_estimated_work( $phase, $step_work );
					while ( $estimated_work >= $batch_size ) {
						++$batches;
						$estimated_work -= $batch_size;
					}
					if ( $this->phase_boundary ) {
						$batches              += $estimated_work ? 1 : 0;
						$estimated_work         = 0;
						$this->phase_boundary = false;
					}
				} elseif ( $this->dry_run && ( $batch_work >= $batch_size || $this->phase_boundary ) ) {
					++$batches;
					$batch_work           = 0;
					$this->phase_boundary = false;
				}
				if ( $this->corrupt || ( 'source-invalid' === $this->error ) ) {
					if ( $this->corrupt && $this->dry_run && ! $this->async && ++$restarts > self::SYNCHRONOUS_RESTART_LIMIT ) {
						$this->fail( 'source-invalid' );
						break;
					}
					$this->state     = $this->new_state();
					$this->nodes     = array();
					$this->persisted = $this->corrupt = false;
					$this->restarted = true;
					// Publish the new generation before prepare can remove the old tree.
					if ( ( ! $this->dry_run || $this->async ) && ! $this->error && ! $this->save() ) {
						$this->fail( 'progress' );
					}
				}
			}
			if ( $this->async && $complete && $estimated_work ) {
				++$batches;
				$estimated_work = 0;
			} elseif ( $this->dry_run && ! $this->async && $batch_work ) {
				++$batches;
			}
			if ( ! $this->dry_run || $this->async ) {
				if ( $this->async && false !== $this->state ) {
					$preview = array(
						'initial'        => $initial,
						'batches'        => max( 1, $batches ),
						'estimated_work' => $estimated_work,
						'repaired'       => $this->preview_repaired,
					);
					$data = array( 'metadata' => $this->preview_metadata );
					$this->save_preview_state( $data );
					bbp_admin_repair_tool_store_preview( $this->state, $preview, array_keys( $preview ), $data );
				}
				if ( false !== $this->state && ! $this->save( ! $this->error && $complete ) ) {
					$this->fail( 'progress' );
				}
				if ( ! $this->dry_run && $total_work ) {
					wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
				}
			}
		}
		return $this->result(
			array(
				'repaired'  => $this->async ? $this->preview_repaired : count( $this->changed_ids ),
				'batches'   => $this->dry_run ? max( 1, $batches ) : 1,
				'initial'   => $initial,
				'restarted' => $this->restarted,
			),
			$complete
		);
	}

	/**
	 * Validate a saved traversal checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $state Saved checkpoint.
	 * @return bool Whether the checkpoint can be resumed.
	 */
	protected function valid_state( $state ) {
		if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'root', 'current' ) ) ) {
			return false;
		}
		$valid = isset( $state['phase'], $state['token'], $state['context'] )
			&& is_string( $state['token'] ) && ( 32 === strlen( $state['token'] ) ) && ctype_xdigit( $state['token'] )
			&& in_array( $state['phase'], $this->phases(), true )
			&& ( $state['context'] === $this->context );
		if ( $valid && isset( $state['_preview_state'] ) ) {
			$valid = bbp_admin_repair_tool_is_valid_preview_state(
				$state,
				array( 'batches', 'estimated_work', 'repaired' ),
				array( 'initial' )
			) && array_key_exists( 'metadata', $state['_preview_state']['data'] )
				&& ( null === $state['_preview_state']['data']['metadata'] || is_array( $state['_preview_state']['data']['metadata'] ) )
				&& $this->valid_preview_state( $state['_preview_state']['data'] );
		}
		if ( ! $valid ) {
			return false;
		}
		return $state['current']
			? ( $state['root'] > $state['cursor'] && $state['root'] <= $state['upper_id'] && in_array( $state['phase'], $this->active_phases(), true ) )
			: ( 0 === $state['root'] );
	}

	/**
	 * Create a new traversal checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array|false New checkpoint, or false after a database failure.
	 */
	protected function new_state() {
		$upper = $this->upper_id();
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
			return false;
		}
		return array(
			'cursor'   => 0,
			'upper_id' => $upper,
			'root'     => 0,
			'current'  => 0,
			'phase'    => 'prepare',
			'token'    => str_replace( '-', '', wp_generate_uuid4() ),
			'context'  => $this->context
		);
	}

	/**
	 * Return the option-name prefix for traversal nodes.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return string Traversal node prefix.
	 */
	protected function node_option_prefix() {
		return bbp_admin_repair_tool_batch_option_name( $this->tool_id ) . '_tree_';
	}

	/**
	 * Return the option name for one traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int $id Post ID.
	 * @return string Traversal node option name.
	 */
	protected function node_option_name( $id ) {
		return $this->node_option_prefix() . $this->state['token'] . '_' . $id;
	}

	/**
	 * Load and validate one traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int $id Post ID.
	 * @return array|false Traversal node, or false when none exists or it is invalid.
	 */
	protected function get_node( $id ) {
		if ( ! array_key_exists( $id, $this->nodes ) ) {
			$this->nodes[ $id ] = $this->read_node( $id );
		}
		$node = $this->nodes[ $id ];
		if ( false !== $node ) {
			$valid = is_array( $node ) && isset( $node['phase'], $node['values'], $node['counted'], $node['children'], $node['changed'] );
			foreach ( array( 'id', 'parent', 'depth', 'next', 'await', 'edge', 'key' ) as $field ) {
				$valid = $valid && isset( $node[ $field ] ) && is_int( $node[ $field ] ) && ( $node[ $field ] >= 0 );
			}
			$valid = $valid && ( $node['id'] === $id ) && in_array( $node['phase'], array( 'children', 'write', 'done' ), true )
				&& is_bool( $node['counted'] ) && is_bool( $node['changed'] ) && is_array( $node['values'] ) && ( array_keys( $node['values'] ) === $this->meta_keys )
				&& ( false === $node['children'] || is_array( $node['children'] ) ) && ( $node['key'] <= count( $this->meta_keys ) );
			if ( $valid ) {
				foreach ( $node['values'] as $key => $value ) {
					$valid = $valid && $this->valid_value( $key, $value, $node['counted'] );
				}
				$valid = $valid && ( 'write' !== $node['phase'] || count( $this->meta_keys ) > $node['key'] );
				$valid = $valid && ( 'done' !== $node['phase'] || count( $this->meta_keys ) === $node['key'] );
				$valid = $valid && ( $node['parent'] !== $id ) && ( $node['await'] !== $id );
				$valid = $valid && ( $node['parent'] ? ( $node['depth'] > 0 ) : ( 0 === $node['depth'] ) );
			}
			if ( ! $valid || ! $this->valid_extra_node( $node ) ) {
				$this->corrupt = true;
				return false;
			}
		}
		return $node;
	}

	/**
	 * Persist one traversal node and cache it for this request.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Traversal node.
	 * @return bool Whether the node was saved.
	 */
	protected function persist_node( $node ) {
		$id = $node['id'];
		if ( ! $this->save_node( $node ) ) {
			$this->fail( 'progress' );
			return false;
		}
		$this->nodes[ $id ] = $node;
		return true;
	}

	/**
	 * Read one traversal node from storage.
	 *
	 * Allows independent work to use the same node validation and field writer.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int $id Post ID.
	 * @return array|false Stored node, or false when no node exists.
	 */
	protected function read_node( $id ) {
		if ( ! $this->persisted ) {
			return false;
		}
		list( $read, $node ) = bbp_admin_repair_get_option( $this->node_option_name( $id ) );
		if ( ! $read ) {
			$this->fail( 'progress' );
		}
		return $node;
	}

	/**
	 * Save one traversal node to storage.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Traversal node.
	 * @return bool Whether the node was saved or safely retained in memory.
	 */
	protected function save_node( $node ) {
		$id = $node['id'];
		if ( $this->dry_run && ! $this->async ) {
			$this->created_options[ $this->node_option_name( $id ) ] = true;
		}
		return ( $this->dry_run && ! $this->async ) || bbp_admin_repair_tool_save_option( $this->node_option_name( $id ), $node );
	}

	/**
	 * Enter an existing node or create a new traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int  $id        Post ID.
	 * @param int  $parent_id Parent post ID in this traversal.
	 * @param bool $counted   Whether to calculate values or prune stale metadata.
	 * @return array|false Traversal node, deferred marker, or false on failure.
	 */
	protected function enter_node( $id, $parent_id, $counted ) {
		$id   = $this->normalize_id( $id, $counted );
		$node = $this->get_node( $id );
		if ( $this->corrupt || $this->error ) {
			return false;
		}
		// Only completed counted nodes are still reachable when pruning begins.
		if ( ! $counted && false !== $node && $node['counted'] && 'done' !== $node['phase'] ) {
			$node = false;
		}
		if ( false !== $node && ( $node['counted'] || ! $counted ) ) {
			if ( ( 'done' !== $node['phase'] ) && ( $parent_id !== $node['parent'] ) ) {
				$this->fail( 'source-invalid' );
				return false;
			}
			return $node;
		}
		$post = $this->source_post( $id );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
			return false;
		}
		if ( empty( $post ) ) {
			$this->fail( 'source-invalid' );
			return false;
		}
		if ( $id > $this->state['upper_id'] ) {
			return array( 'deferred' => true );
		}
		$values = $counted ? $this->values( $id, $post ) : array_fill_keys( $this->meta_keys, null );
		if ( $this->error ) {
			return false;
		}
		$parent_node = $parent_id ? $this->get_node( $parent_id ) : false;
		if ( $this->error ) {
			return false;
		}
		$node        = array(
			'id'       => $id,
			'parent'   => $parent_id,
			'depth'    => $parent_node ? $parent_node['depth'] + 1 : 0,
			'phase'    => $this->node_phase( $post, $counted ),
			'counted'  => $counted,
			'changed'  => false,
			'values'   => $values,
			'next'     => 0,
			'await'    => 0,
			'edge'     => 0,
			'key'      => 0,
			'children' => false
		);
		$node = $this->prepare_node( $node, $post );
		if ( $counted && 'children' === $node['phase'] && $this->context['filtered'] ) {
			$children = $this->child_ids( $id );
			if ( $this->error ) {
				return false;
			}
			$node['children'] = array_values( array_map( 'absint', (array) apply_filters( 'bbp_forum_query_subforum_ids', $children, $id ) ) );
		}
		return $this->persist_node( $node ) ? $node : false;
	}

	/**
	 * Query child forum IDs for one traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int      $id     Parent forum ID.
	 * @param int|null $cursor Last child ID, or null to return the complete list.
	 * @return array Child forum IDs.
	 */
	protected function child_ids( $id, $cursor = null ) {
		if ( empty( $this->context['forums'] ) ) {
			return array();
		}
		$slots      = implode( ', ', array_fill( 0, count( $this->context['forums'] ), '%s' ) );
		$args       = array_merge( array( $id, $this->context['forum'] ), $this->context['forums'], array( $this->state['upper_id'] ) );
		$sql        = "SELECT ID FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s AND post_status IN ({$slots}) AND ID <= %d";
		$descending = 'topics' !== $this->type;
		if ( null !== $cursor && $cursor ) {
			$sql .= $descending ? ' AND ID < %d' : ' AND ID > %d';
			$args[] = $cursor;
		}
		$sql .= $descending ? ' ORDER BY ID DESC' : ' ORDER BY ID ASC';
		if ( null !== $cursor ) {
			$sql .= ' LIMIT 1';
		}
		$ids = $this->db->get_col( $this->db->prepare( $sql, $args ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
		}
		return $ids;
	}

	/**
	 * Advance one node through its child traversal.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Traversal node.
	 */
	protected function visit_children( $node ) {
		// Persist each edge before descending. Clear it only with its merged total,
		// so replaying either checkpoint cannot add a completed child twice.
		if ( $node['await'] ) {
			$child = $this->get_node( $node['await'] );
			if ( $this->error ) {
				return;
			}
			if ( false === $child ) {
				$this->corrupt = true;
				return;
			}
			if ( $child['parent'] !== $node['id'] || $child['depth'] !== $node['depth'] + 1 ) {
				$this->corrupt = true;
				return;
			}
			if ( 'done' !== $child['phase'] ) {
				$this->state['current'] = $child['id'];
				return;
			}
			if ( ! $this->merge_checked_child( $node, $child, $node['edge'] ) || $this->error || $this->corrupt ) {
				return;
			}
			$node['await'] = 0;
			$this->persist_node( $node );
			return;
		}
		if ( false === $node['children'] ) {
			$children = $this->child_ids( $node['id'], $node['next'] );
			$edge     = ! empty( $children ) ? (int) $children[0] : 0;
			$next     = $edge;
		} else {
			$edge = isset( $node['children'][ $node['next'] ] ) ? $node['children'][ $node['next'] ] : 0;
			if ( array_key_exists( $node['next'], $node['children'] ) && ( ! is_int( $edge ) || $edge < 1 ) ) {
				$this->fail( 'source-invalid' );
				return;
			}
			$next = $node['next'] + 1;
		}
		if ( $this->error ) {
			return;
		}
		if ( ! $edge ) {
			$node['phase'] = 'write';
			$this->persist_node( $node );
			return;
		}
		$child = $this->enter_node( $edge, $node['id'], true );
		if ( false === $child ) {
			return;
		}
		$node['next'] = $next;
		if ( ! empty( $child['deferred'] ) ) {
			$this->persist_node( $node );
			return;
		}
		if ( 'done' === $child['phase'] ) {
			if ( ! $this->merge_checked_child( $node, $child, $edge ) || $this->error || $this->corrupt ) {
				return;
			}
		} else {
			$node['await'] = $child['id'];
			$node['edge']  = $edge;
		}
		if ( $this->persist_node( $node ) && $node['await'] ) {
			$this->state['current'] = $child['id'];
		}
	}

	/**
	 * Verify one stored metadata value after writing it.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int    $id    Post ID.
	 * @param string $key   Metadata key.
	 * @param mixed  $value Expected metadata value.
	 * @return bool Whether the stored value matches.
	 */
	protected function verify( $id, $key, $value ) {
		$mismatches = bbp_admin_repair_metadata_mismatches( 'post', $key, array( $id => $value ) );
		if ( false === $mismatches ) {
			$this->fail( $this->meta_error() );
			return false;
		}
		if ( $mismatches ) {
			$this->fail( 'metadata-verify' );
		}
		return empty( $mismatches );
	}

	/**
	 * Reconcile one metadata field for a traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node  Traversal node.
	 * @param int   $limit Maximum metadata rows to inspect.
	 * @return int Work units consumed.
	 */
	protected function write_node_field( $node, $limit ) {
		if ( ! $this->before_write( $node ) ) {
			return 1;
		}
		$key    = $this->meta_keys[ $node['key'] ];
		$value  = $node['values'][ $key ];
		$result = bbp_admin_repair_single_metadata( 'post', $node['id'], $key, $value, $limit, $this->dry_run, $this->preview_metadata );
		if ( $result['code'] ) {
			// Translate the shared metadata helper's codes into this worker's contract.
			$codes = array(
				2 => $this->meta_error(),
				3 => 'metadata-write',
				4 => 'metadata-write',
				5 => 'metadata-verify'
			);
			$this->fail( $codes[ $result['code'] ] );
			return $result['work'];
		}
		if ( $result['changed'] ) {
			$node['changed'] = true;
		}
		if ( $result['complete'] ) {
			if ( ! $this->dry_run && ! $this->verify( $node['id'], $key, $value ) ) {
				$node['key'] = 0;
				$this->persist_node( $node );
				return $result['work'];
			}
			$node = $this->after_key( $node, $key );
			if ( $this->error ) {
				return $result['work'];
			}
			++$node['key'];
			if ( $this->async ) {
				$this->preview_metadata = null;
			}
			if ( count( $this->meta_keys ) === $node['key'] ) {
				foreach ( $node['values'] as $field => $expected ) {
					if ( ! $this->dry_run && ! $this->verify( $node['id'], $field, $expected ) ) {
						$node['key'] = 0;
						$this->persist_node( $node );
						return $result['work'];
					}
				}
				$node['phase'] = 'done';
				if ( $node['changed'] ) {
					$this->changed_ids[ $node['id'] ] = true;
				}
				$this->completed_node( $node );
			}
		}
		$this->persist_node( $node );
		return $result['work'];
	}

	/**
	 * Remove temporary traversal-node options in bounded work.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int  $limit    Maximum options to inspect.
	 * @param bool $complete Whether cleanup is complete, passed by reference.
	 * @return int Work units consumed.
	 */
	protected function cleanup( $limit, &$complete ) {
		$preview = $this->dry_run && ! $this->async;
		list( $success, $work, $complete ) = bbp_admin_repair_tool_cleanup_options( $this->node_option_prefix(), $limit, $preview, $this->created_options, $this->inspected_options );
		if ( ! $success ) {
			$this->fail( 'progress' );
		}
		return $work;
	}

	/**
	 * Process one bounded traversal step.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int  $limit    Maximum work available to this step.
	 * @param bool $complete Whether the complete traversal is finished, passed by reference.
	 * @return int Work units consumed.
	 */
	protected function step( $limit, &$complete ) {
		if ( in_array( $this->state['phase'], array( 'prepare', 'cleanup' ), true ) ) {
			$finished = false;
			$work     = $this->cleanup( $limit, $finished );
			if ( $finished ) {
				if ( 'prepare' === $this->state['phase'] ) {
					$this->state['phase'] = 'count';
					$this->persisted      = false;
					$this->nodes          = array();
				} else {
					$complete = true;
				}
			}
			return $work;
		}
		if ( ! $this->state['current'] ) {
			$args    = array( $this->context['forum'], $this->state['cursor'], $this->state['upper_id'] );
			$sql     = "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d";
			$counted = 'count' === $this->state['phase'];
			if ( $counted && null !== $this->root_statuses() ) {
				$statuses = $this->root_statuses();
				$slots    = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
				$sql .= $slots ? " AND post_status IN ({$slots})" : ' AND 1 = 0';
				$args  = array_merge( $args, $statuses );
			}
			$id = (int) $this->db->get_var( $this->db->prepare( $sql . ' ORDER BY ID LIMIT 1', $args ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->fail( 'source-read' );
				return 1;
			}
			if ( ! $id ) {
				// Commit the phase before cleanup can remove nodes still named by a
				// previous checkpoint. Failed final saves can then resume safely.
				$this->state['phase']  = $counted ? 'prune' : 'cleanup';
				$this->state['cursor'] = 0;
				if ( ( ! $this->dry_run || $this->async ) && ! $this->save() ) {
					$this->fail( 'progress' );
				}
				return 1;
			}
			$node = $this->enter_node( $id, 0, $counted );
			if ( false !== $node ) {
				if ( ! empty( $node['deferred'] ) || 'done' === $node['phase'] ) {
					$this->state['cursor'] = $id;
				} else {
					$this->state['root']    = $id;
					$this->state['current'] = $node['id'];
				}
			}
			return 1;
		}
		$node = $this->get_node( $this->state['current'] );
		if ( $this->error ) {
			return 1;
		}
		if ( false === $node ) {
			$this->corrupt = true;
			return 1;
		}
		switch ( $node['phase'] ) {
			case 'children':
				$this->visit_children( $node );
				break;
			case 'write':
				return $this->write_node_field( $node, $limit );
			case 'done':
				if ( $this->async ) {
					if ( $node['changed'] ) {
						++$this->preview_repaired;
					}
					$this->leaving_node( $node );
				}
				if ( $node['parent'] ) {
					$parent_node = $this->get_node( $node['parent'] );
					if ( $this->error ) {
						break;
					}
					if ( false === $parent_node || $node['depth'] !== $parent_node['depth'] + 1 ) {
						$this->corrupt = true;
						break;
					}
				}
				$this->state['current'] = $node['parent'];
				if ( ! $node['parent'] ) {
					$this->state['cursor'] = $this->state['root'];
					$this->state['root']   = 0;
				}
		}
		return 1;
	}

	/**
	 * Check whether the configured repair type is supported.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return bool Whether the repair type is valid.
	 */
	protected function valid_type() {
		return true;
	}

	/**
	 * Return every valid traversal phase.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array Traversal phase names.
	 */
	protected function phases() {
		return array( 'prepare', 'count', 'prune', 'cleanup' );
	}

	/**
	 * Return phases that may have an active traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array Active traversal phase names.
	 */
	protected function active_phases() {
		return array( 'count', 'prune' );
	}

	/**
	 * Validate one calculated node value.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param string $key     Metadata key.
	 * @param mixed  $value   Calculated value.
	 * @param bool   $counted Whether the node is being counted or pruned.
	 * @return bool Whether the value is valid.
	 */
	protected function valid_value( $key, $value, $counted ) {
		return $counted ? is_int( $value ) : null === $value;
	}

	/**
	 * Validate fields added to a node by the concrete repair.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Traversal node.
	 * @return bool Whether the additional fields are valid.
	 */
	protected function valid_extra_node( $node ) {
		return isset( $node['source'] ) && is_array( $node['source'] )
			&& isset( $node['source']['parent'], $node['source']['status'], $node['source']['type'] )
			&& is_int( $node['source']['parent'] ) && $node['source']['parent'] >= 0
			&& is_string( $node['source']['status'] ) && is_string( $node['source']['type'] );
	}

	/**
	 * Return the highest forum ID included in this traversal.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return int Highest forum ID.
	 */
	protected function upper_id() {
		return (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type = %s", $this->context['forum'] ) );
	}

	/**
	 * Return statuses that identify counted root forums.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array|null Forum statuses, or null to include every status.
	 */
	protected function root_statuses() {
		return $this->context['forums'];
	}

	/**
	 * Normalize a source ID before entering its traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int  $id      Source post ID.
	 * @param bool $counted Whether the node is being counted or pruned.
	 * @return int Normalized post ID.
	 */
	protected function normalize_id( $id, $counted ) {
		$id = (int) $id;
		return $counted && 'topics' !== $this->type ? (int) apply_filters( 'bbp_get_forum_id', $id, $id ) : $id;
	}

	/**
	 * Read the source post fields used to build a node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int $id Post ID.
	 * @return object|null Source row, or null when the post does not exist.
	 */
	protected function source_post( $id ) {
		return $this->db->get_row( $this->db->prepare( "SELECT ID, post_parent, post_status, post_type FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $this->context['forum'] ) );
	}

	/**
	 * Choose the first processing phase for a new node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param object $post    Source post row.
	 * @param bool   $counted Whether the node is being counted or pruned.
	 * @return string Node phase.
	 */
	protected function node_phase( $post, $counted ) {
		return $counted ? 'children' : 'write';
	}

	/**
	 * Add shared source identity to a new traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array  $node Traversal node.
	 * @param object $post Source post row.
	 * @return array Prepared traversal node.
	 */
	protected function prepare_node( $node, $post ) {
		$node['source'] = $this->source_identity( $post );
		return $node;
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Subclasses may update the node after a particular metadata key completes.
	/**
	 * Adjust a node after one metadata key is complete.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array  $node Traversal node.
	 * @param string $key  Completed metadata key.
	 * @return array Updated traversal node.
	 */
	protected function after_key( $node, $key ) {
		return $node;
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

	/**
	 * Normalize source fields used to detect changes during a run.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param object $post Source post row.
	 * @return array Normalized source identity.
	 */
	protected function source_identity( $post ) {
		return array(
			'parent' => (int) $post->post_parent,
			'status' => $post->post_status,
			'type'   => $post->post_type,
		);
	}

	/**
	 * Revalidate a node's source identity immediately before writing.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8315) Revalidate source identity before writes.
	 *
	 * @param array $node Traversal node, passed by reference.
	 * @return bool Whether the node remains safe to write.
	 */
	protected function before_write( &$node ) {
		if ( $this->dry_run ) {
			return true;
		}
		$post = $this->source_post( $node['id'] );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
			return false;
		}
		if ( ! $post || $this->source_identity( $post ) !== $node['source'] ) {
			$this->corrupt = true;
			return false;
		}
		return true;
	}

	/**
	 * Revalidate and merge one completed child into its parent.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param array $parent_node Parent traversal node, passed by reference.
	 * @param array $child       Completed child traversal node.
	 * @param int   $edge        Child post ID used by the parent edge.
	 * @return bool Whether the child was ready and merged.
	 */
	protected function merge_checked_child( &$parent_node, $child, $edge ) {
		if ( ! $this->before_write( $child ) ) {
			return false;
		}
		if ( 'done' !== $child['phase'] ) {
			if ( $this->persist_node( $child ) ) {
				$this->state['current'] = $child['id'];
			}
			return false;
		}
		$this->merge_child( $parent_node, $child, $edge );
		return true;
	}

	/**
	 * Act when all metadata fields for a node are complete.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Completed traversal node, passed by reference.
	 */
	protected function completed_node( &$node ) {}

	/**
	 * Validate subclass data in a browser Dry Run checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $data Preview simulation data.
	 * @return bool Whether the subclass data is valid.
	 */
	protected function valid_preview_state( $data ) {
		return true;
	}

	/**
	 * Restore subclass data when a browser Dry Run resumes.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $data Preview simulation data.
	 */
	protected function load_preview_state( $data ) {}

	/**
	 * Add subclass data to a browser Dry Run checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $data Preview simulation data, passed by reference.
	 */
	protected function save_preview_state( &$data ) {}

	/**
	 * Record a completed node immediately before its cursor advances.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @param array $node Completed traversal node.
	 */
	protected function leaving_node( $node ) {}

	/**
	 * Return work that a normal Run would count toward its request budget.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @param string $phase Traversal phase before the work was performed.
	 * @param int    $work  Work units consumed by the preview.
	 * @return int Estimated normal Run work.
	 */
	protected function preview_estimated_work( $phase, $work ) {
		return $work;
	}

	/**
	 * Calculate direct values for one source post.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int    $id   Source post ID.
	 * @param object $post Source post row.
	 * @return array Values keyed by metadata name.
	 */
	abstract protected function values( $id, $post );

	/**
	 * Return the failure reason for a metadata read failure.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return string Semantic failure reason.
	 */
	abstract protected function meta_error();

	/**
	 * Merge one completed child into its parent node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $parent_node Parent traversal node, passed by reference.
	 * @param array $child       Completed child traversal node.
	 * @param int   $edge        Child post ID used by the parent edge.
	 */
	abstract protected function merge_child( &$parent_node, $child, $edge );
}
