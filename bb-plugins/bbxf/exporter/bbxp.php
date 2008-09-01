<?php

/**
 * BBXF Export Class
 *
 * This class contains a number of functions used to take formatted
 * input and output it into a BBXF file for use in transporting
 * data between installations.  Class extensions and plugins can be found
 * for various software.
 */
class BBXP
{

	var $db;
	
	/**
	 * Adds formatted user data to the output.
	 */
	function add_user ($user)
	{
?>
	<user id="<?php echo $user['id']; ?>">
		<login><?php echo $user['login']; ?></login>
		<pass type="<?php echo $user['pass']['type']; ?>"><?php echo $user['pass']['pass'] ?></pass>
		<incept><?php echo $user['incept']; ?></incept>
		<status><?php echo $user['status']; ?></status>
<?php
			if ($user['meta'])
			{
				foreach ($user['meta'] as $key => $value)
				{
					$this->add_meta ($key, $value, 'user');
				}
			}
?>
	</user>

<?php
	}

	/**
	 * Adds formatted forum data to the output.
	 */
	function add_forum ($forum)
	{
?>
	<forum id="<?php echo $forum['id']; ?>" in="<?php echo $forum['in']; ?>">
		<title><![CDATA[<?php echo $forum['title']; ?>]]></title>
		<content><![CDATA[<?php echo $forum['content']; ?>]]></content>
<?php
			if ($forum['meta'])
			{
				foreach ($forum['meta'] as $key => $value)
				{
					$this->add_meta ($key, $value, 'forum');
				}
			}
?>
	</forum>
	
<?php
	}

	/**
	 * Adds formatted topic data to the output
	 */
	function add_topic ($topic)
	{
?>
	<topic id="<?php echo $topic['id']; ?>" author="<?php echo $topic['author']; ?>" in="<?php echo $topic['in']; ?>">
		<title><![CDATA[<?php echo $topic['title']; ?>]]></title>
		<incept><?php echo $topic['incept']; ?></incept>
		<status><?php echo $topic['status']; ?></status>
<?php
			if ($topic['meta'])
			{
				foreach ($topic['meta'] as $key => $value)
				{
					$this->add_meta ($key, $value, 'topic');
				}
			}
			if ($topic['tags'])
			{
				foreach ($topic['tags'] as $tag)
				{
					$this->add_tag ($tag);
				}
			}
?>

<?php
			foreach ($topic['posts'] as $post)
			{
				$this->add_post ($post);
			}
?>
	</topic>
	
<?php
	}

	/**
	 * Adds formatted post data to the output.
	 */
	function add_post ($post)
	{
?>
		<post id="<?php echo $post['id']; ?>" author="<?php echo $post['author']; ?>">
			<title><![CDATA[<?php echo $post['title']; ?>]]></title>
			<content><![CDATA[<?php echo $post['content']; ?>]]></content>
			<incept><?php echo $post['incept']; ?></incept>
			<status><?php echo $post['status']; ?></status>
<?php
			if ($post['meta'])
			{
				foreach ($post['meta'] as $key => $value)
				{
					$this->add_meta ($key, $value, 'post');
				}
			}
?>
		</post>
		
<?php
	}

	/**
	 * Adds formatted tag data to the output.
	 */
	function add_tag ($tag)
	{
?>
		<tag><![CDATA[<?php echo $tag; ?>]]></tag>
<?php
	}

	/**
	 * Adds formatted meta data to the output.
	 *
	 * Indentation varies depending on what type of element the meta data
	 * is being added to so as to make the output pretty.
	 */
	function add_meta ($key, $value, $type)
	{
		if ('post' == $type)
		{
?>
			<meta key="<?php echo $key; ?>"><![CDATA[<?php echo $value; ?>]]></meta>
<?php

		}
		else
		{
?>
		<meta key="<?php echo $key; ?>"><![CDATA[<?php echo $value; ?>]]></meta>
<?php

		}
	}

	/**
	 * Writes file headers.
	 * 
	 * Writes HTTP headers and adds the XML declaration as well as
	 * the top level container to the output.
	 */
	function write_header ($filename)
	{
		header ('Content-Description: File Transfer');
		header ('Content-Dispositon: attachment; filename=' . $filename);
		header ('Content-Type: text/xml; charset=UTF-8', true);

		echo '<?xml version="1.0" encoding="UTF-8" ?>';
?>


<forums_data>

<?php
	}

	/**
	 * Adds the closing tag for the top level container to the output.
	 */
	function write_footer ()
	{
?>
</forums_data>
<?php
	}

}

?>
