<?php
function bb_send_304( $bb_last_modified ) {
	$bb_etag = '"' . md5($bb_last_modified) . '"';
	@header("Last-Modified: $bb_last_modified");
	@header	("ETag: $bb_etag");

	// Support for Conditional GET
	if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) $client_etag = stripslashes($_SERVER['HTTP_IF_NONE_MATCH']);
	else $client_etag = false;

	$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE']);
	// If string is empty, return 0. If not, attempt to parse into a timestamp
	$client_modified_timestamp = $client_last_modified ? strtotime($client_last_modified) : 0;

	// Make a timestamp for our most recent modification...	
	$bb_modified_timestamp = strtotime($bb_last_modified);

	if ( ($client_last_modified && $client_etag) ?
		 (($client_modified_timestamp >= $bb_modified_timestamp) && ($client_etag == $bb_etag)) :
		 (($client_modified_timestamp >= $bb_modified_timestamp) || ($client_etag == $bb_etag)) ) {
		status_header( 304 );
		exit;
	}
}
?>
