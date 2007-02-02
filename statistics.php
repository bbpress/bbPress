<?php

require('./bb-load.php');

require_once( BBPATH . BBINC . 'statistics-functions.php');

$popular = get_popular_topics();

$bb->static_title = __('Statistics') . ' &laquo;';

bb_load_template( 'stats.php', array('popular') );

?>
