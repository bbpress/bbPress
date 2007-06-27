<?php
class BB_Query {
	var $type;

	var $query;
	var $query_id;
	var $query_vars = array();
	var $not_set = array();
	var $request;
	var $count_request = 'SELECT FOUND_ROWS()';
	var $match_query = false;

	var $results;
	var $count = 0;
	var $found_rows = 0;

	var $errors;

	// Can optionally pass unique id string to help out filters
	function BB_Query( $type = 'topic', $query = '', $id = '' ) {
		if ( !empty($query) )
			$this->query($type, $query, $id);
	}

	function &query( $type = 'topic', $query, $id = '' ) {
		global $bbdb;
		$this->type = $type;
		$this->parse_query($query, $id);

		if ( 'post' == $type )
			$this->generate_post_sql();
		else
			$this->generate_topic_sql();

		do_action_ref_array( 'bb_query', array(&$this) );

		$this->results = $bbdb->get_results( $this->request );
		$this->count = count( $this->results );
		$this->found_rows = $bbdb->get_var( $this->count_request );
		if ( 'topic' == $this->type && $this->query_vars['append_meta'] )
			$this->results = bb_append_meta( $this->results, 'topic' );
		return $this->results;
	}

	// $defaults = vars to use if not set in GET, POST or allowed
	// $allowed = array( key_name => value, key_name, key_name, key_name => value );
	// 	key_name => value pairs override anything from defaults, GET, POST
	//	Lone key_names are a whitelist.  Only those can be set by defaults, GET, POST
	//	If there are no lone key_names, allow everything but still override with key_name => value pairs
	//	Ex: $allowed = array( 'topic_status' => 0, 'post_status' => 0, 'topic_author', 'started' );
	//		Will only take topic_author and started values from defaults, GET, POST and will query with topic_status = 0 and post_status = 0
	function &query_from_env( $type = 'topic', $defaults = null, $allowed = null, $id = '' ) {
		$vars = $this->fill_query_vars( array() );

		$defaults  = wp_parse_args($defaults);
		$get_vars  = stripslashes_deep( $_GET );
		$post_vars = stripslashes_deep( $_POST );
		$allowed   = wp_parse_args($allowed);

		$_allowed = array();
		foreach ( array_keys($allowed) as $k ) {
			if ( is_numeric($k) ) {
				$_allowed[] = $allowed[$k];
				unset($allowed[$k]);
			} elseif ( !isset($$k) ) {
				$$k = $allowed[$k];
			}
		}

		extract($post_vars, EXTR_SKIP);
		extract($get_vars, EXTR_SKIP);
		extract($defaults, EXTR_SKIP);

		$vars = $_allowed ? compact($_allowed, array_keys($allowed)) : compact(array_keys($vars));
		return $this->query( $type, $vars, $id );
	}

	function init( $id = '' ) {
		unset($this->query);
		$this->query_vars = array();
		$this->query_id = $id;

		unset($this->results);
		$this->count = $this->found_rows = 0;
	}

	function fill_query_vars( $array ) {
		// Should use 0, '' for empty values
		// Function should return false iff not set

		$ints = array(
			'tag_id',	// one tag ID
			'favorites'	// one user ID
		);

		$parse_ints = array(
			// Both
			'post_id',
			'topic_id',
			'forum_id',

			// Topics
			'topic_author_id',
			'post_count',
			'tag_count',

			// Posts
			'post_author_id',
			'position'
		);

		$dates = array(
			'started',	// topic
			'updated',	// topic
			'posted'	// post
		);

		$others = array(
			// Both
			'topic',	// one topic name
			'forum',	// one forum name
			'tag',		// one tag name

			// Topics
			'topic_author',	// one username
			'topic_status',	// normal, deleted, all, parse_int ( and - )
			'open',		// all, yes = open, no = closed, parse_int ( and - )
			'sticky',	// all, no = normal, forum, super = front, parse_int ( and - )
			'meta_key',	// one meta_key ( and - )
			'meta_value',	// range
			'topic_title',	// LIKE search.  Understands "doublequoted strings"
			'search',	// generic search: topic_title OR post_text
					// Can ONLY be used in a topic query
					// Returns additional search_score and (concatenated) post_text columns

			// Posts
			'post_author',	// one username
			'post_status',	// noraml, deleted, all, parse_int ( and - )
			'post_text',	// FULLTEXT search
					// Returns additional search_score column (and (concatenated) post_text column if topic query)

			// SQL
			'order_by',	// fieldname
			'order',	// DESC, ASC
			'_join_type',	// not implemented: For benchmarking only.  Will disappear. join (1 query), in (2 queries)

			// Utility
			'cache_posts'	// not implemented: none, first, last
		);

		foreach ( $ints as $key )
			if ( ( false === $array[$key] = isset($array[$key]) ? (int) $array[$key] : false ) && isset($this) )
				$this->not_set[] = $key;

		foreach ( $parse_ints as $key )
			if ( ( false === $array[$key] = isset($array[$key]) ? preg_replace( '/[^<=>0-9,-]/', '', $array[$key] ) : false ) && isset($this) )
				$this->not_set[] = $key;

		foreach ( $dates as $key )
			if ( ( false === $array[$key] = isset($array[$key]) ? preg_replace( '/[^<>0-9]/', '', $array[$key] ) : false ) && isset($this) )
				$this->not_set[] = $key;

		foreach ( $others as $key ) {
			if ( !isset($array[$key]) )
				$array[$key] = false;
			if ( isset($this) && false === $array[$key] )
				$this->not_set[] = $key;
		}

		// Both
		if ( isset($array['page']) )
			$array['page'] = (int) $array['page'];
		elseif ( isset($GLOBALS['page']) )
			$array['page'] = (int) $GLOBALS['page'];
		else
			$array['page'] = bb_get_uri_page();

		if ( $array['page'] < 1 )
			$array['page'] = 1;

		$array['per_page'] = isset($array['per_page']) ? (int) $array['per_page'] : 0;
		if ( $q['per_page'] < -1 )
			$q['per_page'] = 1;

		// Utility
		$array['append_meta'] = isset($array['append_meta']) ? (int) (bool) $array['append_meta'] : 1;

		// Posts
		if ( ( !$array['ip'] = isset($array['ip']) ? preg_replace('/[^0-9.]/', '', $array['ip']) : false ) && isset($this) )
			$this->not_set[] = 'ip';

		// Only one FULLTEXT search per query please
		if ( $array['search'] )
			unset($array['post_text']);

		return $array;
	}

