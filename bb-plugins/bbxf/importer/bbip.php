<?php

/**
 * BBXF Import Class
 *
 * This class contains a number of functions designed to take
 * formatted input data and insert it into a database.  It also
 * contains functions for conflict resolution between existing
 * data and the data that is to be imported.
 */
class BBIP extends BBXF_Parse
{

	var $db;
	var $export_lib;

	var $existing_data;
	var $skipped_data;

	var $id_mappings;

	var $next_forum_id;
	var $next_post_id;
	var $next_topic_id;
	var $next_user_id;

	var $import_content = TRUE;
	var $import_users = TRUE;
	var $preserve_admins = TRUE;
	var $preserve_current_user = TRUE;
	var $preserve_ids = FALSE;

	function BBIP ()
	{
		$this->BBXF_Parse ();
	}

	/**
	 * Pseudonym for BPDB's get_results.
	 * 
	 * This is a renaming of BPDB's get_results method to eliminate the need
	 * for the second parameter by always returning an associative array.
	 */
	function fetch ($query)
	{
		return $this->db->get_results ($query, 'ARRAY_A');
	}

	/**
	 * Pseudonym for BPDB's insert.
	 */
	function insert ($table, $data)
	{
		$this->db->insert ($table, $data);
	}

	function init_id_mappings ()
	{
		foreach ($this->forum_data['users'] as $user)
		{
			$this->id_mappings['users'][$user['id']] = $user['id'];
		}
		foreach ($this->forum_data['forums'] as $forum)
		{
			$this->id_mappings['forums'][$forum['id']] = $forum['id'];
		}
		foreach ($this->forum_data['topics'] as $topic)
		{
			$this->id_mappings['topics'][$topic['id']] = $topic['id'];
			foreach ($topic['posts'] as $post)
			{
				$this->id_mappings['posts'][$post['id']] = $post['id'];
			}
		}

	}

	function init_next_ids ()
	{
		$users = array_merge ($this->existing_data['user_ids'], $this->id_mappings['users']);
		arsort ($users);
		$users = array_values ($users);
		$this->next_user_id = $users[0];
		
		$forums = array_merge ($this->existing_data['forum_ids'], $this->id_mappings['forums']);
		arsort ($forums);
		$forums = array_values ($forums);
		$this->next_forum_id = $forums[0];
		
		$topics = array_merge ($this->existing_data['topic_ids'], $this->id_mappings['topics']);
		arsort ($topics);
		$topics = array_values ($topics);
		$this->next_topic_id = $topics[0];
		
		$posts = array_merge ($this->existing_data['post_ids'], $this->id_mappings['posts']);
		arsort ($posts);
		$posts = array_values ($posts);
		$this->next_post_id = $posts[0];
	}

	function prep_existing_user_data ()
	{
		foreach ($this->existing_data['users'] as $user)
		{
			$this->existing_data['user_ids'][] = $user['id'];
			$this->existing_data['user_logins'][] = $user['login'];
		}
	}

	function prep_existing_content_data ()
	{
		foreach ($this->existing_data['forums'] as $forum)
		{
			$this->existing_data['forum_ids'][] = $forum['id'];
			$this->existing_data['forum_titles'][$forum['in']][] = $forum['title'];
		}
		foreach ($this->existing_data['topics'] as $topic)
		{
			$this->existing_data['topic_ids'][] = $topic['id'];
			foreach ($topic['posts'] as $post)
			{
				$this->existing_data['post_ids'][] = $post['id'];
			}
			if ($topic['tags'])
			{
				foreach ($topic['tags'] as $tag)
				{
					$this->existing_data['tags'][] = $tag;
				}
			}
		}
	}

	function user_conflict ($user)
	{
		$conflicts = array ();
		if ($this->preserve_current_user && $this->is_current_user ($user))
		{
			$conflicts[] = 'current_user';
		}
		if ($this->preserve_admins && $this->is_admin ($user))
		{
			$conflicts[] = 'admin';
		}
		if (in_array ($user['id'], $this->existing_data['user_ids']))
		{
			$conflicts[] = 'id';
		}
		if (in_array ($user['login'], $this->existing_data['user_logins']))
		{
			$conflicts[] = 'login';
		}
		return $conflicts;
	}

	function forum_conflict ($forum)
	{
		$conflicts = array ();
		if (in_array ($forum['id'], $this->existing_data['forum_ids']))
		{
			$conflicts[] = 'id';
		}
		if (in_array ($forum['title'], $this->existing_data['forum_titles'][$forum['in']]))
		{
			$conflicts[] = 'title';
		}
		return $conflicts;
	}

