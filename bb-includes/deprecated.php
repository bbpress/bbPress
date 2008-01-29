<?php
function bb_specialchars( $text, $quotes = 0 ) {
	return wp_specialchars( $text, $quotes );
}

function bb_make_clickable( $ret ) {
	return make_clickable( $ret );
}

function bb_apply_filters($tag, $string, $filter = true) {
	$args = func_get_args();
	return call_user_func_array('apply_filters', $args);
}

function bb_add_filter($tag, $function_to_add, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('add_filter', $args);
}

function bb_remove_filter($tag, $function_to_remove, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('remove_filter', $args);
}

function bb_do_action($tag) {
	$args = func_get_args();
	return call_user_func_array('do_action', $args);
}

function bb_add_action($tag, $function_to_add, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('add_action', $args);
}

function bb_remove_action($tag, $function_to_remove, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('remove_action', $args);
}

function bb_add_query_arg() {
	$args = func_get_args();
	return call_user_func_array('add_query_arg', $args);
}

function bb_remove_query_arg($key, $query = '') {
	return remove_query_arg($key, $query);
}

if ( !function_exists('language_attributes') ) :
function language_attributes( $xhtml = 0 ) {
	bb_language_attributes( $xhtml );
}
endif;

function cast_meta_value( $data ) {
	return bb_maybe_unserialize( $data );
}

function option( $option ) {
	return bb_option( $option );
}

// Use topic_time
function topic_date( $format = '', $id = 0 ) {
	echo bb_gmdate_i18n( $format, get_topic_timestamp( $id ) );
}
function get_topic_date( $format = '', $id = 0 ){
	return bb_gmdate_i18n( $format, get_topic_timestamp( $id ) );
}
function get_topic_timestamp( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return bb_gmtstrtotime( $topic->topic_time );
}

// Use topic_start_time
function topic_start_date( $format = '', $id = 0 ) {
	echo bb_gmdate_i18n( $format, get_topic_start_timestamp( $id ) );
}
function get_topic_start_timestamp( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return bb_gmtstrtotime( $topic->topic_start_time );
}

// Use bb_post_time
function post_date( $format ) {
	echo bb_gmdate_i18n( $format, get_post_timestamp() );
}
function get_post_timestamp() {
	global $bb_post;
	return bb_gmtstrtotime( $bb_post->post_time );
}

function get_inception() {
	return bb_get_inception( 'timestamp' );
}

function forum_dropdown( $c = false, $a = false ) {
	bb_forum_dropdown( $c, $a );
}

function get_ids_by_role( $role = 'moderator', $sort = 0, $limit_str = '' ) {
	return bb_get_ids_by_role( $role , $sort , $limit_str);
}

function get_deleted_posts( $page = 1, $limit = false, $status = 1, $topic_status = 0 ) {
	return get_deleted_posts( $page , $limit , $status , $topic_status );
}

function bozo_posts( $where ) {
	return bb_bozo_posts( $where );
}

function bozo_topics( $where ) {
	return bb_bozo_topics( $where );
}

function get_bozos( $page = 1 ) {
	return bb_get_bozos($page);
}

function current_user_is_bozo( $topic_id = false ) {
	return bb_current_user_is_bozo( $topic_id );
}

function bozo_pre_permalink() {
	return bb_bozo_pre_permalink();
}

function bozo_latest_filter() {
	return bb_bozo_latest_filter();
}

function bozo_topic_db_filter() {
	return bb_bozo_topic_db_filter();
}

function bozo_profile_db_filter() {
	return bb_bozo_profile_db_filter();
}

function bozo_recount_topics() {
	return bb_bozo_recount_topics();
}

function bozo_recount_users() {
	return bb_bozo_recount_users();
}

function bozo_post_del_class( $status ) {
	return bb_bozo_post_del_class( $status );
}

function bozo_add_recount_list() {
	return bb_bozo_add_recount_list() ;
}

function bozo_topic_pages_add( $add ) {
	return bb_bozo_topic_pages_add( $add );
}

function bozo_get_topic_posts( $topic_posts ) {
	return bb_bozo_get_topic_posts( $topic_posts ) ;
}

function bozo_new_post( $post_id ) {
	return bb_bozo_new_post( $post_id );
}

function bozo_pre_post_status( $status, $post_id, $topic_id ) {
	return bb_bozo_pre_post_status( $status, $post_id, $topic_id ) ;
}

function bozo_delete_post( $post_id, $new_status, $old_status ) {
	return bb_bozo_delete_post( $post_id, $new_status, $old_status ) ;
}

function bozon( $user_id, $topic_id = 0 ) {
	return bb_bozon( $user_id, $topic_id ) ;
}

function fermion( $user_id, $topic_id = 0 ) {
	return bb_fermion( $user_id, $topic_id ) ;
}

