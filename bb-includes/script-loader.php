<?php

function bb_default_scripts( $scripts ) {
	$base = bb_get_option( 'uri' );
	$scripts->add( 'fat', $base . BB_INC . 'js/fat.js', array('add-load-event'), '1.0-RC1_3660' );
	$scripts->add( 'prototype', $base . BB_INC . 'js/prototype.js', false, '1.5.0' );
	$scripts->add( 'wp-ajax', $base . BB_INC . 'js/wp-ajax-js.php', array('prototype'), '2.1-beta2' );
	$scripts->add( 'listman', $base . BB_INC . 'js/list-manipulation-js.php', array('add-load-event', 'wp-ajax', 'fat'), '440' );
	$scripts->add( 'topic', $base . BB_INC . 'js/topic-js.php', array('add-load-event', 'listman', 'jquery'), '433' );
	$scripts->add( 'jquery', $base . BB_INC . 'js/jquery/jquery.js', false, '1.1.3.1');
	$scripts->add( 'interface', $base . BB_INC . 'js/jquery/interface.js', array('jquery'), '1.2.3');
	$scripts->add( 'jquery-color', $base . BB_INC . 'js/jquery/jquery.color.js', array('jquery'), '1.0' );
	$scripts->add( 'add-load-event', $base . BB_INC . 'js/add-load-event.js' );
	$scripts->add( 'content-forums', $base . '/bb-admin/js/content-forums.js', array('listman', 'interface'), 4 );
	$scripts->localize( 'content-forums', 'bbSortForumsL10n', array(
		'handleText' => __('drag'),
		'saveText' => __('Save Forum Order &#187;')
	));
}

add_action( 'wp_default_scripts', 'bb_default_scripts' );

?>
