jQuery( document ).ready(function() {

	jQuery( '#misc-publishing-actions' ).find( '.misc-pub-section' ).first().remove();
	jQuery( '#save-action' ).remove();

	var bbp_topic_id = jQuery( '#bbp_topic_id' );
	var bbp_forum_id = jQuery( '#bbp_forum_id' );
	var bbp_reply_to = jQuery( '#bbp_reply_to' );
	var savedReplyToOptions = bbp_reply_to.html();
	var savedReplyToValue = bbp_reply_to.val();
	var status = jQuery( '.bbp-reply-forum-status' );
	var previewTimer;
	var previewRequest;

	function selectForum( forumId, forumTitle ) {
		if ( forumId && ! bbp_forum_id.find( 'option' ).filter(function() {
			return this.value === String( forumId );
		} ).length ) {
			jQuery( '<option>' ).val( forumId ).text( forumTitle ).appendTo( bbp_forum_id );
		}

		bbp_forum_id.val( forumId ? String( forumId ) : '' );
	}

	function selectSavedForum() {
		selectForum( bbp_topic_id.data( 'forum-id' ), bbp_forum_id.data( 'forum-title' ) );
	}

	function selectReplyTo( dropdown ) {
		var previewReplyTo = jQuery( dropdown ).filter( '#bbp_reply_to' );

		if ( bbp_reply_to.length && previewReplyTo.length ) {
			bbp_reply_to.html( previewReplyTo.html() ).val( previewReplyTo.val() );
		}
	}

	function selectSavedReplyTo() {
		if ( bbp_reply_to.length ) {
			bbp_reply_to.html( savedReplyToOptions ).val( savedReplyToValue );
		}
	}

	function syncForum() {
		var topicId = bbp_topic_id.val().trim();

		window.clearTimeout( previewTimer );
		if ( previewRequest ) {
			previewRequest.abort();
		}

		status.text( '' );

		if ( topicId === String( bbp_topic_id.data( 'saved-topic-id' ) ) ) {
			selectSavedForum();
			selectSavedReplyTo();
			return;
		}

		if ( ! /^\d+$/.test( topicId ) ) {
			selectSavedForum();
			selectSavedReplyTo();
			return;
		}

		previewTimer = window.setTimeout(function() {
			previewRequest = jQuery.post( window.ajaxurl, {
				action: 'bbp_reply_topic_forum',
				reply_id: bbp_topic_id.data( 'reply-id' ),
				topic_id: topicId,
				_ajax_nonce: bbp_topic_id.data( 'forum-nonce' )
			} ).done(function( response ) {
				if ( topicId !== bbp_topic_id.val().trim() ) {
					return;
				}

				if ( response.success ) {
					selectForum( response.data.forum_id, response.data.forum_title );
					selectReplyTo( response.data.reply_to_dropdown );
				} else {
					selectSavedForum();
					selectSavedReplyTo();
					status.text( response.data && response.data.message ? response.data.message : bbp_topic_id.data( 'preview-error' ) );
				}
			} ).fail(function( response, textStatus ) {
				if ( 'abort' !== textStatus && topicId === bbp_topic_id.val().trim() ) {
					selectSavedForum();
					selectSavedReplyTo();
					status.text( response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ? response.responseJSON.data.message : bbp_topic_id.data( 'preview-error' ) );
				}
			} );
		}, 250 );
	}

	bbp_topic_id.suggest(
		bbp_topic_id.data( 'ajax-url' ),
		{
			onSelect: function() {
				var value = this.value;
				bbp_topic_id.val( value.substr( 0, value.indexOf( ' ' ) ) );
				syncForum();
			}
		}
	);

	bbp_topic_id.on( 'change', syncForum );
	bbp_reply_to.on( 'change', function() {
		if ( bbp_topic_id.val().trim() === String( bbp_topic_id.data( 'saved-topic-id' ) ) ) {
			savedReplyToValue = this.value;
		}
	} );
	syncForum();
} );
