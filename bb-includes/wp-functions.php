<?php

if ( !function_exists('stripslashes_deep') ) :
function stripslashes_deep($value) { // [2700]
   return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
}
endif;

/* Formatting */

if ( !function_exists('clean_pre') ) : // [WP2056]
function clean_pre( $text ) {
	$text = str_replace('<br />', '', $text);
	$text = str_replace('<p>', "\n", $text);
	$text = str_replace('</p>', '', $text);
	return $text;
}
endif;

if ( !function_exists('wp_specialchars') ) :
function wp_specialchars( $text, $quotes = 0 ) { // [4451]
	// Like htmlspecialchars except don't double-encode HTML entities
	$text = str_replace('&&', '&#038;&', $text);
	$text = str_replace('&&', '&#038;&', $text);
	$text = preg_replace('/&(?:$|([^#])(?![a-z1-4]{1,8};))/', '&#038;$1', $text);
	$text = str_replace('<', '&lt;', $text);
	$text = str_replace('>', '&gt;', $text);
	if ( 'double' === $quotes ) {
		$text = str_replace('"', '&quot;', $text);
	} elseif ( 'single' === $quotes ) {
		$text = str_replace("'", '&#039;', $text);
	} elseif ( $quotes ) {
		$text = str_replace('"', '&quot;', $text);
		$text = str_replace("'", '&#039;', $text);
	}
	return $text;
}
endif;

if ( !function_exists('utf8_uri_encode') ) :
function utf8_uri_encode( $utf8_string, $length = 0 ) { // [WP4560]
	$unicode = '';
	$values = array();
	$num_octets = 1;

	for ($i = 0; $i < strlen( $utf8_string ); $i++ ) {

		$value = ord( $utf8_string[ $i ] );

		if ( $value < 128 ) {
			if ( $length && ( strlen($unicode) + 1 > $length ) )
				break; 
			$unicode .= chr($value);
		} else {
			if ( count( $values ) == 0 ) $num_octets = ( $value < 224 ) ? 2 : 3;

			$values[] = $value;

			if ( $length && ( (strlen($unicode) + ($num_octets * 3)) > $length ) )
				break;
			if ( count( $values ) == $num_octets ) {
				if ($num_octets == 3) {
					$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
				} else {
					$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
				}

				$values = array();
				$num_octets = 1;
			}
		}
	}

	return $unicode;
}
endif;

// Escape single quotes, specialchar double quotes, and fix line endings.
if ( !function_exists('js_escape') ) :
function js_escape($text) { // [3907]
	$text = wp_specialchars($text, 'double');
	$text = str_replace('&#039;', "'", $text);
	return preg_replace("/\r?\n/", "\\n", addslashes($text));
}
endif;

