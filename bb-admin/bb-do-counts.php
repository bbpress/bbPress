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
	if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS count FROM $bbdb->posts WHERE post_status = '0' GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting posts...') . "<br />\n";
		foreach ($topics as $topic)
			$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '$topic->count' WHERE topic_id = '$topic->topic_id'");
		unset($topics, $topic);
	endif;
	echo "\t\t" . __('Done counting posts.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['topic-deleted-posts']) && 1 == $_POST['topic-deleted-posts'] ):
	echo "\t<li>\n";
	$old = (array) $bbdb->get_col("SELECT topic_id FROM $bbdb->topicmeta WHERE meta_key = 'deleted_posts'");
	$old = array_flip($old);
	if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS count FROM $bbdb->posts WHERE post_status != '0' GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting deleted posts...') . "<br />\n";
		foreach ( $topics as $topic ) :
			bb_update_topicmeta( $topic->topic_id, 'deleted_posts', $topic->count );
			unset($old[$topic->topic_id]);
		endforeach;
		unset($topics, $topic);
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
			$bbdb->query("UPDATE $bbdb->forums SET topics = '$forum->topic_count', posts = '$forum->post_count' WHERE forum_id = '$forum->forum_id'");
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
	if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(DISTINCT tag_id) AS count FROM $bbdb->tagged GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting topic tags...') . "<br />\n";
		$topic_col = array_flip( (array) $bbdb->get_col("SELECT topic_id FROM $bbdb->topics") );
		foreach ( $topics as $topic ) {
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = '$topic->count' WHERE topic_id = '$topic->topic_id'");
			unset($topic_col[$topic->topic_id]);
		}
		foreach ( $topic_col as $id => $i )
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = 0 WHERE topic_id = '$id'");
		unset($topics, $topic, $topic_col, $id, $i);
	endif;
	echo "\t\t" . __('Done counting topic tags.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['tags-tag-count']) && 1 == $_POST['tags-tag-count'] ) :
	echo "\t<li>\n";
	if ( $tags = (array) $bbdb->get_results("SELECT tag_id, COUNT(DISTINCT topic_id) AS count FROM $bbdb->tagged GROUP BY tag_id") ) :
		echo "\t\t" . __('Counting tagged topics...') . "<br />\n";
		$tag_col = array_flip( (array) $bbdb->get_col("SELECT tag_id FROM $bbdb->tags") );
		foreach ( $tags as $tag ) {
			$bbdb->query("UPDATE $bbdb->tags SET tag_count = '$tag->count' WHERE tag_id = '$tag->tag_id'");
			unset($tag_col[$tag->tag_id]);
		}
		foreach ( $tag_col as $id => $i )
			$bbdb->query("UPDATE $bbdb->tags SET tag_count = 0 WHERE tag_id = '$id'");
		unset($tags, $tag, $tag_col, $id, $i);
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

if ( isset($_POST['clean-favorites']) && 1 == $_POST['clean-favorites'] ):
	echo "\t<li>\n";
	if ( $users = $bbdb->get_results("SELECT user_id AS id, meta_value AS favorites FROM $bbdb->usermeta WHERE meta_key = 'favorites'") ) :
		echo "\t\t" . __('Removing deleted topics from users\' favorites...') . "<br />\n";
		$topics = $bbdb->get_col("SELECT topic_id FROM $bbdb->topics WHERE topic_status = '0'");
		foreach ( $users as $user ) {
			foreach ( explode(',', $user->favorites) as $favorite )
				if ( !in_array($favorite, $topics) )
					bb_remove_user_favorite( $user->id, $favorite );
		}
		unset($topics, $users, $user, $favorite);
	endif;
	echo "\t\t" . __('Done removing deleted topics from users\' favorites.');
	echo "\n\t</li>\n";
endif;

bb_recount_list();
foreach ( (array) $recount_list as $item )
	if ( isset($item[2]) && isset($_POST[$item[0]]) && 1 == $_POST[$item[0]] && is_callable($item[2]) )
		call_user_func( $item[2] );

echo "</ul>\n\n<p>\n\t" . __('Done recounting.  The process took') . "\n\t";
printf(__('%1$d queries and %2$s seconds.'), $bbdb->num_queries, bb_timer_stop(0));
echo "\n</p>";

$bb_cache->flush_all();

endif;

bb_get_admin_footer(); ?>
