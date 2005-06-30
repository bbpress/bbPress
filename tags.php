<?php
require_once('bb-config.php');

bb_repermalink();

// Temporary, refactor this!

if ( !$tag && $tag_name )
	die('Tag not found');

if ( $tag_name && $tag ) :

if ($topic_ids = $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag->tag_id' ORDER BY tagged_on DESC LIMIT 30")) {
	$topic_ids = join( $topic_ids, ',' );
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids) ORDER BY topic_time DESC");
}

include('bb-templates/tag-single.php');

else :

include('bb-templates/tags.php');

endif;
?>
