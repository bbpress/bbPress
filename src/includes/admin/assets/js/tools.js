jQuery( function( $ ) {
	var settings      = window.bbpToolsRunner,
		recovery      = window.bbpToolsRecovery,
		marker        = $( '.bbp-tools-runner' ),
		notice        = marker.closest( '.notice' ),
		tools,
		message,
		status,
		progressText,
		activity,
		controls,
		control,
		stopControl,
		batchRequest,
		activityTimer,
		activityFrame = 0,
		progressBatch = 0,
		paused        = false,
		statusPending = false,
		complete      = false,
		redirecting   = false,
		stopping      = false,
		delay         = 0;

	function persistRunStatus( config, value, runId, toolAction, beacon ) {
		var data = {
			action: 'bbp_tools_run_status',
			nonce: config.statusNonce,
			type: config.type,
			toolAction: toolAction,
			run: runId,
			status: value
		};

		if ( beacon && window.navigator.sendBeacon && window.FormData ) {
			var form = new window.FormData();
			$.each( data, function( key, fieldValue ) {
				form.append( key, fieldValue );
			} );

			if ( window.navigator.sendBeacon( config.url, form ) ) {
				return true;
			}
		}

		return $.post( config.url, data );
	}

	if ( recovery ) {
		$.each( recovery.runs, function( index, run ) {
			$( 'a' ).filter( function() {
				return this.href === run.url;
			} ).on( 'click', function( event ) {
				var link = $( this );

				event.preventDefault();
				if ( link.attr( 'aria-disabled' ) ) {
					return;
				}

				link.attr( 'aria-disabled', 'true' ).text( recovery.discarding );
				link.siblings( '.bbp-tools-recovery-error' ).remove();
				persistRunStatus( recovery, 'discarded', run.id, run.action, false ).done( function() {
					window.location.replace( recovery.returnUrl );
				} ).fail( function( request ) {
					var response = request.responseJSON,
						error    = response && response.data && response.data.message ? response.data.message : recovery.error;

					link.removeAttr( 'aria-disabled' ).text( recovery.discard );
					link.after( $( '<span class="bbp-tools-recovery-error"></span>' ).text( ' ' + error ) );
				} );
			} );
		} );
	}

	if ( ! settings || ! notice.length || ! Array.isArray( settings.tools ) || ! settings.tools.length ) {
		return;
	}

	tools        = settings.tools.slice();
	message      = notice.find( 'p' ).first().attr( {
		role: 'status',
		'aria-live': 'polite'
	} );
	progressText = settings.running;
	status       = $( '<span class="bbp-tools-runner-status"></span>' ).text( progressText );
	activity     = $( '<span class="bbp-tools-runner-activity" aria-hidden="true"></span>' );
	controls     = $( '<span class="bbp-tools-runner-controls"></span>' );
	control      = $( '<button type="button" class="button-link bbp-tools-runner-control"></button>' );
	stopControl  = $( '<a class="bbp-tools-runner-stop"></a>' ).attr( 'href', settings.stopUrl ).text( settings.stop );
	controls.append( control, document.createTextNode( ' | ' ), stopControl );

	notice.append( $( '<p class="bbp-tools-runner-progress"></p>' ).append( status, activity, controls ) );

	// Reloading the page must not replay the original Run request.
	window.history.replaceState( null, '', settings.returnUrl );

	function setControl( label ) {
		control.text( label ).attr( 'aria-label', label );
	}

	function setProgress( batches ) {
		if ( ! paused ) {
			// Run callbacks report work for this request. Dry Runs restore their
			// aggregate estimate from the preview checkpoint.
			if ( 'run' === settings.action ) {
				batches = progressBatch + 1;
			}

			if ( batches < 1 ) {
				return;
			}

			progressBatch = batches;
			progressText  = settings.progress.replace( '%1$s', settings.running ).replace( '%2$s', batches );
			status.text( progressText );
		}
	}

	function startActivity() {
		if ( activityTimer ) {
			return;
		}

		activityFrame = 0;
		activity.text( '...' );
		activityTimer = window.setInterval( function() {
			activityFrame = ( activityFrame + 1 ) % 4;
			activity.text( [ '', '.', '..', '...' ][ activityFrame ] );
		}, 400 );
	}

	function stopActivity() {
		if ( activityTimer ) {
			window.clearInterval( activityTimer );
			activityTimer = null;
		}

		activity.empty();
	}

	function persistStatus( value, beacon ) {
		return persistRunStatus( settings, value, settings.run, settings.action, beacon );
	}

	function stop( error ) {
		paused = true;
		stopActivity();
		status.text( error || ( progressBatch > 0 ? settings.pausedBatch.replace( '%s', progressBatch ) : settings.paused ) );
		setControl( settings.resume );
	}

	function finish() {
		stopActivity();
		control.prop( 'disabled', true );
		stopControl.attr( 'aria-disabled', 'true' );
		if ( 'dry-run' === settings.action ) {
			complete = true;
			status.parent().remove();
			return;
		}

		redirecting = true;
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
			control.prop( 'disabled', true );
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
				toolAction: settings.action,
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
			setProgress( response.data.batches );
			if ( response.data.complete ) {
				tools.shift();
				if ( tools.length ) {
					message = $( '<p></p>' ).attr( {
						role: 'status',
						'aria-live': 'polite'
					} ).insertBefore( status.parent() );
					progressBatch = 0;
					progressText  = settings.running;
					if ( ! paused ) {
						status.text( progressText );
					}
				}
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
				delay = 0;
			}
		} );
	}

	control.on( 'click', function( event ) {
		event.preventDefault();

		if ( control.prop( 'disabled' ) || statusPending ) {
			return;
		}

		if ( paused ) {
			if ( complete ) {
				finish();
				return;
			}

			statusPending = true;
			status.text( progressText );
			startActivity();
			setControl( settings.pause );
			persistStatus( 'running', false ).done( function() {
				paused = false;
			} ).fail( function( request ) {
				handleError( request );
			} ).always( function() {
				statusPending = false;
				if ( ! paused ) {
					if ( complete ) {
						finish();
					} else {
						run();
					}
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

	stopControl.on( 'click', function( event ) {
		var statusRequest,
			request,
			discard = function() {
				persistStatus( 'discarded', false ).done( function() {
					redirecting = true;
					window.location.replace( settings.returnUrl );
				} ).fail( function( request ) {
					stopping = false;
					control.prop( 'disabled', false );
					stopControl.removeAttr( 'aria-disabled' );
					handleError( request );
				} );
			};

		event.preventDefault();

		if ( complete || redirecting || stopping ) {
			return;
		}

		stopping = true;
		paused   = true;
		status.text( settings.stopping );
		startActivity();
		control.prop( 'disabled', true );
		stopControl.attr( 'aria-disabled', 'true' );

		// Pause the queue and let its in-flight batch save before discarding it.
		request       = batchRequest;
		statusRequest = persistStatus( 'paused', false );
		statusRequest.always( function() {
			if ( request ) {
				request.always( discard );
			} else {
				discard();
			}
		} );
	} );

	$( window ).on( 'pagehide', function() {
		if ( ! complete && ! redirecting ) {
			stop();
			persistStatus( 'paused', true );
		}
	} );

	setControl( settings.pause );
	startActivity();
	window.setTimeout( run, 0 );
} );
