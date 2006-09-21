<?php

require('./bb-load.php');

require_once( BBPATH . BBINC . '/statistics-functions.php');

$popular = get_popular_topics();

$static_title = __('Statistics');

if (file_exists( BBPATH . 'my-templates/stats.php' ))
	require( BBPATH . 'my-templates/stats.php' );
else	require( BBPATH . 'bb-templates/stats.php');

?>
