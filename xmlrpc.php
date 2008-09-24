<?php
/**
 * XML-RPC protocol support for bbPress
 *
 * @package bbPress
 */



/**
 * Whether this is an XML-RPC Request
 *
 * @var bool
 */
define('XMLRPC_REQUEST', true);

// Some browser-embedded clients send cookies. We don't want them.
$_COOKIE = array();

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

// fix for mozBlog and other cases where '<?xml' isn't on the very first line
if ( isset($HTTP_RAW_POST_DATA) ) {
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);
}

/** Include the bootstrap for setting up bbPress environment */
require('./bb-load.php');



if ( isset( $_GET['rsd'] ) ) { // http://archipelago.phrasewise.com/rsd
	header('Content-Type: text/xml; charset=UTF-8', true);
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?'.'>' . "\n"; ?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
	<service>
		<engineName>bbPress</engineName>
		<engineLink>http://bbpress.org/</engineLink>
		<homePageLink><?php bb_uri() ?></homePageLink>
		<apis>
			<api name="bbPress" blogID="" preferred="true" apiLink="<?php bb_uri('xmlrpc.php') ?>" />
		</apis>
	</service>
</rsd>
<?php
	exit;
}



include_once(BB_PATH . 'bb-admin/admin-functions.php');
include_once(BACKPRESS_PATH . '/class.ixr.php');



// Turn off all warnings and errors.
// error_reporting(0);

/**
 * Whether to enable XML-RPC Logging.
 *
 * @name bb_xmlrpc_logging
 * @var int|bool
 */
$bb_xmlrpc_logging = 0;

/**
 * bb_logIO() - Writes logging info to a file.
 *
 * @uses $bb_xmlrpc_logging
 * @package bbPress
 * @subpackage Logging
 *
 * @param string $io Whether input or output
 * @param string $msg Information describing logging reason.
 * @return bool Always return true
 */
function bb_logIO($io, $msg)
{
	global $bb_xmlrpc_logging;
	if ($bb_xmlrpc_logging) {
		$fp = fopen("../xmlrpc.log","a+");
		$date = gmdate("Y-m-d H:i:s ");
		$iot = ($io == "I") ? " Input: " : " Output: ";
		fwrite($fp, "\n\n".$date.$iot.$msg);
		fclose($fp);
	}
	return true;
}

if ( isset($HTTP_RAW_POST_DATA) ) {
	bb_logIO("I", $HTTP_RAW_POST_DATA);
}



/**
 * XML-RPC server class to allow for remote publishing
 *
 * @package bbPress
 * @subpackage Publishing
 * @uses class IXR_Server
 */
