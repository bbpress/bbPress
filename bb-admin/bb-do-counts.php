<?php
require_once('admin.php');
$bb_current_menu = $bb_menu[315];
$bb_current_submenu = $bb_submenu['site.php'][5];

$bb_admin_body_class = ' bb-admin-tools';

bb_get_admin_header();


if ( bb_current_user_can('recount') ) :

bb_check_admin_referer( 'do-counts' ); ?>

<div class="wrap">

<h2><?php _e('Recounting'); ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>
<ul>

<?php
if ( isset($_POST['topic-posts']) && 1 == $_POST['topic-posts'] ) {
	echo "\t<li>\n";
	if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS count FROM $bbdb->posts WHERE post_status = '0' GROUP BY topic_id") ) {
		echo "\t\t" . __('Counting posts&#8230;') . "<br />\n";
		foreach ($topics as $topic) {
			$topic_id = (int) $topic->topic_id;
			$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->topics SET topic_posts = %s WHERE topic_id = %s" ), $topic->count, $topic_id );
		}
		unset($topics, $topic, $topic_id);
	}
	echo "\t\t" . __('Done counting posts.');
	echo "\n\t</li>\n";
}

if ( isset($_POST['topic-voices']) && 1 == $_POST['topic-voices'] ) {
	echo "\t<li>\n";
	if ( $topics = (array) $bbdb->get_results("SELECT topic_id FROM $bbdb->topics ORDER BY topic_id") ) {
		echo "\t\t" . __('Counting voices&#8230;') . "<br />\n";
		foreach ($topics as $topic) {
			$topic_id = (int) $topic->topic_id;
			if ( $voices = $bbdb->get_col( $bbdb->prepare( "SELECT DISTINCT poster_id FROM $bbdb->posts WHERE topic_id = %s AND post_status = '0';", $topic_id ) ) ) {
				$voices = count( $voices );
				bb_update_topicmeta( $topic_id, 'voices_count', $voices );
			}
		}
		unset($topics, $topic, $topic_id);
	}
	echo "\t\t" . __('Done counting voices.');
	echo "\n\t</li>\n";
}

if ( isset($_POST['topic-deleted-posts']) && 1 == $_POST['topic-deleted-posts'] ):
	echo "\t<li>\n";
	$old = (array) $bbdb->get_col("SELECT object_id FROM $bbdb->meta WHERE object_type = 'bb_topics' AND meta_key = 'deleted_posts'");
	$old = array_flip($old);
	if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS count FROM $bbdb->posts WHERE post_status != '0' GROUP BY topic_id") ) :
		echo "\t\t" . __('Counting deleted posts&#8230;') . "<br />\n";
		foreach ( $topics as $topic ) {
			bb_update_topicmeta( $topic->topic_id, 'deleted_posts', $topic->count );
			unset($old[$topic->topic_id]);
		}
		unset($topics, $topic);
	endif;
	if ( $old ) :
		$old = join(',', array_flip($old));
		$bbdb->query("DELETE FROM $bbdb->meta WHERE object_type = 'bb_topic' AND object_id IN ($old) AND meta_key = 'deleted_posts'");
		echo "\t\t" . __('Done counting deleted posts.');
	else :
		echo "\t\t" . __('No deleted posts to count.');
	endif;
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['forums']) && 1 == $_POST['forums'] ) :
	echo "\t<li>\n";
	if ( $all_forums = (array) $bbdb->get_col("SELECT forum_id FROM $bbdb->forums") ) :
		echo "\t\t" . __('Counting forum topics and posts&#8230;') . "<br />\n";
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
		echo "\t\t" . __('Counting topics to which each user has replied&#8230;') . "<br />\n";
		foreach ( $users as $user )
			bb_update_topics_replied( $user );
		unset($users, $user);
	endif;
	echo "\t\t" . __('Done counting topics.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['topic-tag-count']) && 1 == $_POST['topic-tag-count'] ) {
	// Reset tag count to zero
	$bbdb->query( "UPDATE $bbdb->topics SET tag_count = 0" );

	// Get all tags
	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag' );

	echo "\t<li>\n";
	if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
		echo "\t\t" . __('Counting topic tags&#8230;') . "<br />\n";
		foreach ( $terms as $term ) {
			$topic_ids = bb_get_tagged_topic_ids( $term->term_id );
			if ( !is_wp_error( $topic_ids ) && is_array( $topic_ids ) ) {
				$bbdb->query(
					"UPDATE $bbdb->topics SET tag_count = tag_count + 1 WHERE topic_id IN (" . join( ',', $topic_ids ) . ")"
				);
			}
			unset( $topic_ids );
		}
	}
	unset( $terms, $term );
	echo "\t\t" . __('Done counting topic tags.');
	echo "\n\t</li>\n";
}

if ( isset($_POST['tags-tag-count']) && 1 == $_POST['tags-tag-count'] ) :
	// Get all tags
	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', array( 'hide_empty' => false ) );

	echo "\t<li>\n";
	if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
		echo "\t\t" . __('Counting tagged topics&#8230;') . "<br />\n";
		$_terms = array();
		foreach ( $terms as $term ) {
			$_terms[] = $term->term_id;
		}
		if ( count( $_terms ) ) {
			$wp_taxonomy_object->update_term_count( $_terms, 'bb_topic_tag' );
		}
	}
	unset( $term, $_terms );
	echo "\t\t" . __('Done counting tagged topics.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['zap-tags']) && 1 == $_POST['zap-tags'] ):
	// Get all tags
	if ( !isset( $terms ) ) {
		$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', array( 'hide_empty' => false ) );
	}

	echo "\t<li>\n";
	if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
		echo "\t\t" . __('Deleting tags with no topics&#8230;') . "<br />\n";
		foreach ( $terms as $term ) {
			$topic_ids = bb_get_tagged_topic_ids( $term->term_id );
			if ( !is_wp_error( $topic_ids ) && is_array( $topic_ids ) ) {
				if ( false === $topic_ids || ( is_array( $topic_ids ) && !count( $topic_ids ) ) ) {
					bb_destroy_tag( $term->term_taxonomy_id );
				}
			}
			unset( $topic_ids );
		}
	}
	unset( $terms, $term );
	echo "\t\t" . __('Done deleting tags with no topics.');
	echo "\n\t</li>\n";
endif;

if ( isset($_POST['clean-favorites']) && 1 == $_POST['clean-favorites'] ):
	echo "\t<li>\n";
	$favorites_key = $bbdb->prefix . 'favorites';
	if ( $users = $bbdb->get_results("SELECT user_id AS id, meta_value AS favorites FROM $bbdb->usermeta WHERE meta_key = '" . $favorites_key . "'") ) :
		echo "\t\t" . __('Removing deleted topics from users\' favorites&#8230;') . "<br />\n";
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

wp_cache_flush();

endif;
?>

</div>

<?php bb_get_admin_footer(); ?>
