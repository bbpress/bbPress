<?php

/**
 * bbPress BBIP Extension
 *
 * This class includes functions necessary for bbPress to interface
 * with the BBIP class, allowing for importation of bbPress data to
 * the database.
 */
class BBIP_bbPress extends BBIP
{

	function BBIP_bbPress ()
	{
		$this->BBIP ();
		$this->export_lib = new BBXP_bbPress;
	}

	/**
	 * Fetches and prepares existing user data using subroutines.
	 */
	function fetch_existing_users ()
	{
		$users = $this->export_lib->fetch_users ();
		foreach ($users as $user)
		{
			$user_meta = $this->export_lib->fetch_user_meta ($user['ID']);
			$user = $this->export_lib->prep_user_data ($user, $user_meta);
			$this->existing_data['users'][] = $user;
		}
	}

	/**
	 * Fetches and prepares existing forum data using subroutines.
	 */
	function fetch_existing_forums ()
	{
		$forums = $this->export_lib->fetch_forums ();
		foreach ($forums as $forum)
		{
			$forum = $this->export_lib->prep_forum_data ($forum);
			$this->existing_data['forums'][] = $forum;
		}
	}

	/**
	 * Fetches and prepares existing topic data using subroutines.
	 */
	function fetch_existing_topics ()
	{
		$topics = $this->export_lib->fetch_topics ();
		foreach ($topics as $topic)
		{
			$topic_meta = $this->export_lib->fetch_topic_meta ($topic['topic_id']);
			$topic_tags = $this->export_lib->fetch_topic_tags ($topic['topic_id']);
			$topic_posts = $this->export_lib->fetch_posts ($topic['topic_id']);
			$topic = $this->export_lib->prep_topic_data ($topic, $topic_meta, $topic_tags, $topic_posts);
			$this->existing_data['topics'][] = $topic;
		}
	}

	function is_current_user ($user)
	{
		$id = bb_get_current_user_info ('id');
		if ($id == $user['id'])
		{
			return TRUE;
		}
		return FALSE;
	}

	function is_admin ($user)
	{
		if (FALSE !== strpos ($user['meta']['capabilities'], 'keymaster'))
		{
			return TRUE;
		}
		return FALSE;
	}

	function insert_users ()
	{
		foreach ($this->forum_data['users'] as $user)
		{
			$data['ID'] = $user['id'];
			$data['user_login'] = $user['login'];
			// Check type!
			$data['user_pass'] = $user['pass']['pass'];
			$data['user_registered'] = $user['incept'];
			if (0 === $user['status'] || 1 === $user['status'])
			{
				$data['user_status'] = $user['status'];
			}
			$meta = $user['meta'];
			$data['user_nicename'] = $meta['nicename'];
			$data['user_email'] = $meta['email'];
			$data['user_url'] = $meta['url'];
			$data['display_name'] = $meta['display_name'];
			unset ($meta['user_nicename'], $meta['user_email'], $meta['user_url'], $meta['display_name']);
			$this->insert ($this->db->users, $data);
			if ($meta)
			{
				$this->insert_user_meta ($user['id'], $meta);
			}
		}
	}

	function insert_forums ()
	{
		foreach ($this->forum_data['forums'] as $forum)
		{
			$data['forum_id'] = $forum['id'];
			$data['forum_parent'] = $forum['in'];
			$data['forum_name'] = $forum['title'];
			$data['forum_desc'] = $forum['content'];
			$meta = $forum['meta'];
			$data['forum_slug'] = $meta['forum_slug'];
			$data['forum_order'] = $meta['forum_order'];
			$this->insert ($this->db->forums, $data);
		}
	}

	function insert_topics ()
	{
		foreach ($this->forum_data['topics'] as $topic)
		{
			$data['topic_id'] = $topic['id'];
			$data['forum_id'] = $topic['in'];
			$data['topic_title'] = $topic['title'];
			$data['topic_poster'] = $topic['author'];
			$data['topic_start_time'] = $topic['incept'];
			$data['topic_status'] = $topic['status'];
			$meta = $topic['meta'];
			$data['topic_slug'] = $meta['slug'];
			$data['topic_open'] = $meta['open'];
			$data['topic_sticky'] = $meta['sticky'];
			unset ($meta['slug'], $meta['open'], $meta['sticky']);
			// topic_poster_name
			// topic_last_poster
			// topic_last_poster_name
			// topic_time
			// topic_last_post_id
			$data['topic_posts'] = sizeof ($topic['posts']);
			$data['tag_count'] = sizeof ($topic['tags']);			

			$this->insert ($this->db->topics, $data);
			$this->insert_topic_meta ($topic['id'], $meta);
			$this->insert_tags ($topic['id'], $topic['tags']);
			$this->insert_posts ($topic['id'], $topic['in'], $topic['posts']);
		}
	}

	function insert_posts ($topic_id, $forum_id, $posts)
	{
		foreach ($posts as $post)
		{
			$data['post_id'] = $post['id'];
			$data['forum_id'] = $forum_id;
			$data['topic_id'] = $topic_id;
			$data['poster_id'] = $post['author'];
			$data['post_text'] = $post['content'];
			$data['post_time'] = $post['incept'];
			if (0 === $post['status'] || 1 === $post['status'])
			{
				$data['post_status'] = $post['status'];
			}
			$meta = $post['meta'];
			$data['poster_ip'] = $meta['poster_ip'];
			$this->insert ($this->db->posts, $data);
		}
	}

	function insert_tags ($topic_id, $tags)
	{
		foreach ($tags as $tag)
		{
			if (!$this->tag_exists ($tag))
			{
				bb_add_topic_tag ($topic_id, $tag);
			}
		}
	}

	function insert_user_meta ($user_id, $user_meta)
	{
		foreach ($user_meta as $key => $value)
		{
			$data = array ('user_id' => user_meta, 'meta_key' => $key, 'meta_value' => $value);
			$this->insert ($this->db->user_meta, $data);
		}
	}

	function insert_topic_meta ($topic_id, $topic_meta)
	{
		foreach ($topic_meta as $key => $value)
		{
			$data = array ('object_type' => 'bb_topic', 'object_id' => $topic_id, 'meta_key' => $key, 'meta_value' => $value);
			$this->insert ($this->db->meta, $data);
		}
	}
	
}

?>
