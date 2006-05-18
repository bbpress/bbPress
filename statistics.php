<?php

require('./bb-load.php');

require_once( BBPATH . '/bb-includes/statistics-functions.php');

$popular = get_popular_topics();

$static_title = 'Statistics';

if (file_exists( BBPATH . 'my-templates/stats.php' ))
	require( BBPATH . 'my-templates/stats.php' );
else	require( BBPATH . 'bb-templates/stats.php');

?>