	// Parse a query string and set query flag booleans.
	function parse_query($query, $id = '') {
		if ( !empty($query) || !isset($this->query) ) {
			$this->init( $id );
			if ( is_array($query) )
				$this->query_vars = $query;
			else
				wp_parse_str($query, $this->query_vars);
			$this->query = $query;
		}

		$this->query_vars = $this->fill_query_vars($this->query_vars);

		if ( !empty($query) )
			do_action_ref_array('bb_parse_query', array(&$this));
	}

	// Reparse the query vars.
	function parse_query_vars() {
		$this->parse_query('');
	}

	function get($query_var) {
		return isset($this->query_vars[$query_var]) ? $this->query_vars[$query_var] : null;
	}

	function set($query_var, $value) {
		$this->query_vars[$query_var] = $value;
	}

	function generate_topic_sql( $_part_of_post_query = false ) {
		global $bbdb;

		$q =& $this->query_vars;
		$distinct = '';
		$sql_calc_found_rows = 'SQL_CALC_FOUND_ROWS';
		$fields = 't.*';
		$join = '';
		$where = '';
		$group_by = '';
		$having = '';
		$order_by = '';

		$post_where = '';
		$post_queries = array('post_author_id', 'post_author', 'posted', 'post_status', 'position', 'post_text', 'ip');

		if ( !$_part_of_post_query && ( $q['search'] || array_diff($post_queries, $this->not_set) ) ) :
			$join .= " JOIN $bbdb->posts as p ON ( t.topic_id = p.topic_id )";
			$post_where = $this->generate_post_sql( true );
			if ( $q['search'] ) {
				$post_where .= ' AND ( ';
				$post_where .= $this->generate_topic_title_sql( $q['search'] );
				$post_where .= ' OR ';
				$post_where .= $this->generate_post_text_sql( $q['search'] );
				$post_where .= ' )';
			}

			$group_by = 't.topic_id';

			$fields .= ", MIN(p.post_id) as post_id";

			// GROUP_CONCAT requires MySQL >= 4.1
			if ( version_compare('4.1', mysql_get_client_info(), '<=') )
				$fields .= ", GROUP_CONCAT(p.post_text SEPARATOR ' ') AS post_text";
			else
				$fields .= ", p.post_text";

			if ( $this->match_query ) {
				$fields .= ", AVG($this->match_query) AS search_score";
				if ( !$q['order_by'] )
					$q['order_by'] = 'search_score';
			} elseif ( $q['search'] || $q['post_text'] ) {
				$fields .= ", 0 AS search_score";
			}
		endif;

		if ( !$_part_of_post_query ) :
			if ( $q['post_id'] ) :
				$post_topics = $post_topics_no = array();
				$op = substr($q['post_id'], 0, 1);
				if ( in_array($op, array('>','<')) ) :
					$post_topics = $bbdb->get_col( "SELECT DISTINCT topic_id FROM $bbdb->posts WHERE post_id $op '" . (int) substr($q['post_id'], 1) . "'" );
				else :
					global $bb_post_cache, $bb_cache;
					$posts = explode(',', $q['post_id']);
					$get_posts = array();
					foreach ( $posts as $post_id ) :
						$post_id = (int) $post_id;
						$_post_id = abs($post_id);
						if ( !isset($bb_post_cache[$_post_id]) )
							$get_posts[] = $_post_id;
					endforeach;
					$get_posts = join(',', $get_posts);
					$bb_cache->cache_posts( "SELECT * FROM $bbdb->posts WHERE post_id IN ($get_posts)" );

					foreach ( $posts as $post_id ) :
						$post = bb_get_post( abs($post_id) );
						if ( $post_id < 0 )
							$post_topics_no[] = $post->topic_id;
						else
							$post_topics[] = $post->topic_id;
					endforeach;
				endif;
				if ( $post_topics )
					$where .= " AND t.topic_id IN (" . join(',', $post_topics) . ")";
				if ( $post_topics_no )
					$where .= " AND t.topic_id NOT IN (" . join(',', $post_topics_no) . ")";
			endif;

			if ( $q['topic_id'] ) :
				$where .= $this->parse_value( 't.topic_id', $q['topic_id'] );
			elseif ( $q['topic'] ) :
				$q['topic'] = bb_slug_sanitize( $q['topic'] );
				$where .= " AND t.topic_slug = '$q[topic]'";
			endif;

			if ( $q['forum_id'] ) :
				$where .= $this->parse_value( 't.forum_id', $q['forum_id'] );
			elseif ( $q['forum'] ) :
				if ( !$q['forum_id'] = bb_get_id_from_slug( 'forum', $q['forum'] ) )
					$this->error( 'query_var:forum', 'No forum by that name' );
				$where .= " AND t.forum_id = $q[forum_id]";
			endif;
		endif; // !_part_of_post_query


		if ( $q['topic_title'] )
			$where .= ' AND ' . $this->generate_topic_title_sql( $q['topic_title'] );

		if ( $q['started'] )
			$where .= $this->date( 't.topic_start_time', $q['started'] );

		if ( $q['updated'] )
			$where .= $this->date( 't.topic_time', $q['updated'] );

		if ( $q['topic_author_id'] ) :
			$where .= $this->parse_value( 't.topic_poster', $q['topic_author_id'] );
		elseif ( $q['topic_author'] ) :
			$user = bb_get_user( $q['topic_author'] );
			if ( !$q['topic_author_id'] = (int) $user->ID )
				$this->error( 'query_var:user', 'No user by that name' );
			$where .= " AND t.topic_poster = $q[topic_author_id]";
		endif;

		if ( !$q['topic_status'] ) :
			$where .= " AND t.topic_status = '0'";
		elseif ( false === strpos($q['topic_status'], 'all') ) :
			$stati = array( 'normal' => 0, 'deleted' => 1 );
			$q['topic_status'] = str_replace(array_keys($stati), array_values($stati), $q['topic_status']);
			$where .= $this->parse_value( 't.topic_status', $q['topic_status'] );
		endif;

		if ( false !== $q['open'] && false === strpos($q['open'], 'all') ) :
			$stati = array( 'no' => 0, 'closed' => 0, 'yes' => 1, 'open' => 1 );
			$q['open'] = str_replace(array_keys($stati), array_values($stati), $q['open']);
			$where .= $this->parse_value( 't.topic_open', $q['open'] );
		endif;

		if ( false !== $q['sticky'] && false === strpos($q['sticky'], 'all') ) :
			$stickies = array( 'no' => 0, 'normal' => 0, 'forum' => 1, 'super' => 2, 'front' => 2 );
			$q['sticky'] = str_replace(array_keys($stickies), array_values($stickies), $q['sticky']);
			$where .= $this->parse_value( 't.topic_sticky', $q['sticky'] );
		endif;

		if ( false !== $q['post_count'] )
			$where .= $this->parse_value( 't.topic_posts', $q['post_count'] );

		if ( false !== $q['tag_count'] )
			$where .= $this->parse_value( 't.tag_count', $q['tag_count'] );

		/* Convert to JOIN after new taxonomy tables are in */

		if ( $q['tag'] && !is_int($q['tag_id']) )
			$q['tag_id'] = (int) get_tag_id( $q['tag'] );

		if ( is_numeric($q['tag_id']) ) :
			if ( $tagged_topic_ids = get_tagged_topic_ids( $q['tag_id'] ) )
				$where .= " AND t.topic_id IN (" . join(',', $tagged_topic_ids) . ")";
			else
				$where .= " /* No such tag */ AND 0";
		endif;

		if ( is_numeric($q['favorites']) && $f_user = bb_get_user( $q['favorites'] ) )
			$where .= $this->parse_value( 't.topic_id', $f_user->favorites );

		if ( $q['meta_key'] ) :
			$q['meta_key'] = preg_replace('|[^a-z0-9_-]|i', '', $q['meta_key']);
			if ( '-' == substr($q['meta_key'], 0, 1) ) :
				$join  .= " LEFT JOIN $bbdb->topicmeta AS tm ON ( t.topic_id = tm.topic_id AND meta_key = '$q[meta_key]' )";
				$where .= " AND tm.meta_key IS NULL";
			elseif ( $q['meta_value'] ) :
				$join   = " JOIN $bbdb->topicmeta AS tm ON ( t.topic_id = tm.topic_id AND meta_key = '$q[meta_key]' )";
				$q['meta_value'] = bb_maybe_serialize( $q['meta_value'] );
				$where .= $this->parse_value( 'tm.meta_value', $q['meta_value'] );
			endif;
		endif;

		// Just getting topic part for inclusion in post query
		if ( $_part_of_post_query )
			return $where;

		$where .= $post_where;

		if ( $where ) // Get rid of initial " AND " (this is pre-filters)
			$where = substr($where, 5);

		if ( $q['order_by'] )
			$order_by = $q['order_by'];
		else
			$order_by = 't.topic_time';

		$bits = compact( array('distinct', 'sql_calc_found_rows', 'fields', 'join', 'where', 'group_by', 'having', 'order_by') );
		$this->request = $this->_filter_sql( $bits, "$bbdb->topics AS t" );

		return $this->request;
	}

