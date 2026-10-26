<?php

/**
 * bbPress Formatting
 *
 * @package bbPress
 * @subpackage Formatting
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** WordPress Content *********************************************************/

/**
 * Filter shortcodes in bbPress post content in WordPress content loops.
 *
 * bbPress renders its post content without running shortcodes, but a theme can
 * pass the raw content through WordPress's the_content filter. Offer a bbPress
 * sub-filter for this case without affecting shortcodes in other post types.
 *
 * @since 2.6.20 bbPress (r7848)
 * @since 2.6.21 bbPress (r8046) Remove shortcodes from WordPress feed content.
 *
 * @param false|string $output  Short-circuit value from an earlier filter.
 * @param string       $tag     Shortcode name.
 * @param array        $attr    Shortcode attributes.
 * @param array        $matches Shortcode regular expression match.
 * @return false|string Filtered short-circuit value.
 */
function bbp_pre_do_shortcode_tag( $output, $tag, $attr, $matches ) {
	if ( ! doing_filter( 'the_content' ) ) {
		return $output;
	}

	if ( ! in_array( get_post_type(), bbp_get_post_types(), true ) ) {
		return $output;
	}

	return apply_filters( 'bbp_pre_do_shortcode_tag', $output, $tag, $attr, $matches );
}

/**
 * Prevent shortcodes in bbPress post content unless explicitly allowed.
 *
 * The allow-list is empty by default because bbPress does not execute
 * shortcodes in forum, topic, or reply content on its own content paths.
 *
 * @since 2.6.20 bbPress (r7848)
 *
 * @param false|string $output  Short-circuit value from an earlier filter.
 * @param string       $tag     Shortcode name.
 * @param array        $attr    Shortcode attributes.
 * @param array        $matches Shortcode regular expression match.
 * @return false|string Short-circuit value or the literal shortcode.
 */
function bbp_prevent_content_shortcodes( $output, $tag, $attr, $matches ) {
	if ( is_feed() ) {
		return '';
	}

	/**
	 * Filter shortcode tags allowed to run in bbPress post content.
	 *
	 * @since 2.6.20 bbPress (r7848)
	 *
	 * @param array        $allowed   Allowed shortcode tags. Empty by default.
	 * @param string|false $post_type Current post type.
	 */
	$allowed = (array) apply_filters( 'bbp_allowed_content_shortcodes', array(), get_post_type() );

	if ( ! in_array( $tag, $allowed, true ) ) {
		$output = $matches[0];
	}

	return $output;
}

/** Kses **********************************************************************/

/**
 * Custom allowed tags for forum topics and replies
 *
 * Allows all users to post links, quotes, code, formatting, lists, and images
 *
 * @since 2.3.0 bbPress (r4604)
 *
 * @return array Associative array of allowed tags and attributes
 */
function bbp_kses_allowed_tags() {

	// Filter & return
	return (array) apply_filters(
		'bbp_kses_allowed_tags',
		array(

			// Links
			'a' => array(
				'href'   => true,
				'title'  => true,
				'rel'    => true,
				'target' => true
			),

			// Quotes
			'blockquote' => array(
				'cite' => true
			),

			// Code
			'code' => array(),
			'pre'  => array(
				'class' => true
			),

			// Formatting
			'em'     => array(),
			'strong' => array(),
			'del'    => array(
				'datetime' => true,
				'cite'     => true
			),
			'ins' => array(
				'datetime' => true,
				'cite'     => true
			),

			// Lists
			'ul' => array(),
			'ol' => array(
				'start' => true,
			),
			'li' => array(),

			// Images
			'img' => array(
				'src'    => true,
				'border' => true,
				'alt'    => true,
				'height' => true,
				'width'  => true,
			)
		)
	);
}

/**
 * Custom kses filter for forum topics and replies, for filtering incoming data
 *
 * @since 2.3.0 bbPress (r4604)
 *
 * @param string $data Content to filter, expected to be escaped with slashes
 * @return string Filtered content
 */
function bbp_filter_kses( $data = '' ) {
	return wp_slash( wp_kses( wp_unslash( $data ), bbp_kses_allowed_tags() ) );
}

