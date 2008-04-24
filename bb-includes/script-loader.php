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
		$this->add( 'fat', '/' . BB_INC . 'js/fat.js', array('add-load-event'), '1.0-RC1_3660' );
		$this->add( 'prototype', '/' . BB_INC . 'js/prototype.js', false, '1.5.0' );
		$this->add( 'wp-ajax', '/' . BB_INC . 'js/wp-ajax-js.php', array('prototype'), '2.1-beta2' );
		$this->add( 'listman', '/' . BB_INC . 'js/list-manipulation-js.php', array('add-load-event', 'wp-ajax', 'fat'), '440' );
		$this->add( 'topic', '/' . BB_INC . 'js/topic.js', array('add-load-event', 'listman', 'jquery'), '20080422' );
		$this->add( 'jquery', '/' . BB_INC . 'js/jquery/jquery.js', false, '1.1.3.1');
		$this->add( 'interface', '/' . BB_INC . 'js/jquery/interface.js', array('jquery'), '1.2.3');
		$this->add( 'jquery-color', '/' . BB_INC . 'js/jquery/jquery.color.js', array('jquery'), '2.0-4561' );
		$this->add( 'add-load-event', '/' . BB_INC . 'js/add-load-event.js' );
		$this->add( 'content-forums', '/bb-admin/js/content-forums.js', array('listman', 'interface'), '20080319' );
		$this->localize( 'content-forums', 'bbSortForumsL10n', array(
			'handleText' => __('drag'),
			'saveText' => __('Save Forum Order &#187;'),
			'editText' => __('Edit Forum Order &#187;')
		));
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
		global $bb_db_version;

		// Print the queue if nothing is passed.  If a string is passed, print that script.  If an array is passed, print those scripts.
		$handles = false === $handles ? $this->queue : (array) $handles;
		$this->all_deps( $handles );

		$to_print = apply_filters( 'print_scripts_array', array_keys($this->to_print) );

		foreach( $to_print as $handle ) {
			if ( !in_array($handle, $this->printed) && isset($this->scripts[$handle]) ) {
				if ( $this->scripts[$handle]->src ) { // Else it defines a group.
					$ver = $this->scripts[$handle]->ver ? $this->scripts[$handle]->ver : $bb_db_version;
					if ( isset($this->args[$handle]) )
						$ver .= '&amp;' . $this->args[$handle];
					$src = $this->scripts[$handle]->src;

					if ( !preg_match('|^https?://|', $src) )
						$src = rtrim(bb_get_option( 'uri' ), ' /') . $src;

					$src = add_query_arg('ver', $ver, $src);
					$src = clean_url(apply_filters( 'bb_script_loader_src', $src ));
					$this->print_scripts_l10n( $handle );
					echo "<script type='text/javascript' src='$src'></script>\n";
				}
				$this->printed[] = $handle;
			}
		}

		$this->to_print = array();
		return $this->printed;
	}

	function print_scripts_l10n( $handle ) {
		if ( empty($this->scripts[$handle]->l10n_object) || empty($this->scripts[$handle]->l10n) || !is_array($this->scripts[$handle]->l10n) )
			return;

		$object_name = $this->scripts[$handle]->l10n_object;

		echo "<script type='text/javascript'>\n";
		echo "/* <![CDATA[ */\n";
		echo "\t$object_name = {\n";
		$eol = '';
		foreach ( $this->scripts[$handle]->l10n as $var => $val ) {
			echo "$eol\t\t$var: \"" . js_escape( $val ) . '"';
			$eol = ",\n";
		}
		echo "\n\t}\n";
		echo "/* ]]> */\n";
		echo "</script>\n";
	}

	/**
	 * Determines dependencies of scripts
	 *
	 * Recursively builds array of scripts to print taking dependencies into account.  Does NOT catch infinite loops.
	 *
	 * @param mixed handles Accepts (string) script name or (array of strings) script names
	 * @param bool recursion Used internally when function calls itself
	 */
	function all_deps( $handles, $recursion = false ) {
		if ( !$handles = (array) $handles )
			return false;

		foreach ( $handles as $handle ) {
			$handle = explode('?', $handle);
			if ( isset($handle[1]) )
				$this->args[$handle[0]] = $handle[1];
			$handle = $handle[0];

			if ( isset($this->to_print[$handle]) ) // Already grobbed it and its deps
				continue;

			$keep_going = true;
			if ( !isset($this->scripts[$handle]) )
				$keep_going = false; // Script doesn't exist
			elseif ( $this->scripts[$handle]->deps && array_diff($this->scripts[$handle]->deps, array_keys($this->scripts)) )
				$keep_going = false; // Script requires deps which don't exist (not a necessary check.  efficiency?)
			elseif ( $this->scripts[$handle]->deps && !$this->all_deps( $this->scripts[$handle]->deps, true ) )
				$keep_going = false; // Script requires deps which don't exist

			if ( !$keep_going ) { // Either script or its deps don't exist.
				if ( $recursion )
					return false; // Abort this branch.
				else
					continue; // We're at the top level.  Move on to the next one.
			}

			$this->to_print[$handle] = true;
		}

		return true;
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

	/**
	 * Localizes a script
	 *
	 * Localizes only if script has already been added
	 *
	 * @param string handle Script name
	 * @param string object_name Name of JS object to hold l10n info
	 * @param array l10n Array of JS var name => localized string
	 * @return bool Successful localization
	 */
	function localize( $handle, $object_name, $l10n ) {
		if ( !isset($this->scripts[$handle]) )
			return false;
		return $this->scripts[$handle]->localize( $object_name, $l10n );
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
	var $l10n_object = '';
	var $l10n = array();

	function _BB_Script() {
		@list($this->handle, $this->src, $this->deps, $this->ver ) = func_get_args();
		if ( !is_array($this->deps) )
			$this->deps = array();
		if ( !$this->ver )
			$this->ver = false;
	}

	function localize( $object_name, $l10n ) {
		if ( !$object_name || !is_array($l10n) )
			return false;
		$this->l10n_object = $object_name;
		$this->l10n = $l10n;
		return true;
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

/**
 * Localizes a script
 *
 * Localizes only if script has already been added
 *
 * @see BB_Script::localize()
 */
function bb_localize_script( $handle, $object_name, $l10n ) {
	global $bb_scripts;
	if ( !is_a($bb_scripts, 'BB_Scripts') )
		return false;

	return $bb_scripts->localize( $handle, $object_name, $l10n );
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

function bb_prototype_before_jquery( $js_array ) {
	if ( false === $jquery = array_search( 'jquery', $js_array ) )
		return $js_array;

	if ( false === $prototype = array_search( 'prototype', $js_array ) )
		return $js_array;

	if ( $prototype < $jquery )
		return $js_array;

	unset($js_array[$prototype]);

	array_splice( $js_array, $jquery, 0, 'prototype' );

	return $js_array;
}

function bb_just_in_time_script_localization() {
	bb_localize_script( 'topic', 'bbTopicJS', array(
		'currentUserId' => bb_get_current_user_info( 'id' ),
		'topicId' => get_topic_id(),
		'favoritesLink' => get_favorites_link(),
		'isFav' => (int) is_user_favorite( bb_get_current_user_info( 'id' ) ),
		'confirmPostDelete' => __("Are you sure you wanna delete this post by '%author%'?"),
		'confirmTagDelete' => __("Are you sure you want to remove the '%tag%' tag?"),
		'favLinkYes' => __( 'favorites' ),
		'favLinkNo' => __( '?' ),
		'favYes' => __( 'This topic is one of your %favLinkYes% [%favDel%]' ),
		'favNo' => __( '%favAdd% (%favLinkNo%)' ),
		'favDel' => __( '&times;' ),
		'favAdd' => __( 'Add this topic to your favorites' )
	));
}

add_action( 'bb_print_scripts', 'bb_just_in_time_script_localization' );
add_filter( 'print_scripts_array', 'bb_prototype_before_jquery' );

?>