	function topic_conflict ($topic)
	{
		if (in_array ($topic['id'], $this->existing_data['topic_ids']))
		{
			return TRUE;
		}
		return FALSE;
	}

	function post_conflict ($post)
	{
		if (in_array ($post['id'], $this->existing_data['post_ids']))
		{
			TRUE;
		}
		return FALSE;
	}

	function resolve_users ()
	{
		foreach ($this->forum_data['users'] as $user)
		{
			$conflicts = $this->user_conflict ($user);
			if (in_array ('current_user', $conflicts) ||
				in_array ('admin', $conflicts) ||
				in_array ('login', $conflicts) ||
				in_array ('id', $conflicts) && $this->preserve_ids)
			{
				$this->skip_user ($user);
			}
			if (in_array ('id', $conflicts) && !$this->preserve_ids)
			{
				$user = $this->update_user_id ($user);
			}
		}
	}

	function resolve_forums ()
	{
		foreach ($this->forum_data['forums'] as $forum)
		{
			$conflicts = $this->forum_conflict ($forum);
			if (in_array ('title', $conflicts) ||
				in_array ('id', $conflicts) && $this->preserve_ids)
			{
				$this->skip_forum ($forum);
			}
			if (in_array ('id', $conflicts) && !$this->preserve_ids)
			{
				$forum = $this->update_forum_id ($forum);
			}
		}
	}
	
	function resolve_topics ()
	{
		foreach ($this->forum_data['topics'] as $topic)
		{
			$topic_conflict = $this->topic_conflict ($topic);
			if ($topic_conflict && $this->preserve_ids)
			{
				$this->skip_topic ($topic);
			}
			elseif ($topic_conflict)
			{
				$topic = $this->update_topic_id ($topic);
			}
			foreach ($topic['posts'] as $post)
			{
				$post_conflict = $this->post_conflict ($post);
				if ($post_conflict && $this->preserve_ids)
				{
					$this->skip_post ($post);
				}
				elseif ($post_conflict)
				{
					$post = $this->update_post_id ($post);
				}
			}
		}
	}

	function skip_user ($user)
	{
		$this->skipped_data['users'][] = $user;
		$remove = array_search ($user, $this->forum_data['users']);
		unset ($this->forum_data['users'][$remove]);
	}

	function skip_forum ($forum)
	{
		$this->skipped_data['forums'][] = $forum;
		$remove = array_search ($forum, $this->forum_data['forums']);
		unset ($this->forum_data['forums'][$remove]);
	}

	function skip_topic ($topic)
	{
		$this->skipped_data['topics'][] = $topic;
		$remove = array_search ($topic, $this->forum_data['topics']);
		unset ($this->forum_data['topics'][$remove]);
	}

	function skip_post ($post)
	{
		$this->skipped_data['topics'][$post['in']]['posts'] = $post;
		$remove = array_search ($post, $this->forum_data['topics'][$post['in']]['posts']);
		unset ($this->forum_data['topics'][$post['in']]['posts'][$remove]);
	}

	function update_user_id ($user)
	{
		$this->id_mappings['users'][$user['id']] = $this->next_user_id++;
		$user['id'] = $this->id_mappings['users'][$user['id']];
		return $user;
	}

	function update_forum_id ($forum)
	{
		$this->id_mappings['forums'][$forum['id']] = $this->next_forum_id++;
		$forum['id'] = $this->id_mappings['forums'][$forum['id']];
		return $forum;
	}

	function update_topic_id ($topic)
	{
		$this->id_mappings['topics'][$topic['id']] = $this->next_topic_id++;
		$topic['id'] = $this->id_mappings['topics'][$topic['id']];
		return $topic;
	}

	function update_post_id ($post)
	{
		$this->id_mappings['posts'][$post['id']] = $this->next_topic_id++;
		$post['id'] = $this->id_mappings['posts'][$post['id']];
		return $post;
	}

	function tag_exists ($tag)
	{
		return in_array ($tag, $this->existing_data['tags']);
	}

	function import_prep ()
	{
		if ($this->import_users)
		{
			$this->fetch_existing_users ();
			$this->prep_existing_user_data ();
		}
		if ($this->import_content)
		{
			$this->fetch_existing_forums ();
			$this->fetch_existing_topics ();
			$this->prep_existing_content_data ();
		}
		
		$this->init_id_mappings ();
		$this->init_next_ids ();
	
		if ($this->import_users)
		{
			$this->resolve_users ();
		}
		if ($this->import_content)
		{
			$this->resolve_forums ();
			$this->resolve_topics ();
		}
	}

}

?>