/**
 * Custom kses filter for forum topics and replies, for raw data
 *
 * @since 2.3.0 bbPress (r4604)
 *
 * @param string $data Content to filter, expected to not be escaped
 * @return string Filtered content
 */
function bbp_kses_data( $data = '' ) {
	return wp_kses( $data, bbp_kses_allowed_tags() );
}

/** Formatting ****************************************************************/

/**
 * Wrap runs of list items that have no list container in displayed content.
 *
 * Runs after force_balance_tags(), which closes items but does not supply
 * missing parent lists. Requires the HTML API available in WordPress 6.9.
 * Public HTML APIs validate explicit tag structure before wrappers are added.
 * All original source bytes are retained. Implicit closures, foreign content,
 * and ambiguous literal list-item tags are left unchanged.
 * Code examples and block content are excluded from repair. Fragments without
 * orphan list items and sites using other character encodings are unchanged.
 * Character references retain their spelling for later formatting filters.
 * Nested malformed list containers are not reconstructed.
 *
 * @since 2.6.21 bbPress (r8135)
 *
 * @param string $content Balanced topic or reply content.
 * @param int    $post_id Optional. Topic or reply ID.
 * @return string Content with missing unordered list containers supplied.
 */
function bbp_wrap_orphan_list_items( $content = '', $post_id = 0 ) {
	if ( ! class_exists( 'WP_HTML_Processor' ) || false === stripos( $content, '<li' ) || ! preg_match( '/<li(?=[\t\n\f\r \/>])/i', $content ) || has_blocks( $content ) ) {
		return $content;
	}
	if ( ! empty( $post_id ) && has_blocks( get_post_field( 'post_content', $post_id, 'raw' ) ) ) {
		return $content;
	}
	$processor = WP_HTML_Processor::create_fragment( $content );
	if ( ! is_callable( array( $processor, 'serialize_token' ) ) || ! is_utf8_charset() ) {
		return $content;
	}
	$excluded = array( 'UL', 'OL', 'MENU', 'DIR', 'PRE', 'CODE', 'LISTING' );
	$orphan = false;
	// Valid lists need only this initial semantic scan.
	while ( $processor->next_tag( 'LI' ) ) {
		if ( ! array_intersect( $excluded, $processor->get_breadcrumbs() ) ) {
			$orphan = true;
			break;
		}
	}
	if ( ! $orphan ) {
		return $content;
	}

	// Match source boundaries only when lexical and semantic tags agree exactly.
	$item_pattern = '/<\/?li(?=[\t\n\f\r \/>])/i';
	$processor = WP_HTML_Processor::create_fragment( $content );
	$tags = new WP_HTML_Tag_Processor( $content );
	$query = array( 'tag_closers' => 'visit' );
	$insertions = array();
	$search = 0;
	$depth = 0;
	$end = 0;
	while ( $tags->next_tag( $query ) ) {
		if ( ! $processor->next_tag( $query ) || $tags->get_tag() !== $processor->get_tag() || $tags->is_tag_closer() !== $processor->is_tag_closer() || 'html' !== $processor->get_namespace() ) {
			return $content;
		}
		if ( 'LI' !== $tags->get_tag() ) {
			continue;
		}
		if ( ! preg_match( $item_pattern, $content, $item, PREG_OFFSET_CAPTURE, $search ) || $tags->is_tag_closer() !== ( '/' === $item[0][0][1] ) ) {
			return $content;
		}
		$offset = $item[0][1];
		$search = $offset + strlen( $item[0][0] );
		if ( $tags->is_tag_closer() ) {
			if ( ! preg_match( '/\G<\/li[\t\n\f\r ]*>/i', $content, $closer, 0, $offset ) ) {
				return $content;
			}
			if ( $depth && $processor->get_current_depth() === $depth - 1 ) {
				$end = $offset + strlen( $closer[0] );
				$insertions[ $end ] = '</ul>';
				$depth = 0;
			}
		} elseif ( ! array_intersect( $excluded, $processor->get_breadcrumbs() ) ) {
			if ( $depth ) {
				return $content;
			}
			$gap = $end ? substr( $content, $end, $offset - $end ) : '';
			$adjacent = '' === trim( $gap, " \t\n\r\f" );
			// Only whitespace and comments join consecutive items.
			if ( ! $adjacent && ( false !== strpos( $gap, '<!--' ) || false !== strpos( $gap, '&' ) ) ) {
				$spacing = new WP_HTML_Tag_Processor( $gap );
				$adjacent = true;
				while ( $spacing->next_token() ) {
					if ( '#comment' !== $spacing->get_token_type() && ( '#text' !== $spacing->get_token_type() || '' !== trim( $spacing->get_modifiable_text(), " \t\n\r\f" ) ) ) {
						$adjacent = false;
						break;
					}
				}
			}
			if ( $end && $adjacent ) {
				unset( $insertions[ $end ] );
			} else {
				$insertions[ $offset ] = '<ul>';
			}
			$depth = $processor->get_current_depth();
		}
	}
	if ( $depth || preg_match( $item_pattern, $content, $item, 0, $search ) || $processor->next_tag( $query ) || null !== $processor->get_last_error() || $processor->paused_at_incomplete_token() || $tags->paused_at_incomplete_token() ) {
		return $content;
	}
	// Apply insertions only after the whole fragment passes validation.
	$output = '';
	$copied = 0;
	foreach ( $insertions as $offset => $wrapper ) {
		$output .= substr( $content, $copied, $offset - $copied );
		$output .= $wrapper;
		$copied = $offset;
	}
	return $output . substr( $content, $copied );
}