/*
 balanceTags
 
 Balances Tags of string using a modified stack.
 
 @param text      Text to be balanced
 @return          Returns balanced text
 @author          Leonard Lin (leonard@acm.org)
 @version         v1.1
 @date            November 4, 2001
 @license         GPL v2.0
 @notes           
 @changelog       
 ---  Modified by Scott Reilly (coffee2code) 02 Aug 2004
             1.2  ***TODO*** Make better - change loop condition to $text
             1.1  Fixed handling of append/stack pop order of end text
                  Added Cleaning Hooks
             1.0  First Version
*/
if ( !function_exists('balanceTags') ) :
function balanceTags($text, $is_comment = 0) {
	
	$tagstack = array(); $stacksize = 0; $tagqueue = ''; $newtext = '';

	# WP bug fix for comments - in case you REALLY meant to type '< !--'
	$text = str_replace('< !--', '<    !--', $text);
	# WP bug fix for LOVE <3 (and other situations with '<' before a number)
	$text = preg_replace('#<([0-9]{1})#', '&lt;$1', $text);

	while (preg_match("/<(\/?\w*)\s*([^>]*)>/",$text,$regex)) {
		$newtext .= $tagqueue;

		$i = strpos($text,$regex[0]);
		$l = strlen($regex[0]);

		// clear the shifter
		$tagqueue = '';
		// Pop or Push
		if ($regex[1][0] == "/") { // End Tag
			$tag = strtolower(substr($regex[1],1));
			// if too many closing tags
			if($stacksize <= 0) { 
				$tag = '';
				//or close to be safe $tag = '/' . $tag;
			}
			// if stacktop value = tag close value then pop
			else if ($tagstack[$stacksize - 1] == $tag) { // found closing tag
				$tag = '</' . $tag . '>'; // Close Tag
				// Pop
				array_pop ($tagstack);
				$stacksize--;
			} else { // closing tag not at top, search for it
				for ($j=$stacksize-1;$j>=0;$j--) {
					if ($tagstack[$j] == $tag) {
					// add tag to tagqueue
						for ($k=$stacksize-1;$k>=$j;$k--){
							$tagqueue .= '</' . array_pop ($tagstack) . '>';
							$stacksize--;
						}
						break;
					}
				}
				$tag = '';
			}
		} else { // Begin Tag
			$tag = strtolower($regex[1]);

			// Tag Cleaning

			// If self-closing or '', don't do anything.
			if((substr($regex[2],-1) == '/') || ($tag == '')) {
			}
			// ElseIf it's a known single-entity tag but it doesn't close itself, do so
			elseif ($tag == 'br' || $tag == 'img' || $tag == 'hr' || $tag == 'input') {
				$regex[2] .= '/';
			} else {	// Push the tag onto the stack
				// If the top of the stack is the same as the tag we want to push, close previous tag
				if (($stacksize > 0) && ($tag != 'div') && ($tagstack[$stacksize - 1] == $tag)) {
					$tagqueue = '</' . array_pop ($tagstack) . '>';
					$stacksize--;
				}
				$stacksize = array_push ($tagstack, $tag);
			}

			// Attributes
			$attributes = $regex[2];
			if($attributes) {
				$attributes = ' '.$attributes;
			}
			$tag = '<'.$tag.$attributes.'>';
			//If already queuing a close tag, then put this tag on, too
			if ($tagqueue) {
				$tagqueue .= $tag;
				$tag = '';
			}
		}
		$newtext .= substr($text,0,$i) . $tag;
		$text = substr($text,$i+$l);
	}  

	// Clear Tag Queue
	$newtext .= $tagqueue;

	// Add Remaining text
	$newtext .= $text;

	// Empty Stack
	while($x = array_pop($tagstack)) {
		$newtext .= '</' . $x . '>'; // Add remaining tags to close
	}

	// WP fix for the bug with HTML comments
	$newtext = str_replace("< !--","<!--",$newtext);
	$newtext = str_replace("<    !--","< !--",$newtext);

	return $newtext;
}
endif;

if ( !function_exists('make_clickable') ) : // [WP4387]
function make_clickable($ret) {
	$ret = ' ' . $ret;
	// in testing, using arrays here was found to be faster
	$ret = preg_replace(
		array(
			'#([\s>])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is',
			'#([\s>])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is',
			'#([\s>])([a-z0-9\-_.]+)@([^,< \n\r]+)#i'),
		array(
			'$1<a href="$2" rel="nofollow">$2</a>',
			'$1<a href="http://$2" rel="nofollow">$2</a>',
			'$1<a href="mailto:$2@$3">$2@$3</a>'),$ret);
	// this one is not in an array because we need it to run last, for cleanup of accidental links within links
	$ret = preg_replace("#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i", "$1$3</a>", $ret);
	$ret = trim($ret);
	return $ret;
}
endif;

if ( !function_exists('seems_utf8') ) :
function seems_utf8($Str) { # by bmorel at ssi dot fr // [1345]
	for ($i=0; $i<strlen($Str); $i++) {
		if (ord($Str[$i]) < 0x80) continue; # 0bbbbbbb
		elseif ((ord($Str[$i]) & 0xE0) == 0xC0) $n=1; # 110bbbbb
		elseif ((ord($Str[$i]) & 0xF0) == 0xE0) $n=2; # 1110bbbb
		elseif ((ord($Str[$i]) & 0xF8) == 0xF0) $n=3; # 11110bbb
		elseif ((ord($Str[$i]) & 0xFC) == 0xF8) $n=4; # 111110bb
		elseif ((ord($Str[$i]) & 0xFE) == 0xFC) $n=5; # 1111110b
		else return false; # Does not match any model
		for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
			if ((++$i == strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80))
			return false;
		}
	}
	return true;
}
endif;

