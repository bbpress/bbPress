<?php

/**
 * bbPress Formatting
 *
 * @package bbPress
 * @subpackage Formatting
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Kses **********************************************************************/

/**
 * Custom allowed tags for forum topics and replies
 *
 * Allows all users to post links, quotes, code, formatting, lists, and images
 *
 * @since bbPress (r4603)
 *
 * @return array Associative array of allowed tags and attributes
 */
function bbp_kses_allowed_tags() {
	return apply_filters( 'bbp_kses_allowed_tags', array(

		// Links
		'a' => array(
			'href'     => array(),
			'title'    => array(),
			'rel'      => array()
		),

		// Quotes
		'blockquote'   => array(
			'cite'     => array()
		),

		// Code
		'code'         => array(),
		'pre'          => array(),

		// Formatting
		'em'           => array(),
		'strong'       => array(),
		'del'          => array(
			'datetime' => true,
		),

		// Lists
		'ul'           => array(),
		'ol'           => array(
			'start'    => true,
		),
		'li'           => array(),

		// Images
		/*
		'img'          => array(
			'src'      => true,
			'border'   => true,
			'alt'      => true,
			'height'   => true,
			'width'    => true,
		)
		*/
	) );
}

/**
 * Custom kses filter for forum topics and replies, for filtering incoming data
 *
 * @since bbPress (r4603)
 *
 * @param string $data Content to filter, expected to be escaped with slashes
 * @return string Filtered content
 */
function bbp_filter_kses( $data = '' ) {
	return addslashes( wp_kses( stripslashes( $data ), bbp_kses_allowed_tags() ) );
}

/**
 * Custom kses filter for forum topics and replies, for raw data
 *
 * @since bbPress (r4603)
 *
 * @param string $data Content to filter, expected to not be escaped
 * @return string Filtered content
 */
function bbp_kses_data( $data = '' ) {
	return wp_kses( $data , bbp_kses_allowed_tags() );
}

/** Formatting ****************************************************************/

/**
 * Filter the topic or reply content and output code and pre tags
 *
 * @since bbPress (r4641)
 *
 * @param string $content Topic and reply content
 * @return string Partially encodedd content
 */
function bbp_code_trick( $content = '' ) {
	$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
	$content = preg_replace_callback( "|(`)(.*?)`|",      'bbp_encode_callback', $content );
	$content = preg_replace_callback( "!(^|\n)`(.*?)`!s", 'bbp_encode_callback', $content );

	return $content;
}

/**
 * When editing a topic or reply, reverse the code trick so the textarea
 * contains the correct editable content.
 *
 * @since bbPress (r4641)
 *
 * @param string $content Topic and reply content
 * @return string Partially encodedd content
 */
function bbp_code_trick_reverse( $content = '' ) {

	// Setup variables
	$openers = array( '<p>', '<br />' );
	$content    = preg_replace_callback( "!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s", 'bbp_decode_callback', $content );

	// Do the do
	$content    = str_replace( $openers,       '',       $content );
	$content    = str_replace( '</p>',         "\n",     $content );
	$content    = str_replace( '<coded_br />', '<br />', $content );
	$content    = str_replace( '<coded_p>',    '<p>',    $content );
	$content    = str_replace( '</coded_p>',   '</p>',   $content );

	return $content;
}

/**
 * Filter the content and encode any bad HTML tags
 *
 * @since bbPress (r4641)
 *
 * @param string $content Topic and reply content
 * @return string Partially encodedd content
 */
function bbp_encode_bad( $content = '' ) {

	// Setup variables
	$content = _wp_specialchars( $content, ENT_NOQUOTES );
	$content = preg_split( '@(`[^`]*`)@m', $content, -1, PREG_SPLIT_NO_EMPTY + PREG_SPLIT_DELIM_CAPTURE );
	$allowed = bbp_kses_allowed_tags();
	$empty   = array(
		'br'    => true,
		'hr'    => true,
		'img'   => true,
		'input' => true,
		'param' => true,
		'area'  => true,
		'col'   => true,
		'embed' => true
	);

	// Add 'p' and 'br' tags to allowed array, so they are not encoded
	$allowed['p']  = array();
	$allowed['br'] = array();

	// Loop through allowed tags and compare for empty and normal tags
	foreach ( $allowed as $tag => $args ) {
		$preg = $args ? "{$tag}(?:\s.*?)?" : $tag;

		// Which walker to use based on the tag and argments
		if ( isset( $empty[$tag] ) ) {
			array_walk( $content, 'bbp_encode_empty_callback',  $preg );
		} else {
			array_walk( $content, 'bbp_encode_normal_callback', $preg );
		}
	}

	// Return the joined content array
	return join( '', $content );
}

/** Code Callbacks ************************************************************/

/**
 * Callback to encode the tags in topic or reply content
 *
 * @since bbPress (r4641)
 *
 * @param array $matches
 * @return string
 */
function bbp_encode_callback( $matches = array() ) {
	$content = trim( $matches[2] );
	$content = htmlspecialchars( $content, ENT_QUOTES );
	$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
	$content = preg_replace( "|\n\n\n+|", "\n\n", $content );
	$content = str_replace( '&amp;amp;', '&amp;', $content );
	$content = str_replace( '&amp;lt;',  '&lt;',  $content );
	$content = str_replace( '&amp;gt;',  '&gt;',  $content );
	$content = '<code>' . $content . '</code>';

	if ( "`" != $matches[1] )
		$content = '<pre>' . $content . '</pre>';

	return $content;
}

/**
 * Callback to decode the tags in topic or reply content
 *
 * @since bbPress (r4641)
 *
 * @param array $matches
 * @return string
 */
function bbp_decode_callback( $matches = array() ) {

	// Setup variables
	$trans_table = array_flip( get_html_translation_table( HTML_ENTITIES ) );
	$amps        = array( '&#38;','&amp;' );
	$content     = $matches[2];
	$content     = strtr( $content, $trans_table );

	// Do the do
	$content = str_replace( '<br />', '<coded_br />', $content );
	$content = str_replace( '<p>',    '<coded_p>',    $content );
	$content = str_replace( '</p>',   '</coded_p>',   $content );
	$content = str_replace( $amps,    '&',            $content );
	$content = str_replace( '&#39;',  "'",            $content );

	// Return content wrapped in code tags
	return '`' . $content . '`';
}

/**
 * Callback to replace empty HTML tags in a content string
 *
 * @since bbPress (r4641)
 *
 * @internal Used by bbp_encode_bad()
 * @param string $content
 * @param string $key Not used
 * @param string $preg
 */
function bbp_encode_empty_callback( &$content = '', $key = '', $preg = '' ) {
	if ( strpos( $content, '`' ) !== 0 ) {
		$content = preg_replace( "|&lt;({$preg})\s*?/*?&gt;|i", '<$1 />', $content );
	}
}

/**
 * Callback to replace normal HTML tags in a content string
 *
 * @since bbPress (r4641)
 *
 * @internal Used by bbp_encode_bad()
 * @param type $content
 * @param type $key
 * @param type $preg
 */
function bbp_encode_normal_callback( &$content = '', $key = '', $preg = '') {
	if ( strpos( $content, '`' ) !== 0 ) {
		$content = preg_replace( "|&lt;(/?{$preg})&gt;|i", '<$1>', $content );
	}
}
