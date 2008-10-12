<?php

require('./bb-load.php');

require_once( BB_PATH . BB_INC . 'functions.bb-statistics.php' );

$popular = get_popular_topics();

$bb->static_title = __('Statistics') . ' &laquo;';

bb_load_template( 'stats.php', array('popular') );

?>
