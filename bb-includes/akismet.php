<?php
if ( !bb_get_option( 'akismet_key' ) )
	return; // Bail if no key.

$bb_ksd_api_host = bb_get_option( 'akismet_key' ) . '.rest.akismet.com';
$bb_ksd_api_port = 80;
$bb_ksd_user_agent = 'bbPress/' . bb_get_option( 'version' ) . ' | bbAkismet/'. bb_get_option( 'version' );

function bb_akismet_verify_key( $key ) {
	global $bb_ksd_pre_post_status, $bb_ksd_api_host, $bb_ksd_api_port;
	$blog = urlencode( bb_get_option('uri') );
	$response = bb_ksd_http_post("key=$key&blog=$blog", 'rest.akismet.com', '/1.1/verify-key', $bb_ksd_api_port);
	if ( 'valid' == $response[1] )
		return true;
	else
		return false;
}

// Returns array with headers in $response[0] and entity in $response[1]
function bb_ksd_http_post($request, $host, $path, $port = 80) {
	global $bb_ksd_user_agent;

	$http_request  = "POST $path HTTP/1.0\r\n";
	$http_request .= "Host: $host\r\n";
	$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n"; // for now
	$http_request .= "Content-Length: " . strlen($request) . "\r\n";
	$http_request .= "User-Agent: $bb_ksd_user_agent\r\n";
	$http_request .= "\r\n";
	$http_request .= $request;
	$response = '';
	if( false != ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
		fwrite($fs, $http_request);

		while ( !feof($fs) )
			$response .= fgets($fs, 1160); // One TCP-IP packet
		fclose($fs);
		$response = explode("\r\n\r\n", $response, 2);
	}
	return $response;
}

function bb_ksd_submit( $submit, $type = false ) {
	global $bb_ksd_api_host, $bb_ksd_api_port;

	switch ( $type ) :
	case 'ham' :
	case 'spam' :
		$path = "/1.1/submit-$type";

		$bb_post = bb_get_post( $submit );
		if ( !$bb_post )
			return;
		$user = bb_get_user( $bb_post->poster_id );
		if ( bb_is_trusted_user( $user->ID ) )
			return;

		$_submit = array(
			'blog' => bb_get_option('uri'),
			'user_ip' => $bb_post->poster_ip,
			'permalink' => get_topic_link( $bb_post->topic_id ), // First page
			'comment_type' => 'forum',
			'comment_author' => get_user_name( $user->ID ),
			'comment_author_email' =>  bb_get_user_email( $user->ID ),
			'comment_author_url' => get_user_link( $user->ID ),
			'comment_content' => $bb_post->post_text,
			'comment_date_gmt' => $bb_post->post_time
		);
		break;
	case 'hammer' :
	case 'spammer' :
		$path = '/1.1/submit-' . substr($type, 0, -3);

		$user = bb_get_user( $submit );
		if ( !$user )
			return;
		if ( bb_is_trusted_user( $user->ID ) )
			return;

		$_submit = array(
			'blog' => bb_get_option('uri'),
			'permalink' => get_user_profile_link( $user->ID ),
			'comment_type' => 'profile',
			'comment_author' => get_user_name( $user->ID ),
			'comment_author_email' =>  bb_get_user_email( $user->ID ),
			'comment_author_url' => get_user_link( $user->ID ),
			'comment_content' => $user->occ . ' ' . $user->interests,
			'comment_date_gmt' => $user->user_registered
		);
		break;
	default :
		if ( bb_is_trusted_user( bb_get_current_user() ) )
			return;

		$path = '/1.1/comment-check';

		$_submit = array(
			'blog' => bb_get_option('uri'),
			'user_ip' => preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] ),
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'referrer' => $_SERVER['HTTP_REFERER'],
			'comment_type' => isset($_POST['topic_id']) ? 'forum' : 'profile',
			'comment_author' => bb_get_current_user_info( 'name' ),
			'comment_author_email' => bb_get_current_user_info( 'email' ),
			'comment_author_url' => bb_get_current_user_info( 'url' ),
			'comment_content' => $submit
		);
		if ( isset($_POST['topic_id']) )
			$_submit['permalink'] = get_topic_link( $_POST['topic_id'] ); // First page
		break;
	endswitch;

	$query_string = '';
	foreach ( $_submit as $key => $data )
		$query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';
	return bb_ksd_http_post($query_string, $bb_ksd_api_host, $path, $bb_ksd_api_port);
}

function bb_ksd_submit_ham( $post_id ) {
	bb_ksd_submit( $post_id, 'ham' );
}

function bb_ksd_submit_spam( $post_id ) {
	bb_ksd_submit( $post_id, 'spam' );
}

function bb_ksd_check_post( $post_text ) {
	global $bb_current_user, $bb_ksd_pre_post_status;
	if ( in_array($bb_current_user->roles[0], bb_trusted_roles()) ) // Don't filter content from users with a trusted role
		return $post_text;

	$response = bb_ksd_submit( $post_text );
	if ( 'true' == $response[1] )
		$bb_ksd_pre_post_status = '2';
	bb_akismet_delete_old();
	return $post_text;
}

