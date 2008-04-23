bbTopicJS = jQuery.extend( {
	currentUserId: 0,
	topicId: 0,
	favoritesLink: '',
	isFav: 0,
	confirmPostDelete: 'Are you sure you wanna delete this post by "%author%"?',
	confirmTagDelete: 'Are you sure you want to remove the "%tag%" tag?',
	favLinkYes: 'favorites',
	favLinkNo: '?',
	favYes: 'This topic is one of your %favLinkYes% [%favDel%]',
	favNo: '%favAdd% (%favLinkNo%)',
	favDel: 'x',
	favAdd: 'Add this topic to your favorites'
}, bbTopicJS );

bbTopicJS.isFav = parseInt( bbTopicJS.isFav );

addLoadEvent( function() { // Posts
	thePostList = new listMan('thread');
	thePostList.alt = 'alt';
	thePostList.altOffset = 1;
} );

function ajaxPostDelete(postId, postAuthor, a) {
	if ( !confirm( bbTopicJS.confirmPostDelete.replace( /%author%/, postAuthor ) ) ) { return false; }
	thePostList.inputData = '&_ajax_nonce=' + a.href.toQueryParams()['_wpnonce'];
	return thePostList.ajaxDelete( 'post', postId );
}

function newPostAddIn() { // Not currently loaded
	jQuery('#postformsub').click( function() { return thePostList.ajaxAdder( 'post', 'postform' ); } );
}

addLoadEvent( function() { // Tags
	var newtag = jQuery('#tag');
	if (!newtag)
		return;
	newtag.attr('autocomplete', 'off');

	yourTagList = new listMan('yourtaglist');
	yourTagList.alt = false;
	yourTagList.showLink = false;
	yourTagList.inputData = '&topic_id=' + bbTopicJS.topicId;
	othersTagList = new listMan('otherstaglist');
	othersTagList.alt = false;
	othersTagList.inputData = '&topic_id=' + bbTopicJS.topicId;

	if ( !yourTagList.theList )
		return;
	jQuery('#tag-form').submit( function() {
		yourTagList.inputData = '&topic_id=' + bbTopicJS.topicId;
		return yourTagList.ajaxAdder( 'tag', 'tag-form' );
	} );
} );

function ajaxDelTag(tag, user, tagName, a) {
	yourTagList.inputData = '&topic_id=' + bbTopicJS.topicId + '&_ajax_nonce=' + a.href.toQueryParams()['_wpnonce'];
	othersTagList.inputData = '&topic_id=' + bbTopicJS.topicId + '&_ajax_nonce=' + a.href.toQueryParams()['_wpnonce'];
	if ( !confirm( bbTopicJS.confirmTagDelete.replace( /%tag%/, tagName ) ) ) { return false; }
	if ( bbTopicJS.currentUserId == user )
		return yourTagList.ajaxDelete( 'tag', tag + '_' + user );
	else
		return othersTagList.ajaxDelete( 'tag', tag + '_' + user );
}

addLoadEvent( function() { // TopicMeta
	var favoritesToggle = jQuery('#favorite-toggle');
	favoritesToggle[ bbTopicJS.isFav ? 'removeClass' : 'addClass' ]( 'is-not-favorite' );
	theTopicMeta = new listMan('topicmeta');
	theTopicMeta.showLink = false;
	var nonce = jQuery( '#favorite-toggle a[href*="_wpnonce="]' ).click( FavIt ).attr( 'href' ).toQueryParams()['_wpnonce'];
	theTopicMeta.inputData = '&user_id=' + bbTopicJS.currentUserId + '&topic_id=' + bbTopicJS.topicId + '&_ajax_nonce=' + nonce;
	theTopicMeta.dimComplete = function(what, id, dimClass) {
		if ( 'is-not-favorite' == dimClass ) {
			bbTopicJS.isFav = favoritesToggle.is('.' + dimClass) ? 0 : 1;
			favLinkSetup();
		}
	}
} );

function favLinkSetup() {
	var favoritesToggle = jQuery('#favorite-toggle');
	if ( bbTopicJS.isFav ) {
		html = bbTopicJS.favYes
			.replace( /%favLinkYes%/, "<a href='" + bbTopicJS.favoritesLink + "'>" + bbTopicJS.favLinkYes + "</a>" )
			.replace( /%favDel%/, "<a href='#' onclick='return FavIt();'>" + bbTopicJS.favDel + "</a>" );
	} else {
		html = bbTopicJS.favNo
			.replace( /%favLinkNo%/, "<a href='" + bbTopicJS.favoritesLink + "'>" + bbTopicJS.favLinkNo + "</a>" )
			.replace( /%favAdd%/, "<a href='#' onclick='return FavIt();'>" + bbTopicJS.favAdd + "</a>" );
	}
	favoritesToggle.html( html );
}

function FavIt() { return theTopicMeta.ajaxDimmer( 'favorite', 'toggle', 'is-not-favorite' ); }
