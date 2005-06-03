<?php
//?zap=1 to drop all tags with tag_count = 0.

require('admin-header.php');
header('Content-type: text/plain');

$tags = $bbdb->get_results("SELECT COUNT($bbdb->tagged.tag_id) AS ccount, $bbdb->tags.tag_id FROM $bbdb->tagged RIGHT JOIN $bbdb->tags ON 
($bbdb->tagged.tag_id = $bbdb->tags.tag_id) GROUP BY $bbdb->tags.tag_id");

if ( 1 == $_GET['zap'] ) {
	foreach ( $tags as $tag )
		if ( 0 == $tag->ccount )
			$bbdb->query("DELETE FROM $bbdb->tags WHERE tag_id = '$tag->tag_id'");
} else {
	foreach ( $tags as $tag )
		$bbdb->query("UPDATE $bbdb->tags SET tag_count = $tag->ccount WHERE tag_id = $tag->tag_id");
}

echo "$bbdb->num_queries queries. " . bb_timer_stop() . ' seconds';
?>
