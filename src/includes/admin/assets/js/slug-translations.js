( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		var dialog = document.getElementById( 'bbp-slug-translation' ),
			links,
			cancelButton,
			form,
			opener = null;

		if ( ! dialog ) {
			return;
		}

		function openReview( link ) {
			if ( dialog.hasAttribute( 'open' ) ) {
				return;
			}

			opener = link || document.querySelector( 'a[href="#bbp-slug-translation"]' );

			if ( 'function' === typeof dialog.showModal ) {
				dialog.showModal();
			} else {
				dialog.classList.add( 'bbp-slug-translation-fallback' );
				dialog.setAttribute( 'open', '' );
			}

			document.getElementById( 'bbp-slug-translation-title' ).focus();
		}

		function closeReview() {
			if ( 'function' === typeof dialog.close ) {
				dialog.close();
			} else {
				dialog.removeAttribute( 'open' );
			}

			if ( '#bbp-slug-translation' === window.location.hash ) {
				window.history.replaceState( null, '', window.location.pathname + window.location.search );
			}

			if ( opener ) {
				opener.focus();
			}
		}

		function dismissReview() {
			var helpLink = document.getElementById( 'bbp-slug-translation-help-link' ),
				data;

			closeReview();

			// The prompt is already dismissed when the review opens from Help.
			if ( helpLink && ! helpLink.hidden ) {
				return;
			}

			data = new window.FormData();
			data.append( 'action', 'bbp_slug_translation_cancel' );
			data.append( '_wpnonce', form.querySelector( '[name="_wpnonce"]' ).value );

			window.fetch( ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} ).then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Slug suggestion dismissal failed.' );
				}

				return response.json();
			} ).then( function( result ) {
				var notice,
					menuCount,
					helpButton;

				if ( ! result.success ) {
					throw new Error( 'Slug suggestion dismissal failed.' );
				}

				notice = document.querySelector( '.bbp-slug-translation-notice' );
				menuCount = document.querySelector( '.bbp-slug-translation-count' );
				helpButton = document.getElementById( 'contextual-help-link' );

				if ( helpLink ) {
					helpLink.hidden = false;
				}
				if ( notice ) {
					notice.remove();
				}
				if ( menuCount ) {
					menuCount.remove();
				}
				if ( opener && ! opener.isConnected && helpButton ) {
					helpButton.focus();
				}
			} ).catch( function() {
				openReview( opener );
			} );
		}

		links = document.querySelectorAll( 'a[href$="#bbp-slug-translation"]' );
		Array.prototype.forEach.call( links, function( link ) {
			link.addEventListener( 'click', function( event ) {
				if ( event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
					return;
				}

				event.preventDefault();
				openReview( link );
			} );
		} );

		cancelButton = dialog.querySelector( '.bbp-slug-translation-cancel' );
		form = dialog.querySelector( 'form' );
		cancelButton.addEventListener( 'click', function( event ) {
			if ( ! window.fetch ) {
				return;
			}

			event.preventDefault();
			dismissReview();
		} );
		dialog.addEventListener( 'cancel', function( event ) {
			event.preventDefault();
			cancelButton.click();
		} );
		dialog.addEventListener( 'click', function( event ) {
			var bounds = dialog.getBoundingClientRect();

			if ( event.target === dialog && ( event.clientX < bounds.left || event.clientX > bounds.right ||
				event.clientY < bounds.top || event.clientY > bounds.bottom ) ) {
				cancelButton.click();
			}
		} );
		dialog.addEventListener( 'keydown', function( event ) {
			if ( 'Escape' === event.key && 'function' !== typeof dialog.showModal ) {
				event.preventDefault();
				cancelButton.click();
			}
		} );

		if ( '#bbp-slug-translation' === window.location.hash ) {
			openReview();
		}
	} );
}() );
