<?php

require_once('bb-config.php');

// Comment to hide forums
$forums = get_forums();

$topics = get_latest_topics();

include('bb-templates/front-page.php');

?>