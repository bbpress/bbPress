<?php // ?zap_tags=1 to delete tags with 0 tag_count

require('../bb-config.php');
header('Content-type: text/plain');

if( current_user_can('recount') ) :

	if ( $topics = $bbdb->get_col("SELECT topic_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status = '0' GROUP BY topic_id") ) :
		$counts = $bbdb->get_col('', 1);
		foreach ($topics as $t => $i)
			$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '{$counts[$t]}' WHERE topic_id = $i");
		unset($topics, $t, $i, $counts);
	endif;

	if ( $topics = $bbdb->get_col("SELECT topic_id, COUNT(DISTINCT tag_id) FROM $bbdb->tagged GROUP BY topic_id") ) :
		$counts = $bbdb->get_col('', 1);
		foreach ($topics as $t => $i)
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = '{$counts[$t]}' WHERE topic_id = $i");
		$not_tagged = array_diff($bbdb->get_col("SELECT topic_id FROM $bbdb->topics"), $topics);
		foreach ( $not_tagged as $i )
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = 0 WHERE topic_id = $i");
		unset($topics, $t, $i, $counts, $not_tagged);
	endif;

	if ( $all_forums = $bbdb->get_col("SELECT forum_id FROM $bbdb->forums") ) :
		$all_forums = array_flip( $all_forums );
		$forums = $bbdb->get_results("SELECT forum_id, COUNT(topic_id) AS topic_count, SUM(topic_posts) AS post_count FROM $bbdb->topics
			WHERE topic_status = 0 GROUP BY forum_id");
		foreach ($forums as $forum) :
			$bbdb->query("UPDATE $bbdb->forums SET topics = $forum->topic_count, posts = $forum->post_count WHERE forum_id = $forum->forum_id");
			unset($all_forums[$forum->forum_id]);
		endforeach;
		if ( $all_forums ) :
			$all_forums = implode(',', array_flip( $all_forums ) );
			$bbdb->query("UPDATE $bbdb->forums SET topics = 0, posts = 0 WHERE forum_id IN ($all_forums)");
		endif;
		unset($all_forums, $forums, $forum);
	endif;

	if ( $users = $bbdb->get_col("SELECT ID FROM $bbdb->users") ) :
		foreach ( $users as $user ) :
			$topics_replied = $bbdb->get_var("SELECT COUNT(DISTINCT topic_id) FROM $bbdb->posts WHERE post_status = '0' AND poster_id = $user");
			update_usermeta( $user, $table_prefix. 'topics_replied', $topics_replied );
		endforeach;
		unset($users, $user, $topics_replied);
	endif;

	if ( $tags = $bbdb->get_col("SELECT tag_id, COUNT(DISTINCT topic_id) FROM $bbdb->tagged GROUP BY tag_id") ) :
		$counts = $bbdb->get_col('', 1);
		foreach ( $tags as $t => $i )
			$bbdb->query("UPDATE $bbdb->tags SET tag_count = '{$counts[$t]}' WHERE tag_id = $i");
		$not_tagged = array_diff($bbdb->get_col("SELECT tag_id FROM $bbdb->tags"), $tags);
		foreach ( $not_tagged as $i )
			$bbdb->query("UPDATE $bbdb->tags SET tag_count = 0 WHERE tag_id = $i");
		unset($tags, $t, $i, $counts, $not_tagged);
	else :
		$bbdb->query("UPDATE $bbdb->tags SET tag_count = 0");
	endif;

	if ( 1 == $_GET['zap_tags'] )
		$bbdb->query("DELETE FROM $bbdb->tags WHERE tag_count = 0");

endif;

echo "$bbdb->num_queries queries. " . bb_timer_stop(0) . ' seconds';
?>