function bozo_profile_admin_keys( $a ) {
	return bb_bozo_profile_admin_keys( $a ) ;
}
function bozo_add_admin_page() {
	return bb_bozo_add_admin_page() ;
}

function bozo_admin_page() {
	return bb_bozo_admin_page() ;
}

function encodeit( $matches ) {
	return bb_encodeit( $matches ) ;
}

function decodeit( $matches ) {
	return bb_decodeit( $matches ) ;
}

function code_trick( $text ) {
	return bb_code_trick( $text ) ;
}

function code_trick_reverse( $text ) {
	return bb_code_trick_reverse( $text ) ;
}

function encode_bad( $text ) {
	return bb_encode_bad( $text ) ;
}

function user_sanitize( $text, $strict = false ) {
	return sanitize_user( $text, $strict );
}

function bb_user_sanitize( $text, $strict = false ) {
	return sanitize_user( $text, $strict );
}

function utf8_cut( $utf8_string, $length ) {
	return bb_utf8_cut( $utf8_string, $length ) ;
}

function tag_sanitize( $tag ) {
	return bb_tag_sanitize( $tag ) ;
}

function sanitize_with_dashes( $text, $length = 200 ) { // Multibyte aware
	return bb_sanitize_with_dashes( $text, $length ) ;
}

function bb_make_feed( $link ) {
	return trim( $link );
}

function show_context( $term, $text ) {
	return bb_show_context( $term, $text );
}

function closed_title( $title ) {
	return bb_closed_label( $title );
}

// Closed label now applied using bb_topic_labels filters
function bb_closed_title( $title ) {
	return bb_closed_label( $title );
}

function make_link_view_all( $link ) {
	return bb_make_link_view_all( $link );
}

function remove_topic_tag( $tag_id, $user_id, $topic_id ) {
	return bb_remove_topic_tag( $tag_id, $user_id, $topic_id );
}

function add_topic_tag( $topic_id, $tag ) {
	return bb_add_topic_tag( $topic_id, $tag );
}

function add_topic_tags( $topic_id, $tags ) {
	return bb_add_topic_tags( $topic_id, $tags );
}

function create_tag( $tag ) {
	return bb_create_tag( $tag );
}

function destroy_tag( $tag_id, $recount_topics = true ) {
	return bb_destroy_tag( $tag_id, $recount_topics );
}

if ( !function_exists( 'get_tag_id' ) ) :
function get_tag_id( $tag ) {
	return bb_get_tag_id( $tag );
}
endif;

if ( !function_exists( 'get_tag' ) ) :
function get_tag( $tag_id, $user_id = 0, $topic_id = 0 ) {
	return bb_get_tag( $tag_id, $user_id, $topic_id );
}
endif;

if ( !function_exists( 'get_tag_by_name' ) ) :
function get_tag_by_name( $tag ) {
	return bb_get_tag_by_name( $tag );
}
endif;

function get_topic_tags( $topic_id ) {
	return bb_get_topic_tags( $topic_id );
}

function get_user_tags( $topic_id, $user_id ) {
	return bb_get_user_tags( $topic_id, $user_id );
}

function get_other_tags( $topic_id, $user_id ) {
	return bb_get_other_tags( $topic_id, $user_id );
}

function get_public_tags( $topic_id ) {
	return bb_get_public_tags( $topic_id );
}

function get_tagged_topic_ids( $tag_id ) {
	return bb_get_tagged_topic_ids( $tag_id );
}

if ( !function_exists( 'get_top_tags' ) ) :
function get_top_tags( $recent = true, $limit = 40 ) {
	return bb_get_top_tags( $recent, $limit );
}
endif;

if ( !function_exists( 'get_tag_name' ) ) :
function get_tag_name( $id = 0 ) {
	return bb_get_tag_name( $id );
}
endif;

if ( !function_exists( 'tag_name' ) ) :
function tag_name( $id = 0 ) {
	bb_tag_name( $id );
}
endif;

if ( !function_exists( 'get_tag_rss_link' ) ) :
function get_tag_rss_link( $id = 0 ) {
	return bb_get_tag_rss_link( $id );
}
endif;

if ( !function_exists( 'tag_rss_link' ) ) :
function tag_rss_link( $id = 0 ) {
	bb_tag_rss_link( $id );
}
endif;

if ( !function_exists( 'get_tag_page_link' ) ) :
function get_tag_page_link() {
	bb_get_tag_page_link();
}
endif;

if ( !function_exists( 'tag_page_link' ) ) :
function tag_page_link() {
	bb_tag_page_link();
}
endif;

if ( !function_exists( 'get_tag_remove_link' ) ) :
function get_tag_remove_link() {
	bb_get_tag_remove_link();
}
endif;

if ( !function_exists( 'tag_remove_link' ) ) :
function tag_remove_link() {
	bb_tag_remove_link();
}
endif;

