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
 * @access private
 */
abstract class BBP_Admin_Forum_Repair {

	protected $db;
	protected $type;
	protected $tool;
	protected $keys;
	protected $context;
	protected $state;
	protected $dry_run;
	protected $legacy = false;
	protected $persisted = false;
	protected $nodes = array();
	protected $created = array();
	protected $cleaned = array();
	protected $changed = array();
	protected $forums = array();
	protected $code = 0;
	protected $restarted = false;
	protected $corrupt = false;
	protected $stop = false;

	/** Run one bounded request, preserving a replayable path through the tree. */
	public function run() {
		$complete = false;
		$initial  = true;
		if ( ! $this->valid_type() ) {
			$this->code = 6;
		} else {
			$saved = $this->legacy ? false : get_option( bbp_admin_repair_tool_batch_option_name( $this->tool ), false );
			if ( ( false !== $saved ) && ! $this->valid_state( $saved ) ) {
				$saved           = false;
				$this->restarted = true;
			}
			$initial         = false === $saved;
			$this->persisted = ! $initial;
			$this->state     = $initial ? $this->new_state() : $saved;
			if ( ! $this->code && ! $this->dry_run && $initial && ! $this->save() ) {
				$this->code = 7;
			}
			$size = $this->legacy ? PHP_INT_MAX : bbp_admin_repair_tool_batch_size( $this->tool );
			$work = 0;
			while ( ! $this->code && ! $complete && ! $this->stop && ( $work < $size ) ) {
				$used  = $this->step( $size - $work, $complete );
				$work += max( 1, $used );
				if ( $this->corrupt || ( 6 === $this->code ) ) {
					$code            = $this->corrupt ? 0 : 6;
					$this->state     = $this->new_state();
					$this->nodes     = array();
					$this->persisted = $this->corrupt = false;
					$this->restarted = true;
					$this->code      = $this->code ? $this->code : $code;
					// Publish the new generation before prepare can remove the old tree.
					if ( ! $this->dry_run && ! $this->code && ! $this->save() ) {
						$this->code = 7;
					}
				}
			}
			if ( ! $this->dry_run ) {
				if ( false !== $this->state && ! $this->save( ! $this->code && $complete ) ) {
					$this->code = 7;
				}
				if ( $work ) {
					wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
				}
			}
		}
		return array(
			'code'      => $this->code,
			'repaired'  => count( $this->changed ),
			'initial'   => $initial,
			'complete'  => ! $this->code && $complete,
			'restarted' => $this->restarted
		);
	}

