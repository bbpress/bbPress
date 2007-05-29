<?php

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
		$args = bb_parse_args( $args, $defaults );

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

	function pad( $pad ) {
		if ( !is_array($this->elements) || !is_object($this->walker) )
			return false;
		return str_repeat( $pad, $this->walker->depth - 1 );
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
