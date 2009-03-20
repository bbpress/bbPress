<?php

function bb_default_scripts( &$scripts ) {
	$scripts->base_url = bb_get_uri(BB_INC, null, BB_URI_CONTEXT_SCRIPT_SRC);
	$scripts->base_url_admin = bb_get_uri('bb-admin/', null, BB_URI_CONTEXT_SCRIPT_SRC + BB_URI_CONTEXT_BB_ADMIN);
	$scripts->content_url = ''; // May not work - might need to specify plugin and theme urls
	$scripts->default_version = bb_get_option( 'version' );
	$scripts->default_dirs = array('/bb-admin/js/', '/bb-includes/js/');

	// These are our enqueued scripts
	$scripts->add( 'topic', $scripts->base_url . 'js/topic.js', array('wp-lists'), '20080506' );
	$scripts->add( 'profile-edit', $scripts->base_url . 'js/profile-edit.js', array('password-strength-meter'), '20080721' );
	$scripts->add( 'content-forums', $scripts->base_url_admin . 'js/content-forums.js', array('wp-lists', 'interface'), '20090320' );
	$scripts->localize( 'content-forums', 'bbSortForumsL10n', array(
		'handleText' => __('drag'),
		'saveText'   => __('Save Forum Order &#187;'),
		'editText'   => __('Edit Forum Order &#187;')
	));

	// These are non-3rd-party libraries
	$scripts->add( 'wp-lists', $scripts->base_url . 'js/wp-lists.js', array('wp-ajax-response','jquery-color'), '20080826' );
	$scripts->localize( 'wp-lists', 'wpListL10n', array(
		'url' => $scripts->base_url_admin . 'admin-ajax.php'
	) );
	$scripts->add( 'wp-ajax-response', $scripts->base_url . 'js/wp-ajax-response.js', array('jquery'), '20080316' );
	$scripts->localize( 'wp-ajax-response', 'wpAjax', array(
		'noPerm' => __('You do not have permission to do that.'),
		'broken' => __('An unidentified error has occurred.')
	) );

	// jQuery and friends
	$scripts->add( 'jquery', $scripts->base_url . 'js/jquery/jquery.js', false, '1.2.6');
	$scripts->add( 'jquery-color', $scripts->base_url . 'js/jquery/jquery.color.js', array('jquery'), '2.0-4561' );
	$scripts->add( 'interface', $scripts->base_url . 'js/jquery/interface.js', array('jquery'), '1.2.3');
	$scripts->add( 'password-strength-meter', $scripts->base_url . 'js/jquery/password-strength-meter.js', array('jquery'), '20070405' );
	$scripts->localize( 'password-strength-meter', 'pwsL10n', array(
		'short' => __('Too short'),
		'bad' => __('Bad'),
		'good' => __('Good'),
		'strong' => __('Strong')
	));
}

/**
 * Reorder JavaScript scripts array to place prototype before jQuery.
 *
 * @param array $js_array JavaScript scripst array
 * @return array Reordered array, if needed.
 */
function bb_prototype_before_jquery( $js_array ) {
	if ( false === $jquery = array_search( 'jquery', $js_array, true ) )
		return $js_array;

	if ( false === $prototype = array_search( 'prototype', $js_array, true ) )
		return $js_array;

	if ( $prototype < $jquery )
		return $js_array;

	unset($js_array[$prototype]);

	array_splice( $js_array, $jquery, 0, 'prototype' );

	return $js_array;
}

/**
 * Load localized script just in time for MCE.
 *
 * These localizations require information that may not be loaded even by init.
 */
function bb_just_in_time_script_localization() {
	wp_localize_script( 'topic', 'bbTopicJS', array(
		'currentUserId' => bb_get_current_user_info( 'id' ),
		'topicId' => get_topic_id(),
		'favoritesLink' => get_favorites_link(),
		'isFav' => (int) is_user_favorite( bb_get_current_user_info( 'id' ) ),
		'confirmPostDelete' => __("Are you sure you wanna delete this post?"),
		'confirmPostUnDelete' => __("Are you sure you wanna undelete this post?"),
		'favLinkYes' => __( 'favorites' ),
		'favLinkNo' => __( '?' ),
		'favYes' => __( 'This topic is one of your %favLinkYes% [%favDel%]' ),
		'favNo' => __( '%favAdd% (%favLinkNo%)' ),
		'favDel' => __( '&times;' ),
		'favAdd' => __( 'Add this topic to your favorites' )
	));
}

add_action( 'wp_default_scripts', 'bb_default_scripts' );
add_filter( 'wp_print_scripts', 'bb_just_in_time_script_localization' );
add_filter( 'print_scripts_array', 'bb_prototype_before_jquery' );
