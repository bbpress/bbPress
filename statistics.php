<?php

require('bb-config.php');

require_once( BBPATH . '/bb-includes/statistics-functions.php');

$popular = get_popular_topics();

$static_title = 'Statistics';

include('bb-templates/stats.php');

?>