<?php

require('../bb-config.php');
header('Content-type: text/plain');

$tags = $bbdb->get_results("SELECT COUNT(tag_id) AS ccount, tag_id FROM $bbdb->tagged GROUP BY tag_id");

foreach ( $tags as $tag ) :
	$bbdb->query("UPDATE $bbdb->tags SET tag_count = $tag->ccount WHERE tag_id = $tag->tag_id");
endforeach;


echo "$bbdb->num_queries queries. " . bb_timer_stop() . 'seconds'; ?>
?>