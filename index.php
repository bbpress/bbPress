<?php

require_once('bb-config.php');

$forums = get_forums();

$topics = get_latest_topics();

include('bb-templates/front-page.php');

?>