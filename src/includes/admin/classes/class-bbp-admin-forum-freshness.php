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
 * Run finishes topic activity before forums depend on the repaired metadata.
 * Full previews retain calculated topic activity in memory without writing it.
 * Small automatic runs continue through the existing shared runner. Each field
 * and hierarchy edge consumes work.
 *
 * @since 2.7.0 bbPress (r8299)
 * @access private
 */
class BBP_Admin_Forum_Freshness extends BBP_Admin_Forum_Repair {

	/**
	 * Calculated topic activity times keyed by topic ID.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var array
	 */
	private $topic_times = array();

	/**
	 * Aggregate repair counts keyed by source kind.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @var array
	 */
	private $totals = array(
		'topics' => 0,
		'forums' => 0,
		'stale'  => 0
	);

	/**
	 * Set up the topic and forum freshness worker.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param bool $dry_run Whether to inspect the repair without changing data.
	 */
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

	/**
	 * Run one bounded freshness request and include aggregate totals.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array Repair result, continuation state, and aggregate totals.
	 */
	public function run() {
		return array_merge( parent::run(), $this->totals );
	}

	/**
	 * Return every valid freshness phase.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array Freshness phase names.
	 */
	protected function phases() {
		return array_merge( parent::phases(), array( 'topics', 'orphans', 'preview-cleanup' ) );
	}

	/**
	 * Return freshness phases that may have an active traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array Active freshness phase names.
	 */
	protected function active_phases() {
		return array_merge( parent::active_phases(), array( 'topics' ) );
	}

	/**
	 * Validate a saved freshness checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $state Saved checkpoint.
	 * @return bool Whether the checkpoint can be resumed.
	 */
	protected function valid_state( $state ) {
		$valid = parent::valid_state( $state )
			&& isset( $state['orphan_cursor'], $state['orphan_upper'] )
			&& is_int( $state['orphan_cursor'] ) && is_int( $state['orphan_upper'] )
			&& $state['orphan_cursor'] >= 0 && $state['orphan_upper'] >= $state['orphan_cursor']
			&& array_key_exists( 'topic_node', $state )
			&& ( 'topics' === $state['phase'] && $state['current'] && ! $this->async ? is_array( $state['topic_node'] ) : false === $state['topic_node'] );
		if ( ! $valid || ! isset( $state['_preview_totals'] ) ) {
			return $valid;
		}

		$totals = $state['_preview_totals'];
		return is_array( $totals ) && array_keys( $totals ) === array_keys( $this->totals )
			&& count( array_filter( $totals, 'is_int' ) ) === count( $totals )
			&& min( $totals ) >= 0;
	}

	/**
	 * Return the highest topic or forum ID included in this repair.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return int Highest source post ID.
	 */
	protected function upper_id() {
		return (int) $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type IN (%s, %s)", $this->context['forum'], $this->context['topic'] ) );
	}

	/**
	 * Create a new freshness checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return array|false New checkpoint, or false after a database failure.
	 */
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

	/**
	 * Read one freshness traversal node.
	 *
	 * Topic progress and its cursor share one atomic checkpoint during a normal
	 * Run. Browser Dry Runs use namespaced scratch options across requests.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8331) Store browser Dry Run topic nodes between requests.
	 *
	 * @param int $id Source post ID.
	 * @return array|false Traversal node, or false when no node exists.
	 */
	protected function read_node( $id ) {
		if ( 'topics' !== $this->state['phase'] ) {
			return parent::read_node( $id );
		}
		if ( ! $this->async ) {
			return $this->state['topic_node'];
		}
		if ( ! $this->persisted ) {
			return false;
		}
		list( $read, $node ) = bbp_admin_repair_get_option( $this->topic_option( $id ) );
		if ( ! $read ) {
			$this->code = 7;
		}
		return $node;
	}

