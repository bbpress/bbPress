<?php

/**
 * bbPress Formatting
 *
 * @package bbPress
 * @subpackage Formatting
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Kses **********************************************************************/

/**
 * Custom allowed tags for forum topics and replies
 *
 * Allows all users to post links, quotes, code, formatting, lists, and images
 *
 * @since 2.3.0 bbPress (r4603)
 *
 * @return array Associative array of allowed tags and attributes
 */
function bbp_kses_allowed_tags() {
	return apply_filters( 'bbp_kses_allowed_tags', array(

		// Links
		'a' => array(
			'href'     => true,
			'title'    => true,
			'rel'      => true,
			'target'   => true
		),

		// Quotes
		'blockquote'   => array(
			'cite'     => true
		),

		// Code
		'code'         => array(),
		'pre'          => array(
			'class'    => true
		),

		// Formatting
		'em'           => array(),
		'strong'       => array(),
		'del'          => array(
			'datetime' => true,
			'cite'     => true
		),
		'ins' => array(
			'datetime' => true,
			'cite'     => true
		),

		// Lists
		'ul'           => array(),
		'ol'           => array(
			'start'    => true,
		),
		'li'           => array(),

		// Images
		'img'          => array(
			'src'      => true,
			'border'   => true,
			'alt'      => true,
			'height'   => true,
			'width'    => true,
		)
	) );
}

/**
 * Custom kses filter for forum topics and replies, for filtering incoming data
 *
 * @since 2.3.0 bbPress (r4603)
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
 * @since 2.3.0 bbPress (r4603)
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
 * @since 2.3.0 bbPress (r4641)
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
 * @since 2.3.0 bbPress (r4641)
 *
 * @param string $content Topic and reply content
 * @return string Partially encodedd content
 */
