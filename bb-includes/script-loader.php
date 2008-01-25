<?php

function bb_default_scripts( $scripts ) {
	$base = bb_get_option( 'uri' );
	$scripts->add( 'fat', $base . BBINC . 'js/fat.js', array('add-load-event'), '1.0-RC1_3660' );
	$scripts->add( 'prototype', $base . BBINC . 'js/prototype.js', false, '1.5.0' );
	$scripts->add( 'wp-ajax', $base . BBINC . 'js/wp-ajax-js.php', array('prototype'), '2.1-beta2' );
	$scripts->add( 'listman', $base . BBINC . 'js/list-manipulation-js.php', array('add-load-event', 'wp-ajax', 'fat'), '440' );
	$scripts->add( 'topic', $base . BBINC . 'js/topic-js.php', array('add-load-event', 'listman', 'jquery'), '433' );
	$scripts->add( 'jquery', $base . BBINC . 'js/jquery/jquery.js', false, '1.1.3.1');
	$scripts->add( 'interface', $base . BBINC . 'js/jquery/interface.js', array('jquery'), '1.2');
	$scripts->add( 'jquery-color', $base . BBINC . 'js/jquery/jquery.color.js', array('jquery'), '1.0' );
	$scripts->add( 'add-load-event', $base . BBINC . 'js/add-load-event.js' );
	$scripts->add( 'content-forums', $base . '/bb-admin/js/content-forums.js', array('listman', 'interface'), 4 );
	$scripts->localize( 'content-forums', 'bbSortForumsL10n', array(
		'handleText' => __('drag'),
		'saveText' => __('Save Forum Order &#187;')
	));
}

add_action( 'wp_default_scripts', 'bb_default_scripts' );

?>
