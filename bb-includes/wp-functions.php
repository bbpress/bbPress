<?php

if ( !function_exists('stripslashes_deep') ) :
function stripslashes_deep($value) { // [4495]
	return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
}
endif;

/* Formatting */

if ( !function_exists( 'clean_url' ) ) : // [WP5700]
function clean_url( $url, $protocols = null ) {
	if ('' == $url) return $url;
	$url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%]|i', '', $url);
	$strip = array('%0d', '%0a');
	$url = str_replace($strip, '', $url);
	$url = str_replace(';//', '://', $url);
	// Append http unless a relative link starting with / or a php file.
	if ( strpos($url, '://') === false &&
		substr( $url, 0, 1 ) != '/' && !preg_match('/^[a-z0-9-]+?\.php/i', $url) )
		$url = 'http://' . $url;

	$url = preg_replace('/&([^#])(?![a-z]{2,8};)/', '&#038;$1', $url);
	if ( !is_array($protocols) )
		$protocols = array('http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet'); 
	if ( wp_kses_bad_protocol( $url, $protocols ) != $url )
		return '';
	return $url;
}
endif;

if ( !function_exists('clean_pre') ) : // [WP2056]
function clean_pre( $text ) {
	$text = str_replace('<br />', '', $text);
	$text = str_replace('<p>', "\n", $text);
	$text = str_replace('</p>', '', $text);
	return $text;
}
endif;

