<?php

require_once('bb-config.php');

// Comment to hide forums
$forums = get_forums();

$topics = get_latest_topics();

bb_do_action( 'bb_index.php', '' );

include('bb-templates/front-page.php');

?>