	function generate_post_sql( $_part_of_topic_query = false ) {
		global $bbdb;

		$q =& $this->query_vars;
		$distinct = '';
		$sql_calc_found_rows = 'SQL_CALC_FOUND_ROWS';
		$fields = 'p.*';
		$join = '';
		$where = '';
		$group_by = '';
		$having = '';
		$order_by = '';

		$topic_where = '';
		$topic_queries = array( 'topic_author_id', 'topic_author', 'topic_status', 'post_count', 'tag_count', 'started', 'updated', 'open', 'sticky', 'meta_key', 'meta_value', 'view', 'topic_title' );
		if ( !$_part_of_topic_query && array_intersect(array_keys($q, !false), $topic_queries) ) :
			$join .= " JOIN $bbdb->topics as t ON ( t.topic_id = p.topic_id )";
			$topic_where = $this->generate_topic_sql( true );
		endif;
		
		if ( !$_part_of_topic_query ) :
			if ( $q['post_id'] )
				$where .= $this->parse_value( 'p.post_id', $q['post_id'] );

			if ( $q['topic_id'] ) :
				$where .= $this->parse_value( 'p.topic_id', $q['topic_id'] );
			elseif ( $q['topic'] ) :
				if ( !$q['topic_id'] = bb_get_id_from_slug( 'topic', $q['topic'] ) )
					$this->error( 'query_var:topic', 'No topic by that name' );
				$where .= " AND p.topic_id = $q[topic_id]";
			endif;

			if ( $q['forum_id'] ) :
				$where .= $this->parse_value( 'p.forum_id', $q['forum_id'] );
			elseif ( $q['forum'] ) :
				if ( !$q['forum_id'] = bb_get_id_from_slug( 'forum', $q['forum'] ) )
					$this->error( 'query_var:forum', 'No forum by that name' );
				$where .= " AND p.forum_id = $q[forum_id]";
			endif;
		endif; // !_part_of_topic_query

		if ( $q['post_text'] ) :
			$where  .= ' AND ' . $this->generate_post_text_sql( $q['post_text'] );
			if ( $this->match_query ) {
				$fields .= ", $this->match_query AS search_score";
				if ( !$q['order_by'] )
					$q['order_by'] = 'search_score';
			} else {
				$fields .= ', 0 AS search_score';
			}
		endif;

		if ( $q['posted'] )
			$where .= $this->date( 'p.post_time', $q['posted'] );

		if ( $q['post_author_id'] ) :
			$where .= $this->parse_value( 'p.poster_id', $q['post_author_id'] );
		elseif ( $q['post_author'] ) :
			$user = bb_get_user( $q['post_author'] );
			if ( !$q['post_author_id'] = (int) $user->ID )
				$this->error( 'query_var:user', 'No user by that name' );
			$where .= " AND p.poster_id = $q[post_author_id]";
		endif;

		if ( !$q['post_status'] ) :
			$where .= " AND p.post_status = '0'";
		elseif ( false === strpos($q['post_status'], 'all') ) :
			$stati = array( 'normal' => 0, 'deleted' => 1 );
			$q['post_status'] = str_replace(array_keys($stati), array_values($stati), $q['post_status']);
			$where .= $this->parse_value( 'p.post_status', $q['post_status'] );
		endif;

		if ( false !== $q['position'] )
			$where .= $this->parse_value( 'p.post_position', $q['position'] );

		if ( false !== $q['ip'] )
			$where .= " AND poster_ip = '$q[ip]'";

		// Just getting post part for inclusion in topic query
		if ( $_part_of_topic_query )
			return $where;

		$where .= $topic_where;

		if ( $where ) // Get rid of initial " AND " (this is pre-filters)
			$where = substr($where, 5);

		if ( $q['order_by'] )
			$order_by = $q['order_by'];
		else
			$order_by = 'p.post_time';

		$bits = compact( array('distinct', 'sql_calc_found_rows', 'fields', 'join', 'where', 'group_by', 'having', 'order_by') );
		$this->request = $this->_filter_sql( $bits, "$bbdb->posts AS p" );

		return $this->request;
	}

