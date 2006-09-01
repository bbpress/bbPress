<?php
class BB_Scripts {
	var $scripts = array();
	var $queue = array();
	var $printed = array();
	var $args = array();

	function BB_Scripts() {
		$this->default_scripts();
	}

	function default_scripts() {
		$this->add( 'fat', '/bb-scripts/fat.js', false, '1.0-RC1_3660' );
		$this->add( 'sack', '/bb-scripts/tw-sack.js', false, '1.6.1' );
		$this->add( 'topic', '/bb-scripts/topic.js', array('sack', 'fat'), '3517' );
	}

	/**
	 * Prints script tags
	 *
	 * Prints the scripts passed to it or the print queue.  Also prints all necessary dependencies.
	 *
	 * @param mixed handles (optional) Scripts to be printed.  (void) prints queue, (string) prints that script, (array of strings) prints those scripts.
	 * @return array Scripts that have been printed
	 */
	function print_scripts( $handles = false ) {
		// Print the queue if nothing is passed.  If a string is passed, print that script.  If an array is passed, print those scripts.
		$handles = false === $handles ? $this->queue : (array) $handles;
		$handles = $this->all_deps( $handles );

		$this->_print_scripts( $handles );
		return $this->printed;
	}

	/**
	 * Internally used helper function for printing script tags
	 *
	 * @param array handles Hierarchical array of scripts to be printed
	 * @see BB_Scripts::all_deps()
	 */
	function _print_scripts( $handles ) {
		global $bb_db_version;

		foreach( array_keys($handles) as $handle ) {
			if ( !$handles[$handle] )
				return;
			elseif ( is_array($handles[$handle]) )
				$this->_print_scripts( $handles[$handle] );
			if ( !in_array($handle, $this->printed) && isset($this->scripts[$handle]) ) {
				$ver = $this->scripts[$handle]->ver ? $this->scripts[$handle]->ver : $bb_db_version;
				if ( isset($this->args[$handle]) )
					$ver .= '&amp;' . $this->args[$handle];
				$src = 0 === strpos($this->scripts[$handle]->src, 'http://') ? $this->scripts[$handle]->src : bb_get_option( 'uri' ) . $this->scripts[$handle]->src;
				echo "<script type='text/javascript' src='$src?ver=$ver'></script>\n";
				$this->printed[] = $handle;
			}
		}
	}
				

	/**
	 * Determines dependencies of scripts
	 *
	 * Recursively builds hierarchical array of script dependencies.  Does NOT catch infinite loops.
	 *
	 * @param mixed handles Accepts (string) script name or (array of strings) script names
	 * @param bool recursion Used internally when function calls itself
	 * @return array Hierarchical array of dependencies
	 */
	function all_deps( $handles, $recursion = false ) {
		if ( ! $handles = (array) $handles )
			return array();
		$return = array();
		foreach ( $handles as $handle ) {
			$handle = explode('?', $handle);
			if ( isset($handle[1]) )
				$this->args[$handle[0]] = $handle[1];
			$handle = $handle[0];
			if ( is_null($return[$handle]) ) // Prime the return array with $handles
				$return[$handle] = true;
			if ( $this->scripts[$handle]->deps ) {
				if ( false !== $return[$handle] && array_diff($this->scripts[$handle]->deps, array_keys($this->scripts)) )
					$return[$handle] = false; // Script required deps which don't exist
				else
					$return[$handle] = $this->all_deps( $this->scripts[$handle]->deps, true ); // Build the hierarchy
			}
			if ( $recursion && false === $return[$handle] )
				return false; // Cut the branch
		}
		return $return;
	}

	/**
	 * Adds script
	 *
	 * Adds the script only if no script of that name already exists
	 *
	 * @param string handle Script name
	 * @param string src Script url
	 * @param array deps (optional) Array of script names on which this script depends
	 * @param string ver (optional) Script version (used for cache busting)
	 * @return array Hierarchical array of dependencies
	 */
	function add( $handle, $src, $deps = array(), $ver = false ) {
		if ( isset($this->scripts[$handle]) )
			return false;
		$this->scripts[$handle] = new _BB_Script( $handle, $src, $deps, $ver );
		return true;
	}

	function remove( $handles ) {
		foreach ( (array) $handles as $handle )
			unset($this->scripts[$handle]);
	}

	function enqueue( $handles ) {
		foreach ( (array) $handles as $handle ) {
			$handle = explode('?', $handle);
			if ( !in_array($handle[0], $this->queue) && isset($this->scripts[$handle[0]]) ) {
				$this->queue[] = $handle[0];
				if ( isset($handle[1]) )
					$this->args[$handle[0]] = $handle[1];
			}
		}
	}

	function dequeue( $handles ) {
		foreach ( (array) $handles as $handle )
			unset( $this->queue[$handle] );
	}

	function query( $handle, $list = 'scripts' ) { // scripts, queue, or printed
		switch ( $list ) :
		case 'scripts':
			if ( isset($this->scripts[$handle]) )
				return $this->scripts[$handle];
			break;
		default:
			if ( in_array($handle, $this->$list) )
				return true;
			break;
		endswitch;
		return false;
	}
			
}

class _BB_Script {
	var $handle;
	var $src;
	var $deps = array();
	var $ver = false;
	var $args = false;

	function _BB_Script() {
		@list($this->handle, $this->src, $this->deps, $this->ver) = func_get_args();
		if ( !is_array($this->deps) )
			$this->deps = array();
		if ( !$this->ver )
			$this->ver = false;
	}
}

/**
 * Prints script tags in document head
 *
 * Called by admin-header.php and by bb_head hook. Since it is called by bb_head on every page load,
 * the function does not instantiate the BB_Scripts object unless script names are explicitly passed.
 * Does make use of already instantiated $bb_scripts if present.
 * Use provided bb_print_scripts hook to register/enqueue new scripts.
 *
 * @see BB_Scripts::print_scripts()
 */
function bb_print_scripts( $handles = false ) {
	do_action( 'bb_print_scripts' );
	if ( '' === $handles ) // for bb_head
		$handles = false;

	global $bb_scripts;
	if ( !is_a($bb_scripts, 'BB_Scripts') ) {
		if ( !$handles )
			return array(); // No need to instantiate if nothing's there.
		else
			$bb_scripts = new BB_Scripts();
	}

	return $bb_scripts->print_scripts( $handles );
}

function bb_register_script( $handle, $src, $deps = array(), $ver = false ) {
	global $bb_scripts;
	if ( !is_a($bb_scripts, 'BB_Scripts') )
		$bb_scripts = new BB_Scripts();

	$bb_scripts->add( $handle, $src, $deps, $ver );
}

function bb_deregister_script( $handle ) {
	global $bb_scripts;
	if ( !is_a($bb_scripts, 'BB_Scripts') )
		$bb_scripts = new BB_Scripts();

	$bb_scripts->remove( $handle );
}

/**
 * Equeues script
 *
 * Registers the script if src provided (does NOT overwrite) and enqueues.
 *
 * @see BB_Script::add(), BB_Script::enqueue()
*/
function bb_enqueue_script( $handle, $src = false, $deps = array(), $ver = false ) {
	global $bb_scripts;
	if ( !is_a($bb_scripts, 'BB_Scripts') )
		$bb_scripts = new BB_Scripts();

	if ( $src ) {
		$_handle = explode('?', $handle);
		$bb_scripts->add( $_handle[0], $src, $deps, $ver );
	}
	$bb_scripts->enqueue( $handle );
}
?>