	/**
	 * Save one freshness traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8331) Store browser Dry Run topic nodes between requests.
	 *
	 * @param array $node Traversal node.
	 * @return bool Whether the node was saved.
	 */
	protected function save_node( $node ) {
		if ( 'topics' === $this->state['phase'] ) {
			if ( ! $this->async ) {
				$this->state['topic_node'] = $node;
				return true;
			}
			return bbp_admin_repair_tool_save_option( $this->topic_option( $node['id'] ), $node );
		}
		return parent::save_node( $node );
	}

	/**
	 * Return the option-name prefix for browser Dry Run topic nodes.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @return string Topic-node option prefix.
	 */
	private function topic_prefix() {
		return bbp_admin_repair_tool_batch_option_name( $this->tool_id ) . '_topics_' . $this->state['token'] . '_';
	}

	/**
	 * Return the option name for one browser Dry Run topic node.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @param int $id Topic ID.
	 * @return string Topic-node option name.
	 */
	private function topic_option( $id ) {
		return $this->topic_prefix() . $id;
	}

	/**
	 * Include forums with every status as traversal roots.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return null No root status restriction.
	 */
	protected function root_statuses() {
		return null;
	}

	/**
	 * Normalize a source ID for the current freshness phase.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int  $id      Source post ID.
	 * @param bool $counted Whether the node is being counted or pruned.
	 * @return int Normalized source post ID.
	 */
	protected function normalize_id( $id, $counted ) {
		return 'topics' === $this->state['phase'] ? (int) $id : parent::normalize_id( $id, $counted );
	}