class bb_xmlrpc_server extends IXR_Server
{
	/**
	 * Initialises the XML-RPC server
	 *
	 * @return void
	 **/
	function bb_xmlrpc_server()
	{
		// Demo
		$this->methods = array(
			'demo.sayHello' => 'this:sayHello',
			'demo.addTwoNumbers' => 'this:addTwoNumbers'
		);

		// bbPress publishing API
		if (bb_get_option('enable_xmlrpc')) {
			$this->methods = array_merge($this->methods, array(
				// - Forums
				'bb.getForumCount'		=> 'this:bb_getForumCount',
				'bb.getForums'			=> 'this:bb_getForums',
				'bb.getForum'			=> 'this:bb_getForum',
				//'bb.newForum'			=> 'this:bb_newForum',
				//'bb.editForum'			=> 'this:bb_editForum',
				//'bb.deleteForum'		=> 'this:bb_deleteForum',
				// - Topics
				//'bb.getTopicCount'		=> 'this:bb_getTopicCount',
				//'bb.getTopics'			=> 'this:bb_getTopics',
				//'bb.getTopic'			=> 'this:bb_getTopic',
				//'bb.newTopic'			=> 'this:bb_newTopic',
				//'bb.editTopic'			=> 'this:bb_editTopic',
				//'bb.deleteTopic'		=> 'this:bb_deleteTopic',
				// - Tags
				//'bb.getTagCount'		=> 'this:bb_getTagCount',
				//'bb.getTags'			=> 'this:bb_getTags',
				//'bb.getTag'				=> 'this:bb_getTag',
				//'bb.newTag'				=> 'this:bb_newTag',
				//'bb.editTag'			=> 'this:bb_editTag',
				//'bb.deleteTag'			=> 'this:bb_deleteTag',
				//'bb.mergeTags'			=> 'this:bb_mergeTags',
				// - Replies
				//'bb.getReplyCount'		=> 'this:bb_getReplyCount',
				//'bb.getReplies'			=> 'this:bb_getReplies',
				//'bb.getReply'			=> 'this:bb_getReply',
				//'bb.newReply'			=> 'this:bb_newReply',
				//'bb.editReply'			=> 'this:bb_editReply',
				//'bb.deleteReply'		=> 'this:bb_deleteReply',
				// - Options
				//'bb.getOptions'			=> 'this:bb_getOptions',
				//'bb.setOptions'			=> 'this:bb_setOptions',
			));
		}

		// Pingback
		if (bb_get_option('enable_pingback')) {
			$this->methods = array_merge($this->methods, array(
				'pingback.ping' => 'this:pingback_ping',
				'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks'
			));
		}

		//$this->initialise_site_option_info();
		$this->methods = apply_filters('bb_xmlrpc_methods', $this->methods);
		$this->IXR_Server($this->methods);
	}



	/**
	 * Utility methods
	 */

	/**
	 * Initialises site options which can be manipulated using XML-RPC
	 *
	 * @return void
	 **/
	function initialise_site_option_info()
	{
		$this->site_options = array(
			// Read only options
			'software_name'		=> array(
				'desc'			=> __( 'Software Name' ),
				'readonly'		=> true,
				'value'			=> 'bbPress'
			),
			'software_version'	=> array(
				'desc'			=> __( 'Software Version' ),
				'readonly'		=> true,
				'option'		=> 'version'
			),
			'site_url'			=> array(
				'desc'			=> __( 'Site URL' ),
				'readonly'		=> true,
				'option'		=> 'uri'
			),

			// Updatable options
			'site_name'		=> array(
				'desc'			=> __( 'Site Name' ),
				'readonly'		=> false,
				'option'		=> 'name'
			),
			'site_description'	=> array(
				'desc'			=> __( 'Site Description' ),
				'readonly'		=> false,
				'option'		=> 'description'
			),
			'time_zone'			=> array(
				'desc'			=> __( 'Time Zone' ),
				'readonly'		=> false,
				'option'		=> 'gmt_offset'
			),
			'date_format'		=> array(
				'desc'			=> __( 'Date/Time Format' ),
				'readonly'		=> false,
				'option'		=> 'datetime_format'
			),
			'date_format'		=> array(
				'desc'			=> __( 'Date Format' ),
				'readonly'		=> false,
				'option'		=> 'date_format'
			)
		);

		$this->site_options = apply_filters( 'xmlrpc_site_options', $this->site_options );
	}

	/*
	// To be implemented
	function login_pass_ok($user_login, $user_pass)
	{
		if (!user_pass_ok($user_login, $user_pass)) {
			$this->error = new IXR_Error(403, __('Bad login/pass combination.'));
			return false;
		}
		return true;
	}
	*/

	/**
	 * Sanitises data from XML-RPC request parameters
	 *
	 * @return mixed The sanitised variable, should come back with the same type
	 * @param $array mixed The variable to be sanitised
	 * @uses $bbdb BackPress database class instance
	 **/
	function escape(&$array)
	{
		global $bbdb;

		if (!is_array($array)) {
			// Escape it
			$array = $bbdb->escape($array);
		} else {
			foreach ( (array) $array as $k => $v ) {
				if (is_array($v)) {
					// Recursively sanitize arrays
					$this->escape($array[$k]);
				} else if (is_object($v)) {
					// Don't sanitise objects - shouldn't happen anyway
				} else {
					// Escape it
					$array[$k] = $bbdb->escape($v);
				}
			}
		}
		
		return $array;
	}



