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
	$text = str_replace('&#39;', '"', $text);
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
		require_once( ABSPATH . '/bb-includes/kses.php');
	return wp_kses($data, $allowedtags);
}

function user_sanitize( $text ) {
    $text = preg_replace('/[^a-z0-9_-]/i', '', $text);
	return $text;
}

function show_context( $term, $text ) {
	$text = strip_tags($text);
	$term = preg_quote($term);
	$text = preg_replace("|.*?(.{0,80})$term(.{0,80}).*|is", "... $1<strong>$term</strong>$2 ...", $text, 1);
	$text = substr($text, 0, 210);
	return $text;
}

?>