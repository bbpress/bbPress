<?php
require_once('admin.php');
$bb_current_menu = $bb_menu[15];
$bb_current_submenu = $bb_submenu['site.php'][5];
bb_get_admin_header();


if ( bb_current_user_can('recount') ) :

bb_check_admin_referer( 'do-counts' ); ?>

<h2><?php _e('Recounting'); ?></h2>
<ul>

<?php
if ( isset($_POST['topic-posts']) && 1 == $_POST['topic-posts'] ):
	echo "\t<li>\n";
	if ( $topics = (array) $bbdb->get_col("SELECT topic_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status = '0' GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting posts...') . "<br />\n";
		$counts = (array) $bbdb->get_col('', 1);
		foreach ($topics as $t => $i)
			$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '{$counts[$t]}' WHERE topic_id = $i");
		unset($topics, $t, $i, $counts);
	endif;
	echo "\t\t" . __('Done counting posts.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['topic-deleted-posts']) && 1 == $_POST['topic-deleted-posts'] ):
	echo "\t<li>\n";
	$old = (array) $bbdb->get_col("SELECT topic_id FROM $bbdb->topicmeta WHERE meta_key = 'deleted_posts'");
	$old = array_flip($old);
	if ( $topics = (array) $bbdb->get_col("SELECT topic_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status != '0' GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting deleted posts...') . "<br />\n";
		$counts = (array) $bbdb->get_col('', 1);
		foreach ( $topics as $t => $i ) :
			bb_update_topicmeta( $i, 'deleted_posts', $counts[$t] );
			unset($old[$i]);
		endforeach;
		unset($topics, $t, $i, $counts);
	endif;
	if ( $old ) :
		$old = join(',', array_flip($old));
		$bbdb->query("DELETE FROM $bbdb->topicmeta WHERE topic_id IN ($old) AND meta_key = 'deleted_posts'");
		echo "\t\t" . __('Done counting deleted posts.');
	else :
		echo "\t\t" . __('No deleted posts to count.');
	endif;
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['forums']) && 1 == $_POST['forums'] ) :
	echo "\t<li>\n";
	if ( $all_forums = (array) $bbdb->get_col("SELECT forum_id FROM $bbdb->forums") ) :
		echo "\t\t" . __('Counting forum topics and posts...') . "<br />\n";
		$all_forums = array_flip( $all_forums );
		$forums = $bbdb->get_results("SELECT forum_id, COUNT(topic_id) AS topic_count, SUM(topic_posts) AS post_count FROM $bbdb->topics
			WHERE topic_status = 0 GROUP BY forum_id");
		foreach ( (array) $forums as $forum ) :
			$bbdb->query("UPDATE $bbdb->forums SET topics = $forum->topic_count, posts = $forum->post_count WHERE forum_id = $forum->forum_id");
			unset($all_forums[$forum->forum_id]);
		endforeach;
		if ( $all_forums ) :
			$all_forums = implode(',', array_flip( $all_forums ) );
			$bbdb->query("UPDATE $bbdb->forums SET topics = 0, posts = 0 WHERE forum_id IN ($all_forums)");
		endif;
		unset($all_forums, $forums, $forum);
	endif;
	echo "\t\t" . __('Done counting forum topics and posts.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['topics-replied']) && 1 == $_POST['topics-replied'] ) :
	echo "\t<li>\n";
	if ( $users = (array) $bbdb->get_col("SELECT ID FROM $bbdb->users") ) :
		echo "\t\t" . __('Counting topics to which each user has replied...') . "<br />\n";
		foreach ( $users as $user )
			bb_update_topics_replied( $user );
		unset($users, $user);
	endif;
	echo "\t\t" . __('Done counting topics.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['topic-tag-count']) && 1 == $_POST['topic-tag-count'] ) :
	echo "\t<li>\n";
	if ( $topics = (array) $bbdb->get_col("SELECT topic_id, COUNT(DISTINCT tag_id) FROM $bbdb->tagged GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting topic tags...') . "<br />\n";
		$counts = (array) $bbdb->get_col('', 1);
		foreach ( $topics as $t => $i)
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = '{$counts[$t]}' WHERE topic_id = $i");
		$not_tagged = array_diff( (array) $bbdb->get_col("SELECT topic_id FROM $bbdb->topics"), $topics);
		foreach ( $not_tagged as $i )
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = 0 WHERE topic_id = $i");
		unset($topics, $t, $i, $counts, $not_tagged);
	endif;
	echo "\t\t" . __('Done counting topic tags.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['tags-tag-count']) && 1 == $_POST['tags-tag-count'] ) :
	echo "\t<li>\n";
	if ( $tags = (array) $bbdb->get_col("SELECT tag_id, COUNT(DISTINCT topic_id) FROM $bbdb->tagged GROUP BY tag_id") ) :
		echo "\t\t" . __('Counting tagged topics...') . "<br />\n";
		$counts = (array) $bbdb->get_col('', 1);
		foreach ( $tags as $t => $i )
			$bbdb->query("UPDATE $bbdb->tags SET tag_count = '{$counts[$t]}' WHERE tag_id = $i");
		$not_tagged = array_diff((array) $bbdb->get_col("SELECT tag_id FROM $bbdb->tags"), $tags);
		foreach ( $not_tagged as $i )
			$bbdb->query("UPDATE $bbdb->tags SET tag_count = 0 WHERE tag_id = $i");
		unset($tags, $t, $i, $counts, $not_tagged);
	else :
		$bbdb->query("UPDATE $bbdb->tags SET tag_count = 0");
	endif;
	echo "\t\t" . __('Done counting tagged topics.');
	echo "\n\t</li>\n";

	if ( isset($_POST['zap-tags']) && 1 == $_POST['zap-tags'] ) :
		echo "\t<li>\n\t\t";
		$bbdb->query("DELETE FROM $bbdb->tags WHERE tag_count = 0");
		_e('Deleted tags with no topics.');
		echo "\n\t</li>\n";
	endif;
	echo "\n\t</li>\n";
endif;

bb_recount_list();
 if ( $recount_list )
	foreach ( (array) $recount_list as $item )
		if ( isset($item[2]) && isset($_POST[$item[0]]) && 1 == $_POST[$item[0]])
			$item[2]();

echo "</ul>\n\n<p>\n\t" . __('Done recounting.  The process took') . "\n\t";
printf(__('%1$d queries and %2$s seconds.'), $bbdb->num_queries, bb_timer_stop(0));
echo "\n</p>";

endif;

bb_get_admin_footer(); ?>