	/**
	 * Demo XML-RPC methods
	 */

	/**
	 * Hello world demo function for XML-RPC
	 *
	 * @return string The phrase 'Hello!'.
	 * @param array $args Arguments passed by the XML-RPC call.
	 *
	 * XML-RPC request to get a greeting
	 * <methodCall>
	 *     <methodName>demo.sayHello</methodName>
	 *     <params></params>
	 * </methodCall>
	 **/
	function sayHello($args)
	{
		return 'Hello!';
	}

	/**
	 * Adds two numbers together as a demo of XML-RPC
	 *
	 * @return integer The sum of the two supplied numbers.
	 * @param array $args Arguments passed by the XML-RPC call.
	 * @param integer $args[0] The first number to be added.
	 * @param integer $args[1] The second number to be added.
	 *
	 * XML-RPC request to get the sum of two numbers
	 * <methodCall>
	 *     <methodName>demo.addTwoNumbers</methodName>
	 *     <params>
	 *         <param><value><int>5</int></value></param>
	 *         <param><value><int>102</int></value></param>
	 *     </params>
	 * </methodCall>
	 **/
	function addTwoNumbers($args)
	{
		$number1 = $args[0];
		$number2 = $args[1];
		return $number1 + $number2;
	}



	/**
	 * bbPress publishing API - Forum XML-RPC methods
	 */

	/**
	 * Returns a numerical count of forums
	 *
	 * This method does not require authentication
	 *
	 * @return integer|object The number of forums when successfully executed or an IXR_Error object on failure
	 * @param array $args Arguments passed by the XML-RPC call.
	 * @param integer|string $args[0] The parent forum's id or slug (optional).
	 * @param integer $args[1] is the depth of child forums to retrieve (optional).
	 *
	 * XML-RPC request to get a count of all forums in the bbPress instance
	 * <methodCall>
	 *     <methodName>bb.getForumCount</methodName>
	 *     <params></params>
	 * </methodCall>
	 *
	 * XML-RPC request to get a count of all child forums in the forum with id number 34
	 * <methodCall>
	 *     <methodName>bb.getForumCount</methodName>
	 *     <params>
	 *         <param><value><int>34</int></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get a count of all child forums in the forum with slug "first-forum"
	 * <methodCall>
	 *     <methodName>bb.getForumCount</methodName>
	 *     <params>
	 *         <param><value><string>first-forum</string></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get a count of all child forums in the forum with id number 34 no more than 2 forums deep in the hierarchy
	 * <methodCall>
	 *     <methodName>bb.getForumCount</methodName>
	 *     <params>
	 *         <param><value><int>34</int></value></param>
	 *         <param><value><int>2</int></value></param>
	 *     </params>
	 * </methodCall>
	 **/
	function bb_getForumCount($args)
	{
		do_action('bb_xmlrpc_call', 'bb.getForumCount');

		$this->escape($args);

		if (is_array($args)) {
			// Can be numeric id or slug - sanitised in get_forum()
			$forum_id = $args[0];

			// Can only be an integer
			$depth = (int) $args[1];
		} else {
			$forum_id = $args;
		}

		// Setup an array to store arguments to pass to get_forums() function
		$get_forums_args = array();

		if ($forum_id) {
			// First check the requested forum exists
			if (!$forum = get_forum($forum_id)) {
				return new IXR_Error(404, __('The requested parent forum does not exist.'));
			}
			// Add the specific forum to the arguments
			$get_forums_args['child_of'] = $forum->forum_id;
		}

		if ($depth) {
			// Add the depth to traverse to to the arguments
			$get_forums_args['depth'] = $depth;
			// Only make it hierarchical if the depth !== 1
			if ($depth === 1) {
				$get_forums_args['hierarchical'] = 0;
			} else {
				$get_forums_args['hierarchical'] = 1;
			}
		}

		// Get the forums
		$forums = (array) get_forums($get_forums_args);

		// Return an error when no forums exist
		if ( !$forums || ( isset($forums[0]) && count($forums[0]) === 1 ) ) {
			return new IXR_Error(404, __('No forums found.'));
		}

		// Return a count of the forums
		return count($forums);
	}

