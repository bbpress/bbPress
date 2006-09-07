<?php
require_once('admin.php');
header('Content-type: text/plain');

if ( bb_current_user_can('recount') ) :

if ( isset($_POST['topic-posts']) && 1 == $_POST['topic-posts'] ):
	if ( $topics = $bbdb->get_col("SELECT topic_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status = '0' GROUP BY topic_id") ) :
		$approved=isset($approved)?$approved : 'No';
		echo sprintf(__("%s comments approved"), $approved) . "\n";
		printf (__('Counting posts...'). "\n");
		$counts = $bbdb->get_col('', 1);
		foreach ($topics as $t => $i)
			$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '{$counts[$t]}' WHERE topic_id = $i");
		unset($topics, $t, $i, $counts);
	endif;
	printf (__('Done counting posts.'). "\n\n");
endif;

if ( isset($_POST['topic-deleted-posts']) && 1 == $_POST['topic-deleted-posts'] ):
	$old = $bbdb->get_col("SELECT topic_id FROM $bbdb->topicmeta WHERE meta_key = 'deleted_posts'");
	$old = array_flip($old);
	if ( $topics = $bbdb->get_col("SELECT topic_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status != '0' GROUP BY topic_id") ) :
		printf (__('Counting deleted posts...'). "\n");
		$counts = $bbdb->get_col('', 1);
		foreach ($topics as $t => $i) :
			bb_update_topicmeta( $i, 'deleted_posts', $counts[$t] );
			unset($old[$i]);
		endforeach;
		unset($topics, $t, $i, $counts);
	endif;
	if ( $old ) :
		$old = join(',', array_flip($old));
		$bbdb->query("DELETE FROM $bbdb->topicmeta WHERE topic_id IN ($old) AND meta_key = 'deleted_posts'");
	endif;
	printf(__('Done counting deleted posts.'). "\n\n");
endif;

if ( isset($_POST['forums']) && 1 == $_POST['forums'] ) :
	if ( $all_forums = $bbdb->get_col("SELECT forum_id FROM $bbdb->forums") ) :
		printf(__('Counting forum topics and posts...'). "\n");
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
	printf(__('Done counting forum topics and posts.'). "\n\n");
endif;

if ( isset($_POST['topics-replied']) && 1 == $_POST['topics-replied'] ) :
	if ( $users = $bbdb->get_col("SELECT ID FROM $bbdb->users") ) :
		printf(__('Counting topics to which each user has replied...'). "\n");
		foreach ( $users as $user ) :
			$topics_replied = $bbdb->get_var("SELECT COUNT(DISTINCT topic_id) FROM $bbdb->posts WHERE post_status = '0' AND poster_id = $user");
			bb_update_usermeta( $user, $bb_table_prefix. 'topics_replied', $topics_replied );
		endforeach;
		unset($users, $user, $topics_replied);
	endif;
	printf(__('Done counting topics.'). "\n\n");
endif;

if ( isset($_POST['topic-tag-count']) && 1 == $_POST['topic-tag-count'] ) :
	if ( $topics = $bbdb->get_col("SELECT topic_id, COUNT(DISTINCT tag_id) FROM $bbdb->tagged GROUP BY topic_id") ) :
		printf(__('Counting topic tags...'). "\n");
		$counts = $bbdb->get_col('', 1);
		foreach ($topics as $t => $i)
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = '{$counts[$t]}' WHERE topic_id = $i");
		$not_tagged = array_diff($bbdb->get_col("SELECT topic_id FROM $bbdb->topics"), $topics);
		foreach ( $not_tagged as $i )
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = 0 WHERE topic_id = $i");
		unset($topics, $t, $i, $counts, $not_tagged);
	endif;
	printf(__('Done counting topic tags.'). "\n\n");
endif;

if ( isset($_POST['tags-tag-count']) && 1 == $_POST['tags-tag-count'] ) :
	if ( $tags = $bbdb->get_col("SELECT tag_id, COUNT(DISTINCT topic_id) FROM $bbdb->tagged GROUP BY tag_id") ) :
		printf(__('Counting tagged topics...'). "\n");
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
	printf(__('Done counting tagged topics.'));

	if ( isset($_POST['zap-tags']) && 1 == $_POST['zap-tags'] ) :
		$bbdb->query("DELETE FROM $bbdb->tags WHERE tag_count = 0");
		printf("\n". __('Deleted tags with no topics.'));
	endif;
	echo "\n\n";
endif;

bb_recount_list();
 if ( $recount_list )
	foreach ( $recount_list as $item )
		if ( isset($item[2]) && isset($_POST[$item[0]]) && 1 == $_POST[$item[0]])
			$item[2]();

endif;

echo "$bbdb->num_queries queries. " . bb_timer_stop(0) . ' seconds';
?>
