<?php
require_once('bb-config.php');

// Temporary, refactor this!

$tag = 0;

$tag = $_GET['tag'];
if ( !$tag )
	$tag = get_path();

$tag_id = get_tag_id( $tag );

if ( !$tag_id )
	die('Tag not found');

if ( $tag ) :

$topic_ids = $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag_id' ORDER BY tagged_on DESC LIMIT 30");
$topic_ids = join( $topic_ids, ',' );
$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids) ORDER BY topic_time DESC");

include('bb-templates/tag-single.php');

else :

$tags = $bbdb->get_results("SELECT DISTINCT tag_id, * FROM $bbdb->tagged JOIN $bbdb->tags ON ($bbdb->tags.tag_id = $bbdb->tagged.tag_id) ORDER BY tagged_on DESC");

endif;
?>