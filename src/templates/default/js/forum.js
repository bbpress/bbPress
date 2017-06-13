/* global bbpForumJS */
jQuery( document ).ready( function ( $ ) {

	function bbp_ajax_call( action, forum_id, nonce, update_selector ) {
		var $data = {
			action : action,
			id     : forum_id,
			nonce  : nonce
		};

		$.post( bbpForumJS.bbp_ajaxurl, $data, function ( response ) {
			if ( response.success ) {
				$( update_selector ).html( response.content );
			} else {
				if ( !response.content ) {
					response.content = bbpForumJS.generic_ajax_error;
				}
				window.alert( response.content );
			}
		} );
	}

	$( '#subscription-toggle' ).on( 'click', 'span a.subscription-toggle', function( e ) {
		var nonce = ( bbpForumJS.forum_id === 0 )
			? $( this ).data( 'bbp-nonce' )
			: bbpForumJS.subs_nonce;

		e.preventDefault();
		bbp_ajax_call( 'forum_subscription', $( this ).attr( 'data-forum' ), nonce, '#subscription-toggle' );
	} );
} );