if ( !function_exists('wp_specialchars') ) :
function wp_specialchars( $text, $quotes = 0 ) { // [WP4451]
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
function js_escape($text) { // [WP4660]
	$safe_text = wp_specialchars($text, 'double');
	$safe_text = str_replace('&#039;', "'", $safe_text);
	$safe_text = preg_replace("/\r?\n/", "\\n", addslashes($safe_text));
	return apply_filters('js_escape', $safe_text, $text);
}
endif;

// Escaping for HTML attributes
if ( !function_exists('attribute_escape') ) :
function attribute_escape($text) { // [WP4660]
	$safe_text = wp_specialchars($text, true);
	return apply_filters('attribute_escape', $safe_text, $text);
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
if ( !function_exists('balanceTags') ) : // [WP5623]
function balanceTags($text, $force = false) {

	if ( !$force ) // This line differs from WP
		return $text;

	$tagstack = array(); $stacksize = 0; $tagqueue = ''; $newtext = '';
	$single_tags = array('br', 'hr', 'img', 'input'); //Known single-entity/self-closing tags
	$nestable_tags = array('blockquote', 'div', 'span'); //Tags that can be immediately nested within themselves

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
			elseif ( in_array($tag, $single_tags) ) {
				$regex[2] .= '/';
			} else {	// Push the tag onto the stack
				// If the top of the stack is the same as the tag we want to push, close previous tag
				if (($stacksize > 0) && !in_array($tag, $nestable_tags) && ($tagstack[$stacksize - 1] == $tag)) {
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

if ( !function_exists('seems_utf8') ) : // [WP1345]
function seems_utf8($Str) { # by bmorel at ssi dot fr
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

if ( !function_exists('remove_accents') ) : // [WP4320]
function remove_accents($string) {
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
function wp_referer_field() { // [WP4656]
	$ref = attribute_escape($_SERVER['REQUEST_URI']);
	echo '<input type="hidden" name="_wp_http_referer" value="'. $ref . '" />';
	if ( wp_get_original_referer() ) {
		$original_ref = attribute_escape(stripslashes(wp_get_original_referer()));
		echo '<input type="hidden" name="_wp_original_http_referer" value="'. $original_ref . '" />';
	}
}
endif;

if ( !function_exists('wp_original_referer_field') ) :
function wp_original_referer_field() { // [WP4656]
	echo '<input type="hidden" name="_wp_original_http_referer" value="' . attribute_escape(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
}
endif;

if ( !function_exists('wp_get_referer') ) :
function wp_get_referer() { // [WP3908]
	foreach ( array($_REQUEST['_wp_http_referer'], $_SERVER['HTTP_REFERER']) as $ref )
		if ( !empty($ref) )
			return $ref;
	return false;
}
endif;

if ( !function_exists('wp_get_original_referer') ) :
function wp_get_original_referer() { // [WP3908]
	if ( !empty($_REQUEST['_wp_original_http_referer']) )
		return $_REQUEST['_wp_original_http_referer'];
	return false;
}
endif;

/* Plugin API */

if ( !function_exists('add_filter') ) : // [WP5169]
function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
	global $wp_filter, $merged_filters;

	// So the format is wp_filter['tag']['array of priorities']['array of functions serialized']['array of ['array (functions, accepted_args)]']
	$wp_filter[$tag][$priority][serialize($function_to_add)] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
	unset( $merged_filters[ $tag ] );
	return true;
}
endif;


if ( !function_exists('apply_filters') ) : // [WP5170]
function apply_filters($tag, $string) {
	global $wp_filter, $merged_filters;

	if ( !isset( $merged_filters[ $tag ] ) )
		merge_filters($tag);

	if ( !isset($wp_filter[$tag]) )
		return $string;

	reset( $wp_filter[ $tag ] );

	$args = func_get_args();

	do{
		foreach( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) ){
				$args[1] = $string;
				$string = call_user_func_array($the_['function'], array_slice($args, 1, (int) $the_['accepted_args']));
			}

	} while ( next($wp_filter[$tag]) );

	return $string;
}
endif;

if ( !function_exists('merge_filters') ) : // [WP5169]
function merge_filters($tag) {
	global $wp_filter, $merged_filters;

	if ( isset($wp_filter['all']) && is_array($wp_filter['all']) )
		$wp_filter[$tag] = array_merge($wp_filter['all'], (array) $wp_filter[$tag]);

	if ( isset($wp_filter[$tag]) ){
		reset($wp_filter[$tag]);
		uksort($wp_filter[$tag], "strnatcasecmp");
	}
	$merged_filters[ $tag ] = true;
}
endif;

if ( !function_exists('remove_filter') ) : // [WP5393]
function remove_filter($tag, $function_to_remove, $priority = 10, $accepted_args = 1) {
	$function_to_remove = serialize($function_to_remove);

	$r = isset($GLOBALS['wp_filter'][$tag][$priority][$function_to_remove]);

	unset($GLOBALS['wp_filter'][$tag][$priority][$function_to_remove]);
	unset($GLOBALS['merged_filters'][$tag]);

	return $r;
}
endif;

if ( !function_exists('add_action') ) : // [WP3893]
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
	add_filter($tag, $function_to_add, $priority, $accepted_args);
}
endif;

if ( !function_exists('do_action') ) : // [WP5409]
function do_action($tag, $arg = '') {
	global $wp_filter, $wp_actions;

	if ( is_array($wp_actions) )
		$wp_actions[] = $tag;
	else
		$wp_actions = array($tag);

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

	do{
		foreach( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) )
				call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

	} while ( next($wp_filter[$tag]) );

}
endif;

if ( !function_exists('do_action_ref_array') ) : // [WP4955]
function do_action_ref_array($tag, $args) {
	global $wp_filter, $wp_actions;

	if ( !is_array($wp_actions) )
		$wp_actions = array($tag);
	else
		$wp_actions[] = $tag;

	merge_filters($tag);

	if ( !isset($wp_filter[$tag]) )
		return;

	do{
		foreach( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) )
				call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

	} while ( next($wp_filter[$tag]) );

}
endif;

if ( !function_exists('did_action') ) : // [WP5413]
function did_action($tag) {
	global $wp_actions;

	if ( empty($wp_actions) )
		return 0;

	return count(array_keys($wp_actions, $tag));
}
endif;

if ( !function_exists('remove_action') ) : // [WP5393]
function remove_action($tag, $function_to_remove, $priority = 10, $accepted_args = 1) {
	return remove_filter($tag, $function_to_remove, $priority, $accepted_args);
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
if ( !function_exists('add_query_arg') ) : // [WP5709]
function add_query_arg() {
	$ret = '';
	if ( is_array(func_get_arg(0)) ) {
		if ( @func_num_args() < 2 || false === @func_get_arg(1) )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = @func_get_arg(1);
	} else {
		if ( @func_num_args() < 3 || false === @func_get_arg(2) )
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

	if (strpos($uri, '?') !== false) {
		$parts = explode('?', $uri, 2);
		if ( 1 == count($parts) ) {
			$base = '?';
			$query = $parts[0];
		} else {
			$base = $parts[0] . '?';
			$query = $parts[1];
		}
	} elseif (!empty($protocol) || strpos($uri, '/') !== false) {
		$base = $uri . '?';
		$query = '';
	} else {
		$base = '';
		$query = $uri;
	}

	wp_parse_str($query, $qs);
	$qs = urlencode_deep($qs);
	if ( is_array(func_get_arg(0)) ) {
		$kayvees = func_get_arg(0);
		$qs = array_merge($qs, $kayvees);
	} else {
		$qs[func_get_arg(0)] = func_get_arg(1);
	}

	foreach($qs as $k => $v) {
		if ( $v !== FALSE ) {
			if ( $ret != '' )
				$ret .= '&';
			if ( empty($v) && !preg_match('|[?&]' . preg_quote($k, '|') . '=|', $query) )
				$ret .= $k;
			else
				$ret .= "$k=$v";
		}
	}
	$ret = trim($ret, '?');
	$ret = $protocol . $base . $ret . $frag;
	$ret = rtrim($ret, '?');
	return $ret;
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

if ( !function_exists('remove_query_arg') ) : // [WP5705]
function remove_query_arg($key, $query=FALSE) {
	if ( is_array($key) ) { // removing multiple keys
		foreach ( (array) $key as $k )
			$query = add_query_arg($k, FALSE, $query);
		return $query;
	}
	return add_query_arg($key, FALSE, $query);
}
endif;

if ( !function_exists('get_status_header_desc') ) : // [WP5700]
function get_status_header_desc( $code ) {
	global $wp_header_to_desc;

	$code = (int) $code;

	if ( isset( $wp_header_to_desc[$code] ) ) {
		return $wp_header_to_desc[$code];
	} else {
		return '';
	}
}
endif;

if ( !function_exists('status_header') ) : // [WP5700]
function status_header( $header ) {
	$text = get_status_header_desc( $header );

	if ( empty( $text ) )
		return false;

	$protocol = $_SERVER["SERVER_PROTOCOL"];
	if ( ('HTTP/1.1' != $protocol) && ('HTTP/1.0' != $protocol) )
		$protocol = 'HTTP/1.0';
	$status_header = "$protocol $header $text";
	$status_header = apply_filters('status_header', $status_header, $header, $text, $protocol);

	if ( version_compare( phpversion(), '4.3.0', '>=' ) ) {
		return @header( $status_header, true, $header );
	} else {
		return @header( $status_header );
	}
}
endif;

if ( !function_exists('nocache_headers') ) :
function nocache_headers() { // [WP2623]
	@ header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	@ header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	@ header('Cache-Control: no-cache, must-revalidate, max-age=0');
	@ header('Pragma: no-cache');
}
endif;

if ( !function_exists('cache_javascript_headers') ) :
function cache_javascript_headers() { // [WP5640] Not verbatim WP.  Charset hardcoded.
	$expiresOffset = 864000; // 10 days
	header("Content-Type: text/javascript; charset=utf-8");
	header("Vary: Accept-Encoding"); // Handle proxies
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expiresOffset) . " GMT");
}
endif;

/* Templates */

if ( !function_exists('paginate_links') ) : // [WP5709]
function paginate_links( $args = '' ) {
	$defaults = array( 
		'base' => '%_%', // http://example.com/all_posts.php%_% : %_% is replaced by format (below)
		'format' => '?page=%#%', // ?page=%#% : %#% is replaced by the page number
		'total' => 1,
		'current' => 0,
		'show_all' => false,
		'prev_next' => true,
		'prev_text' => __('&laquo; Previous'),
		'next_text' => __('Next &raquo;'),
		'end_size' => 1, // How many numbers on either end including the end
		'mid_size' => 2, // How many numbers to either side of current not including current
		'type' => 'plain',
		'add_args' => false // array of query args to aadd
	);

	$args = wp_parse_args( $args, $defaults );
	extract($args, EXTR_SKIP);

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
		$page_links[] = "<a class='prev page-numbers' href='" . clean_url($link) . "'>$prev_text</a>";
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
				$page_links[] = "<a class='page-numbers' href='" . clean_url($link) . "'>$n</a>";
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
		$page_links[] = "<a class='next page-numbers' href='" . clean_url($link) . "'>$next_text</a>";
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

if ( !function_exists('ent2ncr') ) : // [WP2689]
function ent2ncr($text) {
	$to_ncr = array(
		'&quot;' => '&#34;',
		'&amp;' => '&#38;',
		'&frasl;' => '&#47;',
		'&lt;' => '&#60;',
		'&gt;' => '&#62;',
		'|' => '&#124;',
		'&nbsp;' => '&#160;',
		'&iexcl;' => '&#161;',
		'&cent;' => '&#162;',
		'&pound;' => '&#163;',
		'&curren;' => '&#164;',
		'&yen;' => '&#165;',
		'&brvbar;' => '&#166;',
		'&brkbar;' => '&#166;',
		'&sect;' => '&#167;',
		'&uml;' => '&#168;',
		'&die;' => '&#168;',
		'&copy;' => '&#169;',
		'&ordf;' => '&#170;',
		'&laquo;' => '&#171;',
		'&not;' => '&#172;',
		'&shy;' => '&#173;',
		'&reg;' => '&#174;',
		'&macr;' => '&#175;',
		'&hibar;' => '&#175;',
		'&deg;' => '&#176;',
		'&plusmn;' => '&#177;',
		'&sup2;' => '&#178;',
		'&sup3;' => '&#179;',
		'&acute;' => '&#180;',
		'&micro;' => '&#181;',
		'&para;' => '&#182;',
		'&middot;' => '&#183;',
		'&cedil;' => '&#184;',
		'&sup1;' => '&#185;',
		'&ordm;' => '&#186;',
		'&raquo;' => '&#187;',
		'&frac14;' => '&#188;',
		'&frac12;' => '&#189;',
		'&frac34;' => '&#190;',
		'&iquest;' => '&#191;',
		'&Agrave;' => '&#192;',
		'&Aacute;' => '&#193;',
		'&Acirc;' => '&#194;',
		'&Atilde;' => '&#195;',
		'&Auml;' => '&#196;',
		'&Aring;' => '&#197;',
		'&AElig;' => '&#198;',
		'&Ccedil;' => '&#199;',
		'&Egrave;' => '&#200;',
		'&Eacute;' => '&#201;',
		'&Ecirc;' => '&#202;',
		'&Euml;' => '&#203;',
		'&Igrave;' => '&#204;',
		'&Iacute;' => '&#205;',
		'&Icirc;' => '&#206;',
		'&Iuml;' => '&#207;',
		'&ETH;' => '&#208;',
		'&Ntilde;' => '&#209;',
		'&Ograve;' => '&#210;',
		'&Oacute;' => '&#211;',
		'&Ocirc;' => '&#212;',
		'&Otilde;' => '&#213;',
		'&Ouml;' => '&#214;',
		'&times;' => '&#215;',
		'&Oslash;' => '&#216;',
		'&Ugrave;' => '&#217;',
		'&Uacute;' => '&#218;',
		'&Ucirc;' => '&#219;',
		'&Uuml;' => '&#220;',
		'&Yacute;' => '&#221;',
		'&THORN;' => '&#222;',
		'&szlig;' => '&#223;',
		'&agrave;' => '&#224;',
		'&aacute;' => '&#225;',
		'&acirc;' => '&#226;',
		'&atilde;' => '&#227;',
		'&auml;' => '&#228;',
		'&aring;' => '&#229;',
		'&aelig;' => '&#230;',
		'&ccedil;' => '&#231;',
		'&egrave;' => '&#232;',
		'&eacute;' => '&#233;',
		'&ecirc;' => '&#234;',
		'&euml;' => '&#235;',
		'&igrave;' => '&#236;',
		'&iacute;' => '&#237;',
		'&icirc;' => '&#238;',
		'&iuml;' => '&#239;',
		'&eth;' => '&#240;',
		'&ntilde;' => '&#241;',
		'&ograve;' => '&#242;',
		'&oacute;' => '&#243;',
		'&ocirc;' => '&#244;',
		'&otilde;' => '&#245;',
		'&ouml;' => '&#246;',
		'&divide;' => '&#247;',
		'&oslash;' => '&#248;',
		'&ugrave;' => '&#249;',
		'&uacute;' => '&#250;',
		'&ucirc;' => '&#251;',
		'&uuml;' => '&#252;',
		'&yacute;' => '&#253;',
		'&thorn;' => '&#254;',
		'&yuml;' => '&#255;',
		'&OElig;' => '&#338;',
		'&oelig;' => '&#339;',
		'&Scaron;' => '&#352;',
		'&scaron;' => '&#353;',
		'&Yuml;' => '&#376;',
		'&fnof;' => '&#402;',
		'&circ;' => '&#710;',
		'&tilde;' => '&#732;',
		'&Alpha;' => '&#913;',
		'&Beta;' => '&#914;',
		'&Gamma;' => '&#915;',
		'&Delta;' => '&#916;',
		'&Epsilon;' => '&#917;',
		'&Zeta;' => '&#918;',
		'&Eta;' => '&#919;',
		'&Theta;' => '&#920;',
		'&Iota;' => '&#921;',
		'&Kappa;' => '&#922;',
		'&Lambda;' => '&#923;',
		'&Mu;' => '&#924;',
		'&Nu;' => '&#925;',
		'&Xi;' => '&#926;',
		'&Omicron;' => '&#927;',
		'&Pi;' => '&#928;',
		'&Rho;' => '&#929;',
		'&Sigma;' => '&#931;',
		'&Tau;' => '&#932;',
		'&Upsilon;' => '&#933;',
		'&Phi;' => '&#934;',
		'&Chi;' => '&#935;',
		'&Psi;' => '&#936;',
		'&Omega;' => '&#937;',
		'&alpha;' => '&#945;',
		'&beta;' => '&#946;',
		'&gamma;' => '&#947;',
		'&delta;' => '&#948;',
		'&epsilon;' => '&#949;',
		'&zeta;' => '&#950;',
		'&eta;' => '&#951;',
		'&theta;' => '&#952;',
		'&iota;' => '&#953;',
		'&kappa;' => '&#954;',
		'&lambda;' => '&#955;',
		'&mu;' => '&#956;',
		'&nu;' => '&#957;',
		'&xi;' => '&#958;',
		'&omicron;' => '&#959;',
		'&pi;' => '&#960;',
		'&rho;' => '&#961;',
		'&sigmaf;' => '&#962;',
		'&sigma;' => '&#963;',
		'&tau;' => '&#964;',
		'&upsilon;' => '&#965;',
		'&phi;' => '&#966;',
		'&chi;' => '&#967;',
		'&psi;' => '&#968;',
		'&omega;' => '&#969;',
		'&thetasym;' => '&#977;',
		'&upsih;' => '&#978;',
		'&piv;' => '&#982;',
		'&ensp;' => '&#8194;',
		'&emsp;' => '&#8195;',
		'&thinsp;' => '&#8201;',
		'&zwnj;' => '&#8204;',
		'&zwj;' => '&#8205;',
		'&lrm;' => '&#8206;',
		'&rlm;' => '&#8207;',
		'&ndash;' => '&#8211;',
		'&mdash;' => '&#8212;',
		'&lsquo;' => '&#8216;',
		'&rsquo;' => '&#8217;',
		'&sbquo;' => '&#8218;',
		'&ldquo;' => '&#8220;',
		'&rdquo;' => '&#8221;',
		'&bdquo;' => '&#8222;',
		'&dagger;' => '&#8224;',
		'&Dagger;' => '&#8225;',
		'&bull;' => '&#8226;',
		'&hellip;' => '&#8230;',
		'&permil;' => '&#8240;',
		'&prime;' => '&#8242;',
		'&Prime;' => '&#8243;',
		'&lsaquo;' => '&#8249;',
		'&rsaquo;' => '&#8250;',
		'&oline;' => '&#8254;',
		'&frasl;' => '&#8260;',
		'&euro;' => '&#8364;',
		'&image;' => '&#8465;',
		'&weierp;' => '&#8472;',
		'&real;' => '&#8476;',
		'&trade;' => '&#8482;',
		'&alefsym;' => '&#8501;',
		'&crarr;' => '&#8629;',
		'&lArr;' => '&#8656;',
		'&uArr;' => '&#8657;',
		'&rArr;' => '&#8658;',
		'&dArr;' => '&#8659;',
		'&hArr;' => '&#8660;',
		'&forall;' => '&#8704;',
		'&part;' => '&#8706;',
		'&exist;' => '&#8707;',
		'&empty;' => '&#8709;',
		'&nabla;' => '&#8711;',
		'&isin;' => '&#8712;',
		'&notin;' => '&#8713;',
		'&ni;' => '&#8715;',
		'&prod;' => '&#8719;',
		'&sum;' => '&#8721;',
		'&minus;' => '&#8722;',
		'&lowast;' => '&#8727;',
		'&radic;' => '&#8730;',
		'&prop;' => '&#8733;',
		'&infin;' => '&#8734;',
		'&ang;' => '&#8736;',
		'&and;' => '&#8743;',
		'&or;' => '&#8744;',
		'&cap;' => '&#8745;',
		'&cup;' => '&#8746;',
		'&int;' => '&#8747;',
		'&there4;' => '&#8756;',
		'&sim;' => '&#8764;',
		'&cong;' => '&#8773;',
		'&asymp;' => '&#8776;',
		'&ne;' => '&#8800;',
		'&equiv;' => '&#8801;',
		'&le;' => '&#8804;',
		'&ge;' => '&#8805;',
		'&sub;' => '&#8834;',
		'&sup;' => '&#8835;',
		'&nsub;' => '&#8836;',
		'&sube;' => '&#8838;',
		'&supe;' => '&#8839;',
		'&oplus;' => '&#8853;',
		'&otimes;' => '&#8855;',
		'&perp;' => '&#8869;',
		'&sdot;' => '&#8901;',
		'&lceil;' => '&#8968;',
		'&rceil;' => '&#8969;',
		'&lfloor;' => '&#8970;',
		'&rfloor;' => '&#8971;',
		'&lang;' => '&#9001;',
		'&rang;' => '&#9002;',
		'&larr;' => '&#8592;',
		'&uarr;' => '&#8593;',
		'&rarr;' => '&#8594;',
		'&darr;' => '&#8595;',
		'&harr;' => '&#8596;',
		'&loz;' => '&#9674;',
		'&spades;' => '&#9824;',
		'&clubs;' => '&#9827;',
		'&hearts;' => '&#9829;',
		'&diams;' => '&#9830;'
	);

	return str_replace( array_keys($to_ncr), array_values($to_ncr), $text );
}
endif;

if ( !function_exists('wp_parse_args') ) : // [WP5709]
function wp_parse_args( $args, $defaults = '' ) {
	if ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	else
		return $r;
}
endif;

if ( !function_exists('urlencode_deep') ) : // [WP5261]
function urlencode_deep($value) {
	 $value = is_array($value) ?
		 array_map('urlencode_deep', $value) :
		 urlencode($value);

	 return $value;
}
endif;

if ( !function_exists( 'zeroise' ) ) : // [WP2018]
function zeroise($number,$threshold) { // function to add leading zeros when necessary
	return sprintf('%0'.$threshold.'s', $number);
}
endif;

if ( !function_exists( 'backslashit' ) ) : // [WP3855]
function backslashit($string) {
	$string = preg_replace('/^([0-9])/', '\\\\\\\\\1', $string);
	$string = preg_replace('/([a-z])/i', '\\\\\1', $string);
	return $string;
}
endif;

if ( !function_exists( 'wp_parse_str' ) ) : // [WP5709]
function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if ( get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array ); // parse_str() adds slashes if magicquotes is on.  See: http://php.net/parse_str
	$array = apply_filters( 'wp_parse_str', $array );
}
endif;

if ( !function_exists( 'wp_remote_fopen' ) ) : // [WP4752]
function wp_remote_fopen( $uri ) {
	$timeout = 10;
	$parsed_url = @parse_url($uri);

	if ( !$parsed_url || !is_array($parsed_url) )
		return false;

	if ( !isset($parsed_url['scheme']) || !in_array($parsed_url['scheme'], array('http','https')) )
		$uri = 'http://' . $uri;

	if ( ini_get('allow_url_fopen') ) {
		$fp = @fopen( $uri, 'r' );
		if ( !$fp )
			return false;

		//stream_set_timeout($fp, $timeout); // Requires php 4.3
		$linea = '';
		while( $remote_read = fread($fp, 4096) )
			$linea .= $remote_read;
		fclose($fp);
		return $linea;
	} else if ( function_exists('curl_init') ) {
		$handle = curl_init();
		curl_setopt ($handle, CURLOPT_URL, $uri);
		curl_setopt ($handle, CURLOPT_CONNECTTIMEOUT, 1);
		curl_setopt ($handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($handle, CURLOPT_TIMEOUT, $timeout);
		$buffer = curl_exec($handle);
		curl_close($handle);
		return $buffer;
	} else {
		return false;
	}
}
endif;

?>