if ( !function_exists('remove_accents') ) :
function remove_accents($string) { // [4320]
	if ( !preg_match('/[\x80-\xff]/', $string) )
		return $string;

	if (seems_utf8($string)) {
		$chars = array(
		// Decompositions for Latin-1 Supplement
		chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
		chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
		chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
		chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
		chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
		chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
		chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
		chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
		chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
		chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
		chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
		chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
		chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
		chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
		chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
		chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
		chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
		chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
		chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
		chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
		chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
		chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
		chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
		chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
		chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
		chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
		chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
		chr(195).chr(191) => 'y',
		// Decompositions for Latin Extended-A
		chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
		chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
		chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
		chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
		chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
		chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
		chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
		chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
		chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
		chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
		chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
		chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
		chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
		chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
		chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
		chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
		chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
		chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
		chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
		chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
		chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
		chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
		chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
		chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
		chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
		chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
		chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
		chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
		chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
		chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
		chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
		chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
		chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
		chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
		chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
		chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
		chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
		chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
		chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
		chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
		chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
		chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
		chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
		chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
		chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
		chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
		chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
		chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
		chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
		chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
		chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
		chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
		chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
		chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
		chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
		chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
		chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
		chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
		chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
		chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
		chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
		chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
		chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
		chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
		// Euro Sign
		chr(226).chr(130).chr(172) => 'E',
		// GBP (Pound) Sign
		chr(194).chr(163) => '');

		$string = strtr($string, $chars);
	} else {
		// Assume ISO-8859-1 if not UTF-8
		$chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
			.chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
			.chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
			.chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
			.chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
			.chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
			.chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
			.chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
			.chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
			.chr(252).chr(253).chr(255);

		$chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

		$string = strtr($string, $chars['in'], $chars['out']);
		$double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
		$double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
		$string = str_replace($double_chars['in'], $double_chars['out'], $string);
	}

	return $string;
}
endif;

/* Forms */

if ( !function_exists('wp_referer_field') ) :
function wp_referer_field() { // [3919]
	$ref = wp_specialchars($_SERVER['REQUEST_URI']);
	echo '<input type="hidden" name="_wp_http_referer" value="'. $ref . '" />';
	if ( wp_get_original_referer() ) {
		$original_ref = wp_specialchars(stripslashes(wp_get_original_referer()));
		echo '<input type="hidden" name="_wp_original_http_referer" value="'. $original_ref . '" />';
	}
}
endif;

if ( !function_exists('wp_original_referer_field') ) :
function wp_original_referer_field() { // [3908]
	echo '<input type="hidden" name="_wp_original_http_referer" value="' . wp_specialchars(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
}
endif;

if ( !function_exists('wp_get_referer') ) :
function wp_get_referer() { // [3908]
	foreach ( array($_REQUEST['_wp_http_referer'], $_SERVER['HTTP_REFERER']) as $ref )
		if ( !empty($ref) )
			return $ref;
	return false;
}
endif;

if ( !function_exists('wp_get_original_referer') ) :
function wp_get_original_referer() { // [3908]
	if ( !empty($_REQUEST['_wp_original_http_referer']) )
		return $_REQUEST['_wp_original_http_referer'];
	return false;
}
endif;

/* Plugin API */

if ( !function_exists('add_filter') ) :
function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) { // [3893]
	global $wp_filter;

	// check that we don't already have the same filter at the same priority
	if ( isset($wp_filter[$tag]["$priority"]) ) {
		foreach($wp_filter[$tag]["$priority"] as $filter) {
			// uncomment if we want to match function AND accepted_args
			// if ( $filter == array($function, $accepted_args) ) {
			if ( $filter['function'] == $function_to_add ) {
				return true;
			}
		}
	}

	// So the format is wp_filter['tag']['array of priorities']['array of ['array (functions, accepted_args)]']
	$wp_filter[$tag]["$priority"][] = array('function'=>$function_to_add, 'accepted_args'=>$accepted_args);
	return true;
}
endif;