/**
 * Filter the topic or reply content and output code and pre tags
 *
 * @since 2.3.0 bbPress (r4642)
 *
 * @param string $content Topic and reply content
 * @return string Partially encoded content
 */
function bbp_code_trick( $content = '' ) {
	$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
	$content = preg_replace_callback('|(`)(.*?)`|',      'bbp_encode_callback', $content );
	$content = preg_replace_callback( "!(^|\n)`(.*?)`!s", 'bbp_encode_callback', $content );

	return $content;
}

/**
 * When editing a topic or reply, reverse the code trick so the textarea
 * contains the correct editable content.
 *
 * @since 2.3.0 bbPress (r4642)
 *
 * @param string $content Topic and reply content
 * @return string Partially encoded content
 */
function bbp_code_trick_reverse( $content = '' ) {

	// Setup variables
	$openers = array( '<p>', '<br />' );
	$content = preg_replace_callback( '!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s', 'bbp_decode_callback', $content );

	// Do the do
	$content = str_replace( $openers,       '',       $content );
	$content = str_replace( '</p>',         "\n",     $content );
	$content = str_replace( '<coded_br />', '<br />', $content );
	$content = str_replace( '<coded_p>',    '<p>',    $content );
	$content = str_replace( '</coded_p>',   '</p>',   $content );

	return $content;
}

/**
 * Filter the content and encode any bad HTML tags
 *
 * @since 2.3.0 bbPress (r4642)
 *
 * @param string $content Topic and reply content
 * @return string Partially encoded content
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

	// Loop through allowed tags and compare for empty and normal tags
	foreach ( $allowed as $tag => $args ) {
		$preg = $args ? "{$tag}(?:\s.*?)?" : $tag;

		// Which walker to use based on the tag and arguments
		if ( isset( $empty[ $tag ] ) ) {
			array_walk( $content, 'bbp_encode_empty_callback',  $preg );
		} else {
			array_walk( $content, 'bbp_encode_normal_callback', $preg );
		}
	}

	// Return the joined content array
	return implode( '', $content );
}

/** Code Callbacks ************************************************************/

/**
 * Callback to encode the tags in topic or reply content
 *
 * @since 2.3.0 bbPress (r4642)
 *
 * @param array $matches
 * @return string
 */
function bbp_encode_callback( $matches = array() ) {

	// Trim inline code, not pre blocks (to prevent removing indentation)
	if ( '`' === $matches[1] ) {
		$content = trim( $matches[2] );
	} else {
		$content = $matches[2];
	}

	// Do some replacing
	$content = htmlspecialchars( $content, ENT_QUOTES );
	$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
	$content = preg_replace( "|\n\n\n+|", "\n\n", $content );
	$content = str_replace( '&amp;amp;', '&amp;', $content );
	$content = str_replace( '&amp;lt;',  '&lt;',  $content );
	$content = str_replace( '&amp;gt;',  '&gt;',  $content );

	// Wrap in code tags
	$content = '<code>' . $content . '</code>';

	// Wrap blocks in pre tags
	if ( '`' !== $matches[1] ) {
		$content = "\n<pre>" . $content . "</pre>\n";
	}

	return $content;
}

