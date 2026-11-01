jQuery( function( $ ) {
	var settings      = window.bbpToolsRunner,
		marker        = $( '.bbp-tools-runner' ),
		notice        = marker.closest( '.notice' ),
		tools,
		message,
		status,
		control,
		batchRequest,
		paused        = false,
		statusPending = false,
		complete      = false,
		redirecting   = false,
		delay         = 250;

	if ( ! settings || ! notice.length || ! Array.isArray( settings.tools ) || ! settings.tools.length ) {
		return;
	}

	tools   = settings.tools.slice();
	message = notice.find( 'p' ).first().attr( {
		role: 'status',
		'aria-live': 'polite'
	} );
	status  = $( '<span class="bbp-tools-runner-status"></span>' ).text( settings.running );
	control = $( '<a href="#"></a>' ).text( settings.pause );

	notice.append( $( '<p></p>' ).append( status, ' ', control ) );

	// Reloading the page must not replay the original Run request.
	window.history.replaceState( null, '', settings.returnUrl );

	function persistStatus( value, beacon ) {
		var data = {
			action: 'bbp_tools_run_status',
			nonce: settings.statusNonce,
			type: settings.type,
			run: settings.run,
			status: value
		};

		if ( beacon && window.navigator.sendBeacon && window.FormData ) {
			var form = new window.FormData();
			$.each( data, function( key, value ) {
				form.append( key, value );
			} );

			if ( window.navigator.sendBeacon( settings.url, form ) ) {
				return true;
			}
		}

		return $.post( settings.url, data );
	}

	function stop( error ) {
		paused = true;
		status.text( error || settings.paused );
		control.text( settings.resume );
	}

	function finish() {
		redirecting = true;
		control.removeAttr( 'href' ).attr( 'aria-disabled', 'true' );
		window.location.replace( settings.completeUrl );
	}

	function handleError( request ) {
		var response = request.responseJSON;
		if ( response && response.data && 'stale_run' === response.data.code ) {
			if ( complete ) {
				finish();
				return;
			}

			redirecting = true;
			control.removeAttr( 'href' ).attr( 'aria-disabled', 'true' );
			window.location.replace( settings.returnUrl );
			return;
		}

		stop( response && response.data && response.data.message ? response.data.message : settings.error );
	}

	function run() {
		if ( paused || statusPending || batchRequest || complete || redirecting || ! tools.length ) {
			return;
		}

		batchRequest = $.ajax( {
			url: settings.url,
			method: 'POST',
			dataType: 'json',
			timeout: 60000,
			data: {
				action: 'bbp_tools_process',
				nonce: settings.nonce,
				type: settings.type,
				tool: tools[ 0 ],
				run: settings.run
			}
		} );
		batchRequest.done( function( response ) {
			if ( ! response || ! response.success || ! response.data || typeof response.data.complete !== 'boolean' ) {
				handleError( { responseJSON: response } );
				return;
			}

			message.empty().append( $( '<strong></strong>' ).text( response.data.title ) );
			if ( response.data.message ) {
				$.each( response.data.message.split( /\r\n|\r|\n/ ), function( index, line ) {
					message.append( $( '<br>' ), document.createTextNode( line ) );
				} );
			}
			if ( response.data.complete ) {
				tools.shift();
			}

			complete = ! tools.length;
			if ( complete && ! paused ) {
				finish();
			}
		} ).fail( function( request ) {
			var response = request.responseJSON;
			if ( 409 === request.status && response && response.data && 'locked' === response.data.code ) {
				if ( ! paused ) {
					status.text( response && response.data && response.data.message ? response.data.message : settings.running );
					delay = 2000;
				}
			} else {
				handleError( request );
				if ( ! response ) {
					persistStatus( 'paused', false );
				}
			}
		} ).always( function() {
			batchRequest = null;
			if ( ! paused && ! complete && ! redirecting ) {
				window.setTimeout( run, delay );
				delay = 250;
			}
		} );
	}

	control.on( 'click', function( event ) {
		event.preventDefault();

		if ( 'true' === control.attr( 'aria-disabled' ) || statusPending ) {
			return;
		}

		if ( paused ) {
			if ( complete ) {
				finish();
				return;
			}

			statusPending = true;
			status.text( settings.running );
			control.text( settings.pause );
			persistStatus( 'running', false ).done( function() {
				paused = false;
			} ).fail( function( request ) {
				handleError( request );
			} ).always( function() {
				statusPending = false;
				if ( ! paused ) {
					run();
				}
			} );
		} else {
			// Let the in-flight batch save its checkpoint before stopping.
			statusPending = true;
			stop();
			persistStatus( 'paused', false ).fail( function( request ) {
				handleError( request );
			} ).always( function() {
				statusPending = false;
			} );
		}
	} );

	$( window ).on( 'pagehide', function() {
		if ( ! complete && ! redirecting ) {
			stop();
			persistStatus( 'paused', true );
		}
	} );

	window.setTimeout( run, 250 );
} );