if ( !function_exists('apply_filters') ) :
function apply_filters($tag, $string) { // [4179]
	global $wp_filter;

	$args = array();
	for ( $a = 2; $a < func_num_args(); $a++ )
		$args[] = func_get_arg($a);

	merge_filters($tag);

	if ( !isset($wp_filter[$tag]) ) {
		return $string;
	}
	foreach ($wp_filter[$tag] as $priority => $functions) {
		if ( !is_null($functions) ) {
			foreach($functions as $function) {

				$function_name = $function['function'];
				$accepted_args = $function['accepted_args'];

				$the_args = $args;
				array_unshift($the_args, $string);
				if ( $accepted_args > 0 )
					$the_args = array_slice($the_args, 0, $accepted_args);
				elseif ( $accepted_args == 0 )
					$the_args = NULL;

				$string = call_user_func_array($function_name, $the_args);
			}
		}
	}
	return $string;
}
endif;

if ( !function_exists('merge_filters') ) :
function merge_filters($tag) { // [4289]
	global $wp_filter;
	if ( isset($wp_filter['all']) ) {
		foreach ($wp_filter['all'] as $priority => $functions) {
			if ( isset($wp_filter[$tag][$priority]) )
				$wp_filter[$tag][$priority] = array_merge($wp_filter['all'][$priority], $wp_filter[$tag][$priority]);
			else
				$wp_filter[$tag][$priority] = array_merge($wp_filter['all'][$priority], array());
			$wp_filter[$tag][$priority] = array_unique($wp_filter[$tag][$priority]);
		}
	}

	if ( isset($wp_filter[$tag]) )
		uksort( $wp_filter[$tag], "strnatcasecmp" );
}
endif;

if ( !function_exists('remove_filter') ) :
function remove_filter($tag, $function_to_remove, $priority = 10, $accepted_args = 1) { // [3893]
	global $wp_filter;

	// rebuild the list of filters
	if ( isset($wp_filter[$tag]["$priority"]) ) {
		$new_function_list = array();
		foreach($wp_filter[$tag]["$priority"] as $filter) {
			if ( $filter['function'] != $function_to_remove ) {
				$new_function_list[] = $filter;
			}
		}
		$wp_filter[$tag]["$priority"] = $new_function_list;
	}
	return true;
}
endif;

if ( !function_exists('add_action') ) :
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) { // [3893]
	add_filter($tag, $function_to_add, $priority, $accepted_args);
}
endif;

if ( !function_exists('do_action') ) :
function do_action($tag, $arg = '') { // [4179]
	global $wp_filter;
	$args = array();
	if ( is_array($arg) && 1 == count($arg) && is_object($arg[0]) ) // array(&$this)
		$args[] =& $arg[0];
	else
		$args[] = $arg;
	for ( $a = 2; $a < func_num_args(); $a++ )
		$args[] = func_get_arg($a);

	merge_filters($tag);

	if ( !isset($wp_filter[$tag]) )
		return;

	foreach ($wp_filter[$tag] as $priority => $functions) {
		if ( !is_null($functions) ) {
			foreach($functions as $function) {

				$function_name = $function['function'];
				$accepted_args = $function['accepted_args'];

				if ( $accepted_args > 0 )
					$the_args = array_slice($args, 0, $accepted_args);
				elseif ( $accepted_args == 0 )
					$the_args = NULL;
				else
					$the_args = $args;

				call_user_func_array($function_name, $the_args);
			}
		}
	}
}
endif;

if ( !function_exists('do_action_ref_array') ) :
function do_action_ref_array($tag, $args) { // [4186]
	global $wp_filter;

	merge_filters($tag);

	if ( !isset($wp_filter[$tag]) )
		return;

	foreach ($wp_filter[$tag] as $priority => $functions) {
		if ( !is_null($functions) ) {
			foreach($functions as $function) {

				$function_name = $function['function'];
				$accepted_args = $function['accepted_args'];

				if ( $accepted_args > 0 )
					$the_args = array_slice($args, 0, $accepted_args);
				elseif ( $accepted_args == 0 )
					$the_args = NULL;
				else
					$the_args = $args;

				call_user_func_array($function_name, $the_args);
			}
		}
	}
}
endif;