function bbp_code_trick_reverse( $content = '' ) {

	// Setup variables
	$openers = array( '<p>', '<br />' );
	$content = preg_replace_callback( "!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s", 'bbp_decode_callback', $content );

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
 * @since 2.3.0 bbPress (r4641)
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
 * @since 2.3.0 bbPress (r4641)
 *
 * @param array $matches
 * @return string
 */
function bbp_encode_callback( $matches = array() ) {

	// Trim inline code, not pre blocks (to prevent removing indentation)
	if ( "`" === $matches[1] ) {
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
	if ( "`" !== $matches[1] ) {
		$content = "\n<pre>" . $content . "</pre>\n";
	}

	return $content;
}

/**
 * Callback to decode the tags in topic or reply content
 *
 * @since 2.3.0 bbPress (r4641)
 *
 * @param array $matches
 * @todo Experiment with _wp_specialchars()
 * @return string
 */
function bbp_decode_callback( $matches = array() ) {

	// Setup variables
	$trans_table = array_flip( get_html_translation_table( HTML_ENTITIES ) );
	$amps        = array( '&#38;','&#038;', '&amp;' );
	$single      = array( '&#39;','&#039;'          );
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
 * @since 2.3.0 bbPress (r4641)
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
 * @since 2.3.0 bbPress (r4641)
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

/** No Follow *****************************************************************/

/**
 * Catches links so rel=nofollow can be added (on output, not save)
 *
 * @since 2.3.0 bbPress (r4865)
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
 * @since 2.3.0 bbPress (r4865)
 *
 * @param array $matches
 * @return string $text Link with rel=nofollow added
 */
function bbp_rel_nofollow_callback( $matches = array() ) {
	$text = $matches[1];
	$text = str_replace( array( ' rel="nofollow"', " rel='nofollow'" ), '', $text );
	return "<a $text rel=\"nofollow\">";
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
 * @since 2.4.0 bbPress (r4941)
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
			$nested_code_pre++;
		} elseif ( $nested_code_pre && ( '</code>' === strtolower( $piece ) || '</pre>' === strtolower( $piece ) || '</script>' === strtolower( $piece ) || '</style>' === strtolower( $piece ) ) ) {
			$nested_code_pre--;
		}

		if ( $nested_code_pre || empty( $piece ) || ( $piece[0] === '<' && ! preg_match( '|^<\s*[\w]{1,20}+://|', $piece ) ) ) {
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
			$r .= $ret;
		}
	}

	// Cleanup of accidental links within links
	return preg_replace( '#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', "$1$3</a>", $r );
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
 * Callback to convert mention matchs to HTML A tag.
 *
 * @since 2.6.0 (r6014)
 *
 * @param array $matches Single Regex Match.
 *
 * @return string HTML A tag with link to user profile.
 */
function bbp_make_mentions_clickable_callback( $matches = array() ) {

	// Get user; bail if not found
	$user = get_user_by( 'slug', $matches[2] );
	if ( empty( $user ) || bbp_is_user_inactive( $user->ID ) ) {
		return $matches[0];
	}

	// Create the link to the user's profile
	$url    = bbp_get_user_profile_url( $user->ID );
	$anchor = '<a href="%1$s" rel="nofollow">@%2$s</a>';
	$link   = sprintf( $anchor, esc_url( $url ), esc_html( $user->user_nicename ) );

	return $matches[1] . $link;
}

/** Numbers *******************************************************************/

/**
 * A bbPress specific method of formatting numeric values
 *
 * @since 2.0.0 bbPress (r2486)
 *
 * @param string $number Number to format
 * @param string $decimals Optional. Display decimals
 * @uses apply_filters() Calls 'bbp_number_format' with the formatted values,
 *                        number and display decimals bool
 * @return string Formatted string
 */
function bbp_number_format( $number = 0, $decimals = false, $dec_point = '.', $thousands_sep = ',' ) {

	// If empty, set $number to (int) 0
	if ( ! is_numeric( $number ) ) {
		$number = 0;
	}

	return apply_filters( 'bbp_number_format', number_format( $number, $decimals, $dec_point, $thousands_sep ), $number, $decimals, $dec_point, $thousands_sep );
}

/**
 * A bbPress specific method of formatting numeric values
 *
 * @since 2.1.0 bbPress (r3857)
 *
 * @param string $number Number to format
 * @param string $decimals Optional. Display decimals
 * @uses apply_filters() Calls 'bbp_number_format' with the formatted values,
 *                        number and display decimals bool
 * @return string Formatted string
 */
function bbp_number_format_i18n( $number = 0, $decimals = false ) {

	// If empty, set $number to (int) 0
	if ( ! is_numeric( $number ) ) {
		$number = 0;
	}

	return apply_filters( 'bbp_number_format_i18n', number_format_i18n( $number, $decimals ), $number, $decimals );
}

/** Dates *********************************************************************/

/**
 * Convert time supplied from database query into specified date format.
 *
 * @since 2.0.0 bbPress (r2544)
 *
 * @param string $time Time to convert
 * @param string $d Optional. Default is 'U'. Either 'G', 'U', or php date
 *                             format
 * @param bool $translate Optional. Default is false. Whether to translate the
 *                                   result
 * @uses mysql2date() To convert the format
 * @uses apply_filters() Calls 'bbp_convert_date' with the time, date format
 *                        and translate bool
 * @return string Returns timestamp
 */
function bbp_convert_date( $time, $d = 'U', $translate = false ) {
	$new_time = mysql2date( $d, $time, $translate );

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
 * @uses bbp_get_time_since() To get the formatted time
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
	 * @uses current_time() To get the current time in mysql format
	 * @uses human_time_diff() To get the time differene in since format
	 * @uses apply_filters() Calls 'bbp_get_time_since' with the time
	 *                        difference and time
	 * @return string Formatted time
	 */
	function bbp_get_time_since( $older_date, $newer_date = false, $gmt = false ) {

		// Setup the strings
		$unknown_text   = apply_filters( 'bbp_core_time_since_unknown_text',   __( 'sometime',  'bbpress' ) );
		$right_now_text = apply_filters( 'bbp_core_time_since_right_now_text', __( 'right now', 'bbpress' ) );
		$ago_text       = apply_filters( 'bbp_core_time_since_ago_text',       __( '%s ago',    'bbpress' ) );

		// array of time period chunks
		$chunks = array(
			array( 60 * 60 * 24 * 365 , __( 'year',   'bbpress' ), __( 'years',   'bbpress' ) ),
			array( 60 * 60 * 24 * 30 ,  __( 'month',  'bbpress' ), __( 'months',  'bbpress' ) ),
			array( 60 * 60 * 24 * 7,    __( 'week',   'bbpress' ), __( 'weeks',   'bbpress' ) ),
			array( 60 * 60 * 24 ,       __( 'day',    'bbpress' ), __( 'days',    'bbpress' ) ),
			array( 60 * 60 ,            __( 'hour',   'bbpress' ), __( 'hours',   'bbpress' ) ),
			array( 60 ,                 __( 'minute', 'bbpress' ), __( 'minutes', 'bbpress' ) ),
			array( 1,                   __( 'second', 'bbpress' ), __( 'seconds', 'bbpress' ) )
		);

		if ( ! empty( $older_date ) && ! is_numeric( $older_date ) ) {
			$time_chunks = explode( ':', str_replace( ' ', ':', $older_date ) );
			$date_chunks = explode( '-', str_replace( ' ', '-', $older_date ) );
			$older_date  = gmmktime( (int) $time_chunks[1], (int) $time_chunks[2], (int) $time_chunks[3], (int) $date_chunks[1], (int) $date_chunks[2], (int) $date_chunks[0] );
		}

		// $newer_date will equal false if we want to know the time elapsed
		// between a date and the current time. $newer_date will have a value if
		// we want to work out time elapsed between two known dates.
		$newer_date = ( ! $newer_date ) ? strtotime( current_time( 'mysql', $gmt ) ) : $newer_date;

		// Difference in seconds
		$since = $newer_date - $older_date;

		// Something went wrong with date calculation and we ended up with a negative date.
		if ( 0 > $since ) {
			$output = $unknown_text;

		// We only want to output two chunks of time here, eg:
		//     x years, xx months
		//     x days, xx hours
		// so there's only two bits of calculation below:
		} else {

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
				$output = ( 1 == $count ) ? '1 '. $chunks[ $i ][1] : $count . ' ' . $chunks[ $i ][2];

				// Step two: the second chunk
				if ( $i + 2 < $j ) {
					$seconds2 = $chunks[ $i + 1 ][0];
					$name2    = $chunks[ $i + 1 ][1];
					$count2   = floor( ( $since - ( $seconds * $count ) ) / $seconds2 );

					// Add to output var
					if ( 0 != $count2 ) {
						$output .= ( 1 == $count2 ) ? _x( ',', 'Separator in time since', 'bbpress' ) . ' 1 '. $name2 : _x( ',', 'Separator in time since', 'bbpress' ) . ' ' . $count2 . ' ' . $chunks[ $i + 1 ][2];
					}
				}

				// No output, so happened right now
				if ( ! (int) trim( $output ) ) {
					$output = $right_now_text;
				}
			}
		}

		// Append 'ago' to the end of time-since if not 'right now'
		if ( $output != $right_now_text ) {
			$output = sprintf( $ago_text, $output );
		}

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

	// Format reason for proper display
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