	/**
	 * Read source fields for a topic or forum freshness node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int $id Source post ID.
	 * @return object|null Source row, or null when the post does not exist.
	 */
	protected function source_post( $id ) {
		$type = 'topics' === $this->state['phase'] ? $this->context['topic'] : $this->context['forum'];
		return $this->db->get_row( $this->db->prepare( "SELECT ID, post_parent, post_status, post_type, post_date FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $type ) );
	}

	/**
	 * Choose the first processing phase for a freshness node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param object $post    Source post row.
	 * @param bool   $counted Whether the node is being counted or pruned.
	 * @return string Node phase.
	 */
	protected function node_phase( $post, $counted ) {
		return $counted && $this->context['forum'] === $post->post_type && 'auto-draft' !== $post->post_status ? 'children' : 'write';
	}

	/**
	 * Validate one calculated freshness value.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param string $key     Metadata key.
	 * @param mixed  $value   Calculated value.
	 * @param bool   $counted Whether the node is being counted or pruned.
	 * @return bool Whether the value is valid.
	 */
	protected function valid_value( $key, $value, $counted ) {
		return null === $value || ( $counted && ( '_bbp_last_active_time' === $key ? is_string( $value ) : is_int( $value ) ) );
	}

	/**
	 * Add freshness-specific fields to a traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array  $node Traversal node.
	 * @param object $post Source post row.
	 * @return array Prepared traversal node.
	 */
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

	/**
	 * Validate freshness-specific fields in a traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Traversal node.
	 * @return bool Whether the freshness fields are valid.
	 */
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

	/**
	 * Calculate direct activity values for one topic or forum.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int    $id   Source post ID.
	 * @param object $post Source post row.
	 * @return array Activity values keyed by metadata name.
	 */
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
		$args = array(
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
		);
		if ( $this->dry_run ) {
			// Compare against the topic activity calculated by this same preview.
			unset( $args['meta_key'], $args['meta_type'] );
			$args['numberposts'] = -1;
			$args['orderby']     = 'ID';
		}
		$topics = get_posts( $args );
		if ( $this->dry_run ) {
			usort( $topics, array( $this, 'compare_topic_times' ) );
		}
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

	/**
	 * Return the repair error code for a metadata read failure.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return int Repair error code.
	 */
	protected function meta_error() {
		return 5;
	}

	/**
	 * Compare topics by calculated activity time and topic ID.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @param int|object $left  Left topic ID or object.
	 * @param int|object $right Right topic ID or object.
	 * @return int Less than, equal to, or greater than zero for sorting.
	 */
	public function compare_topic_times( $left, $right ) {
		$left  = is_object( $left ) ? (int) $left->ID : (int) $left;
		$right = is_object( $right ) ? (int) $right->ID : (int) $right;
		$comparison = strcmp( $this->topic_time( $right ), $this->topic_time( $left ) );
		return $comparison ? $comparison : $right <=> $left;
	}

	/**
	 * Return the repaired or stored activity time for one topic.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8331) Read browser Dry Run values from topic-node options.
	 *
	 * @param int $id Topic ID.
	 * @return string Topic activity time.
	 */
	private function topic_time( $id ) {
		if ( $this->dry_run && isset( $this->topic_times[ $id ] ) ) {
			return $this->topic_times[ $id ];
		}
		if ( $this->async && $id ) {
			list( $read, $node ) = bbp_admin_repair_get_option( $this->topic_option( $id ) );
			if ( ! $read ) {
				$this->code = 7;
				return '';
			}
			if ( ! is_array( $node ) || ! isset( $node['kind'], $node['values']['_bbp_last_active_time'] ) || 'topics' !== $node['kind'] ) {
				$this->corrupt = true;
				return '';
			}
			$this->topic_times[ $id ] = (string) $node['values']['_bbp_last_active_time'];
			return $this->topic_times[ $id ];
		}
		$time = $id ? get_post_meta( $id, '_bbp_last_active_time', true ) : '';
		if ( ! empty( $this->db->last_error ) ) {
			wp_cache_delete( $id, 'post_meta' );
			$this->code = 5;
			return '';
		}
		return empty( $time ) ? $this->post_date( $id, 'db' ) : (string) $time;
	}

	/**
	 * Return one post's date while handling database read failures.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int    $id      Post ID.
	 * @param string $context Date context accepted by get_post_field().
	 * @return string Post date, or an empty string when unavailable.
	 */
	private function post_date( $id, $context = 'display' ) {
		$date = $id ? get_post_field( 'post_date', $id, $context ) : '';
		if ( ! empty( $this->db->last_error ) ) {
			wp_cache_delete( $id, 'posts' );
			$this->code = 3;
		}
		return (string) $date;
	}

	/**
	 * Merge a completed child forum's activity into its parent.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $parent_node Parent traversal node, passed by reference.
	 * @param array $child       Completed child traversal node.
	 * @param int   $edge        Child forum ID used by the parent edge.
	 */
	protected function merge_child( &$parent_node, $child, $edge ) {
		$topic = $child['returns']['topic'];
		$time  = $this->topic_time( $topic );
		$prior = $parent_node['values']['_bbp_last_topic_id'];
		$comparison = strcmp( $time, $this->topic_time( $prior ) );
		if ( $comparison > 0 || ( 0 === $comparison && $topic > $prior ) ) {
			$parent_node['values']['_bbp_last_topic_id'] = $topic;
		}
		// Preserve the updaters' final-child ordering, including custom child lists
		$parent_node['values']['_bbp_last_reply_id']  = max( $parent_node['direct']['_bbp_last_reply_id'], $child['returns']['reply'] );
		$parent_node['values']['_bbp_last_active_id'] = max( $parent_node['direct']['_bbp_last_active_id'], $child['returns']['active'] );
	}

	/**
	 * Apply existing freshness filters after one metadata key is complete.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array  $node Traversal node.
	 * @param string $key  Completed metadata key.
	 * @return array Updated traversal node.
	 */
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

	/**
	 * Record one completed topic or forum node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param array $node Completed traversal node.
	 */
	protected function completed_node( $node ) {
		if ( $this->dry_run && ! $this->async && 'topics' === $node['kind'] ) {
			$this->topic_times[ $node['id'] ] = $node['values']['_bbp_last_active_time'];
		}
		if ( ! $this->async ) {
			++$this->totals[ $node['kind'] ];
		}
	}

	/**
	 * Add one completed browser Dry Run node to the aggregate totals.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @param array $node Completed traversal node.
	 */
	protected function leaving_node( $node ) {
		++$this->totals[ $node['kind'] ];
	}

	/**
	 * Restore aggregate totals when a browser Dry Run resumes.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 */
	protected function load_preview_state() {
		if ( isset( $this->state['_preview_totals'] ) ) {
			$this->totals = $this->state['_preview_totals'];
		}
	}

	/**
	 * Add aggregate totals to the browser Dry Run checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 */
	protected function save_preview_state() {
		$this->state['_preview_totals'] = $this->totals;
	}

	/**
	 * Include the source post date in freshness identity checks.
	 *
	 * @since 2.7.0 bbPress (r8315)
	 *
	 * @param object $post Source post row.
	 * @return array Normalized source identity.
	 */
	protected function source_identity( $post ) {
		$source         = parent::source_identity( $post );
		$source['date'] = $post->post_date;
		return $source;
	}

	/**
	 * Inspect and remove orphaned freshness metadata in bounded work.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int $limit Maximum metadata rows to inspect.
	 * @return int Work units consumed.
	 */
	private function prune_orphans( $limit ) {
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
			$this->state['phase']  = 'topics';
			$this->state['cursor'] = 0;
		}
		return max( 1, $work );
	}

	/**
	 * Process one bounded freshness step.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @param int  $limit    Maximum work available to this step.
	 * @param bool $complete Whether the complete repair is finished, passed by reference.
	 * @return int Work units consumed.
	 */
	protected function step( $limit, &$complete ) {
		if ( 'preview-cleanup' === $this->state['phase'] ) {
			list( $success, $work, $complete ) = bbp_admin_repair_tool_cleanup_options( $this->topic_prefix(), $limit, false, $this->created_options, $this->inspected_options );
			if ( ! $success ) {
				$this->code = 7;
			}
			return $work;
		}
		if ( 'orphans' === $this->state['phase'] ) {
			return $this->prune_orphans( $limit );
		}
		if ( 'topics' === $this->state['phase'] && ! $this->state['current'] ) {
			$id = (int) $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $this->context['topic'], $this->state['cursor'], $this->state['upper_id'] ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->code = 3;
				return 1;
			}
			if ( ! $id ) {
				$this->state['phase']  = 'count';
				$this->state['cursor'] = 0;
				// Only a subsequent Run or preview may depend on the repaired topic rows
				$this->stop     = ! $this->dry_run;
				$this->phase_boundary = $this->dry_run;
				return 1;
			}
			$node = $this->enter_node( $id, 0, true );
			if ( false !== $node ) {
				$this->state['root'] = $this->state['current'] = $id;
			}
			return 1;
		}
		$phase = $this->state['phase'];
		$id    = $this->state['current'];
		$work  = parent::step( $limit, $complete );
		if ( $this->async && 'cleanup' === $phase && $complete ) {
			$complete             = false;
			$this->state['phase'] = 'preview-cleanup';
		}
		if ( 'topics' === $phase && ! $this->state['current'] ) {
			$this->state['topic_node'] = false;
			unset( $this->nodes[ $id ] );
		} elseif ( 'prepare' === $phase && 'count' === $this->state['phase'] ) {
			$this->state['phase'] = 'orphans';
		}
		return $work;
	}

	/**
	 * Exclude scratch-option cleanup from the estimated normal Run work.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @param string $phase Freshness phase before the work was performed.
	 * @param int    $work  Work units consumed by the preview.
	 * @return int Estimated normal Run work.
	 */
	protected function preview_estimated_work( $phase, $work ) {
		return 'preview-cleanup' === $phase ? 0 : $work;
	}
}