if ( !function_exists('remove_action') ) :
function remove_action($tag, $function_to_remove, $priority = 10, $accepted_args = 1) { // [3893]
	remove_filter($tag, $function_to_remove, $priority, $accepted_args);
}
endif;

/*
add_query_arg: Returns a modified querystring by adding
a single key & value or an associative array.
Setting a key value to emptystring removes the key.
Omitting oldquery_or_uri uses the $_SERVER value.

Parameters:
add_query_arg(newkey, newvalue, oldquery_or_uri) or
add_query_arg(associative_array, oldquery_or_uri)
*/
if ( !function_exists('add_query_arg') ) :
function add_query_arg() { // [4123]
	$ret = '';
	if ( is_array(func_get_arg(0)) ) {
		if ( @func_num_args() < 2 || '' == @func_get_arg(1) )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = @func_get_arg(1);
	} else {
		if ( @func_num_args() < 3 || '' == @func_get_arg(2) )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = @func_get_arg(2);
	}

	if ( $frag = strstr($uri, '#') )
		$uri = substr($uri, 0, -strlen($frag));
	else
		$frag = '';

	if ( preg_match('|^https?://|i', $uri, $matches) ) {
		$protocol = $matches[0];
		$uri = substr($uri, strlen($protocol));
	} else {
		$protocol = '';
	}

	if ( strstr($uri, '?') ) {
		$parts = explode('?', $uri, 2);
		if ( 1 == count($parts) ) {
			$base = '?';
			$query = $parts[0];
		} else {
			$base = $parts[0] . '?';
			$query = $parts[1];
		}
	} else if ( !empty($protocol) || strstr($uri, '/') ) {
		$base = $uri . '?';
		$query = '';
	} else {
		$base = '';
		$query = $uri;
	}

	parse_str($query, $qs);
	if ( is_array(func_get_arg(0)) ) {
		$kayvees = func_get_arg(0);
		$qs = array_merge($qs, $kayvees);
	} else {
		$qs[func_get_arg(0)] = func_get_arg(1);
	}

	foreach($qs as $k => $v) {
		if ( $v != '' ) {
			if ( $ret != '' )
				$ret .= '&';
			$ret .= "$k=$v";
		}
	}
	$ret = $protocol . $base . $ret . $frag;
	if ( get_magic_quotes_gpc() )
		$ret = stripslashes($ret); // parse_str() adds slashes if magicquotes is on.  See: http://php.net/parse_str
	return trim($ret, '?');
}
endif;

/*
remove_query_arg: Returns a modified querystring by removing
a single key or an array of keys.
Omitting oldquery_or_uri uses the $_SERVER value.

Parameters:
remove_query_arg(removekey, [oldquery_or_uri]) or
remove_query_arg(removekeyarray, [oldquery_or_uri])
*/

if ( !function_exists('remove_query_arg') ) :
function remove_query_arg($key, $query='') { // [3857]
	if ( is_array($key) ) { // removing multiple keys
		foreach ( (array) $key as $k )
			$query = add_query_arg($k, '', $query);
		return $query;
	}
	return add_query_arg($key, '', $query);
}
endif;

if ( !function_exists('status_header') ) :
function status_header( $header ) { // [4725]
	if ( 200 == $header )
		$text = 'OK';
	elseif ( 301 == $header )
		$text = 'Moved Permanently';
	elseif ( 302 == $header )
		$text = 'Moved Temporarily';
	elseif ( 304 == $header )
		$text = 'Not Modified';
	elseif ( 404 == $header )
		$text = 'Not Found';
	elseif ( 410 == $header )
		$text = 'Gone';

	if ( version_compare(phpversion(), '4.3.0', '>=') )
		@header("HTTP/1.1 $header $text", true, $header);
	else
		@header("HTTP/1.1 $header $text");
}
endif;

if ( !function_exists('nocache_headers') ) :
function nocache_headers() { // [2623]
	@ header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	@ header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	@ header('Cache-Control: no-cache, must-revalidate, max-age=0');
	@ header('Pragma: no-cache');
}
endif;

