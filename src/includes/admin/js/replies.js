jQuery( document ).ready(function() {

	jQuery( '#misc-publishing-actions' ).find( '.misc-pub-section' ).first().remove();
	jQuery( '#save-action' ).remove();

	var bbp_topic_id = jQuery( '#bbp_topic_id' );

	bbp_topic_id.suggest( ajaxurl + '?action=bbp_suggest_topic', {
		onSelect: function() {
			var value = this.value;
			bbp_topic_id.val( value.substr( 0, value.indexOf( ' ' ) ) );
		}
	} );
} );