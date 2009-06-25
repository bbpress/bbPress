<?php
require_once('admin.php');

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
	bb_check_admin_referer( 'do-counts' );

	$messages = array();
	if ( isset($_POST['topic-posts']) && 1 == $_POST['topic-posts'] ) {
		if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS count FROM $bbdb->posts WHERE post_status = '0' GROUP BY topic_id") ) {
			$messages[] = __('Counted posts');
			foreach ($topics as $topic) {
				$topic_id = (int) $topic->topic_id;
				$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->topics SET topic_posts = %s WHERE topic_id = %s" ), $topic->count, $topic_id );
			}
			unset($topics, $topic, $topic_id);
		}
	}

	if ( isset($_POST['topic-voices']) && 1 == $_POST['topic-voices'] ) {
		if ( $topics = (array) $bbdb->get_results("SELECT topic_id FROM $bbdb->topics ORDER BY topic_id") ) {
			$messages[] = __('Counted voices');
			foreach ($topics as $topic) {
				$topic_id = (int) $topic->topic_id;
				if ( $voices = $bbdb->get_col( $bbdb->prepare( "SELECT DISTINCT poster_id FROM $bbdb->posts WHERE topic_id = %s AND post_status = '0';", $topic_id ) ) ) {
					$voices = count( $voices );
					bb_update_topicmeta( $topic_id, 'voices_count', $voices );
				}
			}
			unset($topics, $topic, $topic_id);
		}
	}

	if ( isset($_POST['topic-deleted-posts']) && 1 == $_POST['topic-deleted-posts'] ) {
		$old = (array) $bbdb->get_col("SELECT object_id FROM $bbdb->meta WHERE object_type = 'bb_topics' AND meta_key = 'deleted_posts'");
		$old = array_flip($old);
		if ( $topics = (array) $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS count FROM $bbdb->posts WHERE post_status != '0' GROUP BY topic_id") ) {
			$messages[] = __('Counting deleted posts&#8230;');
			foreach ( $topics as $topic ) {
				bb_update_topicmeta( $topic->topic_id, 'deleted_posts', $topic->count );
				unset($old[$topic->topic_id]);
			}
			unset($topics, $topic);
		}
		if ( $old ) {
			$old = join(',', array_flip($old));
			$bbdb->query("DELETE FROM $bbdb->meta WHERE object_type = 'bb_topic' AND object_id IN ($old) AND meta_key = 'deleted_posts'");
			$messages[] = __('&#8230;counted deleted posts');
		} else {
			$messages[] = __('&#8230;no deleted posts to count');
		}
	}

	if ( isset($_POST['forums']) && 1 == $_POST['forums'] ) {
		if ( $all_forums = (array) $bbdb->get_col("SELECT forum_id FROM $bbdb->forums") ) {
			$messages[] = __('Counted forum topics and posts');
			$all_forums = array_flip( $all_forums );
			$forums = $bbdb->get_results("SELECT forum_id, COUNT(topic_id) AS topic_count, SUM(topic_posts) AS post_count FROM $bbdb->topics WHERE topic_status = 0 GROUP BY forum_id");
			foreach ( (array) $forums as $forum ) {
				$bbdb->query("UPDATE $bbdb->forums SET topics = '$forum->topic_count', posts = '$forum->post_count' WHERE forum_id = '$forum->forum_id'");
				unset($all_forums[$forum->forum_id]);
			}
			if ( $all_forums ) {
				$all_forums = implode(',', array_flip( $all_forums ) );
				$bbdb->query("UPDATE $bbdb->forums SET topics = 0, posts = 0 WHERE forum_id IN ($all_forums)");
			}
			unset($all_forums, $forums, $forum);
		}
	}

	if ( isset($_POST['topics-replied']) && 1 == $_POST['topics-replied'] ) {
		if ( $users = (array) $bbdb->get_col("SELECT ID FROM $bbdb->users") ) {
			$messages[] = __('Counted topics to which each user has replied');
			foreach ( $users as $user )
				bb_update_topics_replied( $user );
			unset($users, $user);
		}
	}

	if ( isset($_POST['topic-tag-count']) && 1 == $_POST['topic-tag-count'] ) {
		// Reset tag count to zero
		$bbdb->query( "UPDATE $bbdb->topics SET tag_count = 0" );

		// Get all tags
		$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag' );

		if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
			$messages[] = __('Counted topic tags');
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
	}

	if ( isset($_POST['tags-tag-count']) && 1 == $_POST['tags-tag-count'] ) {
		// Get all tags
		$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', array( 'hide_empty' => false ) );

		if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
			$messages[] = __('Counted tagged topics');
			$_terms = array();
			foreach ( $terms as $term ) {
				$_terms[] = $term->term_id;
			}
			if ( count( $_terms ) ) {
				$wp_taxonomy_object->update_term_count( $_terms, 'bb_topic_tag' );
			}
		}
		unset( $term, $_terms );
	}

	if ( isset($_POST['tags-delete-empty']) && 1 == $_POST['tags-delete-empty'] ) {
		// Get all tags
		if ( !isset( $terms ) ) {
			$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', array( 'hide_empty' => false ) );
		}

		if ( !is_wp_error( $terms ) && is_array( $terms ) ) {
			$messages[] = __('Deleted tags with no topics');
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
	}

	if ( isset($_POST['clean-favorites']) && 1 == $_POST['clean-favorites'] ) {
		$favorites_key = $bbdb->prefix . 'favorites';
		if ( $users = $bbdb->get_results("SELECT user_id AS id, meta_value AS favorites FROM $bbdb->usermeta WHERE meta_key = '" . $favorites_key . "'") ) {
			$messages[] = __('Removed deleted topics from users\' favorites');
			$topics = $bbdb->get_col("SELECT topic_id FROM $bbdb->topics WHERE topic_status = '0'");
			foreach ( $users as $user ) {
				foreach ( explode(',', $user->favorites) as $favorite ) {
					if ( !in_array($favorite, $topics) ) {
						bb_remove_user_favorite( $user->id, $favorite );
					}
				}
			}
			unset($topics, $users, $user, $favorite);
		}
	}

	bb_recount_list();
	foreach ( (array) $recount_list as $item ) {
		if ( isset($item[2]) && isset($_POST[$item[0]]) && 1 == $_POST[$item[0]] && is_callable($item[2]) ) {
			call_user_func( $item[2] );
		}
	}
	
	if ( count( $messages ) ) {
		$messages = join( '</p>' . "\n" . '<p>', $messages );
		bb_admin_notice( $messages );
	}
}