/**
 * Callback to decode the tags in topic or reply content
 *
 * @since 2.3.0 bbPress (r4642)
 *
 * @param array $matches
 * @todo Experiment with _wp_specialchars()
 * @return string
 */
function bbp_decode_callback( $matches = array() ) {

	// Setup variables
	$trans_table = array_flip( get_html_translation_table( HTML_ENTITIES ) );
	$amps        = array( '&#38;', '&#038;', '&amp;' );
	$single      = array( '&#39;', '&#039;'          );
	$content     = $matches[2];
	$content     = strtr( $content, $trans_table );

	// Do the do
	$content = str_replace( '<br />', '<coded_br />', $content );
	$content = str_replace( '<p>',    '<coded_p>',    $content );
	$content = str_replace( '</p>',   '</coded_p>',   $content );
	$content = str_replace( $amps,    '&',            $content );
	$content = str_replace( $single,  "'",            $content );

	// Return content wrapped in code tags
	return '`' . $content . '`';
}

/**
 * Callback to replace empty HTML tags in a content string
 *
 * @since 2.3.0 bbPress (r4642)
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
 * @since 2.3.0 bbPress (r4642)
 *
 * @internal Used by bbp_encode_bad()
 *
 * @param string $content
 * @param string $key
 * @param string $preg
 */
function bbp_encode_normal_callback( &$content = '', $key = '', $preg = '' ) {
	if ( strpos( $content, '`' ) !== 0 ) {
		$content = preg_replace( "|&lt;(/?{$preg})&gt;|i", '<$1>', $content );
	}
}

/** No Follow *****************************************************************/

/**
 * Catches links so rel=nofollow can be added (on output, not save)
 *
 * @since 2.4.0 bbPress (r4866)
 *
 * @param string $text Post text
 * @return string $text Text with rel=nofollow added to any links
 */
function bbp_rel_nofollow( $text = '' ) {
	return preg_replace_callback( '|<a (.+?)>|i', 'bbp_rel_nofollow_callback', $text );
}

/**
 * Adds rel=nofollow to a link
 *
 * @since 2.4.0 bbPress (r4866)
 * @since 2.6.17 bbPress (r7492) Use the WordPress link relationship callback.
 *
 * @param array $matches
 * @return string $text Link with rel=nofollow added
 */
function bbp_rel_nofollow_callback( $matches = array() ) {
	return wp_rel_callback( $matches, 'nofollow' );
}

/**
 * Add the ugc link relationship on output, not save.
 *
 * @since 2.6.21 bbPress (r8110)
 *
 * @param string $text Post text.
 * @return string Text with the ugc link relationship.
 */
function bbp_rel_ugc( $text = '' ) {
	return preg_replace_callback( '|<a (.+?)>|i', 'bbp_rel_ugc_callback', $text );
}

/**
 * Add the ugc relationship to a user-generated link.
 *
 * @since 2.6.21 bbPress (r8112)
 *
 * @param array $matches
 * @return string Link with the ugc relationship.
 */
function bbp_rel_ugc_callback( $matches = array() ) {
	return wp_rel_callback( $matches, 'ugc' );
}

/** Make Clickable ************************************************************/

/**
 * Convert plaintext URI to HTML links.
 *
 * Converts URI, www and ftp, and email addresses. Finishes by fixing links
 * within links.
 *
 * This custom version of WordPress's make_clickable() skips links inside of
 * pre and code tags.
 *
 * @since 2.4.0 bbPress (r4942)
 *
 * @param string $text Content to convert URIs.
 * @return string Content with converted URIs.
 */
