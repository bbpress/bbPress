bbpTopicJS = jQuery.extend( {
	currentUserId: '0',
	topicId: '0',
	favoritesLink: '',
	isFav: 0,
	favLinkYes: 'favorites',
	favLinkNo: '?',
	favYes: 'This topic is one of your %favLinkYes% [%favDel%]',
	favNo: '%favAdd% (%favLinkNo%)',
	favDel: 'x',
	favAdd: 'Add this topic to your favorites'
}, bbpTopicJS );

bbpTopicJS.isFav = parseInt( bbpTopicJS.isFav );

jQuery( function($) {
	// Favorites
	var favoritesToggle = $('#favorite-toggle')
		.addClass( 'list:favorite' )
		.wpList( { alt: '', dimAfter: favLinkSetup } );

	var favoritesToggleSpan = favoritesToggle.children( 'span' )
		[bbpTopicJS.isFav ? 'addClass' : 'removeClass' ]( 'is-favorite' );

	function favLinkSetup() {
		bbpTopicJS.isFav = favoritesToggleSpan.is('.is-favorite');
		var aLink = "<a href='" + bbpTopicJS.favoritesLink + "'>";
		var aDim  = "<a href='" + favoritesToggleSpan.find( 'a[class^="dim:"]' ).attr( 'href' ) + "' class='dim:favorite-toggle:" + favoritesToggleSpan.attr( 'id' ) + ":is-favorite'>";
		if ( bbpTopicJS.isFav ) {
			html = bbpTopicJS.favYes
				.replace( /%favLinkYes%/, aLink + bbpTopicJS.favLinkYes + "</a>" )
				.replace( /%favDel%/, aDim + bbpTopicJS.favDel + "</a>" );
		} else {
			html = bbpTopicJS.favNo
				.replace( /%favLinkNo%/, aLink + bbpTopicJS.favLinkNo + "</a>" )
				.replace( /%favAdd%/, aDim + bbpTopicJS.favAdd + "</a>" );
		}
		favoritesToggleSpan.html( html );
		favoritesToggle.get(0).wpList.process( favoritesToggle );
	}
} );
