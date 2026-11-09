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
class BBP_Admin_Forum_Counts extends BBP_Admin_Forum_Repair {

	public function __construct( $type, $dry_run = false ) {
		$this->db        = bbp_db();
		$this->type      = $type;
		$this->dry_run   = $dry_run;
		$this->tool_id   = 'bbp-forum-' . $type;
		$this->meta_keys = ( 'topics' === $type )
			? array( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden', '_bbp_total_topic_count_hidden' )
			: array( '_bbp_reply_count', '_bbp_total_reply_count' );
		if ( 'hidden-replies' === $type ) {
			$this->meta_keys = array( '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden' );
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

	/** Read child counts through the existing filters without shared-cache writes. */
	protected function child_count( $id, $type, $statuses, $hidden ) {
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

	protected function values( $id, $post ) {
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
			return array_combine( $this->meta_keys, array( $public, $public, $hidden, $hidden ) );
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
		return array_combine( $this->meta_keys, array( (int) $count, (int) $count ) );
	}

	protected function prepare_node( $node, $post ) {
		$node           = parent::prepare_node( $node, $post );
		$node['direct'] = $node['values'];
		return $node;
	}

	protected function valid_extra_node( $node ) {
		if ( ! parent::valid_extra_node( $node ) || ! isset( $node['direct'] ) || ! is_array( $node['direct'] )
			|| array_keys( $node['direct'] ) !== $this->meta_keys || $node['source']['type'] !== $this->context['forum'] ) {
			return false;
		}
		foreach ( $node['direct'] as $key => $value ) {
			if ( ! $this->valid_value( $key, $value, $node['counted'] ) ) {
				return false;
			}
		}
		return true;
	}

	protected function before_write( $node ) {
		if ( ! parent::before_write( $node ) ) {
			return false;
		}
		if ( ! $this->legacy && $node['counted'] ) {
			$direct = $this->values( $node['id'], null );
			if ( $this->code ) {
				return false;
			}
			if ( $direct !== $node['direct'] ) {
				$this->corrupt = true;
				return false;
			}
		}
		return true;
	}

	protected function meta_error() {
		return ( 'hidden-replies' === $this->type ) ? 4 : 5;
	}

	protected function merge_child( &$parent_node, $child, $edge ) {
		if ( 'topics' === $this->type ) {
			$parent_node['values'][ $this->meta_keys[1] ] += (int) apply_filters( 'bbp_update_forum_topic_count', $child['values'][ $this->meta_keys[1] ], $edge );
			$parent_node['values'][ $this->meta_keys[3] ] += (int) apply_filters( 'bbp_get_forum_topic_count_hidden_int', $child['values'][ $this->meta_keys[3] ], $edge );
		} else {
			$hook = ( 'hidden-replies' === $this->type ) ? 'bbp_update_forum_reply_count_hidden' : 'bbp_update_forum_reply_count';
			$parent_node['values'][ $this->meta_keys[1] ] += (int) apply_filters( $hook, $child['values'][ $this->meta_keys[1] ], $child['id'] );
		}
	}

	protected function valid_type() {
		return in_array( $this->type, array( 'topics', 'replies', 'hidden-replies' ), true );
	}
}