if ( !function_exists('cache_javascript_headers') ) :
function cache_javascript_headers() { // Not verbatim WP.  Charset hardcoded.
	$expiresOffset = 864000; // 10 days
	header("Content-type: text/javascript; charset=utf-8");
	header("Vary: Accept-Encoding"); // Handle proxies
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expiresOffset) . " GMT");
}
endif;

if ( !class_exists('WP_Error') ) :
class WP_Error { // [4122]
	var $errors = array();
	var $error_data = array();

	function WP_Error($code = '', $message = '', $data = '') {
		if ( empty($code) )
			return;

		$this->errors[$code][] = $message;

		if ( ! empty($data) )
			$this->error_data[$code] = $data;
	}

	function get_error_codes() {
		if ( empty($this->errors) )
			return array();

		return array_keys($this->errors);
	}

	function get_error_code() {
		$codes = $this->get_error_codes();

		if ( empty($codes) )
			return '';

		return $codes[0];	
	}

	function get_error_messages($code = '') {
		// Return all messages if no code specified.
		if ( empty($code) ) {
			$all_messages = array();
			foreach ( $this->errors as $code => $messages )
				$all_messages = array_merge($all_messages, $messages);

			return $all_messages;
		}

		if ( isset($this->errors[$code]) )
			return $this->errors[$code];
		else
			return array();	
	}

	function get_error_message($code = '') {
		if ( empty($code) )
			$code = $this->get_error_code();
		$messages = $this->get_error_messages($code);
		if ( empty($messages) )
			return '';
		return $messages[0];
	}

	function get_error_data($code = '') {
		if ( empty($code) )
			$code = $this->get_error_code();

		if ( isset($this->error_data[$code]) )
			return $this->error_data[$code];
		return null;
	}

	function add($code, $message, $data = '') {
		$this->errors[$code][] = $message;
		if ( ! empty($data) )
			$this->error_data[$code] = $data;
	}

	function add_data($data, $code = '') {
		if ( empty($code) )
			$code = $this->get_error_code();

		$this->error_data[$code] = $data;
	}
}
endif;

if ( !function_exists('is_wp_error') ) :
function is_wp_error($thing) { // [3667]
	if ( is_object($thing) && is_a($thing, 'WP_Error') )
		return true;
	return false;
}
endif;

if ( !class_exists('WP_Ajax_Response') ) :
class WP_Ajax_Response { // [4187]
	var $responses = array();

	function WP_Ajax_Response( $args = '' ) {
		if ( !empty($args) )
			$this->add($args);
	}

	// a WP_Error object can be passed in 'id' or 'data'
	function add( $args = '' ) {
		if ( is_array($args) )
			$r = &$args;
		else
			parse_str($args, $r);

		$defaults = array('what' => 'object', 'action' => false, 'id' => '0', 'old_id' => false,
				'data' => '', 'supplemental' => array());

		$r = array_merge($defaults, $r);
		extract($r);

		if ( is_wp_error($id) ) {
			$data = $id;
			$id = 0;
		}

		$response = '';
		if ( is_wp_error($data) )
			foreach ( $data->get_error_codes() as $code )
				$response .= "<wp_error code='$code'><![CDATA[" . $data->get_error_message($code) . "]]></wp_error>";
		else
			$response = "<response_data><![CDATA[$data]]></response_data>";

		$s = '';
		if ( (array) $supplemental )
			foreach ( $supplemental as $k => $v )
				$s .= "<$k><![CDATA[$v]]></$k>";

		if ( false === $action )
			$action = $_POST['action'];

		$x = '';
		$x .= "<response action='{$action}_$id'>"; // The action attribute in the xml output is formatted like a nonce action
		$x .=	"<$what id='$id'" . ( false !== $old_id ? "old_id='$old_id'>" : '>' );
		$x .=		$response;
		$x .=		$s;
		$x .=	"</$what>";
		$x .= "</response>";

		$this->responses[] = $x;
		return $x;
	}

	function send() {
		header('Content-type: text/xml');
		echo "<?xml version='1.0' standalone='yes'?><wp_ajax>";
		foreach ( $this->responses as $response )
			echo $response;
		echo '</wp_ajax>';
		die();
	}
}
endif;

/* Templates */