function bb_ksd_check_profile( $user_id ) {
	global $bb_current_user, $user_obj;
	$bb_current_id = bb_get_current_user_info( 'id' );
	bb_set_current_user( $user_id );
	if ( $bb_current_id && $bb_current_id != $user_id ) {
		if ( $user_obj->data->is_bozo && !$bb_current_user->data->is_bozo )
			bb_ksd_submit( $user_id, 'hammer' );
		if ( !$user_obj->data->is_bozo && $bb_current_user->data->is_bozo )
			bb_ksd_submit( $user_id, 'spammer' );
	} else {
		$response = bb_ksd_submit( $bb_current_user->data->occ . ' ' . $bb_current_user->data->interests );
		if ( 'true' == $response[1] )
			bb_bozon( bb_get_current_user_info( 'id' ) );
	}
	bb_set_current_user((int) $bb_current_id);
}

function bb_ksd_new_post( $post_id ) {
	global $bb_ksd_pre_post_status;
	if ( '2' != $bb_ksd_pre_post_status )
		return;
	$bb_post = bb_get_post( $post_id );
	$topic = get_topic( $bb_post->topic_id );
	if ( 0 == $topic->topic_posts )
		bb_delete_topic( $topic->topic_id, 2 );
}

function bb_akismet_delete_old() { // Delete old every 20
	$n = mt_rand(1, 20);
	if ( $n % 20 )
		return;
	global $bbdb;
	$now = bb_current_time('mysql');
	$posts = (array) $bbdb->get_col("SELECT post_id FROM $bbdb->posts WHERE DATE_SUB('$now', INTERVAL 15 DAY) > post_time AND post_status = '2'");
	foreach ( $posts as $post )
		bb_delete_post( $post, 1 );
}

function bb_ksd_pre_post_status( $post_status ) {
	global $bb_ksd_pre_post_status;
	if ( '2' == $bb_ksd_pre_post_status )
		$post_status = $bb_ksd_pre_post_status;
	return $post_status;
}

function bb_ksd_admin_menu() {
	global $bb_submenu;
	$bb_submenu['content.php'][] = array(__('Akismet Spam'), 'moderate', 'bb_ksd_admin_page');
}

function bb_ksd_delete_post( $post_id, $new_status, $old_status ) {
	if ( 2 == $new_status && 2 != $old_status )
		bb_ksd_submit_spam( $post_id );
	else if ( 2 != $new_status && 2 == $old_status )
		bb_ksd_submit_ham( $post_id );
}

function bb_ksd_admin_page() {
	global $bb_current_submenu, $bb_posts, $page;
	if ( !bb_akismet_verify_key( bb_get_option( 'akismet_key' ) ) ) : ?>
<div class="error"><p><?php printf(__('The API key you have specified is invalid.  Please double check the <code>$bb->akismet_key</code> variable in your <code>config.php file</code>.  If you don\'t have an API key yet, you can get one at <a href="%s">WordPress.com</a>.'), 'http://wordpress.com/api-keys/'); ?></p></div>
<?php	endif;

	if ( !bb_current_user_can('browse_deleted') )
		die(__("Now how'd you get here?  And what did you think you'd being doing?"));
	add_filter( 'get_topic_where', 'no_where' );
	add_filter( 'get_topic_link', 'bb_make_link_view_all' );
	$post_query = new BB_Query( 'post', array( 'post_status' => 2 ) );
	$bb_posts = $post_query->results;
	$total = $post_query->found_rows;
 ?>
<ol id="the-list">
<?php bb_admin_list_posts(); ?>
</ol>
<?php
	echo get_page_number_links( $page, $total );
}

function bb_ksd_post_delete_link($link, $post_status) {
	if ( !bb_current_user_can('moderate') )
		return $link;
	if ( 2 == $post_status )
		$link .= " <a href='" . attribute_escape( bb_nonce_url( bb_get_option('uri') . 'bb-admin/delete-post.php?id=' . get_post_id() . '&status=0&view=all', 'delete-post_' . get_post_id() ) ) . "' >" . __('Not Spam') ."</a>";
	else
		$link .= " <a href='" . attribute_escape( bb_nonce_url( bb_get_option('uri') . 'bb-admin/delete-post.php?id=' . get_post_id() . '&status=2', 'delete-post_' . get_post_id() ) ) . "' >" . __('Spam') ."</a>";
	return $link;
}

add_action( 'pre_post', 'bb_ksd_check_post', 1 );
add_filter( 'bb_new_post', 'bb_ksd_new_post' );
add_filter( 'pre_post_status', 'bb_ksd_pre_post_status' );
add_action( 'register_user', 'bb_ksd_check_profile', 1);
add_action( 'profile_edited', 'bb_ksd_check_profile', 1);
add_action( 'bb_admin_menu_generator', 'bb_ksd_admin_menu' );
add_action( 'bb_delete_post', 'bb_ksd_delete_post', 10, 3);
add_filter( 'post_delete_link', 'bb_ksd_post_delete_link', 10, 2 );
?>
