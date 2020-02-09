jQuery( document ).ready( function() {

	var bbp_author_id = jQuery( '#bbp_author_id' );

	bbp_author_id.suggest(
		bbp_author_id.data( 'ajax-url' ),
		{
			minchars: 1, // Allow single-digit user IDs
			onSelect: function() {
				var value = this.value;
				bbp_author_id.val( value.substr( 0, value.indexOf( ' ' ) ) );
			}
		}
	);
} );