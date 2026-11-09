<?php

/**
 * bbPress Admin Freshness Repairs.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Repair topic activity before walking the forum hierarchy in bounded batches.
 *
 * A phase boundary keeps forum previews from depending on topic metadata that
 * the same preview has not written. Small automatic runs continue through the
 * existing shared runner. Each field and hierarchy edge consumes work.
 *
 * @since 2.7.0 bbPress (r8299)
 * @access private
 */
class BBP_Admin_Forum_Freshness extends BBP_Admin_Forum_Repair {

	private $totals = array(
		'topics' => 0,
		'forums' => 0,
		'stale'  => 0
	);

	public function __construct( $dry_run = false ) {
		$this->db        = bbp_db();
		$this->type      = 'freshness';
		$this->tool_id   = 'bbp-sync-all-topics-forums';
		$this->dry_run   = $dry_run;
		$this->meta_keys = array( '_bbp_last_topic_id', '_bbp_last_reply_id', '_bbp_last_active_id', '_bbp_last_active_time' );
		$this->context   = array(
			'forum'    => bbp_get_forum_post_type(),
			'topic'    => bbp_get_topic_post_type(),
			'reply'    => bbp_get_reply_post_type(),
			'public'   => bbp_get_public_status_id(),
			'forums'   => bbp_get_string_array_values( bbp_get_countable_forum_statuses() ),
			'topics'   => bbp_get_string_array_values( bbp_get_public_topic_statuses() ),
			'filtered' => false !== has_filter( 'bbp_forum_query_subforum_ids' )
		);
	}

	public function run() {
		return array_merge( parent::run(), $this->totals );
	}

	protected function phases() {
		return array_merge( parent::phases(), array( 'topics', 'orphans' ) );
	}

	protected function active_phases() {
		return array_merge( parent::active_phases(), array( 'topics' ) );
	}

	protected function valid_state( $state ) {
		return parent::valid_state( $state )
			&& isset( $state['orphan_cursor'], $state['orphan_upper'] )
			&& is_int( $state['orphan_cursor'] ) && is_int( $state['orphan_upper'] )
			&& $state['orphan_cursor'] >= 0 && $state['orphan_upper'] >= $state['orphan_cursor']
			&& array_key_exists( 'topic_node', $state )
			&& ( 'topics' === $state['phase'] && $state['current'] ? is_array( $state['topic_node'] ) : false === $state['topic_node'] );
	}