function bbp_make_clickable( $text = '' ) {
	$r               = '';
	$textarr         = preg_split( '/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE ); // split out HTML tags
	$nested_code_pre = 0; // Keep track of how many levels link is nested inside <pre> or <code>

	foreach ( $textarr as $piece ) {

		if ( preg_match( '|^<code[\s>]|i', $piece ) || preg_match( '|^<pre[\s>]|i', $piece ) || preg_match( '|^<script[\s>]|i', $piece ) || preg_match( '|^<style[\s>]|i', $piece ) ) {
			++$nested_code_pre;
		} elseif ( $nested_code_pre && ( '</code>' === strtolower( $piece ) || '</pre>' === strtolower( $piece ) || '</script>' === strtolower( $piece ) || '</style>' === strtolower( $piece ) ) ) {
			--$nested_code_pre;
		}

		if ( $nested_code_pre || empty( $piece ) || ( '<' === $piece[0] && ! preg_match( '|^<\s*[\w]{1,20}+://|', $piece ) ) ) {
			$r .= $piece;
			continue;
		}

		// Long strings might contain expensive edge cases ...
		if ( 10000 < strlen( $piece ) ) {
			// ... break it up
			foreach ( _split_str_by_whitespace( $piece, 2100 ) as $chunk ) { // 2100: Extra room for scheme and leading and trailing paretheses
				if ( 2101 < strlen( $chunk ) ) {
					$r .= $chunk; // Too big, no whitespace: bail.
				} else {
					$r .= bbp_make_clickable( $chunk );
				}
			}
		} else {
			$ret = " {$piece} "; // Pad with whitespace to simplify the regexes
			$ret = apply_filters( 'bbp_make_clickable', $ret, $text );
			$ret = substr( $ret, 1, -1 ); // Remove our whitespace padding.
			$r  .= $ret;
		}
	}

	// Cleanup of accidental links within links
	return preg_replace( '#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a>([^<]*)</a>#i', '$1$3$4</a>', $r );
}

/**
 * Make URLs clickable in content areas
 *
 * @since 2.6.0 bbPress (r6014)
 *
 * @param  string $text
 * @return string
 */
function bbp_make_urls_clickable( $text = '' ) {
	$url_clickable = '~
		([\\s(<.,;:!?])                                # 1: Leading whitespace, or punctuation
		(                                              # 2: URL
			[\\w]{1,20}+://                            # Scheme and hier-part prefix
			(?=\S{1,2000}\s)                           # Limit to URLs less than about 2000 characters long
			[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+     # Non-punctuation URL character
			(?:                                        # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
				[\'.,;:!?)]                            # Punctuation URL character
				[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
			)*
		)
		(\)?)                                          # 3: Trailing closing parenthesis (for parethesis balancing post processing)
	~xS';

	// The regex is a non-anchored pattern and does not have a single fixed starting character.
	// Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.
	return preg_replace_callback( $url_clickable, '_make_url_clickable_cb', $text );
}

/**
 * Make FTP clickable in content areas
 *
 * @since 2.6.0 bbPress (r6014)
 *
 * @see make_clickable()
 *
 * @param  string $text
 * @return string
 */
function bbp_make_ftps_clickable( $text = '' ) {
	return preg_replace_callback( '#([\s>])((www|ftp)\.[\w\\x80-\\xff\#$%&~/.\-;:=,?@\[\]+]+)#is', '_make_web_ftp_clickable_cb', $text );
}

/**
 * Make emails clickable in content areas
 *
 * @since 2.6.0 bbPress (r6014)
 *
 * @see make_clickable()
 *
 * @param  string $text
 * @return string
 */
function bbp_make_emails_clickable( $text = '' ) {
	return preg_replace_callback( '#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', '_make_email_clickable_cb', $text );
}

/**
 * Make mentions clickable in content areas
 *
 * @since 2.6.0 bbPress (r6014)
 *
 * @see make_clickable()
 *
 * @param  string $text
 * @return string
 */
function bbp_make_mentions_clickable( $text = '' ) {
	return preg_replace_callback( '#([\s>])@([0-9a-zA-Z-_]+)#i', 'bbp_make_mentions_clickable_callback', $text );
}

/**
 * Callback to convert mention matches to HTML A tag.
 *
 * @since 2.6.0 bbPress (r6014)
 *
 * @param array $matches Regular expression matches in the current text blob.
 *
 * @return string Original text if no user exists, or link to user profile.
 */
function bbp_make_mentions_clickable_callback( $matches = array() ) {

	// Bail if the match is empty malformed
	if ( empty( $matches[2] ) || ! is_string( $matches[2] ) ) {
		return $matches[0];
	}

	// Get user; bail if not found
	$user = get_user_by( 'slug', $matches[2] );
	if ( empty( $user ) || bbp_is_user_inactive( $user->ID ) ) {
		return $matches[0];
	}

	// Default anchor classes
	$classes = array(
		'bbp-user-mention',
		'bbp-user-id-' . absint( $user->ID )
	);

	// Filter classes
	$classes = (array) apply_filters( 'bbp_make_mentions_clickable_classes', $classes, $user );

	// Escape & implode if not empty, otherwise an empty string
	$class_str = ! empty( $classes )
		? implode( ' ', array_map( 'sanitize_html_class', $classes ) )
		: '';

	// Setup as a variable to avoid a potentially empty class attribute
	$class = ! empty( $class_str )
		? ' class="' . esc_attr( $class_str ) . '"'
		: '';

	// Create the link to the user's profile
	$html   = '<a href="%1$s"' . $class . '>%2$s</a>';
	$url    = bbp_get_user_profile_url( $user->ID );
	$anchor = sprintf( $html, esc_url( $url ), esc_html( $matches[0] ) );

	// Prevent this link from being followed by bots
	$link   = bbp_rel_nofollow( $anchor );

	// Concatenate the matches into the return value
	$retval = $matches[1] . $link;

	// Return the link
	return $retval;
}

/** Numbers *******************************************************************/

/**
 * Never let a numeric value be less than zero.
 *
 * @since 2.6.0 bbPress (r6302)
 *
 * @param int $number
 */
function bbp_number_not_negative( $number = 0 ) {

	// Protect against formatted strings
	if ( is_string( $number ) ) {
		$number = strip_tags( $number );                    // No HTML
		$number = preg_replace( '/[^0-9-]/', '', $number ); // No number-format

	// Protect against objects, arrays, scalars, etc...
	} elseif ( ! is_numeric( $number ) ) {
		$number = 0;
	}

	// Make the number an integer
	$int = intval( $number );

	// Pick the maximum value, never less than zero
	$not_less_than_zero = max( 0, $int );

	// Filter & return
	return (int) apply_filters( 'bbp_number_not_negative', $not_less_than_zero, $int, $number );
}

/**
 * A bbPress specific method of formatting numeric values
 *
 * @since 2.0.0 bbPress (r2486)
 *
 * @param string $number Number to format
 * @param string $decimals Optional. Display decimals
 *
 * @return string Formatted string
 */
function bbp_number_format( $number = 0, $decimals = false, $dec_point = '.', $thousands_sep = ',' ) {

	// If empty, set $number to (int) 0
	if ( ! is_numeric( $number ) ) {
		$number = 0;
	}

	// Filter & return
	return apply_filters( 'bbp_number_format', number_format( $number, $decimals, $dec_point, $thousands_sep ), $number, $decimals, $dec_point, $thousands_sep );
}

/**
 * A bbPress specific method of formatting numeric values
 *
 * @since 2.1.0 bbPress (r3858)
 *
 * @param string $number Number to format
 * @param string $decimals Optional. Display decimals
 *
 * @return string Formatted string
 */
function bbp_number_format_i18n( $number = 0, $decimals = false ) {

	// If empty, set $number to (int) 0
	if ( ! is_numeric( $number ) ) {
		$number = 0;
	}

	// Filter & return
	return apply_filters( 'bbp_number_format_i18n', number_format_i18n( $number, $decimals ), $number, $decimals );
}

/** Dates *********************************************************************/

/**
 * Convert time supplied from database query into specified date format.
 *
 * @since 2.0.0 bbPress (r2722)
 *
 * @param string $time Time to convert
 * @param string $d Optional. Default is 'U'. Either 'G', 'U', or php date
 *                             format
 * @param bool $translate Optional. Default is false. Whether to translate the
 *
 * @return string Returns timestamp
 */
function bbp_convert_date( $time, $d = 'U', $translate = false ) {
	$new_time = mysql2date( $d, $time, $translate );

	// Filter & return
	return apply_filters( 'bbp_convert_date', $new_time, $d, $translate, $time );
}

/**
 * Output formatted time to display human readable time difference.
 *
 * @since 2.0.0 bbPress (r2544)
 *
 * @param string $older_date Unix timestamp from which the difference begins.
 * @param string $newer_date Optional. Unix timestamp from which the
 *                            difference ends. False for current time.
 * @param int $gmt Optional. Whether to use GMT timezone. Default is false.
 */
function bbp_time_since( $older_date, $newer_date = false, $gmt = false ) {
	echo bbp_get_time_since( $older_date, $newer_date, $gmt );
}

/**
 * Return formatted time to display human readable time difference.
 *
 * @since 2.0.0 bbPress (r2544)
 *
 * @param string $older_date Unix timestamp from which the difference begins.
 * @param string $newer_date Optional. Unix timestamp from which the
 *                            difference ends. False for current time.
 * @param int $gmt Optional. Whether to use GMT timezone. Default is false.
 *
 * @return string Formatted time
 */
function bbp_get_time_since( $older_date, $newer_date = false, $gmt = false ) {

	// Setup the strings
	$unknown_text   = apply_filters( 'bbp_core_time_since_unknown_text',   esc_html__( 'sometime',  'bbpress' ) );
	$right_now_text = apply_filters( 'bbp_core_time_since_right_now_text', esc_html__( 'right now', 'bbpress' ) );
	/* translators: %s: Time period */
	$ago_text       = apply_filters( 'bbp_core_time_since_ago_text',       esc_html__( '%s ago',    'bbpress' ) );

	// array of time period chunks
	$chunks = array(
		/* translators: %s: Number of years */
		array( YEAR_IN_SECONDS, _n_noop( '%s year', '%s years', 'bbpress' ) ),

		/* translators: %s: Number of months */
		array( MONTH_IN_SECONDS, _n_noop( '%s month', '%s months', 'bbpress' ) ),

		/* translators: %s: Number of weeks */
		array( WEEK_IN_SECONDS, _n_noop( '%s week', '%s weeks', 'bbpress' ) ),

		/* translators: %s: Number of days */
		array( DAY_IN_SECONDS, _n_noop( '%s day', '%s days', 'bbpress' ) ),

		/* translators: %s: Number of hours */
		array( HOUR_IN_SECONDS, _n_noop( '%s hour', '%s hours', 'bbpress' ) ),

		/* translators: %s: Number of minutes */
		array( MINUTE_IN_SECONDS, _n_noop( '%s minute', '%s minutes', 'bbpress' ) ),

		/* translators: %s: Number of seconds */
		array( 1, _n_noop( '%s second', '%s seconds', 'bbpress' ) ),
	);

	// Attempt to parse non-numeric older date
	if ( ! empty( $older_date ) && ! is_numeric( $older_date ) ) {
		$time_chunks = explode( ':', str_replace( ' ', ':', $older_date ) );
		$date_chunks = explode( '-', str_replace( ' ', '-', $older_date ) );
		$older_date  = gmmktime( (int) $time_chunks[1], (int) $time_chunks[2], (int) $time_chunks[3], (int) $date_chunks[1], (int) $date_chunks[2], (int) $date_chunks[0] );
	}

	// Attempt to parse non-numeric newer date
	if ( ! empty( $newer_date ) && ! is_numeric( $newer_date ) ) {
		$time_chunks = explode( ':', str_replace( ' ', ':', $newer_date ) );
		$date_chunks = explode( '-', str_replace( ' ', '-', $newer_date ) );
		$newer_date  = gmmktime( (int) $time_chunks[1], (int) $time_chunks[2], (int) $time_chunks[3], (int) $date_chunks[1], (int) $date_chunks[2], (int) $date_chunks[0] );
	}

	// Set newer date to current time
	if ( empty( $newer_date ) ) {
		$newer_date = strtotime( current_time( 'mysql', $gmt ) );
	}

	// Cast both dates to ints to avoid notices & errors with invalid values
	$newer_date = intval( $newer_date );
	$older_date = intval( $older_date );

	// Difference in seconds
	$since = intval( $newer_date - $older_date );

	// Something went wrong with date calculation and we ended up with a negative date.
	if ( 0 > $since ) {
		$output = $unknown_text;

	// We only want to output two chunks of time here, eg:
	//     x years, xx months
	//     x days, xx hours
	// so there's only two bits of calculation below:
	} else {

		// Default count values
		$count  = 0;
		$count2 = 0;

		// Step one: the first chunk
		for ( $i = 0, $j = count( $chunks ); $i < $j; ++$i ) {
			$seconds = $chunks[ $i ][0];

			// Finding the biggest chunk (if the chunk fits, break)
			$count = floor( $since / $seconds );
			if ( 0 != $count ) {
				break;
			}
		}

		// If $i iterates all the way to $j, then the event happened 0 seconds ago
		if ( ! isset( $chunks[ $i ] ) ) {
			$output = $right_now_text;

		} else {

			// Set output var
			$output = sprintf( translate_nooped_plural( $chunks[ $i ][1], $count, 'bbpress' ), bbp_number_format_i18n( $count ) );

			// Step two: the second chunk
			if ( $i + 2 < $j ) {
				$seconds2 = $chunks[ $i + 1 ][0];
				$count2   = floor( ( $since - ( $seconds * $count ) ) / $seconds2 );

				// Add to output var
				if ( 0 != $count2 ) {
					$output .= _x( ',', 'Separator in time since', 'bbpress' ) . ' ';
					$output .= sprintf( translate_nooped_plural( $chunks[ $i + 1 ][1], $count2, 'bbpress' ), bbp_number_format_i18n( $count2 ) );
				}
			}

			// Empty counts, so fallback to right now
			if ( empty( $count ) && empty( $count2 ) ) {
				$output = $right_now_text;
			}
		}
	}

	// Append 'ago' to the end of time-since if not 'right now'
		if ( $output != $right_now_text ) {
		$output = sprintf( $ago_text, $output );
	}

	// Filter & return
	return apply_filters( 'bbp_get_time_since', $output, $older_date, $newer_date );
}

/** Revisions *****************************************************************/

/**
 * Formats the reason for editing the topic/reply.
 *
 * Does these things:
 *  - Trimming
 *  - Removing periods from the end of the string
 *  - Trimming again
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param string $reason Optional. User submitted reason for editing.
 * @return string Status of topic
 */
function bbp_format_revision_reason( $reason = '' ) {
	$reason = (string) $reason;

	// Bail if reason is empty
	if ( empty( $reason ) ) {
		return $reason;
	}

	// Trimming
	$reason = trim( $reason );

	// We add our own full stop.
	while ( substr( $reason, -1 ) === '.' ) {
		$reason = substr( $reason, 0, -1 );
	}

	// Trim again
	$reason = trim( $reason );

	return $reason;
}

/** Users *********************************************************************/

/**
 * Format the display name of a user.
 *
 * Prefers wp_is_valid_utf8() from WordPress 6.9, falls back to mbstring
 * library, and uses seems_utf8() & utf8_encode() as a last resort.
 *
 * @link https://bbpress.trac.wordpress.org/ticket/2141
 *
 * @since 2.6.14 bbPress (r7334)
 *
 * @param string $display_name The author display name
 *
 * @return string
 */
function bbp_format_user_display_name( $display_name = '' ) {

	// Default return value
	$retval = $display_name;

	// WordPress 6.9 and higher
	if ( function_exists( 'wp_is_valid_utf8' ) ) {
		if ( ! wp_is_valid_utf8( $display_name ) ) {
			$retval = _wp_utf8_encode_fallback( $display_name );
		}

	// Fallback to mbstring library if extension is loaded
	} elseif ( function_exists( 'mb_check_encoding' ) ) {
		if ( ! mb_check_encoding( $display_name, 'UTF-8' ) ) {
			$retval = mb_convert_encoding( $display_name, 'UTF-8', 'ISO-8859-1' );
		}

	// Fallback to deprecated WordPress & PHP functions
	} elseif ( seems_utf8( $display_name ) === false ) {
		$retval = utf8_encode( $display_name ); // phpcs:ignore
	}

	// Return
	return $retval;
}
