<?php

/**
 * bbPress Admin Forum Recounts.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Resumable, post-order forum recounts shared by topic and reply tools.
 *
 * Nodes live in non-autoloaded options so a request need not load the complete
 * hierarchy. Default child discovery is paginated. A custom child-list filter
 * still receives its complete list, as required by that existing API.
 *
 * @since 2.7.0 bbPress (r8284)
 * @access private
 */
class BBP_Admin_Forum_Counts {

	private $db;
	private $type;
	private $tool;
	private $keys;
	private $context;
	private $state;
	private $dry_run;
	private $legacy = false;
	private $persisted = false;
	private $nodes = array();
	private $created = array();
	private $cleaned = array();
	private $changed = array();
	private $forums = array();
	private $code = 0;
	private $restarted = false;
	private $corrupt = false;

	public function __construct( $type, $dry_run = false ) {
		$this->db      = bbp_db();
		$this->type    = $type;
		$this->dry_run = $dry_run;
		$this->tool    = 'bbp-forum-' . $type;
		$this->keys    = ( 'topics' === $type )
			? array( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden', '_bbp_total_topic_count_hidden' )
			: array( '_bbp_reply_count', '_bbp_total_reply_count' );
		if ( 'hidden-replies' === $type ) {
			$this->keys = array( '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden' );
		}
		$this->context = array(
			'forum'    => bbp_get_forum_post_type(),
			'topic'    => bbp_get_topic_post_type(),
			'reply'    => bbp_get_reply_post_type(),
			'forums'   => bbp_get_string_array_values( bbp_get_countable_forum_statuses() ),
			'topics'   => array_values( array_unique( array_filter( bbp_get_public_topic_statuses() ) ) ),
			'hidden'   => array_values( array_unique( array_filter( bbp_get_non_public_topic_statuses() ) ) ),
			'replies'  => array_values( array_unique( ( 'hidden-replies' === $type ) ? bbp_get_non_public_reply_statuses() : array_filter( bbp_get_public_reply_statuses() ) ) ),
			'filtered' => false !== has_filter( 'bbp_forum_query_subforum_ids' )
		);
	}

	/** Preserve the historical whole-site, read-only status API. */
	public function get_status() {
		$this->legacy = true;
		$result       = $this->run();
		return empty( $result['code'] )
			? array(
				'code'         => 0,
				'forum_ids'    => array_values( $this->forums ),
				'repair_count' => $result['repaired']
			)
			: array( 'code' => $result['code'] );
	}

	/** Run one bounded request, preserving a replayable path through the tree. */
	public function run() {
		$complete = false;
		$initial  = true;
		if ( ! in_array( $this->type, array( 'topics', 'replies', 'hidden-replies' ), true ) ) {
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
			while ( ! $this->code && ! $complete && ( $work < $size ) ) {
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

	private function valid_state( $state ) {
		if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'root', 'current' ) ) ) {
			return false;
		}
		$valid = isset( $state['phase'], $state['token'], $state['context'] )
			&& is_string( $state['token'] ) && ( 32 === strlen( $state['token'] ) ) && ctype_xdigit( $state['token'] )
			&& in_array( $state['phase'], array( 'prepare', 'count', 'prune', 'cleanup' ), true )
			&& ( $state['context'] === $this->context );
		if ( ! $valid ) {
			return false;
		}
		return $state['current']
			? ( $state['root'] > $state['cursor'] && $state['root'] <= $state['upper_id'] && in_array( $state['phase'], array( 'count', 'prune' ), true ) )
			: ( 0 === $state['root'] );
	}

	private function new_state() {
		$upper = (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type = %s", $this->context['forum'] ) );
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

	private function save( $complete = false ) {
		return bbp_admin_repair_tool_save_checkpoint( $this->tool, $this->state, $complete );
	}

	private function prefix() {
		return bbp_admin_repair_tool_batch_option_name( $this->tool ) . '_tree_';
	}

	private function option( $id ) {
		return $this->prefix() . $this->state['token'] . '_' . $id;
	}

	private function node( $id ) {
		if ( ! array_key_exists( $id, $this->nodes ) ) {
			$this->nodes[ $id ] = $this->persisted ? get_option( $this->option( $id ), false ) : false;
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
				foreach ( $node['values'] as $value ) {
					$valid = $valid && ( $node['counted'] ? is_int( $value ) : null === $value );
				}
				$valid = $valid && ( 'write' !== $node['phase'] || count( $this->keys ) > $node['key'] );
				$valid = $valid && ( 'done' !== $node['phase'] || count( $this->keys ) === $node['key'] );
				$valid = $valid && ( $node['parent'] !== $id ) && ( $node['await'] !== $id );
				$valid = $valid && ( $node['parent'] ? ( $node['depth'] > 0 ) : ( 0 === $node['depth'] ) );
			}
			if ( ! $valid ) {
				$this->corrupt = true;
				return false;
			}
		}
		return $node;
	}

	private function put( $node ) {
		$id = $node['id'];
		if ( ! $this->dry_run && ! bbp_admin_repair_tool_save_option( $this->option( $id ), $node ) ) {
			$this->code = 7;
			return false;
		}
		if ( $this->dry_run && empty( $this->nodes[ $id ] ) ) {
			$this->created[ $this->option( $id ) ] = true;
		}
		$this->nodes[ $id ] = $node;
		return true;
	}

	private function enter( $id, $parent_id, $counted ) {
		$id = (int) $id;
		if ( $counted && ( 'topics' !== $this->type ) ) {
			$id = (int) apply_filters( 'bbp_get_forum_id', $id, $id );
		}
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
		$forum = $this->db->get_row( $this->db->prepare( "SELECT ID, post_parent, post_status FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $this->context['forum'] ) );
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
		$values = $counted ? $this->values( $id ) : array_fill_keys( $this->keys, null );
		if ( $this->code ) {
			return false;
		}
		$parent_node = $parent_id ? $this->node( $parent_id ) : false;
		$node        = array(
			'id'       => $id,
			'parent'   => $parent_id,
			'depth'    => $parent_node ? $parent_node['depth'] + 1 : 0,
			'phase'    => $counted ? 'children' : 'write',
			'counted'  => $counted,
			'values'   => $values,
			'next'     => 0,
			'await'    => 0,
			'edge'     => 0,
			'key'      => 0,
			'children' => false
		);
		if ( $counted && $this->context['filtered'] ) {
			$children = $this->children( $id );
			if ( $this->code ) {
				return false;
			}
			$node['children'] = array_values( array_map( 'absint', (array) apply_filters( 'bbp_forum_query_subforum_ids', $children, $id ) ) );
		}
		return $this->put( $node ) ? $node : false;
	}

	private function children( $id, $cursor = null ) {
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

	/** Read child counts through the existing filters without shared-cache writes. */
	private function child_count( $id, $type, $statuses, $hidden ) {
		$sql = "SELECT p.post_type AS type, p.post_status AS status, COUNT(*) AS count
			FROM {$this->db->posts} AS p WHERE p.post_parent = %d AND p.post_type IN (%s, %s)
			GROUP BY p.post_status, p.post_type UNION ALL
			SELECT r.post_type AS type, r.post_status AS status, COUNT(*) AS count
			FROM {$this->db->posts} AS r INNER JOIN {$this->db->posts} AS t ON r.post_parent = t.ID
			WHERE t.post_parent = %d AND t.post_type = %s AND r.post_type = %s GROUP BY r.post_status, r.post_type";
		$query = $this->db->prepare( $sql, $id, $this->context['forum'], $this->context['topic'], $id, $this->context['topic'], $this->context['reply'] );
		$query = apply_filters( 'bbp_get_child_counts_query', $query, $id, $this->context['forum'] );
		$rows  = $this->db->get_results( $query, ARRAY_A );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = $hidden ? 5 : 9;
			return 0;
		}
		$counts = wp_list_pluck( $rows, 'type', 'type' );
		foreach ( $rows as $row ) {
			if ( ! is_array( $counts[ $row['type'] ] ) ) {
				$counts[ $row['type'] ] = array_fill_keys( get_post_stati(), 0 );
			}
			$counts[ $row['type'] ][ $row['status'] ] = bbp_number_not_negative( $row['count'] );
		}
		$counts   = (array) apply_filters( 'bbp_get_child_counts', $counts, $id );
		$types    = array_flip( array( $type ) );
		$lookup   = array_flip( $statuses );
		$filtered = array();
		foreach ( $counts as $key => $values ) {
			if ( isset( $types[ $key ] ) ) {
				$filtered[ $key ] = empty( $lookup ) ? $values : array_intersect_key( $values, $lookup );
			}
		}
		$filtered = (array) apply_filters( 'bbp_filter_child_counts_list', $filtered, $id, $types, $lookup );
		$count    = isset( $filtered[ $type ] ) ? bbp_number_not_negative( array_sum( array_values( $filtered[ $type ] ) ) ) : 0;
		return (int) apply_filters( $hidden ? 'bbp_get_non_public_child_count' : 'bbp_get_public_child_count', $count, $id, $type );
	}

	private function values( $id ) {
		if ( 'topics' === $this->type ) {
			$public = $this->child_count( $id, $this->context['topic'], $this->context['topics'], false );
			$hidden = 0;
			if ( ! $this->code && ! empty( $this->context['hidden'] ) ) {
				$slots  = implode( ', ', array_fill( 0, count( $this->context['hidden'] ), '%s' ) );
				$hidden = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s AND post_status IN ({$slots})", array_merge( array( $id, $this->context['topic'] ), $this->context['hidden'] ) ) );
				if ( ! empty( $this->db->last_error ) ) {
					$this->code = 4;
				}
			}
			return array_combine( $this->keys, array( $public, $public, $hidden, $hidden ) );
		}
		$type = $this->db->get_var( $this->db->prepare( "SELECT meta_value FROM {$this->db->postmeta} WHERE post_id = %d AND BINARY meta_key = '_bbp_forum_type' ORDER BY meta_id LIMIT 1", $id ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = $this->meta_error();
			return array();
		}
		$type     = maybe_unserialize( $type );
		$type     = apply_filters( 'bbp_get_forum_type', empty( $type ) ? 'forum' : $type, $id );
		$category = (bool) apply_filters( 'bbp_is_forum_category', ! empty( $type ) && ( 'category' === $type ), $id );
		$count    = 0;
		if ( ! $category ) {
			if ( 'hidden-replies' === $this->type ) {
				$count = $this->child_count( $id, $this->context['reply'], $this->context['replies'], true );
			} elseif ( ! empty( $this->context['topics'] ) && ! empty( $this->context['replies'] ) ) {
				$topics  = implode( ', ', array_fill( 0, count( $this->context['topics'] ), '%s' ) );
				$replies = implode( ', ', array_fill( 0, count( $this->context['replies'] ), '%s' ) );
				$query   = $this->db->prepare(
					"SELECT COUNT(*) FROM {$this->db->posts} AS replies INNER JOIN {$this->db->posts} AS topics ON replies.post_parent = topics.ID
					WHERE topics.post_parent = %d AND topics.post_type = %s AND topics.post_status IN ({$topics}) AND replies.post_type = %s AND replies.post_status IN ({$replies})",
					array_merge( array( $id, $this->context['topic'] ), $this->context['topics'], array( $this->context['reply'] ), $this->context['replies'] )
				);
				$count = bbp_number_not_negative( $this->db->get_var( $query ) );
				if ( ! empty( $this->db->last_error ) ) {
					$this->code = 4;
				}
			}
		}
		return array_combine( $this->keys, array( (int) $count, (int) $count ) );
	}

	private function meta_error() {
		return ( 'hidden-replies' === $this->type ) ? 4 : 5;
	}

	private function merge_child( &$parent_node, $child, $edge ) {
		if ( 'topics' === $this->type ) {
			$parent_node['values'][ $this->keys[1] ] += (int) apply_filters( 'bbp_update_forum_topic_count', $child['values'][ $this->keys[1] ], $edge );
			$parent_node['values'][ $this->keys[3] ] += (int) apply_filters( 'bbp_get_forum_topic_count_hidden_int', $child['values'][ $this->keys[3] ], $edge );
		} else {
			$hook = ( 'hidden-replies' === $this->type ) ? 'bbp_update_forum_reply_count_hidden' : 'bbp_update_forum_reply_count';
			$parent_node['values'][ $this->keys[1] ] += (int) apply_filters( $hook, $child['values'][ $this->keys[1] ], $child['id'] );
		}
	}

	private function visit( $node ) {
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
		} else {
			$node['await'] = $child['id'];
			$node['edge']  = $edge;
		}
		if ( $this->put( $node ) && $node['await'] ) {
			$this->state['current'] = $child['id'];
		}
	}

	private function verify( $id, $key, $value ) {
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

	private function write( $node, $limit ) {
		$key    = $this->keys[ $node['key'] ];
		$value  = $node['values'][ $key ];
		$result = bbp_admin_repair_count_metadata( 'post', $node['id'], $key, $value, $limit, $this->dry_run );
		if ( $result['code'] ) {
			$codes = array(
				2 => $this->meta_error(),
				3 => 1,
				4 => 8
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
			}
		}
		$this->put( $node );
		return $result['work'];
	}

	private function cleanup( $limit, &$complete ) {
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

	private function step( $limit, &$complete ) {
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
			if ( $counted ) {
				$slots = implode( ', ', array_fill( 0, count( $this->context['forums'] ), '%s' ) );
				$sql .= $slots ? " AND post_status IN ({$slots})" : ' AND 1 = 0';
				$args  = array_merge( $args, $this->context['forums'] );
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
}
