<?php

/**
 * bbPress BBXP Extension
 *
 * This class includes functions necessary for bbPress to interface
 * with the BBXP class, allowing for exportation of bbPress data to
 * a BBXF file.
 */
class BBXP_bbPress extends BBXP
{

	/**
	 * Alias for BPDB's get_results that eliminates a parameter.
	 */
	function fetch ($query)
	{
		return $this->db->get_results ($query, 'ARRAY_A');
	}
	
	/**
	 * Fetches users from the database.
	 */
	function fetch_users ()
	{
		return $this->fetch ('SELECT * FROM ' . $this->db->users . ' WHERE 1');
	}

	/**
	 * Fetches forums from the database.
	 */
	function fetch_forums ()
	{
		return $this->fetch ('SELECT * FROM ' . $this->db->forums . ' WHERE 1');
	}

	/**
	 * Fetches topics from the database.
	 */
	function fetch_topics ()
	{
		return $this->fetch ('SELECT * FROM ' . $this->db->topics . ' WHERE 1');
	}

	/**
	 * Fetches posts from the database.
	 */
	function fetch_posts ($topic_id)
	{
		return $this->fetch ('SELECT * FROM ' . $this->db->posts . ' WHERE topic_id="' . $topic_id . '"');
	}

	/**
	 * Fetches user meta data from the database.
	 */
	function fetch_user_meta ($user_id)
	{
		return $this->fetch ('SELECT meta_key, meta_value FROM ' . $this->db->usermeta . ' WHERE user_id="' . $user_id . '"');
	}

	/**
	 * Fetches topic meta data from the database.
	 */
	function fetch_topic_meta ($topic_id)
	{
		return $this->fetch ('SELECT meta_key, meta_value FROM ' . $this->db->meta . ' WHERE object_type="bb_topic" AND object_id="' . $topic_id . '"');
	}

	/**
	 * Fetches topic tags from the database.
	 *
	 * Fetching topic tags requires multiple queries to
	 * determine the relationships between terms and IDs.
	 */
	function fetch_topic_tags ($topic_id)
	{
		$taxonomy_ids = $this->fetch ('SELECT term_taxonomy_id FROM ' . $this->db->term_relationships . ' WHERE object_id="' . $topic_id . '"');
		if ($taxonomy_ids)
		{
			foreach ($taxonomy_ids as $taxonomy_id)
			{
				$term_id = $this->fetch ('SELECT term_id FROM ' . $this->db->term_taxonomy . ' WHERE term_taxonomy_id="' . $taxonomy_id['term_taxonomy_id'] . '"');
				$tag = $this->fetch ('SELECT name FROM ' . $this->db->terms . ' WHERE term_id="' . $term_id[0]['term_id'] . '"');
				$tags[] = $tag[0];
			}
		}
		return $tags;
	}

	/**
	 * Prepares retrieved user data for output.
	 */
	function prep_user_data ($raw_user, $raw_meta)
	{
		$user['id'] = $raw_user['ID'];
		$user['login'] = $raw_user['user_login'];
		if (32 < strlen ($raw_user['user_pass']))
		{
			$user['pass']['type'] = 'phpass';
		}
		else
		{
			$user['pass']['type'] = 'md5';
		}
		$user['pass']['pass'] = $raw_user['user_pass'];
		$user['incept'] = $raw_user['user_registered'];
		$user['status'] = $raw_user['user_status'];
		$user['meta']['nicename'] = $raw_user['user_nicename'];
		$user['meta']['email'] = $raw_user['user_email'];
		$user['meta']['url'] = $raw_user['user_url'];
		$user['meta']['display_name'] = $raw_user['display_name'];
		if ($raw_meta)
		{
			foreach ($raw_meta as $raw_meta_entry)
			{
				$user['meta'][$raw_meta_entry['meta_key']] = $raw_meta_entry['meta_value'];
			}
		}
		return $user;
	}

	/**
	 * Prepares retrieved forum data for output.
	 */
	function prep_forum_data ($raw_forum)
	{
		$forum['id'] = $raw_forum['forum_id'];
		$forum['in'] = $raw_forum['forum_parent'];
		$forum['title'] = $raw_forum['forum_name'];
		$forum['content'] = $raw_forum['forum_desc'];
		$forum['meta']['slug'] = $raw_forum['forum_slug'];
		$forum['meta']['order'] = $raw_forum['forum_order'];
		return $forum;
	}

	/**
	 * Prepares retrieved topic data for output.
	 */
	function prep_topic_data ($raw_topic, $raw_meta, $raw_tags, $raw_posts)
	{
		$topic['id'] = $raw_topic['topic_id'];
		$topic['author'] = $raw_topic['topic_poster'];
		$topic['in'] = $raw_topic['forum_id'];
		$topic['title'] = $raw_topic['topic_title'];
		$topic['incept'] = $raw_topic['topic_start_time'];
		$topic['status'] = $raw_topic['topic_status'];
		$topic['meta']['slug'] = $raw_topic['topic_slug'];
		$topic['meta']['open'] = $raw_topic['topic_open'];
		$topic['meta']['sticky'] = $raw_topic['topic_sticky'];
		if ($raw_meta)
		{
			foreach ($raw_meta as $raw_meta_entry)
			{
				$topic['meta'][$raw_meta_entry['meta_key']] = $raw_meta_entry['meta_value'];
			}
		}
		if ($raw_tags)
		{
			foreach ($raw_tags as $raw_tag)
			{
				$topic['tags'][] = $this->prep_tag_data ($raw_tag);
			}
		}
		foreach ($raw_posts as $raw_post)
		{
			$topic['posts'][] = $this->prep_post_data ($raw_post);
		}
		return $topic;
	}

	/**
	 * Prepares retrieved post data for output.
	 */
	function prep_post_data ($raw_post)
	{
		$post['id'] = $raw_post['post_id'];
		$post['author'] = $raw_post['poster_id'];
		$post['title'] = '';
		$post['content'] = $raw_post['post_text'];
		$post['incept'] = $raw_post['post_time'];
		$post['status'] = $raw_post['post_status'];
		$post['meta']['ip_address'] = $raw_post['poster_ip'];
		return $post;
	}

	/**
	 * Prepares retrieved tag data for output.
	 */
	function prep_tag_data ($raw_tag)
	{
		return $raw_tag['name'];
	}

	/**
	 * Fetches, prepares, and outputs user data using subroutines.
	 */
	function write_users ()
	{
		$users = $this->fetch_users ();
		foreach ($users as $user)
		{
			$user_meta = $this->fetch_user_meta ($user['ID']);
			$user = $this->prep_user_data ($user, $user_meta);
			$this->add_user ($user);
		}
	}

	/**
	 * Fetches, prepares, and outputs forum data using subroutines.
	 */
	function write_forums ()
	{
		$forums = $this->fetch_forums ();
		foreach ($forums as $forum)
		{
			$forum = $this->prep_forum_data ($forum, $forum_meta);
			$this->add_forum ($forum);
		}
	}

	/**
	 * Fetches, prepares, and outputs topic data using subroutines.
	 */
	function write_topics ()
	{
		$topics = $this->fetch_topics ();
		foreach ($topics as $topic)
		{
			$topic_meta = $this->fetch_topic_meta ($topic['topic_id']);
			$topic_tags = $this->fetch_topic_tags ($topic['topic_id']);
			$topic_posts = $this->fetch_posts ($topic['topic_id']);
			$topic = $this->prep_topic_data ($topic, $topic_meta, $topic_tags, $topic_posts);
			$this->add_topic ($topic);
		}
	}

}

?>
