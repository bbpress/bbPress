/* global bbpEngagementJS */
jQuery( document ).ready( function ( $ ) {

	function bbp_ajax_call( action, object, type, nonce, update_selector ) {
		var $data = {
			action : action,
			id     : object,
			type   : type,
			nonce  : nonce
		};

		$.post( bbpEngagementJS.bbp_ajaxurl, $data, function ( response ) {
			if ( response.success ) {
				$( update_selector ).html( response.content );
			} else {
				if ( !response.content ) {
					response.content = bbpEngagementJS.generic_ajax_error;
				}
				window.alert( response.content );
			}
		} );
	}

	$( '#favorite-toggle' ).on( 'click', 'span a.favorite-toggle', function( e ) {
		e.preventDefault();
		bbp_ajax_call(
			'favorite',
			$( this ).data( 'bbp-object-id'   ),
			$( this ).data( 'bbp-object-type' ),
			$( this ).data( 'bbp-nonce'       ),
			'#favorite-toggle'
		);
	} );

	$( '#subscription-toggle' ).on( 'click', 'span a.subscription-toggle', function( e ) {
		e.preventDefault();
		bbp_ajax_call(
			'subscription',
			$( this ).data( 'bbp-object-id'   ),
			$( this ).data( 'bbp-object-type' ),
			$( this ).data( 'bbp-nonce'       ),
			'#subscription-toggle'
		);
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