	function generate_topic_title_sql( $string ) {
		global $bbdb;
		$string = trim($string);

		if ( !preg_match_all('/".*?("|$)|((?<=[\s",+])|^)[^\s",+]+/', $string, $matches) ) {
			$string = $bbdb->escape($string);
			return "(t.topic_title LIKE '%$string%')";
		}

		$where = '';

		foreach ( $matches[0] as $match ) {
			$term = trim($match, "\"\n\r ");
			$term = $bbdb->escape($term);
			$where .= " AND t.topic_title LIKE '%$term%'";
		}

		if ( count($matches[0]) > 1 && $string != $matches[0][0] ) {
			$string = $bbdb->escape($string);
			$where .= " OR t.topic_title LIKE '%$string%'";
		}

		return '(' . substr($where, 5) . ')';
	}

	function generate_post_text_sql( $string ) {
		global $bbdb;
		$string = trim($string);
		$_string = $bbdb->escape( $string );
		if ( strlen($string) < 5 )
			return "p.post_text LIKE '%$_string%'";

		return $this->match_query = "MATCH(p.post_text) AGAINST('$_string')";
	}

	function _filter_sql( $bits, $from ) {
		$q =& $this->query_vars;

		$q['order'] = strtoupper($q['order']);
		if ( $q['order'] && in_array($q['order'], array('ASC', 'DESC')) )
			$bits['order_by'] .= " $q[order]";
		else
			$bits['order_by'] .= " DESC";

		if ( !$q['per_page'] )
			$q['per_page'] = (int) bb_get_option( 'page_topics' );

		$bits['limit'] = '';
		if ( $q['per_page'] > 0 ) :
			if ( $q['page'] > 1 )
				$bits['limit'] .= $q['per_page'] * ( $q['page'] - 1 ) . ", ";
			$bits['limit'] .= $q['per_page'];
		endif;

		$name = "get_{$this->type}s_";

		foreach ( $bits as $bit => $value ) {
			if ( $this->query_id )
				$value = apply_filters( "{$this->query_id}_$bit", $value );
			$$bit = apply_filters( "$name$bit", $value );
		}

		if ( $where )
			$where = "WHERE $where";
		if ( $group_by )
			$group_by = "GROUP BY $group_by";
		if ( $having )
			$having = "HAVING $having";
		if ( $order_by )
			$order_by = "ORDER BY $order_by";
		if ( $limit )
			$limit = "LIMIT $limit";

		return "SELECT $distinct $sql_calc_found_rows $fields FROM $from $join $where $group_by $having $order_by $limit";
	}

