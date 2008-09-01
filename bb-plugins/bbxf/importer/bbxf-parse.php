<?php

/**
 * Bulletin Board XML Format Parser Class
 *
 * This class contains functions which facilitate parsing of the Bulletin
 * board XML format for web forum data.  These functions natively
 * handle validation, so if a file is parsed fully, it is also validated.
 */
class BBXF_Parse
{
	
	var $forum_data;
	var $elements = array ();
	var $file_contents;
	var $file_handle;

	/**
	 * Constructor function establishes contents of elements array.
	 *
	 * The elements array is used in determining the validity of elements
	 * placed in the import file.  It includes all valid elements as entries.
	 * Each entry includes information about which parsing function handles
	 * the element, whether it is a single tag or it is paired with a closing
	 * tag, valid child elements, and valid attributes.
	 */
	function BBXF_Parse ()
	{
		$this->elements['!--'] = array ('function' => 'read_comment', 'closing_tag' => FALSE, 'children' => array (), 'attributes' => array ());
		$this->elements['?xml'] = array ('function' => 'read_declaration', 'closing_tag' => FALSE, 'children' => array (), 'attributes' => array('version', 'encoding'));
		$this->elements['forums_data'] = array ('function' => 'read_forums_data', 'closing_tag' => TRUE, 'children' => array ('!--', 'user', 'forum', 'topic'));
		$this->elements['forum'] = array ('function' => 'read_forum', 'closing_tag' => TRUE, 'children' => array ('!--', 'title', 'content', 'meta'), 'attributes' => array('id', 'in'));
		$this->elements['user'] = array ('function' => 'read_user', 'closing_tag' => TRUE, 'children' => array ('!--', 'login', 'pass', 'incept', 'status', 'meta'), 'attributes' => array('id'));
		$this->elements['topic'] = array ('function' => 'read_topic', 'closing_tag' => TRUE, 'children' => array ('!--', 'title', 'incept', 'status', 'meta', 'tag', 'post'), 'attributes' => array('id', 'in', 'author'));
		$this->elements['post'] = array ('function' => 'read_post', 'closing_tag' => TRUE, 'children' => array ('!--', 'title', 'content', 'incept', 'status', 'meta'), 'attributes' => array('id', 'author'));
		$this->elements['meta'] = array ('function' => 'read_meta', 'closing_tag' => TRUE, 'children' => array ('!--', '![CDATA['), 'attributes' => array('key'));
		$this->elements['tag'] = array ('function' => 'read_tag', 'closing_tag' => TRUE, 'children' => array ('!--', '![CDATA['), 'attributes' => array());
		$this->elements['![CDATA['] = array ('function' => 'read_cdata', 'closing_tag' => FALSE, 'children' => array (), 'attributes' => array());
		$this->elements['login'] = array ('function' => 'read_login', 'closing_tag' => TRUE, 'children' => array ('!--', 'data'), 'attributes' => array());
		$this->elements['pass'] = array ('function' => 'read_pass', 'closing_tag' => TRUE, 'children' => array ('!--', 'data'), 'attributes' => array('type'));
		$this->elements['incept'] = array ('function' => 'read_incept', 'closing_tag' => TRUE, 'children' => array ('!--', 'data'), 'attributes' => array());
		$this->elements['status'] = array ('function' => 'read_status', 'closing_tag' => TRUE, 'children' => array ('!--', 'data'), 'attributes' => array());
		$this->elements['title'] = array ('function' => 'read_title', 'closing_tag' => TRUE, 'children' => array ('!--', '![CDATA['), 'attributes' => array());
		$this->elements['content'] = array ('function' => 'read_content', 'closing_tag' => TRUE, 'children' => array ('!--', '![CDATA['), 'attributes' => array());
	}

	/**
	 * Reads contents of file into file_contents variable.
	 */
	function read_file ($path)
	{
		$this->file_handle = fopen ($path, 'r')
			or die ('Could not open specified file.');
		while (!feof ($this->file_handle))
		{
			$this->file_contents = $this->file_contents . $this->read_line ();
		}
		fclose ($this->file_handle);
	}
	
	/**
	 * Reads a line from the file and returns it to calling function
	 */
	function read_line ()
	{
		$line = fgets ($this->file_handle);
		if ($line === FALSE)
		{
			//die ('Could not read new line from file.');
		}
		return trim ($line);
	}

