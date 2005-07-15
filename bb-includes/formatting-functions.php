<?php

function bb_specialchars( $text, $quotes = 0 ) {
	// Like htmlspecialchars except don't double-encode HTML entities
	$text = preg_replace('/&([^#])(?![a-z12]{1,8};)/', '&#038;$1', $text);-
	$text = str_replace('<', '&lt;', $text);
	$text = str_replace('>', '&gt;', $text);
	if ( $quotes ) {
		$text = str_replace('"', '&quot;', $text);
		$text = str_replace('"', '&#039;', $text);
	}
	return $text;
}

function bb_clean_pre($text) {
	$text = str_replace('<br />', '', $text);
	return $text;
}

function bb_autop($pee, $br = 1) { // Reduced to be faster
	$pee = $pee . "\n"; // just to make things a little easier, pad the end
	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$pee = preg_replace('!(<(?:ul|ol|li|pre|blockquote|p|h[1-6])[^>]*>)!', "\n$1", $pee); 
	$pee = preg_replace('!(</(?:ul|ol|li|pre|blockquote|p|h[1-6])>)!', "$1\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines 
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	$pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $pee); // make paragraphs, including one at the end 
	$pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace 
    $pee = preg_replace('!<p>\s*(</?(?:ul|ol|li|pre|blockquote|p|h[1-6])[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?(?:ul|ol|li|pre|blockquote|p|h[1-6])[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?(?:ul|ol|li|pre|blockquote|p|h[1-6])[^>]*>)\s*</p>!', "$1", $pee); 
	if ($br) $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
	$pee = preg_replace('!(</?(?:ul|ol|li|pre|blockquote|p|h[1-6])[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)>)!', '$1', $pee);
	$pee = preg_replace('!(<pre.*?>)(.*?)</pre>!ise', " stripslashes('$1') .  clean_pre('$2')  . '</pre>' ", $pee);
	
	return $pee; 
}
function encodeit($text) {
	$text = stripslashes($text); // because it's a regex callback
	$text = htmlspecialchars($text, ENT_QUOTES);
	$text = preg_replace("|\n+|", "\n", $text);
	$text = nl2br($text);
	$text = str_replace('&amp;lt;', '&lt;', $text);
	$text = str_replace('&amp;gt;', '&gt;', $text);
	return $text;
}

function decodeit($text) {
	$text = stripslashes($text); // because it's a regex callback
	$trans_table = array_flip(get_html_translation_table(HTML_ENTITIES));
	$text = strtr($text, $trans_table);;
	$text = str_replace('<br />', '', $text);
	$text = str_replace('&#38;', '&', $text);
	$text = str_replace('&#39;', "'", $text);
	return $text;
}

function code_trick( $text ) {
	$text = preg_replace("|`(.*?)`|se", "'<code>' . encodeit('$1') . '</code>'", $text);
	return $text;
}

function code_trick_reverse( $text ) {
	$text = preg_replace("|<code>(.*?)</code>|se", "'`' . decodeit('$1') . '`'", $text);
	$text = str_replace('<p>', '', $text);
	$text = str_replace('</p>', "\n", $text);
	return $text;
}

function encode_bad( $text) {
	$text = bb_specialchars($text);
	$text = preg_replace('|&lt;(/?strong)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?em)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?a.*?)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?ol)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?p)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;br /&gt;|', '<br />', $text);
	$text = preg_replace('|&lt;(/?ul)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?li)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?blockquote.*?)&gt;|', '<$1>', $text);
	$text = preg_replace('|&lt;(/?code)&gt;|', '<$1>', $text);

	$text = preg_replace("|`(.*?)`|se", "'<code>' . encodeit('$1') . '</code>'", $text);

	return $text;
}

function bb_filter_kses($data) {
	$allowedtags = array(
		'a' => array(
			'href' => array(),
			'title' => array(),
			'rel' => array()),
		'blockquote' => array('cite' => array()),
		'br' => array(),
		'code' => array(),
		'em' => array(),
		'strong' => array(),
		'ul' => array(),
		'ol' => array(),
		'li' => array()
	);

	if ( !function_exists('wp_kses') )
		require_once( BBPATH . '/bb-includes/kses.php');
	return wp_kses($data, $allowedtags);
}

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

function user_sanitize( $text ) {
    $text = preg_replace('/[^a-z0-9_-]/i', '', $text);
	return $text;
}

function tag_sanitize( $tag ) {
	$tag	= trim         ( $tag );
	$tag	= strtolower   ( $tag );
	$tag	= user_sanitize( $tag );
	return $tag;
}

function show_context( $term, $text ) {
	$text = strip_tags($text);
	$term = preg_quote($term);
	$text = preg_replace("|.*?(.{0,80})$term(.{0,80}).*|is", "... $1<strong>$term</strong>$2 ...", $text, 1);
	$text = substr($text, 0, 210);
	return $text;
}

function bb_make_clickable($ret) {
	$ret = ' ' . $ret . ' ';
	$ret = preg_replace("#([\s>])(https?)://([^\s<>{}()]+[^\s.,<>{}()])#i", "$1<a href='$2://$3'>$2://$3</a>", $ret);
	$ret = preg_replace("#(\s)www\.([a-z0-9\-]+)\.([a-z0-9\-.\~]+)((?:/[^ <>{}()\n\r]*[^., <>{}()\n\r]?)?)#i", "$1<a href='http://www.$2.$3$4'>www.$2.$3$4</a>", $ret);
	$ret = preg_replace("#(\s)([a-z0-9\-_.]+)@([^,< \n\r]+)#i", "$1<a href=\"mailto:$2@$3\">$2@$3</a>", $ret);
	$ret = trim($ret);
	return $ret;
}

function bb_fix_link( $link ) {
	if ( !strstr( $link, 'http' ) ) // for 'www.example.com'
		$link = 'http://' . $link;
	if ( !strstr( $link, '.' ) ) // these are usually random words
		$link = '';
	return $link;
}

function bb_make_feed( $link ) {
	$link = trim( $link );
	if ( 0 !== strpos( $link, 'feed:' ) )
		return 'feed:' . $link;
	else
		return $link;
}

function closed_title( $title ) {
	global $topic;
	if ( '0' === $topic->topic_open )
		return '[closed] <span class="closed">' . $title . '</span>';
	else
		return $title;
}
?>