	protected function valid_state( $state ) {
		if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'root', 'current' ) ) ) {
			return false;
		}
		$valid = isset( $state['phase'], $state['token'], $state['context'] )
			&& is_string( $state['token'] ) && ( 32 === strlen( $state['token'] ) ) && ctype_xdigit( $state['token'] )
			&& in_array( $state['phase'], $this->phases(), true )
			&& ( $state['context'] === $this->context );
		if ( ! $valid ) {
			return false;
		}
		return $state['current']
			? ( $state['root'] > $state['cursor'] && $state['root'] <= $state['upper_id'] && in_array( $state['phase'], $this->active_phases(), true ) )
			: ( 0 === $state['root'] );
	}

	protected function new_state() {
		$upper = $this->upper_id();
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 3;
			return false;
		}
		return array(
			'cursor'   => 0,
			'upper_id' => $upper,
			'root'     => 0,
			'current'  => 0,
			'phase'    => $this->legacy ? 'count' : 'prepare',
			'token'    => str_replace( '-', '', wp_generate_uuid4() ),
			'context'  => $this->context
		);
	}

	protected function save( $complete = false ) {
		return bbp_admin_repair_tool_save_checkpoint( $this->tool, $this->state, $complete );
	}

	protected function prefix() {
		return bbp_admin_repair_tool_batch_option_name( $this->tool ) . '_tree_';
	}

	protected function option( $id ) {
		return $this->prefix() . $this->state['token'] . '_' . $id;
	}

	protected function node( $id ) {
		if ( ! array_key_exists( $id, $this->nodes ) ) {
			$this->nodes[ $id ] = $this->read_node( $id );
		}
		$node = $this->nodes[ $id ];
		if ( false !== $node ) {
			$valid = is_array( $node ) && isset( $node['phase'], $node['values'], $node['counted'], $node['children'] );
			foreach ( array( 'id', 'parent', 'depth', 'next', 'await', 'edge', 'key' ) as $field ) {
				$valid = $valid && isset( $node[ $field ] ) && is_int( $node[ $field ] ) && ( $node[ $field ] >= 0 );
			}
			$valid = $valid && ( $node['id'] === $id ) && in_array( $node['phase'], array( 'children', 'write', 'done' ), true )
				&& is_bool( $node['counted'] ) && is_array( $node['values'] ) && ( array_keys( $node['values'] ) === $this->keys )
				&& ( false === $node['children'] || is_array( $node['children'] ) ) && ( $node['key'] <= count( $this->keys ) );
			if ( $valid ) {
				foreach ( $node['values'] as $key => $value ) {
					$valid = $valid && $this->valid_value( $key, $value, $node['counted'] );
				}
				$valid = $valid && ( 'write' !== $node['phase'] || count( $this->keys ) > $node['key'] );
				$valid = $valid && ( 'done' !== $node['phase'] || count( $this->keys ) === $node['key'] );
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

	protected function put( $node ) {
		$id = $node['id'];
		if ( ! $this->save_node( $node ) ) {
			$this->code = 7;
			return false;
		}
		$this->nodes[ $id ] = $node;
		return true;
	}

	/** Allow independent work to use the same node validation and field writer. */
	protected function read_node( $id ) {
		return $this->persisted ? get_option( $this->option( $id ), false ) : false;
	}

	protected function save_node( $node ) {
		$id = $node['id'];
		if ( $this->dry_run && empty( $this->nodes[ $id ] ) ) {
			$this->created[ $this->option( $id ) ] = true;
		}
		return $this->dry_run || bbp_admin_repair_tool_save_option( $this->option( $id ), $node );
	}

	protected function enter( $id, $parent_id, $counted ) {
		$id = $this->normalize_id( $id, $counted );
		$node = $this->node( $id );
		if ( $this->corrupt ) {
			return false;
		}
		// Only completed counted nodes are still reachable when pruning begins.
		if ( ! $counted && false !== $node && $node['counted'] && 'done' !== $node['phase'] ) {
			$node = false;
		}
		if ( false !== $node && ( $node['counted'] || ! $counted ) ) {
			if ( ( 'done' !== $node['phase'] ) && ( $parent_id !== $node['parent'] ) ) {
				$this->code = 6;
				return false;
			}
			return $node;
		}
		$forum = $this->source_post( $id );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 3;
			return false;
		}
		if ( empty( $forum ) ) {
			$this->code = 6;
			return false;
		}
		if ( $id > $this->state['upper_id'] ) {
			return array( 'deferred' => true );
		}
		$values = $counted ? $this->values( $id, $forum ) : array_fill_keys( $this->keys, null );
		if ( $this->code ) {
			return false;
		}
		$parent_node = $parent_id ? $this->node( $parent_id ) : false;
		$node        = array(
			'id'       => $id,
			'parent'   => $parent_id,
			'depth'    => $parent_node ? $parent_node['depth'] + 1 : 0,
			'phase'    => $this->node_phase( $forum, $counted ),
			'counted'  => $counted,
			'values'   => $values,
			'next'     => 0,
			'await'    => 0,
			'edge'     => 0,
			'key'      => 0,
			'children' => false
		);
		$node = $this->prepare_node( $node, $forum );
		if ( $counted && 'children' === $node['phase'] && $this->context['filtered'] ) {
			$children = $this->children( $id );
			if ( $this->code ) {
				return false;
			}
			$node['children'] = array_values( array_map( 'absint', (array) apply_filters( 'bbp_forum_query_subforum_ids', $children, $id ) ) );
		}
		return $this->put( $node ) ? $node : false;
	}

	protected function children( $id, $cursor = null ) {
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
			$this->code = 3;
		}
		return $ids;
	}

	protected function visit( $node ) {
		// Persist each edge before descending. Clear it only with its merged total,
		// so replaying either checkpoint cannot add a completed child twice.
		if ( $node['await'] ) {
			$child = $this->node( $node['await'] );
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
			$this->merge_child( $node, $child, $node['edge'] );
			if ( $this->code || $this->corrupt ) {
				return;
			}
			$node['await'] = 0;
			$this->put( $node );
			return;
		}
		if ( false === $node['children'] ) {
			$children = $this->children( $node['id'], $node['next'] );
			$edge     = ! empty( $children ) ? (int) $children[0] : 0;
			$next     = $edge;
		} else {
			$edge = isset( $node['children'][ $node['next'] ] ) ? $node['children'][ $node['next'] ] : 0;
			if ( array_key_exists( $node['next'], $node['children'] ) && ( ! is_int( $edge ) || $edge < 1 ) ) {
				$this->code = 6;
				return;
			}
			$next = $node['next'] + 1;
		}
		if ( $this->code ) {
			return;
		}
		if ( ! $edge ) {
			$node['phase'] = 'write';
			$this->put( $node );
			return;
		}
		$child = $this->enter( $edge, $node['id'], true );
		if ( false === $child ) {
			return;
		}
		$node['next'] = $next;
		if ( ! empty( $child['deferred'] ) ) {
			$this->put( $node );
			return;
		}
		if ( 'done' === $child['phase'] ) {
			$this->merge_child( $node, $child, $edge );
			if ( $this->code || $this->corrupt ) {
				return;
			}
		} else {
			$node['await'] = $child['id'];
			$node['edge']  = $edge;
		}
		if ( $this->put( $node ) && $node['await'] ) {
			$this->state['current'] = $child['id'];
		}
	}

	protected function verify( $id, $key, $value ) {
		$row = $this->db->get_row( $this->db->prepare( "SELECT COUNT(*) AS row_count, MIN(meta_key) AS meta_key, MIN(meta_value) AS meta_value FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $key ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = $this->meta_error();
			return false;
		}
		$valid = ( null === $value ) ? ( 0 === (int) $row->row_count )
			: ( 1 === (int) $row->row_count && $key === $row->meta_key && (string) $value === $row->meta_value );
		if ( ! $valid ) {
			$this->code = 8;
		}
		return $valid;
	}

	protected function write( $node, $limit ) {
		if ( ! $this->before_write( $node ) ) {
			return 1;
		}
		$key    = $this->keys[ $node['key'] ];
		$value  = $node['values'][ $key ];
		$result = bbp_admin_repair_single_metadata( 'post', $node['id'], $key, $value, $limit, $this->dry_run );
		if ( $result['code'] ) {
			$codes = array(
				2 => $this->meta_error(),
				3 => 1,
				4 => 8,
				5 => 8
			);
			$this->code = $codes[ $result['code'] ];
			return $result['work'];
		}
		if ( $result['changed'] ) {
			$this->changed[ $node['id'] ] = true;
		}
		if ( $result['complete'] ) {
			if ( ! $this->dry_run && ! $this->verify( $node['id'], $key, $value ) ) {
				$node['key'] = 0;
				$this->put( $node );
				return $result['work'];
			}
			$node = $this->after_key( $node, $key );
			if ( $this->code ) {
				return $result['work'];
			}
			++$node['key'];
			if ( count( $this->keys ) === $node['key'] ) {
				foreach ( $node['values'] as $field => $expected ) {
					if ( ! $this->dry_run && ! $this->verify( $node['id'], $field, $expected ) ) {
						$node['key'] = 0;
						$this->put( $node );
						return $result['work'];
					}
				}
				$node['phase'] = 'done';
				$this->completed_node( $node );
			}
		}
		$this->put( $node );
		return $result['work'];
	}

	protected function cleanup( $limit, &$complete ) {
		if ( $this->legacy ) {
			$complete = true;
			return 1;
		}
		$names = $this->db->get_col( $this->db->prepare( "SELECT option_name FROM {$this->db->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT %d", $this->db->esc_like( $this->prefix() ) . '%', $limit + count( $this->cleaned ) + 1 ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 7;
			return 1;
		}
		$names = array_diff( array_unique( array_merge( $names, array_keys( $this->created ) ) ), array_keys( $this->cleaned ) );
		sort( $names, SORT_STRING );
		$complete = count( $names ) <= $limit;
		$deleted  = array();
		foreach ( array_slice( $names, 0, $limit ) as $name ) {
			if ( ! $this->dry_run && ! bbp_admin_repair_tool_save_option( $name, false, true ) ) {
				$this->code = 7;
				$complete   = false;
				break;
			}
			unset( $this->created[ $name ] );
			if ( $this->dry_run ) {
				$this->cleaned[ $name ] = true;
			} else {
				$deleted[ $name ] = true;
			}
		}
		if ( ! empty( $deleted ) ) {
			// Generation names are never reused. Keep them out of the shared miss cache.
			$notoptions = wp_cache_get( 'notoptions', 'options' );
			if ( is_array( $notoptions ) ) {
				wp_cache_set( 'notoptions', array_diff_key( $notoptions, $deleted ), 'options' );
			}
		}
		return max( 1, min( $limit, count( $names ) ) );
	}

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
				$slots = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
				$sql .= $slots ? " AND post_status IN ({$slots})" : ' AND 1 = 0';
				$args  = array_merge( $args, $statuses );
			}
			$id = (int) $this->db->get_var( $this->db->prepare( $sql . ' ORDER BY ID LIMIT 1', $args ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->code = 3;
				return 1;
			}
			if ( ! $id ) {
				// Commit the phase before cleanup can remove nodes still named by a
				// previous checkpoint. Failed final saves can then resume safely.
				$this->state['phase']  = $counted ? 'prune' : 'cleanup';
				$this->state['cursor'] = 0;
				if ( ! $this->dry_run && ! $this->save() ) {
					$this->code = 7;
				}
				return 1;
			}
			if ( $counted ) {
				$this->forums[ $id ] = $id;
			}
			$node = $this->enter( $id, 0, $counted );
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
		$node = $this->node( $this->state['current'] );
		if ( false === $node ) {
			$this->corrupt = true;
			return 1;
		}
		switch ( $node['phase'] ) {
			case 'children':
				$this->visit( $node );
				break;
			case 'write':
				return $this->write( $node, $limit );
			case 'done':
				if ( $node['parent'] ) {
					$parent_node = $this->node( $node['parent'] );
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

	protected function valid_type() {
		return true;
	}

	protected function phases() {
		return array( 'prepare', 'count', 'prune', 'cleanup' );
	}

	protected function active_phases() {
		return array( 'count', 'prune' );
	}

	protected function valid_value( $key, $value, $counted ) {
		return $counted ? is_int( $value ) : null === $value;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Subclasses validate their additional node fields through this template method.
	protected function valid_extra_node( $node ) {
		return true;
	}

	protected function upper_id() {
		return (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type = %s", $this->context['forum'] ) );
	}

	protected function root_statuses() {
		return $this->context['forums'];
	}

	protected function normalize_id( $id, $counted ) {
		$id = (int) $id;
		return $counted && 'topics' !== $this->type ? (int) apply_filters( 'bbp_get_forum_id', $id, $id ) : $id;
	}

	protected function source_post( $id ) {
		return $this->db->get_row( $this->db->prepare( "SELECT ID, post_parent, post_status FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $this->context['forum'] ) );
	}

	protected function node_phase( $post, $counted ) {
		return $counted ? 'children' : 'write';
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Subclasses may initialize additional node fields from the source post.
	protected function prepare_node( $node, $post ) {
		return $node;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Subclasses may update the node after a particular metadata key completes.
	protected function after_key( $node, $key ) {
		return $node;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Subclasses may revalidate the source before writing its node.
	protected function before_write( $node ) {
		return true;
	}

	protected function completed_node( $node ) {}

	abstract protected function values( $id, $post );
	abstract protected function meta_error();
	abstract protected function merge_child( &$parent_node, $child, $edge );
}
