/* global bbpTopicJS */
jQuery( document ).ready( function ( $ ) {

	function bbp_ajax_call( action, topic_id, nonce, update_selector ) {
		var $data = {
			action : action,
			id     : topic_id,
			nonce  : nonce
		};

		$.post( bbpTopicJS.bbp_ajaxurl, $data, function ( response ) {
			if ( response.success ) {
				$( update_selector ).html( response.content );
			} else {
				if ( !response.content ) {
					response.content = bbpTopicJS.generic_ajax_error;
				}
				window.alert( response.content );
			}
		} );
	}

	$( '#favorite-toggle' ).on( 'click', 'span a.favorite-toggle', function( e ) {
		var nonce = ( bbpTopicJS.topic_id === 0 ) ? $( this ).data( 'bbp-nonce' ) : bbpTopicJS.fav_nonce;

		e.preventDefault();
		bbp_ajax_call( 'favorite', $( this ).attr( 'data-topic' ), nonce, '#favorite-toggle' );
	} );

	$( '#subscription-toggle' ).on( 'click', 'span a.subscription-toggle', function( e ) {
		var nonce = ( bbpTopicJS.topic_id === 0 ) ? $( this ).data( 'bbp-nonce' ) : bbpTopicJS.subs_nonce;

		e.preventDefault();
		bbp_ajax_call( 'subscription', $( this ).attr( 'data-topic' ), nonce, '#subscription-toggle' );
	} );

	$( '.bbp-alert-outer' ).on( 'click', '.bbp-alert-close', function( e ) {
		e.preventDefault();
		$( this ).closest( '.bbp-alert-outer' ).fadeOut();
	} );

	$( '.bbp-alert-outer' ).on( 'click', function( e ) {
		if ( this === e.target ) {
			$( this ).closest( '.bbp-alert-outer' ).fadeOut();
		}
	} );

	$( document ).keyup( function( e ) {
		if ( e.keyCode === 27 ) {
			$( '.bbp-alert-outer .bbp-alert-close' ).click();
		}
	} );
} );