	function parse_value( $field, $value = '' ) {
		if ( !$value && !is_numeric($value) )
			return '';

		global $bbdb;

		$op = substr($value, 0, 1);

		// #, =whatever, <#, >#.  Cannot do < and > at same time
		if ( in_array($op, array('<', '=', '>')) ) :
			$value = substr($value, 1);
			$value = is_numeric($value) ? (float) $value : $bbdb->escape( $value );
			return " AND $field $op '$value'";
		elseif ( false === strpos($value, ',') ) :
			$value = is_numeric($value) ? (float) $value : $bbdb->escape( $value );
			return '-' == $op ? " AND $field != '" . substr($value, 1) . "'" : " AND $field = '$value'";
		endif;

		$y = $n = array();
		foreach ( explode(',', $value) as $v ) {
			$v = is_numeric($v) ? (int) $v : $bbdb->escape( $v );
			if ( '-' == substr($v, 0, 1) )
				$n[] = substr($v, 1);
			else
				$y[] = $v;
		}

		$r = '';
		if ( $y )
			$r .= " AND $field IN ('" . join("','", $y) . "')";
		if ( $n )
			$r .= " AND $field NOT IN ('" . join("','", $n) . "')";

		return $r;
	}

	function date( $field, $date ) {
		if ( !$date && !is_int($date) )
			return '';

		$op = substr($date, 0, 1);
		if ( in_array($op, array('>', '<')) ) :
			$date = (int) substr($date, 1, 14);
			if ( strlen($date) < 14 )
				$date .= str_repeat('0', 14 - strlen($date));
			return " AND $field $op $date";
		endif;

		$date = (int) $date;
		$r = " AND YEAR($field) = " . substr($date, 0, 4);
		if ( strlen($date) > 5 )
			$r .= " AND MONTH($field) = " . substr($date, 4, 2);
		if ( strlen($date) > 7 )
			$r .= " AND DAYOFMONTH($field) = " . substr($date, 6, 2);
		if ( strlen($date) > 9 )
			$r .= " AND HOUR($field) = " . substr($date, 8, 2);
		if ( strlen($date) > 11 )
			$r .= " AND MINUTE($field) = " . substr($date, 10, 2);
		if ( strlen($date) > 13 )
			$r .= " AND SECOND($field) = " . substr($date, 12, 2);
		return $r;
	}

	function error( $code, $message ) {
		if ( is_wp_error($this->errors) )
			$this->errors->add( $code, $message );
		else
			$this->errors = new WP_Error( $code, $message );
	}
}

class BB_Query_Form extends BB_Query {
	var $defaults;
	var $allowed;

	// Can optionally pass unique id string to help out filters
	function BB_Query_Form( $type = 'topic', $defaults = '', $allowed = '', $id = '' ) {
		$this->defaults = wp_parse_args( $defaults );
		$this->allowed  = wp_parse_args( $allowed );
		if ( !empty($defaults) || !empty($allowed) )
			$this->query_from_env($type, $defaults, $allowed, $id);
	}

