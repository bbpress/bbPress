<?php

/**
 * bbPress Kses
 *
 * @package bbPress
 * @subpackage Kses
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

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
 * Filter the content of topics and replies, and turn the value of pre and code
 * tags into entities where appropriate.
 *
 * @since bbPress (r4639)
 *
 * @param string $content
 * @return string
 */
function bbp_pre_code_tags( $content = '' ) {
	return preg_replace_callback( '/<pre.*?>(.*?)<\/pre>/imsu', '_bbp_pre_entities_callback', $content );
}

/**
 * Callback used by bbp_pre_code_tags()
 *
 * @since bbPress (r4639)
 *
 * @internal Used by bbp_improve_pre_code_tags() to make code into entities
 *
 * @param string $matches
 * @return string
 */
function _bbp_pre_entities_callback( $matches = array() ) {
	return str_replace( $matches[1], htmlentities( $matches[1] ), $matches[0] );
}
