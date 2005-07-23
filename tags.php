<?php
require_once('bb-config.php');

$page = (int) $_GET['page'];

bb_repermalink();

// Temporary, refactor this!

if ( !$tag && $tag_name )
	die('Tag not found');

if ( $tag_name && $tag ) :

	$topics = get_tagged_topics($tag->tag_id, $page);
	bb_do_action( 'bb_tag-single.php', $tag->tag_id );
	include('bb-templates/tag-single.php');

else :

	bb_do_action( 'bb_tags.php', '' );
	include('bb-templates/tags.php');

endif;
?>
