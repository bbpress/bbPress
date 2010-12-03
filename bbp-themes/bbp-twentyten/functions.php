<?php

/**
 * bbp_twentyten_enqueue_styles ()
 *
 * Load the theme CSS
 */
function bbp_twentyten_enqueue_styles () {
	// Default styling, taken from twentyten theme
	wp_enqueue_style( 'bbp-twentyten-default', get_stylesheet_directory_uri() . '/css/twentyten.css', false, 20100312, 'screen' );

	// bbPress specific
	wp_enqueue_style( 'bbp-twentyten-bbpress', get_stylesheet_directory_uri() . '/css/bbpress.css', 'bbp-twentyten-default', 20100312, 'screen' );
}
add_action( 'init', 'bbp_twentyten_enqueue_styles' );

/**
 * bbp_twentyten_dim_favorite ()
 *
 * Add or remove a topic from a user's favorites
 *
 * @package bbPress
 * @subpackage bbPress TwentyTen
 * @since bbPress (r2652)
 *
 * @return void
 */
function bbp_twentyten_dim_favorite () {
	global $current_user;

	$current_user = wp_get_current_user();
	$user_id      = $current_user->ID;
	$id           = intval( $_POST['id'] );

	if ( !current_user_can( 'edit_user', $user_id ) )
		die( '-1' );

	if ( !$topic = get_post( $id ) )
		die( '0' );

	check_ajax_referer( "toggle-favorite_$topic->ID" );

	if ( bbp_is_user_favorite( $user_id, $topic->ID ) ) {
		if ( bbp_remove_user_favorite( $user_id, $topic->ID ) )
			die( '1' );
	} else {
		if ( bbp_add_user_favorite( $user_id, $topic->ID ) )
			die( '1' );
	}

	die( '0' );
}
add_action( 'wp_ajax_dim-favorite', 'bbp_twentyten_dim_favorite' );

/**
 * bbp_twentyten_dim_subscription ()
 *
 * Subscribe/Unsubscribe a user from a topic
 *
 * @package bbPress
 * @subpackage bbPress TwentyTen
 * @since bbPress (r2668)
 *
 * @return void
 */
function bbp_twentyten_dim_subscription () {
	global $current_user;

	if ( !bbp_is_subscriptions_active() )
		return;

	$current_user = wp_get_current_user();
	$user_id      = $current_user->ID;
	$id           = intval( $_POST['id'] );

	if ( !current_user_can( 'edit_user', $user_id ) )
		die( '-1' );

	if ( !$topic = get_post( $id ) )
		die( '0' );

	check_ajax_referer( "toggle-subscription_$topic->ID" );

	if ( bbp_is_user_subscribed( $user_id, $topic->ID ) ) {
		if ( bbp_remove_user_subscription( $user_id, $topic->ID ) )
			die( '1' );
	} else {
		if ( bbp_add_user_subscription( $user_id, $topic->ID ) )
			die( '1' );
	}

	die( '0' );
}
add_action( 'wp_ajax_dim-subscription', 'bbp_twentyten_dim_subscription' );

/**
 * bbp_twentyten_enqueue_topic_script ()
 *
 * Enqueue the topic page Javascript file
 *
 * @package bbPress
 * @subpackage bbPress TwentyTen
 * @since bbPress (r2652)
 *
 * @return void
 */
function bbp_twentyten_enqueue_topic_script () {
	if ( !bbp_is_topic() )
		return;

	wp_enqueue_script( 'bbp_topic', get_stylesheet_directory_uri() . '/js/topic.js', array( 'wp-lists' ), '20101202' );
}
add_filter( 'wp_enqueue_scripts', 'bbp_twentyten_enqueue_topic_script' );

/**
 * bbp_twentyten_scripts ()
 *
 * Put some scripts in the header, like AJAX url for wp-lists
 *
 * @package bbPress
 * @subpackage bbPress TwentyTen
 * @since bbPress (r2652)
 *
 * @return void
 */
function bbp_twentyten_scripts () {
	if ( !bbp_is_topic() )
		return; ?>

	<script type='text/javascript'>
		/* <![CDATA[ */
		var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
		/* ]]> */
	</script>

<?php
}
add_filter( 'wp_head', 'bbp_twentyten_scripts', -1 );

/**
 * bbp_twentyten_topic_script_localization ()
 *
 * Load localizations for topic script.
 *
 * These localizations require information that may not be loaded even by init.
 *
 * @package bbPress
 * @subpackage bbPress TwentyTen
 * @since bbPress (r2652)
 *
 * @return void
 */
function bbp_twentyten_topic_script_localization () {
	if ( !bbp_is_topic() )
		return;

	global $current_user;

	$current_user = wp_get_current_user();
	$user_id      = $current_user->ID;

	$localizations = array(
		'currentUserId' => $user_id,
		'topicId'       => bbp_get_topic_id(),
		'favoritesLink' => bbp_get_favorites_permalink( $user_id ),
		'isFav'         => (int) bbp_is_user_favorite( $user_id ),
		'favLinkYes'    => __( 'favorites', 'bbpress' ),
		'favLinkNo'     => __( '?', 'bbpress' ),
		'favYes'        => __( 'This topic is one of your %favLinkYes% [%favDel%]', 'bbpress' ),
		'favNo'         => __( '%favAdd% (%favLinkNo%)', 'bbpress' ),
		'favDel'        => __( '&times;', 'bbpress' ),
		'favAdd'        => __( 'Add this topic to your favorites', 'bbpress' )
	);

	if ( bbp_is_subscriptions_active() ) {
		$localizations['subsActive']   = 1;
		$localizations['isSubscribed'] = (int) bbp_is_user_subscribed( $user_id );
		$localizations['subsSub']      = __( 'Subscribe', 'bbpress' );
		$localizations['subsUns']      = __( 'Unsubscribe', 'bbpress' );
		$localizations['subsLink']     = bbp_get_topic_permalink();
	} else {
		$localizations['subsActive'] = 0;
	}

	wp_localize_script( 'bbp_topic', 'bbpTopicJS', $localizations );
}
add_filter( 'wp_enqueue_scripts', 'bbp_twentyten_topic_script_localization' );

?>
