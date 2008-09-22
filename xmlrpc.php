<?php
/**
 * XML-RPC protocol support for bbPress
 *
 * @package bbPress
 */

/**
 * Whether this is a XMLRPC Request
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
if ( isset($HTTP_RAW_POST_DATA) )
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);

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
			<api name="bbPress" blogID="1" preferred="true" apiLink="<?php bb_uri('xmlrpc.php') ?>" />
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
 * Whether to enable XMLRPC Logging.
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
function bb_logIO($io, $msg) {
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

if ( isset($HTTP_RAW_POST_DATA) )
	bb_logIO("I", $HTTP_RAW_POST_DATA);

/**
 * @internal
 * Left undocumented to work on later. If you want to finish, then please do so.
 *
 * @package WordPress
 * @subpackage Publishing
 */
class bb_xmlrpc_server extends IXR_Server {

	function bb_xmlrpc_server() {
		$this->methods = array(
			// Demo
			'demo.sayHello' => 'this:sayHello',
			'demo.addTwoNumbers' => 'this:addTwoNumbers'
		);

		// bbPress API
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

		// PingBack
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

	function sayHello($args) {
		return 'Hello!';
	}

	function addTwoNumbers($args) {
		$number1 = $args[0];
		$number2 = $args[1];
		return $number1 + $number2;
	}

	/*
	function login_pass_ok($user_login, $user_pass) {
		if (!user_pass_ok($user_login, $user_pass)) {
			$this->error = new IXR_Error(403, __('Bad login/pass combination.'));
			return false;
		}
		return true;
	}
	*/

	function escape(&$array) {
		global $bbdb;

		if(!is_array($array)) {
			return($bbdb->escape($array));
		}
		else {
			foreach ( (array) $array as $k => $v ) {
				if (is_array($v)) {
					$this->escape($array[$k]);
				} else if (is_object($v)) {
					//skip
				} else {
					$array[$k] = $bbdb->escape($v);
				}
			}
		}
	}

	/*
	function get_custom_fields($post_id) {
		$post_id = (int) $post_id;

		$custom_fields = array();

		foreach ( (array) has_meta($post_id) as $meta ) {
			// Don't expose protected fields.
			if ( strpos($meta['meta_key'], '_wp_') === 0 ) {
				continue;
			}

			$custom_fields[] = array(
				"id"    => $meta['meta_id'],
				"key"   => $meta['meta_key'],
				"value" => $meta['meta_value']
			);
		}

		return $custom_fields;
	}

	function set_custom_fields($post_id, $fields) {
		$post_id = (int) $post_id;

		foreach ( (array) $fields as $meta ) {
			if ( isset($meta['id']) ) {
				$meta['id'] = (int) $meta['id'];

				if ( isset($meta['key']) ) {
					update_meta($meta['id'], $meta['key'], $meta['value']);
				}
				else {
					delete_meta($meta['id']);
				}
			}
			else {
				$_POST['metakeyinput'] = $meta['key'];
				$_POST['metavalue'] = $meta['value'];
				add_meta($post_id);
			}
		}
	}

	function initialise_site_option_info( ) {
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
	 * @uses class IXR_Error
	 * @uses function get_forum
	 * @uses function get_forums
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
	 *         <param><value><string>34</string></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get a count of all child forums in the forum with id number 34 no more than 2 forums deep in the hierarchy
	 * <methodCall>
	 *     <methodName>bb.getForumCount</methodName>
	 *     <params>
	 *         <param><value><string>34</string></value></param>
	 *         <param><value><string>2</string></value></param>
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
			if (!get_forum($forum_id)) {
				return new IXR_Error(404, __('The requested parent forum does not exist.'));
			}
			// Add the specific forum to the arguments
			$get_forums_args['child_of'] = $forum_id;
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

		// Return a count of 0 when no forums exist rather than an error
		if (!$forums) {
			return 0;
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
	 * @uses class IXR_Error
	 * @uses function get_forum
	 * @uses function get_forums
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
	 *         <param><value><string>34</string></value></param>
	 *     </params>
	 * </methodCall>
	 *
	 * XML-RPC request to get all child forums in the forum with id number 34 no more than 2 forums deep in the hierarchy
	 * <methodCall>
	 *     <methodName>bb.getForums</methodName>
	 *     <params>
	 *         <param><value><string>34</string></value></param>
	 *         <param><value><string>2</string></value></param>
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
			if (!get_forum($forum_id)) {
				return new IXR_Error(404, __('The requested parent forum does not exist.'));
			}
			// Add the specific forum to the arguments
			$get_forums_args['child_of'] = $forum_id;
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

		// Return an empty array when no forums exist rather than an error
		if (!$forums) {
			return array();
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
	 * @uses class IXR_Error
	 * @uses function get_forum
	 *
	 * XML-RPC request to get the forum with id number 34
	 * <methodCall>
	 *     <methodName>bb.getForum</methodName>
	 *     <params>
	 *         <param><value><string>34</string></value></param>
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
	 * PingBack functions
	 * specs on www.hixie.ch/specs/pingback/pingback
	 **/

	/* pingback.ping gets a pingback and registers it */
	function pingback_ping($args) {
		do_action('bb_xmlrpc_call', 'pingback.ping');

		$this->escape($args);

		$pagelinkedfrom = $args[0];
		$pagelinkedto   = $args[1];

		$title = '';

		$pagelinkedfrom = str_replace('&amp;', '&', $pagelinkedfrom);
		$pagelinkedto = str_replace('&amp;', '&', $pagelinkedto);
		$pagelinkedto = str_replace('&', '&amp;', $pagelinkedto);

		// Check if the topic linked to is in our site
		$pos1 = strpos($pagelinkedto, str_replace(array('http://www.','http://','https://www.','https://'), '', bb_get_uri()));
		if( !$pos1 )
			return new IXR_Error(0, __('Is there no link to us?'));

		// Get the topic
		if ( $topic_to = bb_get_topic_from_uri($pagelinkedto) ) {
			// Topics shouldn't ping themselves
			if ( $topic_from = bb_get_topic_from_uri($pagelinkedfrom) ) {
				if ( $topic_from->topic_id === $topic_to->topic_id ) {
					return new IXR_Error(0, __('The source URL and the target URL cannot both point to the same resource.'));
				}
			}
		} else {
			return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}

		bb_logIO("O","(PB) URL='$pagelinkedto' ID='$topic_to->topic_id'");

		// Let's check that the remote site didn't already pingback this entry
		$query = new BB_Query( 'post', array('topic_id' => $topic_to->topic_id, 'append_meta' => true), 'get_thread' );
		$posts_to = $query->results;
		unset($query);

		// Check if we already have a Pingback from this URL
		foreach ($posts_to as $post)
			if (isset($post->pingback_uri) && trim($post->pingback_uri) === trim($pagelinkedfrom))
				return new IXR_Error(48, __('The pingback has already been registered.'));
		unset($post);

		// very stupid, but gives time to the 'from' server to publish !
		sleep(1);

		// Let's check the remote site
		$linea = wp_remote_fopen( $pagelinkedfrom );
		if ( !$linea )
	  		return new IXR_Error(16, __('The source URL does not exist.'));

		$linea = apply_filters('bb_pre_remote_source', $linea, $pagelinkedto);

		// Work around bug in strip_tags():
		$linea = str_replace('<!DOC', '<DOC', $linea);
		$linea = preg_replace( '/[\s\r\n\t]+/', ' ', $linea ); // normalize spaces
		$linea = preg_replace( "/ <(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/", "\n\n", $linea );

		preg_match('|<title>([^<]*?)</title>|is', $linea, $matchtitle);
		$title = $matchtitle[1];
		if ( empty( $title ) )
			return new IXR_Error(32, __('We cannot find a title on that page.'));

		$linea = strip_tags( $linea, '<a>' ); // just keep the tag we need

		$p = explode( "\n\n", $linea );

		$preg_target = preg_quote($pagelinkedto);

		foreach ( $p as $para ) {
			if ( strpos($para, $pagelinkedto) !== false ) { // it exists, but is it a link?
				preg_match("|<a[^>]+?".$preg_target."[^>]*>([^>]+?)</a>|", $para, $context);

				// If the URL isn't in a link context, keep looking
				if ( empty($context) )
					continue;

				// We're going to use this fake tag to mark the context in a bit
				// the marker is needed in case the link text appears more than once in the paragraph
				$excerpt = preg_replace('|\</?wpcontext\>|', '', $para);

				// prevent really long link text
				if ( strlen($context[1]) > 100 )
					$context[1] = substr($context[1], 0, 100) . '...';

				$marker = '<wpcontext>'.$context[1].'</wpcontext>';    // set up our marker
				$excerpt= str_replace($context[0], $marker, $excerpt); // swap out the link for our marker
				$excerpt = strip_tags($excerpt, '<wpcontext>');        // strip all tags but our context marker
				$excerpt = trim($excerpt);
				$preg_marker = preg_quote($marker);
				$excerpt = preg_replace("|.*?\s(.{0,100}$preg_marker.{0,100})\s.*|s", '$1', $excerpt);
				$excerpt = strip_tags($excerpt); // YES, again, to remove the marker wrapper
				break;
			}
		}

		if ( empty($context) ) // Link to target not found
			return new IXR_Error(17, __('The source URL does not contain a link to the target URL, and so cannot be used as a source.'));

		$excerpt = '[...] ' . wp_specialchars( $excerpt ) . ' [...]';
		$this->escape($excerpt);

		$postdata = array(
			'topic_id' => $topic_to->topic_id,
			'post_text' => $excerpt,
			'poster_id' => 0,
		);
		$post_ID = bb_insert_post($postdata);

		// Post meta data
		$pagelinkedfrom = str_replace('&', '&amp;', $pagelinkedfrom);
		$this->escape($pagelinkedfrom);
		bb_update_postmeta($post_ID, 'pingback_uri', $pagelinkedfrom);
		$this->escape($title);
		bb_update_postmeta($post_ID, 'pingback_title', $title);

		do_action('bb_pingback_post', $post_ID);

		return sprintf(__('Pingback from %1$s to %2$s registered. Keep the web talking! :-)'), $pagelinkedfrom, $pagelinkedto);
	}

	/* pingback.extensions.getPingbacks returns an array of URLs
	that pingbacked the given URL
	specs on http://www.aquarionics.com/misc/archives/blogite/0198.html */
	function pingback_extensions_getPingbacks($args) {
		do_action('bb_xmlrpc_call', 'pingback.extensions.getPingbacks');

		$this->escape($args);
		$url = $args;

		if ( !$topic = bb_get_topic_from_uri($url) )
			return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));

		// Grab the posts
		$query = new BB_Query( 'post', array('topic_id' => $topic_to->topic_id, 'append_meta' => true), 'get_thread' );
		$posts_to = $query->results;
		unset($query);

		// Check for Pingbacks
		$pingbacks = array();
		foreach ($posts_to as $post)
			if (isset($post->pingback_uri))
				$pingbacks[] = $post->pingback_uri;
		unset($post);

		return $pingbacks;
	}
}

$bb_xmlrpc_server = new bb_xmlrpc_server();

?>