	/**
	 * Returns details of multiple forums
	 *
	 * This method does not require authentication
	 *
	 * @return array|object An array containing details of all returned forums when successfully executed or an IXR_Error object on failure
	 * @param array $args Arguments passed by the XML-RPC call.
	 * @param integer|string $args[0] The parent forum's id or slug (optional).
	 * @param integer $args[1] is the depth of child forums to retrieve (optional).
	 *
	 * XML-RPC request to get all forums in the bbPress instance
	 * <methodCall>
	 *     <methodName>bb.getForums</methodName>
	 *     <params></params>
	 * </methodCall>
	 *
	 * XML-RPC request to get all child forums in the forum with id number 34
	 * <methodCall>
	 *     <methodName>bb.getForums</methodName>
	 *     <params>
	 *         <param><value><int>34</int></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get all child forums in the forum with slug "first-forum"
	 * <methodCall>
	 *     <methodName>bb.getForums</methodName>
	 *     <params>
	 *         <param><value><string>first-forum</string></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get all child forums in the forum with id number 34 no more than 2 forums deep in the hierarchy
	 * <methodCall>
	 *     <methodName>bb.getForums</methodName>
	 *     <params>
	 *         <param><value><int>34</int></value></param>
	 *         <param><value><int>2</int></value></param>
	 *     </params>
	 * </methodCall>
	 **/
	function bb_getForums($args)
	{
		do_action('bb_xmlrpc_call', 'bb.getForums');

		$this->escape($args);

		if (is_array($args)) {
			// Can be numeric id or slug - sanitised in get_forum()
			$forum_id = $args[0];

			// Can only be an integer
			$depth = (int) $args[1];
		} else {
			$forum_id = $args;
		}

		// Setup an array to store arguments to pass to get_forums() function
		$get_forums_args = array();

		if ($forum_id) {
			// First check the requested forum exists
			if (!$forum = get_forum($forum_id)) {
				return new IXR_Error(404, __('The requested parent forum does not exist.'));
			}
			// Add the specific forum to the arguments
			$get_forums_args['child_of'] = $forum->forum_id;
		}

		if ($depth) {
			// Add the depth to traverse to to the arguments
			$get_forums_args['depth'] = $depth;
			// Only make it hierarchical if the depth !== 1
			if ($depth === 1) {
				$get_forums_args['hierarchical'] = 0;
			} else {
				$get_forums_args['hierarchical'] = 1;
			}
		}

		// Get the forums
		$forums = (array) get_forums($get_forums_args);

		// Return an error when no forums exist
		if ( !$forums || ( isset($forums[0]) && count($forums[0]) === 1 ) ) {
			return new IXR_Error(404, __('No forums found.'));
		} else {
			// Only include "safe" data in the array
			$_forums = array();
			foreach ($forums as $key => $forum) {
				if (!isset($forum->forum_is_category)) {
					$forum->forum_is_category = 0;
				}
				$_forums[$key] = array(
					'forum_id' =>          $forum->forum_id,
					'forum_name' =>        $forum->forum_name,
					'forum_slug' =>        $forum->forum_slug,
					'forum_desc' =>        $forum->forum_desc,
					'forum_parent' =>      $forum->forum_parent,
					'forum_order' =>       $forum->forum_order,
					'topics' =>            $forum->topics,
					'posts' =>             $forum->posts,
					'forum_is_category' => $forum->forum_is_category
				);
				// Allow plugins to add to the array
				$_forums[$key] = apply_filters('bb.getForums_sanitise', $_forums[$key], $key, $forum);
			}
		}

		// Return the forums
		return $_forums;
	}

