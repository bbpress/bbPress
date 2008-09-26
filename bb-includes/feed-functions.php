<?php

/**
 * Send status headers for clients supporting Conditional Get
 *
 * The function sends the Last-Modified and ETag headers for all clients. It
 * then checks both the If-None-Match and If-Modified-Since headers to see if
 * the client has used them. If so, and the ETag does matches the client ETag
 * or the last modified date sent by the client is newer or the same as the
 * generated last modified, the function sends a 304 Not Modified and exits.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3
 * @param string $bb_last_modified Last modified time. Must be a HTTP-date
 */
function bb_send_304( $bb_last_modified ) {
	$bb_etag = '"' . md5($bb_last_modified) . '"';
	@header("Last-Modified: $bb_last_modified");
	@header	("ETag: $bb_etag");

	// Support for Conditional GET
	if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) $client_etag = stripslashes($_SERVER['HTTP_IF_NONE_MATCH']);
	else $client_etag = false;

	$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE']);
	// If string is empty, return 0. If not, attempt to parse into a timestamp
	$client_modified_timestamp = $client_last_modified ? bb_gmtstrtotime($client_last_modified) : 0;

	// Make a timestamp for our most recent modification...	
	$bb_modified_timestamp = bb_gmtstrtotime($bb_last_modified);

	if ( ($client_last_modified && $client_etag) ?
		 (($client_modified_timestamp >= $bb_modified_timestamp) && ($client_etag == $bb_etag)) :
		 (($client_modified_timestamp >= $bb_modified_timestamp) || ($client_etag == $bb_etag)) ) {
		status_header( 304 );
		exit;
	}
}
?>