$bb_admin_body_class = ' bb-admin-tools';

bb_get_admin_header();
?>
<h2><?php _e('Tools') ?></h2>
<?php do_action( 'bb_admin_notices' ); ?>

<form class="settings" method="post" action="<?php bb_uri('bb-admin/tools-recount.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset>
		<legend><?php _e( 'Re-count' ) ?></legend>
		<p><?php _e( 'To minimize database queries, bbPress keeps it\'s own count of various items like posts in each topic and topics in each forum. Occasionally these internal counters may become incorrect, you can manually re-count these items using this form.' ) ?></p>
		<p><?php _e( 'You can also clean out some stale items here, like empty tags.' ) ?></p>
<?php
bb_recount_list();
if ( $recount_list ) {
?>
		<div id="option-counts">
			<div class="label">
				<?php _e( 'Items to re-count' ); ?>
			</div>
			<div class="inputs">
<?php
	foreach ( $recount_list as $item ) {
		echo '<label class="checkboxs"><input type="checkbox" class="checkbox" name="' . esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ) . '" value="1" /> ' . esc_html( $item[1] ) . '</label>' . "\n";
	}
?>
			</div>
		</div>
<?php
} else {
?>
		<p><?php _e( 'There are no re-count tools available.' ) ?></p>
<?php
}
?>
	</fieldset>
	<fieldset class="submit">
		<?php bb_nonce_field( 'do-counts' ); ?>
		<input class="submit" type="submit" name="submit" value="<?php _e('Recount Items') ?>" />
	</fieldset>
</form>

<?php bb_get_admin_footer(); ?>
