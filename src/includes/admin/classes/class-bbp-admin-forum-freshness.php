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
	 * Saved checkpoint context version.
	 *
	 * @since 2.7.0 bbPress (r8356)
	 * @since 2.7.0 bbPress (r8359) Carry topic activity in forum traversal nodes and batch reply scans.
	 *
	 * @var int
	 */
	const CONTEXT_VERSION = 2;

	/**
	 * Calculated topic activity times keyed by topic ID for this request.
	 *
	 * @since 2.7.0 bbPress (r8331)
	 *
	 * @var array
	 */
	private $topic_times = array();

	/**
	 * Latest direct topic activity keyed by forum ID.
	 *
	 * Batched Runs and browser Dry Runs persist the same aggregate, so a forum
	 * step reads one bounded summary instead of every direct topic.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @var array
	 */
	private $forum_topics = array();

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
			'filtered' => false !== has_filter( 'bbp_forum_query_subforum_ids' ),
			'version'  => self::CONTEXT_VERSION
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
		return array_merge( parent::phases(), array( 'topics', 'orphans', 'summary-cleanup' ) );
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
		return parent::valid_state( $state )
			&& isset( $state['orphan_cursor'], $state['orphan_upper'] )
			&& is_int( $state['orphan_cursor'] ) && is_int( $state['orphan_upper'] )
			&& $state['orphan_cursor'] >= 0 && $state['orphan_upper'] >= $state['orphan_cursor']
			&& array_key_exists( 'topic_node', $state )
			&& ( 'topics' === $state['phase'] && $state['current'] ? is_array( $state['topic_node'] ) : false === $state['topic_node'] );
	}

	/**
	 * Return the highest forum, topic, or reply ID included in this repair.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return int Highest source post ID.
	 */
	protected function upper_id() {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT MAX(ID) FROM {$this->db->posts} WHERE post_type IN (%s, %s, %s)",
				$this->context['forum'],
				$this->context['topic'],
				$this->context['reply']
			)
		);
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
			$this->fail( 'metadata-read' );
			return false;
		}
		$state['orphan_cursor'] = 0;
		$state['orphan_upper']  = $upper;
		$state['topic_node']    = false;
		if ( ! $state['upper_id'] ) {
			$slots  = implode( ', ', array_fill( 0, count( $this->meta_keys ), '%s' ) );
			$exists = $this->db->get_var( $this->db->prepare( "SELECT meta_id FROM {$this->db->postmeta} WHERE meta_key IN ({$slots}) LIMIT 1", $this->meta_keys ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->fail( 'metadata-read' );
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
	 * Topic progress and its cursor share one atomic checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8331) Store browser Dry Run topic nodes between requests.
	 * @since 2.7.0 bbPress (r8359) Store every active topic node in its checkpoint.
	 *
	 * @param int $id Source post ID.
	 * @return array|false Traversal node, or false when no node exists.
	 */
	protected function read_node( $id ) {
		if ( 'topics' !== $this->state['phase'] ) {
			return parent::read_node( $id );
		}
		return $this->state['topic_node'];
	}

	/**
	 * Save one freshness traversal node.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8331) Store browser Dry Run topic nodes between requests.
	 * @since 2.7.0 bbPress (r8359) Store every active topic node in its checkpoint.
	 *
	 * @param array $node Traversal node.
	 * @return bool Whether the node was saved.
	 */
	protected function save_node( $node ) {
		if ( 'topics' === $this->state['phase'] ) {
			$this->state['topic_node'] = $node;
			return true;
		}
		return parent::save_node( $node );
	}

	/**
	 * Return the option-name prefix for direct-topic summaries.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @return string Forum-topic aggregate option prefix.
	 */
	private function forum_topic_prefix() {
		return bbp_admin_repair_tool_batch_option_name( $this->tool_id ) . '_forum_topics_';
	}

	/**
	 * Return the option name for one forum's direct-topic aggregate.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param int $id Forum ID.
	 * @return string Forum-topic aggregate option name.
	 */
	private function forum_topic_option( $id ) {
		return $this->forum_topic_prefix() . $this->state['token'] . '_' . $id;
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
		if ( $counted && $this->context['topic'] === $post->post_type ) {
			return 'replies';
		}
		return $counted && 'auto-draft' !== $post->post_status ? 'children' : 'write';
	}

	/**
	 * Include the bounded reply-scan phase used by topic nodes.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @return array Node phase names.
	 */
	protected function node_phases() {
		return array_merge( parent::node_phases(), array( 'replies' ) );
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
		$node['observed'] = $this->metadata_snapshot( $node['id'] );
		if ( 'topics' === $node['kind'] ) {
			$node['reply_cursor'] = 0;
		}
		$node['topic_time'] = 'topics' === $node['kind']
			? (string) $node['values']['_bbp_last_active_time']
			: ( isset( $this->forum_topics[ $node['id'] ] )
				? $this->forum_topics[ $node['id'] ]['time']
				: $this->topic_time( $node['values']['_bbp_last_topic_id'] ) );
		$node['direct']  = $node['values'];
		$node['returns'] = array(
			'topic'      => 0,
			'topic_time' => '',
			'reply'      => 0,
			'active'     => 0
		);
		if ( $node['counted'] && $this->has_newer_activity( $node['observed'] ) ) {
			if ( $this->accept_concurrent_metadata( $node, $node['observed'], true ) && $this->async ) {
				$this->leaving_node( $node );
			}
		}
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
		if ( ! parent::valid_extra_node( $node ) || ! isset( $node['kind'], $node['topic_time'], $node['direct'], $node['returns'], $node['source'], $node['observed'] ) || ! in_array( $node['kind'], array( 'topics', 'forums' ), true )
			|| ( 'topics' === $this->state['phase'] ? 'topics' : 'forums' ) !== $node['kind']
			|| ! is_string( $node['topic_time'] )
			|| ! is_array( $node['direct'] ) || array_keys( $node['direct'] ) !== $this->meta_keys
			|| ! is_array( $node['observed'] ) || array_keys( $node['observed'] ) !== $this->meta_keys
			|| ! is_array( $node['returns'] ) || array_keys( $node['returns'] ) !== array( 'topic', 'topic_time', 'reply', 'active' ) ) {
			return false;
		}
		if ( ! is_array( $node['source'] ) || array_keys( $node['source'] ) !== array( 'parent', 'status', 'type', 'date' )
			|| ! is_string( $node['source']['date'] )
			|| $node['source']['type'] !== $this->context[ 'topics' === $node['kind'] ? 'topic' : 'forum' ] ) {
			return false;
		}
		if ( 'topics' === $node['kind'] && ( ! isset( $node['reply_cursor'] )
			|| ! is_int( $node['reply_cursor'] ) || $node['reply_cursor'] < 0 || $node['reply_cursor'] > $this->state['upper_id']
			|| ( 'replies' === $node['phase'] && 0 !== $node['key'] )
		) ) {
			return false;
		}
		foreach ( $node['direct'] as $key => $value ) {
			if ( ! $this->valid_value( $key, $value, $node['counted'] ) ) {
				return false;
			}
		}
		foreach ( $node['observed'] as $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
		}
		foreach ( array( 'topic', 'reply', 'active' ) as $field ) {
			$value = $node['returns'][ $field ];
			if ( ! is_int( $value ) ) {
				return false;
			}
		}
		return is_string( $node['returns']['topic_time'] );
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
			$values['_bbp_last_reply_id']    = 0;
			$values['_bbp_last_active_id']   = $id;
			$values['_bbp_last_active_time'] = $post->post_date;
			return $values;
		}
		if ( 'auto-draft' === $post->post_status ) {
			$values['_bbp_last_topic_id'] = (int) $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s AND post_status = %s ORDER BY ID DESC LIMIT 1", $id, $this->context['topic'], $this->context['public'] ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->fail( 'source-read' );
			}
			return $values;
		}
		$topic = $this->forum_topic( $id );
		if ( $this->error || $this->corrupt ) {
			return $values;
		}
		$values['_bbp_last_topic_id'] = $topic;
		$activity = bbp_forum_query_last_activity_id( $id );
		if ( false === $activity || ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
			return $values;
		}
		foreach ( array( '_bbp_last_reply_id', '_bbp_last_active_id' ) as $key ) {
			$values[ $key ] = (int) $activity;
		}
		return $values;
	}

	/**
	 * Read the first stored value for each freshness key without cache filters.
	 *
	 * Each query is limited to one row, so malformed duplicate metadata cannot
	 * make the concurrency snapshot itself unbounded.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param int $id Post ID.
	 * @return array Stored values keyed by metadata name.
	 */
	private function metadata_snapshot( $id ) {
		$args   = array();
		$fields = array();
		foreach ( $this->meta_keys as $index => $key ) {
			$fields[] = "(SELECT meta_value FROM {$this->db->postmeta} WHERE post_id = %d AND BINARY meta_key = %s ORDER BY meta_id LIMIT 1) AS value_{$index}";
			$args[]   = $id;
			$args[]   = $key;
		}
		$row = $this->db->get_row( $this->db->prepare( 'SELECT ' . implode( ', ', $fields ), $args ), ARRAY_N );
		if ( ! empty( $this->db->last_error ) || ! is_array( $row ) || count( $row ) !== count( $this->meta_keys ) ) {
			$this->fail( 'metadata-read' );
			return array_fill_keys( $this->meta_keys, '' );
		}
		return array_combine(
			$this->meta_keys,
			array_map(
				static function ( $value ) {
					return null === $value ? '' : (string) $value;
				},
				$row
			)
		);
	}

	/**
	 * Check whether live metadata names relevant activity outside this snapshot.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param array $values Raw metadata values.
	 * @return bool Whether a newer topic or reply exists.
	 */
	private function has_newer_activity( $values ) {
		$ids = array();
		foreach ( array( '_bbp_last_topic_id', '_bbp_last_reply_id', '_bbp_last_active_id' ) as $key ) {
			$id = isset( $values[ $key ] ) && ctype_digit( $values[ $key ] ) ? (int) $values[ $key ] : 0;
			if ( $id > $this->state['upper_id'] ) {
				$ids[] = $id;
			}
		}
		if ( empty( $ids ) ) {
			return false;
		}

		$ids   = bbp_get_unique_array_values( $ids );
		$slots = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$args  = array_merge( $ids, array( $this->context['topic'], $this->context['reply'] ) );
		$found = $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE ID IN ({$slots}) AND post_type IN (%s, %s) LIMIT 1", $args ) );
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
			return false;
		}
		return ! empty( $found );
	}

	/**
	 * Preserve newer live activity instead of restarting the complete traversal.
	 *
	 * Values that did not change retain this repair's calculated result. Values
	 * changed by concurrent activity remain untouched and are propagated to the
	 * current node's parent. A later repair can revisit malformed concurrent data.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param array $node           Traversal node, passed by reference.
	 * @param array $current        Current raw metadata values.
	 * @param bool  $prefer_current Whether every current value is newer than the snapshot.
	 * @param array $expected       Expected raw metadata after this repair's own write.
	 * @return bool Whether the concurrent values were accepted.
	 */
	private function accept_concurrent_metadata( &$node, $current, $prefer_current = false, $expected = array() ) {
		$accepted     = $node;
		$already_done = 'done' === $node['phase'];
		if ( ! $accepted['counted'] ) {
			$accepted['key']      = count( $this->meta_keys );
			$accepted['phase']    = 'done';
			$accepted['observed'] = $current;
			$this->completed_node( $accepted, ! $already_done );
			if ( $this->error || $this->corrupt || 'done' !== $accepted['phase'] ) {
				return false;
			}
			$node = $accepted;
			return true;
		}

		$expected = $expected ? $expected : $accepted['observed'];
		$values   = array();
		foreach ( $this->meta_keys as $key ) {
			$raw = ( $prefer_current || $current[ $key ] !== $expected[ $key ] )
				? $current[ $key ]
				: ( null === $accepted['values'][ $key ] ? '' : (string) $accepted['values'][ $key ] );
			if ( '_bbp_last_active_time' === $key ) {
				$values[ $key ] = '' === $raw ? null : $raw;
			} elseif ( '' === $raw ) {
				$values[ $key ] = null;
			} elseif ( ctype_digit( $raw ) ) {
				$values[ $key ] = (int) $raw;
			} else {
				$this->fail( 'source-invalid' );
				return false;
			}
		}

		$accepted['values']   = $values;
		$accepted['direct']   = $values;
		$accepted['observed'] = $current;
		$accepted['returns']  = array(
			'topic'      => 0,
			'topic_time' => '',
			'reply'      => 0,
			'active'     => 0,
		);
		$accepted['topic_time'] = 'topics' === $accepted['kind']
			? (string) $accepted['values']['_bbp_last_active_time']
			: $this->topic_time( $accepted['values']['_bbp_last_topic_id'] );
		if ( $this->error ) {
			return false;
		}
		if ( 'forums' === $accepted['kind'] ) {
			foreach ( $this->meta_keys as $key ) {
				$accepted = $this->after_key( $accepted, $key );
				if ( $this->error ) {
					return false;
				}
			}
		}
		$accepted['key']   = count( $this->meta_keys );
		$accepted['phase'] = 'done';
		$this->completed_node( $accepted, ! $already_done );
		if ( $this->error || $this->corrupt || 'done' !== $accepted['phase'] ) {
			return false;
		}
		$node = $accepted;
		return true;
	}

	/**
	 * Revalidate metadata before writing without restarting unrelated work.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param array $node Traversal node, passed by reference.
	 * @return bool Whether the node remains safe to write.
	 */
	protected function before_write( &$node ) {
		if ( ! parent::before_write( $node ) ) {
			return false;
		}
		$current = $this->metadata_snapshot( $node['id'] );
		if ( $this->error || $current === $node['observed'] ) {
			return ! $this->error;
		}
		$replayed = true;
		foreach ( $this->meta_keys as $key ) {
			$expected = null === $node['values'][ $key ] ? '' : (string) $node['values'][ $key ];
			if ( $current[ $key ] !== $node['observed'][ $key ] && $current[ $key ] !== $expected ) {
				$replayed = false;
				break;
			}
		}
		if ( $replayed ) {
			$node['observed'] = $current;
			return true;
		}
		if ( $this->accept_concurrent_metadata( $node, $current ) ) {
			$this->persist_node( $node );
		}
		return false;
	}

	/**
	 * Scan a bounded reply-ID window for one topic.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param array $node  Topic traversal node.
	 * @param int   $limit Maximum reply rows to inspect.
	 * @return int Work units consumed.
	 */
	private function scan_topic_replies( $node, $limit ) {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT ID, post_date FROM {$this->db->posts}
				WHERE post_parent = %d AND post_type = %s AND post_status = %s
					AND ID > %d AND ID <= %d ORDER BY ID LIMIT %d",
				$node['id'],
				$this->context['reply'],
				$this->context['public'],
				$node['reply_cursor'],
				$this->state['upper_id'],
				$limit + 1
			)
		);
		if ( ! empty( $this->db->last_error ) ) {
			$this->fail( 'source-read' );
			return 1;
		}

		$complete = count( $rows ) <= $limit;
		$rows     = array_slice( $rows, 0, $limit );
		foreach ( $rows as $reply ) {
			$first_reply                              = empty( $node['values']['_bbp_last_reply_id'] );
			$node['reply_cursor']                    = (int) $reply->ID;
			$node['values']['_bbp_last_reply_id']    = (int) $reply->ID;
			$node['values']['_bbp_last_active_id']   = (int) $reply->ID;
			if ( $first_reply || strcmp( $reply->post_date, $node['values']['_bbp_last_active_time'] ) > 0 ) {
				$node['values']['_bbp_last_active_time'] = $reply->post_date;
			}
		}
		$node['direct']     = $node['values'];
		$node['topic_time'] = $node['values']['_bbp_last_active_time'];

		if ( ! $this->before_write( $node ) ) {
			return max( 1, count( $rows ) );
		}
		if ( $complete ) {
			$node['phase'] = 'write';
		}
		$this->persist_node( $node );
		return max( 1, count( $rows ) );
	}

	/**
	 * Verify that only the completed metadata field changed, then advance the
	 * stored metadata after this repair's own write.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param array  $node   Traversal node, passed by reference.
	 * @param string $key    Completed metadata key.
	 * @param array  $result Shared metadata result.
	 * @return bool Whether processing may continue.
	 */
	protected function after_write( &$node, $key, $result ) {
		$current = $this->metadata_snapshot( $node['id'] );
		if ( $this->error ) {
			return false;
		}
		$expected = $node['observed'];
		if ( ! $this->dry_run ) {
			$value            = $node['values'][ $key ];
			$expected[ $key ] = null === $value ? '' : (string) $value;
		}
		if ( $current !== $expected ) {
			$this->accept_concurrent_metadata( $node, $current, false, $expected );
			return false;
		}

		$node['observed'] = $current;
		return true;
	}

	/**
	 * Return the failure reason for a metadata read failure.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 *
	 * @return string Semantic failure reason.
	 */
	protected function meta_error() {
		return 'metadata-read';
	}

	/**
	 * Return one forum's latest direct topic from the current repair.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param int $id Forum ID.
	 * @return int Topic ID.
	 */
	private function forum_topic( $id ) {
		if ( isset( $this->forum_topics[ $id ] ) ) {
			return $this->forum_topics[ $id ]['id'];
		}

		$summary = false;
		if ( ! $this->dry_run || $this->async ) {
			list( $read, $summary ) = bbp_admin_repair_get_option( $this->forum_topic_option( $id ) );
			if ( ! $read ) {
				$this->fail( 'progress' );
				return 0;
			}
		}
		if ( false === $summary ) {
			$summary = array(
				'id'   => 0,
				'time' => '',
			);
		}
		if ( ! is_array( $summary ) || array_keys( $summary ) !== array( 'id', 'time' ) || ! is_int( $summary['id'] ) || $summary['id'] < 0 || ! is_string( $summary['time'] ) ) {
			$this->corrupt = true;
			return 0;
		}

		$this->forum_topics[ $id ] = $summary;
		return $summary['id'];
	}

	/**
	 * Add one calculated public topic to its forum's aggregate.
	 *
	 * Replaying a completed topic is safe because the aggregate is a maximum by
	 * activity time and then topic ID.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $node Completed topic node.
	 * @return bool Whether the aggregate was recorded.
	 */
	private function record_forum_topic( $node ) {
		$forum_id = $node['source']['parent'];
		if ( ! $forum_id || $this->context['public'] !== $node['source']['status'] ) {
			return true;
		}

		$this->forum_topic( $forum_id );
		if ( $this->error || $this->corrupt ) {
			return false;
		}
		$summary    = $this->forum_topics[ $forum_id ];
		$topic_id   = $node['id'];
		$topic_time = (string) $node['values']['_bbp_last_active_time'];
		$comparison = strcmp( $topic_time, $summary['time'] );
		if ( $comparison < 0 || ( 0 === $comparison && $topic_id <= $summary['id'] ) ) {
			return true;
		}

		$summary = array(
			'id'   => $topic_id,
			'time' => $topic_time,
		);
		$option = $this->forum_topic_option( $forum_id );
		if ( $this->dry_run && ! $this->async ) {
			$this->created_options[ $option ] = true;
		} elseif ( ! bbp_admin_repair_tool_save_option( $option, $summary ) ) {
			$this->fail( 'progress' );
			return false;
		}
		$this->forum_topics[ $forum_id ] = $summary;
		return true;
	}

	/**
	 * Return the repaired or stored activity time for one topic.
	 *
	 * @since 2.7.0 bbPress (r8299)
	 * @since 2.7.0 bbPress (r8331) Read browser Dry Run values from calculated topics.
	 *
	 * @param int $id Topic ID.
	 * @return string Topic activity time.
	 */
	private function topic_time( $id ) {
		if ( $this->dry_run && isset( $this->topic_times[ $id ] ) ) {
			return $this->topic_times[ $id ];
		}
		if ( $this->dry_run && $this->async && $id ) {
			$post = $this->db->get_row( $this->db->prepare( "SELECT ID, post_parent, post_status, post_type, post_date FROM {$this->db->posts} WHERE ID = %d AND post_type = %s", $id, $this->context['topic'] ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->fail( 'source-read' );
				return '';
			}
			if ( $post ) {
				$time = $this->db->get_var( $this->db->prepare( "SELECT MAX(post_date) FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s AND post_status = %s AND ID <= %d", $id, $this->context['reply'], $this->context['public'], $this->state['upper_id'] ) );
				if ( ! empty( $this->db->last_error ) ) {
					$this->fail( 'source-read' );
					return '';
				}
				$this->topic_times[ $id ] = $time ? (string) $time : $post->post_date;
				return $this->topic_times[ $id ];
			}
		}
		$time = $id ? get_post_meta( $id, '_bbp_last_active_time', true ) : '';
		if ( ! empty( $this->db->last_error ) ) {
			wp_cache_delete( $id, 'post_meta' );
			$this->fail( 'metadata-read' );
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
			$this->fail( 'source-read' );
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
		$topic      = $child['returns']['topic'];
		$time       = $child['returns']['topic_time'];
		$prior      = $parent_node['values']['_bbp_last_topic_id'];
		$comparison = strcmp( $time, $parent_node['topic_time'] );
		if ( $comparison > 0 || ( 0 === $comparison && $topic > $prior ) ) {
			$parent_node['values']['_bbp_last_topic_id'] = $topic;
			$parent_node['topic_time']                   = $time;
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
		if ( '_bbp_last_topic_id' === $key ) {
			$topic = $node['returns']['topic'];
			$node['returns']['topic_time'] = ( $topic === $node['values'][ $key ] )
				? $node['topic_time']
				: $this->topic_time( $topic );
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
	 * @param array $node  Completed traversal node, passed by reference.
	 * @param bool  $count Whether to add this completion to synchronous totals.
	 */
	protected function completed_node( &$node, $count = true ) {
		if ( 'topics' === $node['kind'] ) {
			if ( $this->dry_run ) {
				$this->topic_times[ $node['id'] ] = $node['values']['_bbp_last_active_time'];
			}
			if ( ! $this->record_forum_topic( $node ) ) {
				$node['phase'] = 'write';
				--$node['key'];
				return;
			}
		}
		if ( ! $this->async && $count ) {
			++$this->totals[ $node['kind'] ];
		}
	}

	/**
	 * Remove freshness aggregates owned by an abandoned generation.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 */
	protected function reset_generation() {
		$this->topic_times  = array();
		$this->forum_topics = array();
		$this->totals       = array(
			'topics' => 0,
			'forums' => 0,
			'stale'  => 0,
		);
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
	 * Validate aggregate totals in a browser Dry Run checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $data Preview simulation data.
	 * @return bool Whether the aggregate totals are valid.
	 */
	protected function valid_preview_state( $data ) {
		if ( ! parent::valid_preview_state( $data ) || ! isset( $data['totals'] ) || ! is_array( $data['totals'] ) ) {
			return false;
		}
		$totals = $data['totals'];
		return array_keys( $totals ) === array_keys( $this->totals )
			&& count( array_filter( $totals, 'is_int' ) ) === count( $totals )
			&& min( $totals ) >= 0;
	}

	/**
	 * Restore aggregate totals when a browser Dry Run resumes.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $data Preview simulation data.
	 */
	protected function load_preview_state( $data ) {
		if ( isset( $data['totals'] ) ) {
			$this->totals = $data['totals'];
		}
	}

	/**
	 * Add aggregate totals to the browser Dry Run checkpoint.
	 *
	 * @since 2.7.0 bbPress (r8350)
	 *
	 * @param array $data Preview simulation data, passed by reference.
	 */
	protected function save_preview_state( &$data ) {
		$data['totals'] = $this->totals;
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
			$this->fail( 'metadata-read' );
			return 1;
		}
		$work = 0;
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			if ( $row->is_orphan ) {
				if ( ! $this->dry_run ) {
					$code = bbp_admin_repair_metadata_row( 'post', $row, $row->meta_key, null );
					if ( $code ) {
						$this->fail(
							1 === $code
								? 'metadata-read'
								: 'metadata-write'
						);
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
		if ( 'summary-cleanup' === $this->state['phase'] ) {
			$preview = $this->dry_run && ! $this->async;
			list( $success, $work, $complete ) = bbp_admin_repair_tool_cleanup_options( $this->forum_topic_prefix(), $limit, $preview, $this->created_options, $this->inspected_options );
			if ( ! $success ) {
				$this->fail( 'progress' );
			}
			return $work;
		}
		if ( 'orphans' === $this->state['phase'] ) {
			return $this->prune_orphans( $limit );
		}
		if ( 'topics' === $this->state['phase'] && ! $this->state['current'] ) {
			$id = (int) $this->db->get_var( $this->db->prepare( "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $this->context['topic'], $this->state['cursor'], $this->state['upper_id'] ) );
			if ( ! empty( $this->db->last_error ) ) {
				$this->fail( 'source-read' );
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
				if ( 'done' === $node['phase'] ) {
					$this->state['cursor']     = $id;
					$this->state['topic_node'] = false;
					unset( $this->nodes[ $id ] );
				} else {
					$this->state['root'] = $this->state['current'] = $id;
				}
			}
			return 1;
		}
		if ( 'topics' === $this->state['phase'] && $this->state['current'] ) {
			$node = $this->get_node( $this->state['current'] );
			if ( false === $node ) {
				$this->corrupt = true;
				return 1;
			}
			if ( $this->error || $this->corrupt ) {
				return 1;
			}
			if ( 'replies' === $node['phase'] ) {
				return $this->scan_topic_replies( $node, $limit );
			}
		}
		$phase = $this->state['phase'];
		$id    = $this->state['current'];
		$work  = parent::step( $limit, $complete );
		if ( 'cleanup' === $phase && $complete ) {
			$complete             = false;
			$this->state['phase'] = 'summary-cleanup';
		}
		if ( 'topics' === $phase && ! $this->state['current'] ) {
			$this->state['topic_node'] = false;
			unset( $this->nodes[ $id ] );
		} elseif ( 'prepare' === $phase && 'count' === $this->state['phase'] ) {
			$this->state['phase'] = 'orphans';
		}
		return $work;
	}
}