	/**
	 * Finds first element inside given data and analyzes type.
	 *
	 * This is the main worker function of the parsing library. It
	 * uses regular expression matching on a set of data to search for
	 * the first XML element.  Once found, it checks to make sure that the
	 * element is valid in the standard, then finds the entire element's
	 * data and returns it to the calling function.  If there is no element
	 * found, the data is returned with a 'data' type.
	 */
	function find_element ($data)
	{
		$data = trim ($data);
		if ('' != $data)
		{
			preg_match ('|<([!\?\[\w]+).*?>|s', $data, $opening_tag);
			$element = $opening_tag[1];
			if ('' != $element)
			{
				if (FALSE !== strpos ($element, '![CDATA['))
				{
					$element = '![CDATA[';
				}
				if (array_key_exists ($element, $this->elements) && $this->elements[$element]['closing_tag'])
				{
					preg_match ('|<' . preg_quote ($element) . '.*?>.*?<\/' . preg_quote ($element) . '>|s', $data, $items);
					$item = array ($element, trim ($items[0]));
				}
				elseif (array_key_exists ($element, $this->elements))
				{
					$items = $opening_tag;
					if ($element == '!--')
					{
						unset ($items);
						preg_match ('|<!--.*?-->|s', $data, $items);
					}
					elseif ($element == '![CDATA[')
					{
						unset ($items);
						preg_match ('|<!\[CDATA\[.*?\]\]>|s', $data, $items);
					}
					$item = array ($element, trim ($items[0]));
				}
				else
				{
					die ('Invalid element ' . $element . '.');
				}
				return $item;
			}
			else
			{
				$item[0] = 'data';
				$item[1] = $data;
				return $item;
			}
		}
	}

	/**
	 * Finds attributes of a given element.
	 *
	 * This function finds the attributes of a given element.  It also
	 * determines if they are valid, then returns them in a formatted array.
	 */
	function find_attributes ($element, $data)
	{		
		if ('?xml' != $element)
		{
			preg_match ('|<' . preg_quote ($element) . '.*?>|s', $data, $item);
			$data = $item[0];
			$data = preg_replace ('|<' . preg_quote ($element) . '|', '', $data, 1);
			$data = preg_replace ('|>|', '', $data, 1);
		}
		else
		{
			preg_match ('|<' . preg_quote ($element) . '.*?\?>|s', $data, $item);
			$data = $item[0];
			$data = preg_replace ('|<' . preg_quote ($element) . '|', '', $data, 1);
			$data = preg_replace ('|\?>|', '', $data, 1);
		}
		$data = trim ($data);
		if (0 < strlen ($data))
		{
			while ($data)
			{
				preg_match ('|(\w+)="(.+?)"|', $data, $attribute);
				if ($this->valid_attribute ($attribute[1], $element))
				{
					$attributes[$attribute[1]] = $attribute[2];
					$data = preg_replace ('|' . $attribute[0] . '|', '', $data, 1);
					$data = trim ($data);
				}
				else
				{
					die ('Invalid attribute in ' . $element . ' element.');
				}
			}
		}
		return $attributes;
	}

	/**
	 * Determines if an element is a valid child of the parent.
	 */
	function valid_child ($child, $parent)
	{
		return in_array ($child, $this->elements[$parent]['children']); 
	}

	/**
	 * Determines if an attribute is valid for a given parent.
	 */
	function valid_attribute ($attribute, $parent)
	{
		return in_array ($attribute, $this->elements[$parent]['attributes']);
	}

	/**
	 * Takes a formatted item array and calls the appropriate parsing function.
	 */
	function call_element ($item)
	{
		$function = $this->elements[$item[0]]['function'];
		return call_user_func (array ('BBXF_Parse', $function), $item[1]);
	}

	/**
	 * Removes a given element from a set of data.
	 */
	function remove_element ($target, $data)
	{
		$data = str_replace ($target, '', $data);
		return trim ($data);
	}

	/**
	 * Strips the opening and closing tags from given data.
	 */
	function strip ($element, $data)
	{
		$opening = '|^<' . preg_quote ($element) . '.*?>|s';
		$closing = '|</' . preg_quote ($element) . '.*?>|s';
		$data = preg_replace ($opening, '', $data, 1);
		$data = preg_replace ($closing, '', $data, 1);
		return trim ($data);
	}
	
	/**
	 * Parsing function for the XML declaration.
	 */
	function read_declaration ($data)
	{
		$attributes = $this->find_attributes ('?xml', $data);
		if ('1.0' != $attributes['version'] || 'UTF-8' != $attributes['encoding'])
		{
			die ('Invalid declaration.');
		}
	}

