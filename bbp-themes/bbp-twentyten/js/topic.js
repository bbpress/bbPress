bbpTopicJS = jQuery.extend( {
	// User and Topic
	currentUserId: '0',
	topicId: '0',

	// Favorites
	favoritesLink: '',
	isFav: 0,
	favLinkYes: 'favorites',
	favLinkNo: '?',
	favYes: 'This topic is one of your %favLinkYes% [%favDel%]',
	favNo: '%favAdd% (%favLinkNo%)',
	favDel: 'x',
	favAdd: 'Add this topic to your favorites',

	// Subscriptions
	subsLink: '',
	subsActive: 0,
	isSubscribed: 0,
	subsSub: 'Subscribe',
	subsUns: 'Unsubscribe'
}, bbpTopicJS );

// Topic Global
bbpTopicJS.isFav        = parseInt( bbpTopicJS.isFav );
bbpTopicJS.subsActive   = parseInt( bbpTopicJS.subsActive );
bbpTopicJS.isSubscribed = parseInt( bbpTopicJS.isSubscribed );

// Run it
jQuery( function($) {
	/** Favorites *************************************************************/
	var favoritesToggle = $( '#favorite-toggle' )
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

	/** Subscriptions *********************************************************/
	function subsLinkSetup() {
		bbpTopicJS.isSubscribed = subscriptionToggleSpan.is( '.is-subscribed' );
		var aLink = "<a href='" + bbpTopicJS.subsLink + "'>";
		var aDim  = "<a href='" + subscriptionToggleSpan.find( 'a[class^="dim:"]' ).attr( 'href' ) + "' class='dim:subscription-toggle:" + subscriptionToggleSpan.attr( 'id' ) + ":is-subscribed'>";

		if ( bbpTopicJS.isSubscribed ) {
			html = aDim + bbpTopicJS.subsUns + '</a>';
		} else {
			html = aDim + bbpTopicJS.subsSub + '</a>';
		}

		subscriptionToggleSpan.html( html );
		subscriptionToggle.get(0).wpList.process( subscriptionToggle );
	}

	if ( bbpTopicJS.subsActive == 1 ) {
		var subscriptionToggle = $( '#subscription-toggle' )
			.addClass( 'list:subscription' )
			.wpList( { alt: '', dimAfter: subsLinkSetup } );

		var subscriptionToggleSpan = subscriptionToggle.children( 'span' )
			[bbpTopicJS.isSubscribed ? 'addClass' : 'removeClass' ]( 'is-subscribed' );
	}
} );