	function topic_search_form( $args = null ) {
		$defaults = array(
			'search' => true,
			'forum'  => true,
			'tag'    => false,
			'author' => false,
			'status' => false,
			'open'   => false,
			'topic_title' => false,

			'id'     => 'topic-search-form',
			'method' => 'get',
			'submit' => __('Search &#187;')
		);

		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		$id = attribute_escape( $id );
		$method = 'get' == strtolower($method) ? 'get' : 'post';
		$submit = attribute_escape( $submit );

		if ( $this->query_vars )
			$query_vars =& $this->query_vars;
		else
			$query_vars = $this->fill_query_vars( $this->defaults );

		extract($query_vars, EXTR_PREFIX_ALL, 'q');

		$r  = "<form action='' method='$method' id='$id'>\n";

		$q_search = attribute_escape( $q_search );
		if ( $search ) {
			$r .= "\t<fieldset><legend>" . __('Search&#8230;') . "</legend>\n";
			$r .= "\t\t<input name='search' id='search' type='text' class='text-input' value='$q_search'>";
			$r .= "\t</fieldset>\n\n";
		}

		if ( $forum ) {
			$r .= "\t<fieldset><legend>" . __('Forum&#8230;')  . "</legend>\n";
			$r .= bb_get_forum_dropdown( array('selected' => $q_forum_id, 'none' => true) );
			$r .= "\t</fieldset>\n\n";
		}

		if ( $tag ) {
			$q_tag = attribute_escape( $q_tag );
			$r .= "\t<fieldset><legend>" .  __('Tag&#8230;') . "</legend>\n";
			$r .= "\t\t<input name='tag' id='topic-tag' type='text' class='text-input' value='$q_tag'>";
			$r .= "\t</fieldset>\n\n";
		}

		if ( $author ) {
			$q_topic_author = attribute_escape( $q_topic_author );
			$r .= "\t<fieldset><legend>" . __('Author&#8230;') . "</legend>\n";
			$r .= "\t\t<input name='topic_author' id='topic-author' type='text' class='text-input' value='$q_topic_author'>";
			$r .= "\t</fieldset>\n\n";
		}

		if ( $status ) {
			$r .= "\t<fieldset><legend>" . __('Status&#8230;') . "</legend>\n";
			$r .= "\t\t<select name='topic_status' id='topic-status'>\n";
			foreach ( array( 'all' => __('All'), '0' => __('Normal'), '1' => __('Deleted') ) as $status => $label ) {
				$label = wp_specialchars( $label );
				$selected = (string) $status == (string) $q_topic_status ? " selected='selected'" : '';
				$r .= "\t\t\t<option value='$status'$selected>$label</option>\n";
			}
			$r .= "\t\t</select>\n";
			$r .= "\t</fieldset>\n\n";
		}

		if ( $open ) {
			$r .= "\t<fieldset><legend>" . __('Open?&#8230;') . "</legend>\n";
			$r .= "\t\t<select name='open' id='topic-open'>\n";
			foreach ( array( 'all' => __('All'), '1' => __('Open'), '0' => __('Closed') ) as $status => $label ) {
				$label = wp_specialchars( $label );
				$selected = (string) $status == (string) $q_open ? " selected='selected'" : '';
				$r .= "\t\t\t<option value='$status'$selected>$label</option>\n";
			}
			$r .= "\t\t</select>\n";
			$r .= "\t</fieldset>\n\n";
		}

		if ( $topic_title ) {
			$q_topic_title = attribute_escape( $q_topic_title );
			$r .= "\t<fieldset><legend>" . __('Title&#8230;') . "</legend>\n";
			$r .= "\t\t<input name='topic_title' id='topic-title' type='text' class='text-input' value='$q_topic_title'>";
			$r .= "\t</fieldset>\n\n";
		}

		$r .= "\t<input type='submit' class='button submit-input' value='$submit' id='$id-submit'>\n";
		$r .= "</form>\n\n";

		echo $r;
	}

}

class BB_Dir_Map {
	var $root;
	var $callback;
	var $callback_args;
	var $keep_empty;
	var $apply_to;
	var $recurse;
	var $dots;
	var $flat = array();
	var $error = false;

	var $_current_root;
	var $_current_file;

	function BB_DIR_MAP( $root, $args = '' ) {
		if ( !is_dir( $root ) ) {
			$this->error = new WP_Error( 'bb_dir_map', __('Not a valid directory') );
			return;
		}

		$this->parse_args( $args );
		if ( is_null($this->apply_to) || is_null($this->dots) ) {
			$this->error = new WP_Error( 'bb_dir_map', __('Invalid arguments') );
			return;
		}
		$this->_current_root = $this->root = rtrim($root, '/\\');
		$this->map();
	}

	function parse_args( $args ) {
		// callback: should be callable
		// callback_args: additional args to pass to callback
		// apply_to: all, files, dirs
		// keep_empty: (bool)
		// recurse: (int) depth, -1 = infinite
		// dots: true (everything), false (nothing), nosvn
		$defaults = array( 'callback' => false, 'callback_args' => false, 'keep_empty' => false, 'apply_to' => 'files', 'recurse' => -1, 'dots' => false );
		$this->callback = is_array($args) && isset($args['callback']) ? $args['callback'] : false;
		$args = wp_parse_args( $args, $defaults );

		foreach ( array('callback', 'keep_empty', 'dots') as $a )
			if ( 'false' == $args[$a] )
				$args[$a] = false;
			elseif ( 'true' == $args[$a] )
				$args[$a] = true;

		if ( !isset($this->callback) )
			$this->callback = $args['callback'];
		if ( !is_callable($this->callback) )
			$this->callback = false;
		$this->callback_args = is_array($args['callback_args']) ? $args['callback_args'] : array();

		$this->keep_empty = (bool) $args['keep_empty'];

		$_apply_to = array( 'files' => 1, 'dirs' => 2, 'all' => 3 ); // This begs to be bitwise
		$this->apply_to = @$_apply_to[$args['apply_to']];

		$this->recurse = (int) $args['recurse'];

		$_dots = array( 1 => 3, 0 => 0, 'nosvn' => 1 ); // bitwise here is a little silly
		$this->dots = @$_dots[$args['dots']];
	}