	/**
	 * Parsing function for the forums_data element.
	 *
	 * This function parses the forums_data element and inserts the
	 * data into the forums_data array.
	 */
	function read_forums_data ($data)
	{
		$attributes = $this->find_attributes ('forums_data', $data);
		$data = $this->strip ('forums_data', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'forums_data'))
			{
				print $data;
				die ('Invalid child element (' . $current[0] . ') inside forums_data.');
			}
			if ('!--' != $current[0])
			{
				$result = $this->call_element ($current);
				$this->forum_data[$current[0] . 's'][] = $result;
			}
			$data = $this->remove_element ($current[1], $data);
		}
	}

	/**
	 * Parsing function for the user element.
	 */
	function read_user ($data)
	{
		$attributes = $this->find_attributes ('user', $data);
		$user['id'] = $attributes['id'];
		$data = $this->strip ('user', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'user'))
			{
				die ('Invalid child element inside user element.');
			}
			if ('!--' != $current[0])
			{
				$result = $this->call_element ($current);
				if ('meta' == $current[0])
				{
					$user['meta'][$result['key']] = $result['value'];
				}
				else
				{
					$user[$current[0]] = $result;
				}
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $user;
	}

	/**
	 * Parsing function for the forum element.
	 */
	function read_forum ($data)
	{
		$attributes = $this->find_attributes ('forum', $data);
		$forum['id'] = $attributes['id'];
		$forum['in'] = $attributes['in'];
		$data = $this->strip ('forum', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'forum'))
			{
				die ('Invalid child element inside forum element.');
			}
			if ('!--' != $current[0])
			{
				$result = $this->call_element ($current);
				if ('meta' == $current[0])
				{
				$forum[$current[0]][$result['key']] = $result['value'];
				}
				else
				{
					$forum[$current[0]] = $result;
				}
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $forum;
	}

	/**
	 * Parsing function for the topic element.
	 */
	function read_topic ($data)
	{
		$attributes = $this->find_attributes ('topic', $data);
		$topic['id'] = $attributes['id'];
		$topic['author'] = $attributes['author'];
		$topic['in'] = $attributes['in'];
		$data = $this->strip ('topic', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'topic'))
			{
				die ('Invalid child element inside topic element.');
			}
			if ('!--' != $current[0])
			{
				$result = $this->call_element ($current);
				if ('tag' == $current[0] || 'post' == $current[0])
				{
					$topic[$current[0] . 's'][] = $result;
				}
				elseif ('meta' == $current[0])
				{
					$topic[$current[0]][$result['key']] = $result['value'];
				}
				else
				{
					$topic[$current[0]] = $result;
				}
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $topic;
	}

	/**
	 * Parsing function for the post element.
	 */
	function read_post ($data)
	{
		$attributes = $this->find_attributes ('post', $data);
		$post['id'] = $attributes['id'];
		$post['author'] = $attributes['author'];
		$data = $this->strip ('post', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'post'))
			{
				die ('Invalid child element inside post element.');
			}
			if ('!--' != $current[0])
			{
				$result = $this->call_element ($current);
				if ('meta' == $current[0])
				{
					$post[$current[0]][$result['key']] = $result['value'];
				}
				else
				{
					$post[$current[0]] = $result;
				}
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $post;
	}

	/**
	 * Parsing element for the meta element
	 */
	function read_meta ($data)
	{
		$attributes = $this->find_attributes ('meta', $data);
		$meta['key'] = $attributes['key'];
		$data = $this->strip ('meta', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'meta'))
			{
				die ('Invalid child element inside meta element.');
			}
			if ('!--' != $current[0])
			{
				$meta['value'] = $this->call_element ($current);
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $meta;
	}

	/**
	 * Parsing function for the tag element.
	 */
	function read_tag ($data)
	{
		$attributes = $this->find_attributes ('tag', $data);
		$data = $this->strip ('tag', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'tag'))
			{
				die ('Invalid child element inside tag element.');
			}
			if ('!--' != $current[0])
			{
				$tag = $this->call_element ($current);
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $tag;
	}

	/**
	 * Parsing function for the CDATA element.
	 */
	function read_cdata ($data)
	{
		preg_match ('|<!\[CDATA\[(.*?)\]\]>|s', $data, $result);
		return $result[1];
	}

	/**
	 * Parsing function for the login element.
	 */
	function read_login ($data)
	{
		$attributes = $this->find_attributes ('login', $data);
		$data = $this->strip ('login', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if ($current)
			{
				if (!$this->valid_child ($current[0], 'login'))
				{
					die ('Invalid child element inside login element.');
				}
			}
			if ('data' == $current[0])
			{
				$login = $data;
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $login;
	}

	/**
	 * Parsing function for the incept element.
	 */
	function read_incept ($data)
	{
		$attributes = $this->find_attributes ('incept', $data);
		$data = $this->strip ('incept', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if ($current)
			{
				if (!$this->valid_child ($current[0], 'incept'))
				{
					die ('Invalid child element inside incept element.');
				}
			}
			if ('data' == $current[0])
			{
				$incept = $data;
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $incept;
	}

	/**
	 * Parsing function for the pass element.
	 */
	function read_pass ($data)
	{
		$attributes = $this->find_attributes ('pass', $data);
		$pass['type'] = $attributes['type'];
		$data = $this->strip ('pass', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if ($current)
			{
				if (!$this->valid_child ($current[0], 'incept'))
				{
					die ('Invalid child element inside incept element.');
				}
			}
			if ('data' == $current[0])
			{
				$pass['pass'] = $data;
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $pass;
	}

	/**
	 * Parsing function for the status element.
	 */
	function read_status ($data)
	{
		$attributes = $this->find_attributes ('status', $data);
		$data = $this->strip ('status', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if ($current)
			{
				if (!$this->valid_child ($current[0], 'status'))
				{
					die ('Invalid child element inside status element.');
				}
			}
			if ('data' == $current[0])
			{
				$status = $data;
			}
			$data = $this->remove_element ($current[1], $data);
		}
		return $status;
	}

	/**
	 * Parsing function for the title element.
	 */
	function read_title ($data)
	{
		$attributes = $this->find_attributes ('title', $data);
		$data = $this->strip ('title', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'title'))
			{
				die ('Invalid child element inside title element.');
			}
			$title = $this->call_element ($current);
			$data = $this->remove_element ($current[1], $data);
		}
		return $title;
	}

	/**
	 * Parsing function for the content element.
	 */
	function read_content ($data)
	{
		$attributes = $this->find_attributes ('content', $data);
		$data = $this->strip ('content', $data);
		while ($data)
		{
			$current = $this->find_element ($data);
			if (!$this->valid_child ($current[0], 'content'))
			{
				die ('Invalid child element inside content element.');
			}
			$content = $this->call_element ($current);
			$data = $this->remove_element ($current[1], $data);
		}
		return $content;
	}
	
	/**
	 * Checks import data for duplicate users.
	 */
	function user_duplicates ()
	{
		foreach ($this->forum_data['users'] as $user)
		{
			$ids[] = $user['id'];
			$logins[] = $user['login'];
		}
		if (sizeof ($ids) != sizeof (array_flip ($ids)))
		{
			die ('Duplicate user IDs in data.');
		}
		if (sizeof ($logins) != sizeof (array_flip ($logins)))
		{
			die ('Duplicate user logins in data.');
		}
	}

	/**
	 * Checks import data for duplicate forums.
	 */
	function forum_duplicates ()
	{
		foreach ($this->forum_data['forums'] as $forum)
		{
			$ids[] = $forum['id'];
			$titles[$forum['in']][] = $forum['title'];
			$slugs[] = $forum['meta']['slug'];
		}
		if (sizeof ($ids) != sizeof (array_flip ($ids)))
		{
			die ('Duplicate forum IDs in data.');
		}
		foreach ($titles as $subtitles)
		{
			if (sizeof ($subtitles) != sizeof (array_flip ($subtitles)))
			{
				die ('Duplicate forum titles under the same parent in data.');
			}
		}
		if (sizeof ($slugs) != sizeof (array_flip ($slugs)))
		{
			die ('Duplicate forum slugs in data.');
		}
	}

	/**
	 * Checks import data for duplicate topics and posts.
	 */
	function topic_duplicates ()
	{
		foreach ($this->forum_data['topics'] as $topic)
		{
			$topic_ids[] = $topic['id'];
			foreach ($topic['posts'] as $post)
			{
				$post_ids[] = $post['id'];
			}
		}
		if (sizeof ($topic_ids) != sizeof (array_flip ($topic_ids)))
		{
			die ('Duplicate topic IDs in data.');
		}
		if (sizeof ($post_ids) != sizeof (array_flip ($post_ids)))
		{
			die ('Duplicate post IDs in data.');
		}
	}

	/**
	 * Runs individual duplicate-checking functions.
	 */
	function check_for_duplicates ()
	{
		$this->user_duplicates ();
		$this->forum_duplicates ();
		$this->topic_duplicates ();
	}

}

?>
