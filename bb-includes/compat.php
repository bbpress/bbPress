<?php
// [WP5999]
if (!function_exists('http_build_query')) {
	function http_build_query($data, $prefix=null, $sep=null) {
		return _http_build_query($data, $prefix, $sep);
	}
}

// [WP6387]
if ( ! function_exists('hash_hmac') ):
function hash_hmac($algo, $data, $key, $raw_output = false) {
	$packs = array('md5' => 'H32', 'sha1' => 'H40');

	if ( !isset($packs[$algo]) )
		return false;

	$pack = $packs[$algo];

	if (strlen($key) > 64)
		$key = pack($pack, $algo($key));
	else if (strlen($key) < 64)
		$key = str_pad($key, 64, chr(0));
        
	$ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
	$opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));

	return $algo($opad . pack($pack, $algo($ipad . $data)));
}
endif;

if ( !function_exists( 'htmlspecialchars_decode' ) ) {
	// Added in PHP 5.1.0
	// from php.net (modified by Sam Bauers to deal with some quirks in HTML_SPECIALCHARS constant)
	function htmlspecialchars_decode( $str, $quote_style = ENT_COMPAT ) {
		$table = array_flip( get_html_translation_table( HTML_SPECIALCHARS, $quote_style ) );
		$table = array_merge( array( '&#039;' => "'" ), $table, array( '&#38;' => "&", '&#038;' => "&" ) );
		return strtr( $str, $table );
	}
}
?>