	/**
	 * Returns details of a forum
	 *
	 * This method does not require authentication
	 *
	 * @return array|object An array containing details of the returned forum when successfully executed or an IXR_Error object on failure
	 * @param array $args The forum's id or slug.
	 *
	 * XML-RPC request to get the forum with id number 34
	 * <methodCall>
	 *     <methodName>bb.getForum</methodName>
	 *     <params>
	 *         <param><value><int>34</int></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get the forum with slug "first-forum"
	 * <methodCall>
	 *     <methodName>bb.getForum</methodName>
	 *     <params>
	 *         <param><value><string>first-forum</string></value></param>
	 *     </params>
	 * </methodCall>
	 **/
	function bb_getForum($args)
	{
		do_action('bb_xmlrpc_call', 'bb.getForum');

		$this->escape($args);

		// Don't accept arrays of arguments
		if (is_array($args)) {
			return new IXR_Error(404, __('The requested method only accepts one parameter.'));
		} else {
			// Can be numeric id or slug - sanitised in get_forum()
			$forum_id = $args;
		}

		// Check the requested forum exists
		if (!$forum_id || !$forum = get_forum($forum_id)) {
			return new IXR_Error(404, __('The requested forum does not exist.'));
		}

		// Make sure this is actually set
		if (!isset($forum->forum_is_category)) {
			$forum->forum_is_category = 0;
		}
		// Only include "safe" data in the array
		$_forum = array(
			'forum_id' =>          $forum->forum_id,
			'forum_name' =>        $forum->forum_name,
			'forum_slug' =>        $forum->forum_slug,
			'forum_desc' =>        $forum->forum_desc,
			'forum_parent' =>      $forum->forum_parent,
			'forum_order' =>       $forum->forum_order,
			'topics' =>            $forum->topics,
			'posts' =>             $forum->posts,
			'forum_is_category' => $forum->forum_is_category
		);
		// Allow plugins to add to the array
		$_forum = apply_filters('bb.getForum_sanitise', $_forum, $forum);

		// Return the forums
		return $_forum;
	}



	/**
	 * Pingback XML-RPC methods
	 */