if ( !function_exists('paginate_links') ) :
function paginate_links( $arg = '' ) { // [4276]
	if ( is_array($arg) )
		$a = &$arg;
	else
		parse_str($arg, $a);

	// Defaults
	$base = '%_%'; // http://example.com/all_posts.php%_% : %_% is replaced by format (below)
	$format = '?page=%#%'; // ?page=%#% : %#% is replaced by the page number
	$total = 1;
	$current = 0;
	$show_all = false;
	$prev_next = true;
	$prev_text = __('&laquo; Previous');
	$next_text = __('Next &raquo;');
	$end_size = 1; // How many numbers on either end including the end
	$mid_size = 2; // How many numbers to either side of current not including current
	$type = 'plain';
	$add_args = false; // array of query args to aadd

	extract($a);

	// Who knows what else people pass in $args
	$total    = (int) $total;
	if ( $total < 2 )
		return;
	$current  = (int) $current;
	$end_size = 0  < (int) $end_size ? (int) $end_size : 1; // Out of bounds?  Make it the default.
	$mid_size = 0 <= (int) $mid_size ? (int) $mid_size : 2;
	$add_args = is_array($add_args) ? $add_args : false;
	$r = '';
	$page_links = array();
	$n = 0;
	$dots = false;

	if ( $prev_next && $current && 1 < $current ) :
		$link = str_replace('%_%', 2 == $current ? '' : $format, $base);
		$link = str_replace('%#%', $current - 1, $link);
		if ( $add_args )
			$link = add_query_arg( $add_args, $link );
		$page_links[] = "<a class='prev page-numbers' href='" . wp_specialchars( $link, 1 ) . "'>$prev_text</a>";
	endif;
	for ( $n = 1; $n <= $total; $n++ ) :
		if ( $n == $current ) :
			$page_links[] = "<span class='page-numbers current'>$n</span>";
			$dots = true;
		else :
			if ( $show_all || ( $n <= $end_size || ( $current && $n >= $current - $mid_size && $n <= $current + $mid_size ) || $n > $total - $end_size ) ) :
				$link = str_replace('%_%', 1 == $n ? '' : $format, $base);
				$link = str_replace('%#%', $n, $link);
				if ( $add_args )
					$link = add_query_arg( $add_args, $link );
				$page_links[] = "<a class='page-numbers' href='" . wp_specialchars( $link, 1 ) . "'>$n</a>";
				$dots = true;
			elseif ( $dots && !$show_all ) :
				$page_links[] = "<span class='page-numbers dots'>...</span>";
				$dots = false;
			endif;
		endif;
	endfor;
	if ( $prev_next && $current && ( $current < $total || -1 == $total ) ) :
		$link = str_replace('%_%', $format, $base);
		$link = str_replace('%#%', $current + 1, $link);
		if ( $add_args )
			$link = add_query_arg( $add_args, $link );
		$page_links[] = "<a class='next page-numbers' href='" . wp_specialchars( $link, 1 ) . "'>$next_text</a>";
	endif;
	switch ( $type ) :
		case 'array' :
			return $page_links;
			break;
		case 'list' :
			$r .= "<ul class='page-numbers'>\n\t<li>";
			$r .= join("</li>\n\t<li>", $page_links);
			$r .= "</li>\n</ul>\n";
			break;
		default :
			$r = join("\n", $page_links);
			break;
	endswitch;
	return $r;
}
endif;

if ( !function_exists('is_serialized') ) : // [WP4438]
function is_serialized($data) {
	// if it isn't a string, it isn't serialized
	if ( !is_string($data) )
		return false;
	$data = trim($data);
	if ( 'N;' == $data )
		return true;
	if ( !preg_match('/^([adObis]):/', $data, $badions) )
		return false;
	switch ( $badions[1] ) :
	case 'a' :
	case 'O' :
	case 's' :
		if ( preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data) )
			return true;
		break;
	case 'b' :
	case 'i' :
	case 'd' :
		if ( preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data) )
			return true;
		break;
	endswitch;
	return false;
}
endif;

if ( !function_exists('is_serialized_string') ) : // [WP4438]
function is_serialized_string($data) {
	// if it isn't a string, it isn't a serialized string
	if ( !is_string($data) )
		return false;
	$data = trim($data);
	if ( preg_match('/^s:[0-9]+:.*;$/s',$data) ) // this should fetch all serialized strings
		return true;
	return false;
}
endif;

?>