	protected function upper_id() {
		return (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type IN (%s, %s)", $this->context['forum'], $this->context['topic'] ) );
	}

	protected function new_state() {
		$state = parent::new_state();
		if ( false === $state ) {
			return false;
		}
		$upper = (int) $this->db->get_var( "SELECT MAX(meta_id) FROM {$this->db->postmeta}" );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 5;
			return false;
		}
		$state['orphan_cursor'] = 0;
		$state['orphan_upper']  = $upper;
		$state['topic_node']    = false;
		if ( ! $state['upper_id'] ) {
			$slots  = implode( ', ', array_fill( 0, count( $this->meta_keys ), '%s' ) );
			$exists = $this->db->get_var( $this->db->prepare( "SELECT meta_id FROM {$this->db->postmeta} WHERE meta_key IN ({$slots}) LIMIT 1", $this->meta_keys ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->code = 5;
				return false;
			}
			if ( ! $exists ) {
				$state['phase'] = 'cleanup';
			}
		}
		return $state;
	}

	/** Topic progress and its cursor share one atomic checkpoint, without scratch options. */
	protected function read_node( $id ) {
		return 'topics' === $this->state['phase'] ? $this->state['topic_node'] : parent::read_node( $id );
	}

	protected function save_node( $node ) {
		if ( 'topics' === $this->state['phase'] ) {
			$this->state['topic_node'] = $node;
			return true;
		}
		return parent::save_node( $node );
	}

	protected function root_statuses() {
		return null;
	}

	protected function normalize_id( $id, $counted ) {
		return 'topics' === $this->state['phase'] ? (int) $id : parent::normalize_id( $id, $counted );
	}

	protected function source_post( $id ) {
		$type = 'topics' === $this->state['phase'] ? $this->context['topic'] : $this->context['forum'];
		return $this->db->get_row( $this->db->prepare( "SELECT ID, post_parent, post_status, post_type, post_date FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $type ) );
	}

	protected function node_phase( $post, $counted ) {
		return $counted && $this->context['forum'] === $post->post_type && 'auto-draft' !== $post->post_status ? 'children' : 'write';
	}

	protected function valid_value( $key, $value, $counted ) {
		return null === $value || ( $counted && ( '_bbp_last_active_time' === $key ? is_string( $value ) : is_int( $value ) ) );
	}

	protected function prepare_node( $node, $post ) {
		$node            = parent::prepare_node( $node, $post );
		$node['kind']    = $this->context['topic'] === $post->post_type ? 'topics' : 'forums';
		$node['direct']  = $node['values'];
		$node['returns'] = array(
			'topic'  => 0,
			'reply'  => 0,
			'active' => 0
		);
		return $node;
	}

	protected function valid_extra_node( $node ) {
		if ( ! parent::valid_extra_node( $node ) || ! isset( $node['kind'], $node['direct'], $node['returns'], $node['source'] ) || ! in_array( $node['kind'], array( 'topics', 'forums' ), true )
			|| ( 'topics' === $this->state['phase'] ? 'topics' : 'forums' ) !== $node['kind']
			|| ! is_array( $node['direct'] ) || array_keys( $node['direct'] ) !== $this->meta_keys
			|| ! is_array( $node['returns'] ) || array_keys( $node['returns'] ) !== array( 'topic', 'reply', 'active' ) ) {
			return false;
		}
		if ( ! is_array( $node['source'] ) || array_keys( $node['source'] ) !== array( 'parent', 'status', 'type', 'date' )
			|| ! is_string( $node['source']['date'] )
			|| $node['source']['type'] !== $this->context[ 'topics' === $node['kind'] ? 'topic' : 'forum' ] ) {
			return false;
		}
		foreach ( $node['direct'] as $key => $value ) {
			if ( ! $this->valid_value( $key, $value, $node['counted'] ) ) {
				return false;
			}
		}
		foreach ( $node['returns'] as $value ) {
			if ( ! is_int( $value ) ) {
				return false;
			}
		}
		return true;
	}

	protected function values( $id, $post ) {
		$values = array_fill_keys( $this->meta_keys, null );
		if ( $this->context['topic'] === $post->post_type ) {
			$reply = $this->db->get_row( $this->db->prepare( "SELECT MAX(ID) AS last_id, MAX(post_date) AS last_time FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s AND post_status = %s", $id, $this->context['reply'], $this->context['public'] ) );
			if ( ! empty( $this->db->last_error ) || ! $reply ) {
				$this->code = 3;
				return $values;
			}
			$values['_bbp_last_reply_id']    = (int) $reply->last_id;
			$values['_bbp_last_active_id']   = $reply->last_id ? (int) $reply->last_id : $id;
			$values['_bbp_last_active_time'] = $reply->last_time ? $reply->last_time : $post->post_date;
			return $values;
		}
		if ( 'auto-draft' === $post->post_status ) {
			$values['_bbp_last_topic_id'] = (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s AND post_status = %s", $id, $this->context['topic'], $this->context['public'] ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->code = 3;
			}
			return $values;
		}
		$topics = get_posts(
			array(
				'post_parent'            => $id,
				'post_type'              => $this->context['topic'],
				'meta_key'               => '_bbp_last_active_time',
				'meta_type'              => 'DATETIME',
				'orderby'                => 'meta_value',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false
			)
		);
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 3;
			return $values;
		}
		$values['_bbp_last_topic_id'] = empty( $topics ) ? 0 : (int) ( is_object( $topics[0] ) ? $topics[0]->ID : $topics[0] );
		foreach ( array( '_bbp_last_reply_id', '_bbp_last_active_id' ) as $key ) {
			$activity = bbp_forum_query_last_activity_id( $id );
			if ( false === $activity || ! empty( $this->db->last_error ) ) {
				$this->code = 3;
				return $values;
			}
			$values[ $key ] = (int) $activity;
		}
		return $values;
	}

	protected function meta_error() {
		return 5;
	}

	private function topic_time( $id ) {
		$time = $id ? get_post_meta( $id, '_bbp_last_active_time', true ) : '';
		if ( ! empty( $this->db->last_error ) ) {
			wp_cache_delete( $id, 'post_meta' );
			$this->code = 5;
			return '';
		}
		return empty( $time ) ? $this->post_date( $id, 'db' ) : (string) $time;
	}

	private function post_date( $id, $context = 'display' ) {
		$date = $id ? get_post_field( 'post_date', $id, $context ) : '';
		if ( ! empty( $this->db->last_error ) ) {
			wp_cache_delete( $id, 'posts' );
			$this->code = 3;
		}
		return (string) $date;
	}

	protected function merge_child( &$parent_node, $child, $edge ) {
		$topic = $child['returns']['topic'];
		$time  = $this->topic_time( $topic );
		$prior = $parent_node['values']['_bbp_last_topic_id'];
		$cmp   = strcmp( $time, $this->topic_time( $prior ) );
		if ( $cmp > 0 || ( 0 === $cmp && $topic > $prior ) ) {
			$parent_node['values']['_bbp_last_topic_id'] = $topic;
		}
		// Preserve the updaters' final-child ordering, including custom child lists
		$parent_node['values']['_bbp_last_reply_id']  = max( $parent_node['direct']['_bbp_last_reply_id'], $child['returns']['reply'] );
		$parent_node['values']['_bbp_last_active_id'] = max( $parent_node['direct']['_bbp_last_active_id'], $child['returns']['active'] );
	}

	protected function after_key( $node, $key ) {
		if ( 'forums' !== $node['kind'] || null === $node['direct']['_bbp_last_reply_id'] ) {
			return $node;
		}
		$fields = array(
			'_bbp_last_topic_id'  => 'topic',
			'_bbp_last_reply_id'  => 'reply',
			'_bbp_last_active_id' => 'active'
		);
		if ( isset( $fields[ $key ] ) ) {
			$node['returns'][ $fields[ $key ] ] = (int) apply_filters( str_replace( '_bbp_', 'bbp_update_forum_', $key ), $node['values'][ $key ], $node['id'] );
		}
		if ( '_bbp_last_active_id' === $key ) {
			$date = $this->post_date( $node['returns']['active'] );
			if ( empty( $date ) ) {
				$fallback = (int) apply_filters( 'bbp_get_forum_last_active_id', $node['values'][ $key ], $node['id'] );
				$date = $this->post_date( $fallback );
			}
			$node['values']['_bbp_last_active_time'] = empty( $date ) ? null : $date;
		} elseif ( '_bbp_last_active_time' === $key ) {
			apply_filters( 'bbp_update_forum_last_active', (string) $node['values'][ $key ], $node['id'] );
		}
		return $node;
	}

	protected function completed_node( $node ) {
		++$this->totals[ $node['kind'] ];
	}

	protected function source_identity( $post ) {
		$source         = parent::source_identity( $post );
		$source['date'] = $post->post_date;
		return $source;
	}

	private function orphans( $limit ) {
		$slots = implode( ', ', array_fill( 0, count( $this->meta_keys ), '%s' ) );
		$args  = array_merge( $this->meta_keys, array( $this->state['orphan_cursor'], $this->state['orphan_upper'], $limit + 1, $this->context['forum'], $this->context['topic'] ) );
		// Materialize a primary-key window before filtering. Valid and unrelated rows consume work too.
		$query = $this->db->prepare(
			"SELECT candidates.meta_id, candidates.post_id, candidates.meta_key,
				(posts.ID IS NULL AND candidates.meta_key IN ({$slots})) AS is_orphan
			FROM (SELECT meta_id, post_id, meta_key FROM {$this->db->postmeta}
				WHERE meta_id > %d AND meta_id <= %d ORDER BY meta_id LIMIT %d) AS candidates
			LEFT JOIN {$this->db->posts} AS posts ON candidates.post_id = posts.ID AND posts.post_type IN (%s, %s)
			ORDER BY candidates.meta_id",
			$args
		);
		$rows = $this->db->get_results( $query );
		if ( ! empty( $this->db->last_error ) ) {
			$this->code = 5;
			return 1;
		}
		$work = 0;
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			if ( $row->is_orphan ) {
				if ( ! $this->dry_run ) {
					$code = bbp_admin_repair_metadata_row( 'post', $row, $row->meta_key, null );
					if ( $code ) {
						$this->code = 1 === $code ? 5 : 1;
						return max( 1, $work );
					}
				}
				++$this->totals['stale'];
			}
			$this->state['orphan_cursor'] = (int) $row->meta_id;
			++$work;
		}
		if ( count( $rows ) <= $limit ) {
			$this->state['phase']  = 'count';
			$this->state['cursor'] = 0;
			// Only a subsequent Run or preview may depend on the repaired topic rows
			$this->stop = true;
		}
		return max( 1, $work );
	}

	protected function step( $limit, &$complete ) {
		if ( 'orphans' === $this->state['phase'] ) {
			return $this->orphans( $limit );
		}
		if ( 'topics' === $this->state['phase'] && ! $this->state['current'] ) {
			$id = (int) $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $this->context['topic'], $this->state['cursor'], $this->state['upper_id'] ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->code = 3;
				return 1;
			}
			if ( ! $id ) {
				$this->state['phase']  = 'orphans';
				$this->state['cursor'] = 0;
				return 1;
			}
			$node = $this->enter( $id, 0, true );
			if ( false !== $node ) {
				$this->state['root'] = $this->state['current'] = $id;
			}
			return 1;
		}
		$phase = $this->state['phase'];
		$id    = $this->state['current'];
		$work  = parent::step( $limit, $complete );
		if ( 'topics' === $phase && ! $this->state['current'] ) {
			$this->state['topic_node'] = false;
			unset( $this->nodes[ $id ] );
		} elseif ( 'prepare' === $phase && 'count' === $this->state['phase'] ) {
			$this->state['phase'] = 'topics';
		}
		return $work;
	}
}
