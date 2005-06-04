<?php
require_once('bb-config.php');

// Temporary, refactor this!

$tag = 0;

$url_tag = $_GET['tag'];

if ( !$url_tag )
	$url_tag = get_path();

$tag = get_tag_by_name( $url_tag );

if ( !$tag && $url_tag )
	die('Tag not found');

if ( $url_tag && $tag ) :

if ($topic_ids = $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag->tag_id' ORDER BY tagged_on DESC LIMIT 30")) {
	$topic_ids = join( $topic_ids, ',' );
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids) ORDER BY topic_time DESC");
}

include('bb-templates/tag-single.php');

else :

include('bb-templates/tags.php');

endif;
?>