	/**
	 * Processes pingback requests
	 *
	 * @link http://www.hixie.ch/specs/pingback/pingback
	 * @return string|object A message of success or an IXR_Error object on failure
	 * @param array $args Arguments passed by the XML-RPC call.
	 * @param string $args[0] The full URI of the post where the pingback is being sent from.
	 * @param string $args[1] The full URI of the post where the pingback is being sent to.
	 *
	 * XML-RPC request to register a pingback
	 * <methodCall>
	 *     <methodName>pingback.ping</methodName>
	 *     <params>
	 *         <param><value><string>http://example.org/2008/09/post-containing-a-link/</string></value></param>
	 *         <param><value><string>http://example.com/2008/08/post-being-linked-to/</string></value></param>
	 *     </params>
	 * </methodCall>
	 **/
	function pingback_ping($args)
	{
		do_action('bb_xmlrpc_call', 'pingback.ping');

		$this->escape($args);

		// No particular need to sanitise
		$link_from = $args[0];
		$link_to   = $args[1];

		// Tidy up ampersands in the URLs
		$link_from = str_replace('&amp;', '&', $link_from);
		$link_to   = str_replace('&amp;', '&', $link_to);
		$link_to   = str_replace('&', '&amp;', $link_to);

		// Check if the topic linked to is in our site - a little more strict than WordPress, doesn't pull out the www if added
		if ( !bb_match_domains( $link_to, bb_get_uri() ) ) {
			// These are not the droids you are looking for
			return new IXR_Error(0, __('This is not the site you are trying to pingback.'));
		}

		// Get the topic
		if ( $topic_to = bb_get_topic_from_uri($link_to) ) {
			// Topics shouldn't ping themselves
			if ( $topic_from = bb_get_topic_from_uri($link_from) ) {
				if ( $topic_from->topic_id === $topic_to->topic_id ) {
					return new IXR_Error(0, __('The source URL and the target URL cannot both point to the same resource.'));
				}
			}
		} else {
			return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}

		// Let's check that the remote site didn't already pingback this entry
		$query = new BB_Query( 'post', array('topic_id' => $topic_to->topic_id, 'append_meta' => true), 'get_thread' );
		$posts_to = $query->results;
		unset($query);

		// Make sure we have some posts in the topic, this error should never happen really
		if (!$posts_to || !is_array($posts_to) || !count($posts_to)) {
			return new IXR_Error(0, __('The specified target topic does not contain any posts.'));
		}

		// Check if we already have a pingback from this URL
		foreach ($posts_to as $post) {
			if (isset($post->pingback_uri) && trim($post->pingback_uri) === trim($link_from)) {
				return new IXR_Error(48, __('The pingback has already been registered.'));
			}
		}
		unset($posts_to, $post);

		// Give time for the server sending the pingback to finish publishing it's post.
		sleep(1);

		// Let's check the remote site for valid URL and content
		$link_from_source = wp_remote_fopen( $link_from );
		if ( !$link_from_source ) {
			return new IXR_Error(16, __('The source URL does not exist.'));
		}

		// Allow plugins to filter here
		$link_from_source = apply_filters('bb_pre_remote_source', $link_from_source, $link_to);

		// Work around bug in strip_tags()
		$link_from_source = str_replace('<!DOC', '<DOC', $link_from_source);

		// Normalize spaces
		$link_from_source = preg_replace( '/[\s\r\n\t]+/', ' ', $link_from_source );

		// Turn certain elements to double line returns
		$link_from_source = preg_replace( "/ <(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/", "\n\n", $link_from_source );

		// Find the title of the page
		preg_match('|<title>([^<]*?)</title>|is', $link_from_source, $link_from_title);
		$link_from_title = $link_from_title[1];
		if ( empty( $link_from_title ) ) {
			return new IXR_Error(32, __('We cannot find a title on that page.'));
		}

		// Strip out all tags except anchors
		$link_from_source = strip_tags( $link_from_source, '<a>' ); // just keep the tag we need

		// Split the source into paragraphs
		$link_from_paragraphs = explode( "\n\n", $link_from_source );

		// Prepare the link to search for in preg_match() once here
		$preg_target = preg_quote($link_to);

		// Loop through the paragraphs looking for the context for the url
		foreach ( $link_from_paragraphs as $link_from_paragraph ) {
			// The url exists
			if ( strpos($link_from_paragraph, $link_to) !== false ) {
				// But is it in an anchor tag
				preg_match(
					"|<a[^>]+?" . $preg_target . "[^>]*>([^>]+?)</a>|",
					$link_from_paragraph,
					$context
				);
				// If the URL isn't in an anchor tag, keep looking
				if ( empty($context) ) {
					continue;
				}

				// We're going to use this fake tag to mark the context in a bit
				// the marker is needed in case the link text appears more than once in the paragraph
				$excerpt = preg_replace('|\</?wpcontext\>|', '', $link_from_paragraph);

				// Prevent really long link text
				if ( strlen($context[1]) > 100 ) {
					$context[1] = substr($context[1], 0, 100) . '...';
				}

				// Set up the marker around the context
				$marker = '<wpcontext>' . $context[1] . '</wpcontext>';
				// Swap out the link for our marker
				$excerpt = str_replace($context[0], $marker, $excerpt);
				// Strip all tags except for our context marker
				$excerpt = trim(strip_tags($excerpt, '<wpcontext>'));
				// Make the marker safe for use in regexp
				$preg_marker = preg_quote($marker);
				// Reduce the excerpt to only include 100 characters on either side of the link
				$excerpt = preg_replace("|.*?\s(.{0,100}" . $preg_marker . "{0,100})\s.*|s", '$1', $excerpt);
				// Strip tags again, to remove the marker wrapper
				$excerpt = strip_tags($excerpt);
				break;
			}
		}

		 // Make sure the link to the target was found in the excerpt
		if ( empty($context) ) {
			return new IXR_Error(17, __('The source URL does not contain a link to the target URL, and so cannot be used as a source.'));
		}

		// Add whacky prefix and suffix to the excerpt and sanitize
		$excerpt = '[...] ' . wp_specialchars( $excerpt ) . ' [...]';
		$this->escape($excerpt);

		// Build an array of post data to insert then insert a new post
		$postdata = array(
			'topic_id' => $topic_to->topic_id,
			'post_text' => $excerpt,
			'poster_id' => 0,
		);
		if (!$post_ID = bb_insert_post($postdata)) {
			return new IXR_Error(0, __('The pingback could not be added.'));
		}

		// Add meta to let us know where the pingback came from
		$link_from = str_replace('&', '&amp;', $link_from);
		$this->escape($link_from);
		bb_update_postmeta($post_ID, 'pingback_uri', $link_from);

		// Add the title to meta
		$this->escape($link_from_title);
		bb_update_postmeta($post_ID, 'pingback_title', $link_from_title);

		// Action for plugins and what not
		do_action('bb_pingback_post', $post_ID);

		// Return success message, complete with emoticon
		return sprintf(__('Pingback from %1$s to %2$s registered. Keep the web talking! :-)'), $link_from, $link_to);
	}