	function map( $root = false ) {
		$return = array();
		$_dir = dir($root ? $root : $this->_current_root);
		while ( false !== ( $this->_current_file = $_dir->read() ) ) {
			if ( in_array($this->_current_file, array('.', '..')) )
				continue;
			if ( !$this->dots && '.' == $this->_current_file{0} )
				continue;

			$item = $_dir->path . DIRECTORY_SEPARATOR . $this->_current_file;
			$_item = substr( $item, strlen($this->root) + 1 );
			$_callback_args = $this->callback_args;
			array_push( $_callback_args, $item, $_item ); // $item, $_item will be last two args
			if ( is_dir($item) )  { // dir stuff
				if ( 1 & $this->dots && in_array($this->_current_file, array('.svn', 'CVS')) )
					continue;
				if ( 2 & $this->apply_to ) {
					$result = $this->callback ? call_user_func_array($this->callback, $_callback_args) : true;
					if ( $result || $this->keep_empty )
						$this->flat[$_item] = $result;
				}
				if ( 0 > $this->recurse || $this->recurse ) {
					$this->recurse--;
					$this->map( $item );
					$this->recurse++;
				}
			} else { // file stuff
				if ( !(1 & $this->apply_to) )
					continue;
				$result = $this->callback ? call_user_func_array($this->callback, $_callback_args) : true;
				if ( $result || $this->keep_empty )
					$this->flat[$_item] = $result;
			}
		}
	}

	function get_results() {
		return is_wp_error( $this->error ) ? $this->error : $this->flat;
	}
}

class BB_Walker {
	var $tree_type;
	var $db_fields;

	//abstract callbacks
	function start_lvl($output) { return $output; }
	function end_lvl($output)   { return $output; }
	function start_el($output)  { return $output; }
	function end_el($output)    { return $output; }

	function _init() {
		$this->parents = array();
		$this->depth = 1;
		$this->previous_element = '';
	}		

	function walk($elements, $to_depth) {
		$args = array_slice(func_get_args(), 2);
		$output = '';

		// padding at the end
		$last_element->{$this->db_fields['parent']} = 0;
		$last_element->{$this->db_fields['id']} = 0;
		$elements[] = $last_element;

		$flat = ($to_depth == -1) ? true : false;
		foreach ( $elements as $element )
			$output .= call_user_func_array( array(&$this, 'step'), array_merge( array($element, $to_depth), $args ) );

		return $output;
	}

	function step( $element, $to_depth ) {
		if ( !isset($this->depth) )
			$this->_init();

		$args = array_slice(func_get_args(), 2);
		$id_field = $this->db_fields['id'];
		$parent_field = $this->db_fields['parent'];

		$flat = ($to_depth == -1) ? true : false;

		$output = '';

		// If flat, start and end the element and skip the level checks.
		if ( $flat ) {
			// Start the element.
			if ( isset($element->$id_field) && $element->$id_field != 0 ) {
				$cb_args = array_merge( array(&$output, $element, $this->depth - 1), $args);
				call_user_func_array(array(&$this, 'start_el'), $cb_args);
			}

			// End the element.
			if ( isset($element->$id_field) && $element->$id_field != 0 ) {
				$cb_args = array_merge( array(&$output, $element, $this->depth - 1), $args);
				call_user_func_array(array(&$this, 'end_el'), $cb_args);
			}

			return;
		}

		// Walk the tree.
		if ( !empty($this->previous_element) && ($element->$parent_field == $this->previous_element->$id_field) ) {
			// Previous element is my parent. Descend a level.
			array_unshift($this->parents, $this->previous_element);
			if ( !$to_depth || ($this->depth < $to_depth) ) { //only descend if we're below $to_depth
				$cb_args = array_merge( array(&$output, $this->depth), $args);
				call_user_func_array(array(&$this, 'start_lvl'), $cb_args);
			} else if ( $to_depth && $this->depth == $to_depth  ) {  // If we've reached depth, end the previous element.
				$cb_args = array_merge( array(&$output, $this->previous_element, $this->depth), $args);
				call_user_func_array(array(&$this, 'end_el'), $cb_args);
			}
			$this->depth++; //always do this so when we start the element further down, we know where we are
		} else if ( $element->$parent_field == $this->previous_element->$parent_field) {
			// On the same level as previous element.
			if ( !$to_depth || ($this->depth <= $to_depth) ) {
				$cb_args = array_merge( array(&$output, $this->previous_element, $this->depth - 1), $args);
				call_user_func_array(array(&$this, 'end_el'), $cb_args);
			}
		} else if ( $this->depth > 1 ) {
			// Ascend one or more levels.
			if ( !$to_depth || ($this->depth <= $to_depth) ) {
				$cb_args = array_merge( array(&$output, $this->previous_element, $this->depth - 1), $args);
				call_user_func_array(array(&$this, 'end_el'), $cb_args);
			}

			while ( $parent = array_shift($this->parents) ) {
				$this->depth--;
				if ( !$to_depth || ($this->depth < $to_depth) ) {
					$cb_args = array_merge( array(&$output, $this->depth), $args);
					call_user_func_array(array(&$this, 'end_lvl'), $cb_args);
					$cb_args = array_merge( array(&$output, $parent, $this->depth - 1), $args);
					call_user_func_array(array(&$this, 'end_el'), $cb_args);
				}
				if ( isset($parents[0]) && $element->$parent_field == $this->parents[0]->$id_field ) {
					break;
				}
			}
		} else if ( !empty($this->previous_element) ) {
			// Close off previous element.
			if ( !$to_depth || ($this->depth <= $to_depth) ) {
				$cb_args = array_merge( array(&$output, $this->previous_element, $this->depth - 1), $args);
				call_user_func_array(array(&$this, 'end_el'), $cb_args);
			}
		}

		// Start the element.
		if ( !$to_depth || ($this->depth <= $to_depth) ) {
			if ( $element->$id_field != 0 ) {
				$cb_args = array_merge( array(&$output, $element, $this->depth - 1), $args);
				call_user_func_array(array(&$this, 'start_el'), $cb_args);
			}
		}

		$this->previous_element = $element;
		return $output;
	}
}

