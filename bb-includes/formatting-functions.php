<?php
function bb_autop($pee, $br = 1) { // Reduced to be faster
	$pee = $pee . "\n"; // just to make things a little easier, pad the end
	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$pee = preg_replace('!(<(?:ul|ol|li|blockquote|pre|p)[^>]*>)!', "\n$1", $pee); 
	$pee = preg_replace('!(</(?:ul|ol|li|blockquote|pre|p)>)!', "$1\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines 
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	$pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $pee); // make paragraphs, including one at the end 
	$pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace 
	$pee = preg_replace('!<p>\s*(</?(?:ul|ol|li|blockquote|p)[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?(?:ul|ol|li|blockquote|p)[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?(?:ul|ol|li|blockquote|p)[^>]*>)\s*</p>!', "$1", $pee); 
	if ($br) $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
	$pee = preg_replace('!(</?(?:ul|ol|li|blockquote|p)[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|ul|ol)>)!', '$1', $pee);
	if ( false !== strpos( $pee, '<pre' ) )
		$pee = preg_replace('!(<pre.*?>)(.*?)</pre>!ise', " stripslashes('$1') .  stripslashes(clean_pre('$2'))  . '</pre>' ", $pee);
	return $pee; 
}

function encodeit($text) {
	$text = stripslashes($text); // because it's a regex callback
	$text = htmlspecialchars($text, ENT_QUOTES);
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$text = preg_replace("|\n\n\n+|", "\n\n", $text);
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
	$text = preg_replace("|`(.*?)`|se", "'<pre><code>' . encodeit('$1') . '</code></pre>'", $text);
	return $text;
}

function code_trick_reverse( $text ) {
	$text = preg_replace("!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!se", "'`' . decodeit('$2') . '`'", $text);
	$text = str_replace(array('<p>', '<br />'), '', $text);
	$text = str_replace('</p>', "\n", $text);
	return $text;
}

function encode_bad( $text ) {
	$text = wp_specialchars( $text );
	$text = preg_replace('|&lt;br /&gt;|', '<br />', $text);
	foreach ( bb_allowed_tags() as $tag => $args ) {
		if ( 'br' == $tag )
			continue;
		if ( $args )
			$text = preg_replace("|&lt;(/?$tag.*?)&gt;|", '<$1>', $text);
		else
			$text = preg_replace("|&lt;(/?$tag)&gt;|", '<$1>', $text);
	}

	$text = code_trick( $text );
	return $text;
}

function bb_filter_kses($data) {
	$allowedtags = bb_allowed_tags();
	return wp_kses($data, $allowedtags);
}

function bb_allowed_tags() {
	$tags = array(
		'a' => array(
			'href' => array(),
			'title' => array(),
			'rel' => array()),
		'blockquote' => array('cite' => array()),
		'br' => array(),
		'code' => array(),
		'pre' => array(),
		'em' => array(),
		'strong' => array(),
		'ul' => array(),
		'ol' => array(),
		'li' => array()
	);
	return apply_filters( 'bb_allowed_tags', $tags );
}

function bb_rel_nofollow( $text ) {
	$text = preg_replace('|<a (.+?)>|i', '<a $1 rel="nofollow">', $text);
	return $text;
}

function user_sanitize( $text, $strict = false ) {
	$raw = $text;
	if ( $strict ) {
		$text = preg_replace('/[^a-z0-9-]/i', '', $text);
		$text = preg_replace('|-+|', '-', $text);
	} else
		$text = preg_replace('/[^a-z0-9_-]/i', '', $text); // For backward compatibility.
	return apply_filters( 'user_sanitize', $text, $raw, $strict );
}

function tag_sanitize( $tag ) {
	return sanitize_with_dashes( $tag );
}

function sanitize_with_dashes( $text ) { // Multibyte aware
	$text = strip_tags($text);
	$text = remove_accents($text);

	$text = strtolower($text);
	$text = preg_replace('/&(^\x80-\xff)+?;/', '', $text); // kill entities
	$text = preg_replace('/[^a-z0-9\x80-\xff _-]/', '', $text);
	$text = preg_replace('/\s+/', '-', $text);
	$text = preg_replace(array('|-+|', '|_+|'), array('-', '_'), $text); // Kill the repeats

	return $text;
}

function show_context( $term, $text ) {
	$text = strip_tags($text);
	$term = preg_quote($term);
	$text = preg_replace("|.*?(.{0,80})$term(.{0,80}).*|is", "... $1<strong>$term</strong>$2 ...", $text, 1);
	$text = substr($text, 0, 210);
	return $text;
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
		return sprintf(__('[closed] %s'), $title);
	return $title;
}

function make_link_view_all( $link ) {
	return wp_specialchars( add_query_arg( 'view', 'all', $link ) );
}

function bb_gmtstrtotime( $string ) {
	if ( is_numeric($string) )
		return $string;
	if ( !is_string($string) )
		return -1;

	if ( stristr($string, 'utc') || stristr($string, 'gmt') || stristr($string, '+0000') )
		return strtotime($string);

	if ( -1 == $time = strtotime($string . ' +0000') )
		return strtotime($string);

	return $time;
}

?>