	/**
	 * Returns an array of URLs that pingbacked the given URL
	 *
	 * @link http://www.aquarionics.com/misc/archives/blogite/0198.html
	 * @return array The array of URLs that pingbacked the given topic
	 * @param array $args Arguments passed by the XML-RPC call.
	 * @param string $args[0] The full URI of the post where the pingback is being sent from.
	 * @param string $args[1] The full URI of the post where the pingback is being sent to.
	 *
	 * XML-RPC request to get all pingbacks on a topic
	 * <methodCall>
	 *     <methodName>pingback.ping</methodName>
	 *     <params>
	 *         <param><value><string>http://example.com/2008/08/post-tobe-queried/</string></value></param>
	 *     </params>
	 * </methodCall>
	 **/
	function pingback_extensions_getPingbacks($args)
	{
		do_action('bb_xmlrpc_call', 'pingback.extensions.getPingbacks');

		$this->escape($args);

		// Don't accept arrays of arguments
		if (is_array($args)) {
			return new IXR_Error(404, __('The requested method only accepts one parameter.'));
		} else {
			$url = $args;
		}

		// Tidy up ampersands in the URI
		$url = str_replace('&amp;', '&', $url);
		$url = str_replace('&', '&amp;', $url);

		// Check if the URI is in our site
		if ( !bb_match_domains( $url, bb_get_uri() ) ) {
			// These are not the droids you are looking for
			return new IXR_Error(0, __('The specified target URL is not on this domain.'));
		}

		// Make sure the specified URI is in fact associated with a topic
		if ( !$topic = bb_get_topic_from_uri($url) ) {
			return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}

		// Grab the posts from the topic
		$query = new BB_Query( 'post', array('topic_id' => $topic_to->topic_id, 'append_meta' => true), 'get_thread' );
		$posts_to = $query->results;
		unset($query);

		// Check for pingbacks in the post meta data
		$pingbacks = array();
		foreach ($posts_to as $post) {
			if (isset($post->pingback_uri)) {
				$pingbacks[] = $post->pingback_uri;
			}
		}
		unset($post);

		// This will return an empty array on failure
		return $pingbacks;
	}
}



/**
 * Initialises the XML-RPC server
 *
 * @var object The instance of the XML-RPC server class
 **/
$bb_xmlrpc_server = new bb_xmlrpc_server();

?>
