/*jshint sub:true*/
/* global document, jQuery, ajaxurl, BBP_Converter */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	// Variables
	var message  = $( '#bbp-converter-message'  ),
		stop     = $( '#bbp-converter-stop'     ),
		start    = $( '#bbp-converter-start'    ),
		restart  = $( '#_bbp_converter_restart' ),
		timer    = $( '#bbp-converter-timer'    ),
		settings = $( '#bbp-converter-settings' ),

		// Prefetch the settings
		options  = bbp_converter_settings();

	/**
	 * Start button click
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @param {element} e
	 */
	start.on( 'click', function( e ) {
		bbp_converter_user_start();

		e.preventDefault();
	} );

	/**
	 * Stop button click
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @param {element} e
	 */
	$( stop ).on( 'click', function( e ) {
		bbp_converter_user_stop();

		e.preventDefault();
	} );

	/**
	 * Start the converter
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_user_start() {
		if ( ! BBP_Converter.running ) {
			message.addClass( 'started' );
			start.hide();
			stop.show();

			if ( BBP_Converter.started ) {
				bbp_converter_log( BBP_Converter.strings.start_continue );
			} else {
				bbp_converter_log( BBP_Converter.strings.start_start );
			}

			bbp_converter_next();
		}

		BBP_Converter.started = true;
	}

	/**
	 * Stop the converter
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_user_stop() {
		bbp_converter_stop(
			BBP_Converter.strings.button_continue,
			BBP_Converter.strings.import_stopped_user
		);
	}

	/**
	 * Database error
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_error_db() {
		bbp_converter_stop(
			BBP_Converter.strings.button_start,
			BBP_Converter.strings.import_error_db
		);
	}

	/**
	 * Converter error
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_halt() {
		bbp_converter_stop(
			BBP_Converter.strings.button_continue,
			BBP_Converter.strings.import_error_halt
		);
	}

	/**
	 * Converter complete
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_complete() {
		bbp_converter_stop(
			BBP_Converter.strings.button_start,
			BBP_Converter.strings.import_comelete
		);

		BBP_Converter.started = false;
	}

	/**
	 * Handle successful ajax response
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @param {object} response Ajax response
	 *
	 * @returns {void}
	 */
	function bbp_converter_process( response ) {
		bbp_converter_log( response );

		if ( response === BBP_Converter.strings.import_complete ) {
			bbp_converter_complete();

		} else if ( BBP_Converter.running ) {
			bbp_converter_next();

		} else if ( BBP_Converter.halt ) {
			bbp_converter_halt();
		}
	}

	/**
	 * Return values of converter settings
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {converterL#2.bbp_converter_settings.values}
	 */
	function bbp_converter_settings() {
		var values = {};

		$.each( settings.serializeArray(), function( i, field ) {
			values[ field.name ] = field.value;
		} );

		if ( values['_bbp_converter_restart'] ) {
			restart.removeAttr( 'checked' );
		}

		if ( values['_bbp_converter_delay_time'] ) {
			BBP_Converter.delay = values['_bbp_converter_delay_time'] * 1000;
		}

		values['action']      = 'bbp_converter_process';
		values['_ajax_nonce'] = BBP_Converter.ajax_nonce;

		return values;
	}

	/**
	 * Run the converter step
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_step() {

		if ( ! BBP_Converter.running ) {
			return;
		}

		// Check if the settings have changed
		options = bbp_converter_settings();

		$.post( ajaxurl, options, function( response ) {

			if ( 'bbp_converter_db_connection_failed' === response ) {
				bbp_converter_error_db();
				return;
			}

			bbp_converter_process( response.substring( 0, response.length - 1 ) );
		} );
	}

	/**
	 * Proceed to the next converter step
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @returns {void}
	 */
	function bbp_converter_next() {
		bbp_converter_timer();

		clearTimeout( BBP_Converter.running );

		BBP_Converter.running = setTimeout( function() {
			bbp_converter_step();
		}, BBP_Converter.delay );
	}

	/**
	 * Stop the converter, and update the UI
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @param {string} button New text for button
	 * @param {string} log    New text to add to import monitor
	 *
	 * @returns {void}
	 */
	function bbp_converter_stop( button, log ) {
		start.val( button ).show();
		stop.hide();
		timer.text( BBP_Converter.strings.timer_stopped );

		if ( log ) {
			bbp_converter_log( log );
		}

		clearTimeout( BBP_Converter.running );

		BBP_Converter.running = false;
	}

	/**
	 * Update the timer
	 *
	 * @since 2.6.0 bbPress (r6513)
	 *
	 * @returns {void}
	 */
	function bbp_converter_timer() {
		var remaining = BBP_Converter.delay / 1000;

		timer.text( BBP_Converter.strings.timer_counting.replace( '%s', remaining ) );

		clearInterval( BBP_Converter.timer );

		BBP_Converter.timer = setInterval( function() {
			remaining--;
			timer.text( BBP_Converter.strings.timer_counting.replace( '%s', remaining ) );

			if ( remaining <= 0 ) {
				clearInterval( BBP_Converter.timer );
				timer.text( BBP_Converter.strings.timer_waiting );
			}
		}, 1000 );
	}

	/**
	 * Prepend some text to the import monitor
	 *
	 * @since 2.6.0 bbPress (r6470)
	 *
	 * @param {string} text Text to prepend to the import monitor
	 *
	 * @returns {void}
	 */
	function bbp_converter_log( text ) {

		if ( '<div' !== text.substring( 0, 3 ) ) {
			text = '<p>' + text + '</p>';
		}

		message.prepend( text );
	}
} );