if ( !function_exists( 'tag_heat_map' ) ) :
function tag_heat_map( $args = '' ) {
	$defaults = array( 'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'limit' => 45, 'format' => 'flat' );
	$args = wp_parse_args( $args, $defaults );

	if ( 1 < $fn = func_num_args() ) : // For back compat
		$args['smallest'] = func_get_arg(0);
		$args['largest']  = func_get_arg(1);
		$args['unit']     = 2 < $fn ? func_get_arg(2) : $unit;
		$args['limit']    = 3 < $fn ? func_get_arg(3) : $limit;
	endif;

	bb_tag_heat_map( $args );
}
endif;

function get_bb_location() {
	$r = bb_get_location();
	if ( !$r )
		$r = apply_filters( 'get_bb_location', '' ); // Deprecated filter
	return $r;
}

function bb_parse_args( $args, $defaults = '' ) {
	return wp_parse_args( $args, $defaults );
}

if ( !function_exists( 'is_tag' ) ) :
function is_tag() {
	return is_bb_tag();
}
endif;

if ( !function_exists( 'is_tags' ) ) :
function is_tags() {
	return is_bb_tags();
}
endif;

if ( !function_exists( 'tag_link' ) ) :
function tag_link() {
	bb_tag_link();
}
endif;

if ( !function_exists( 'tag_link_base' ) ) :
function tag_link_base() {
	bb_tag_link_base();
}
endif;

if ( !function_exists( 'get_tag_link' ) ) :
function get_tag_link() {
	bb_get_tag_link();
}
endif;

if ( !function_exists( 'get_tag_link_base' ) ) :
function get_tag_link_base() {
	bb_get_tag_link_base();
}
endif;

// It's not omnipotent
function bb_path_to_url( $path ) {
	return apply_filters( 'bb_path_to_url', bb_convert_path_base( $path, BBPATH, bb_get_option( 'uri' ) ), $path );
}

// Neither is this one
function bb_url_to_path( $url ) {
	return apply_filters( 'bb_url_to_path', bb_convert_path_base( $url, bb_get_option( 'uri' ), BBPATH ), $url );
}

function bb_convert_path_base( $path, $from_base, $to_base ) {
	$last_char = $path{strlen($path)-1};
	if ( '/' != $last_char && '\\' != $last_char )
		$last_char = '';

	list($from_base, $to_base) = bb_trim_common_path_right($from_base, $to_base);

	if ( 0 === strpos( $path, $from_base ) )
		$r = $to_base . substr($path, strlen($from_base)) . $last_char;
	else
		return false;

	$r = str_replace(array('//', '\\\\'), array('/', '\\'), $r);
	$r = preg_replace('|:/([^/])|', '://$1', $r);

	return $r;
}

function bb_trim_common_path_right( $one, $two ) {
	$root_one = false;
	$root_two = false;

	while ( false === $root_one ) {
		$base_one = basename($one);
		$base_two = basename($two);
		if ( !$base_one || !$base_two )
			break;		
		if ( $base_one == $base_two ) {
			$one = dirname($one);
			$two = dirname($two);
		} else {
			$root_one = $one;
			$root_two = $two;
		}
	}

	return array($root_one, $root_two);
}

function deleted_topics( $where ) {
	return preg_replace( '/(\w+\.)?topic_status = ["\']?0["\']?/', "\\1topic_status = 1", $where);
}

function no_replies( $where ) {
	return $where . ' AND topic_posts = 1 ';
}

function untagged( $where ) {
	return $where . ' AND tag_count = 0 ';
}

function get_views() {
	return bb_get_views();
}

if ( !function_exists( 'balanceTags' ) ) :
function balanceTags( $text ) {
	return force_balance_tags( $text );
}
endif;

// With no extra arguments, converts array of objects into object of arrays
// With extra arguments corresponding to name of object properties, returns array of arrays:
//     list($a, $b) = bb_pull_cols( $obj_array, 'a', 'b' );
function bb_pull_cols( $obj_array ) {
	$r = new stdClass;
	foreach ( array_keys($obj_array) as $o )
		foreach ( get_object_vars( $obj_array[$o] ) as $k => $v )
			$r->{$k}[] = $v;

	if ( 1 == func_num_args() )
		return $r;

	$args = func_get_args();
	$args = array_splice($args, 1);

	$a = array();
	foreach ( $args as $arg )
		$a[] = $r->$arg;
	return $a;
}

// $length parameter is deprecated
function bb_random_pass( $length ) {
	return wp_generate_password();
}

// Old RSS related functions
function get_recent_rss_link() {
	return bb_get_posts_rss_link();
}

function forum_rss_link( $forum_id = 0 ) {
	echo bb_get_forum_posts_rss_link( $forum_id );
}

function get_forum_rss_link( $forum_id = 0 ) {
	return bb_get_forum_posts_rss_link( $forum_id );
}

?>