class BB_Walker_Blank extends BB_Walker { // Used for template functions
	var $tree_type;
	var $db_fields = array( 'id' => '', 'parent' => '' );

	var $start_lvl = '';
	var $end_lvl   = '';

	//abstract callbacks
	function start_lvl( $output, $depth ) { 
		if ( !$this->start_lvl )
			return '';
		$indent = str_repeat("\t", $depth);
		$output .= $indent . "$this->start_lvl\n";
		return $output;
	}

	function end_lvl( $output, $depth )   {
		if ( !$this->end_lvl )
			return '';
		$indent = str_repeat("\t", $depth);
		$output .= $indent . "$this->end_lvl\n";
		return $output;
	}

	function start_el()  { return ''; }
	function end_el()    { return ''; }
}

class BB_Loop {
	var $elements;
	var $walker;
	var $_preserve = array();
	var $_looping = false;

	function &start( $elements, $walker = 'BB_Walker_Blank' ) {
		$a = new BB_Loop( $elements );
		if ( !$a->elements )
			return null;
		$a->walker = new $walker;
		return $a;
	}

	function BB_Loop( &$elements ) {
		$this->elements = $elements;
		if ( !is_array($this->elements) || empty($this->elements) )
			return $this->elements = false;
	}

	function step() {
		if ( !is_array($this->elements) || !current($this->elements) || !is_object($this->walker) )
			return false;

		if ( !$this->_looping ) {
			$r = reset($this->elements);
			$this->_looping = true;
		} else {
			$r = next($this->elements);
		}

		if ( !$args = func_get_args() )
			$args = array( 0 );
		echo call_user_func_array( array(&$this->walker, 'step'), array_merge(array(current($this->elements)), $args) );
		return $r;
	}

	function pad( $pad, $offset = 0 ) {
		if ( !is_array($this->elements) || !is_object($this->walker) )
			return false;

		if ( is_numeric($pad) )
			return $pad * ($this->walker->depth - 1) + (int) $offset;

		return str_repeat( $pad, $this->walker->depth - 1 );
	}

	function preserve( $array ) {
		if ( !is_array( $array ) )
			return false;

		foreach ( $array as $key )
			$this->_preserve[$key] = $GLOBALS[$key];
	}

	function reinstate() {
		foreach ( $this->_preserve as $key => $value )
			$GLOBALS[$key] = $value;
	}

	function classes() {
		if ( !is_array($this->elements) || !is_object($this->walker) )
			return false;
		$classes = array();

		$current = current($this->elements);

		if ( $prev = prev($this->elements) )
			next($this->elements);
		else		
			reset($this->elements);

		if ( $next = next($this->elements) )
			prev($this->elements);
		else
			end($this->elements);

		if ( $next->{$this->walker->db_fields['parent']} == $current->{$this->walker->db_fields['id']} )
			$classes[] = 'bb-parent';
		elseif ( $next->{$this->walker->db_fields['parent']} == $current->{$this->walker->db_fields['parent']} )
			$classes[] = 'bb-precedes-sibling';
		else
			$classes[] = 'bb-last-child';

		if ( $current->{$this->walker->db_fields['parent']} == $prev->{$this->walker->db_fields['id']} )
			$classes[] = 'bb-first-child';
		elseif ( $current->{$this->walker->db_fields['parent']} == $prev->{$this->walker->db_fields['parent']} )
			$classes[] = 'bb-follows-sibling';
		elseif ( $prev )
			$classes[] = 'bb-follows-niece';

		if ( $this->walker->depth > 1 )
			$classes[] = 'bb-child';
		else
			$classes[] = 'bb-root';

		$classes = join(' ', $classes);
		return $classes;
	}

}

?>
