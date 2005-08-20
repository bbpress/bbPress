var ajaxTag = new sack();
var newtag;
var tagId;
var userId;
 
function newTagAddIn() {
	var ajaxtag = document.createElement('p');
	ajaxtag.id = 'ajaxtag';

	newtag = document.createElement('input');
	newtag.type = 'text';
	newtag.name = 'newtag';
	newtag.id = 'newtag';
	newtag.size = '10';
	newtag.setAttribute('maxlength', '30');
	newtag.setAttribute('autocomplete', 'off');
	newtag.setAttribute('onkeypress', 'return ajaxNewTagKeyPress(event);');

	var newtagSub = document.createElement('input');
	newtagSub.type = 'button';
	newtagSub.name = 'Button';
	newtagSub.value = '+';
	newtagSub.setAttribute('onclick', 'ajaxNewTag();');

	ajaxtag.appendChild(newtag);
	ajaxtag.appendChild(newtagSub);
	document.getElementById('tags').appendChild(ajaxtag);
}

function favoritesAddIn() {
	var favoritesToggle = document.getElementById('favoritestoggle');
	if ( 'no' == isFav ) {
		return;
	}
	if ( 1 == isFav ) {
		favoritesToggle.innerHTML = 'This topic is one of your <a href="' + favoritesLink + '">favorites</a> [<a href="#" onclick="FavIt(topicId, 0); return false;">x</a>]';
	} else {
		favoritesToggle.innerHTML = '<a href="#" onclick="FavIt(topicId, 1); return false;">Add this topic to your favorites</a> (<a href="' + favoritesLink + '">?</a>)';
	}
}

addLoadEvent(newTagAddIn);
addLoadEvent(favoritesAddIn);

function getResponseElement() {
	var p = document.getElementById('ajaxtagresponse');
	if (!p) {
		p = document.createElement('p');
		document.getElementById('tags').appendChild(p);
		p.id = 'ajaxtagresponse';
	}
	return p;
}

function newTagLoading() {
	var p = getResponseElement();
	p.innerHTML = 'Sending Data...';
}

function newTagLoaded() {
	var p = getResponseElement();
	p.innerHTML = 'Data Sent...';
}

function newTagInteractive() {
	var p = getResponseElement();
	p.innerHTML = 'Processing Data...';
}

function newTagCompletion() {
	var p = getResponseElement();
	var id = parseInt(ajaxTag.response, 10);
	var tagId = ajaxTag.responseXML.getElementsByTagName('id')[0].firstChild.nodeValue;
	var userId = ajaxTag.responseXML.getElementsByTagName('user')[0].firstChild.nodeValue;
	var raw = ajaxTag.responseXML.getElementsByTagName('raw')[0].firstChild.nodeValue;
	var cooked = ajaxTag.responseXML.getElementsByTagName('cooked')[0].firstChild.nodeValue;
	if ( id == '-1' ) {
		p.innerHTML = "You don't have permission to do that.";
		return;
	}
	if ( id == '0' ) {
		p.innerHTML = "Tag not added. Try something else.";
		return;
	}
	p.parentNode.removeChild(p);
	var yourTags = document.getElementById('yourtags');
	if (!yourTags) {
		var tags = document.getElementById('tags');
		yourTags = document.createElement('div');
		yourTags.id = 'yourtags';
		yourTagsP = document.createElement('p');
		yourTagsP.innerHTML = 'Your tags:';
		yourTagsUl = document.createElement('ul');
		yourTagsUl.id = 'yourtaglist';
		yourTags.appendChild(yourTagsP);
		yourTags.appendChild(yourTagsUl);
		tags.insertBefore(yourTags, tags.firstChild);
	}
	var newLi = document.createElement('li');
	var yourTagList = document.getElementById('yourtaglist');
	newLi.innerHTML = '<a href="' + tagLinkBase + cooked + '">' + raw + '</a> [<a href="#" onclick="if ( confirm(\'Are you sure you want to remove the &quot;' + raw + '&quot; tag?\') ) { ajaxDelTag(' + tagId + ', ' + userId + '); } return false;">x</a>]';
	newLi.id = 'tag-' + tagId + '-' + userId;
	newLi.setAttribute('class','fade');
	yourTagList.appendChild(newLi);
	Fat.fade_all();
	newLi.setAttribute('class', '');
	newtag.value = '';
}

function ajaxNewTagKeyPress(e) {
	if (!e) {
		if (window.event) {
			e = window.event;
		} else {
			return;
		}
	}
	if (e.keyCode == 13) {
		ajaxNewTag();
		e.returnValue = false;
		e.cancelBubble = true;
		return false;
	}
}

function delTagCompletion() {
	var p = getResponseElement();
	var id = parseInt(ajaxTag.response, 10);
	var tagId = ajaxTag.responseXML.getElementsByTagName('id')[0].firstChild.nodeValue;
	var userId = ajaxTag.responseXML.getElementsByTagName('user')[0].firstChild.nodeValue;
	if ( id == '-1' ) {
		p.innerHTML = "You don't have permission to do that.";
		return;
	}
	if ( id == '0' ) {
		p.innerHTML = "Tag not removed. Try something else.";
		return;
	}
	p.parentNode.removeChild(p);
	oldTag = document.getElementById('tag-' + tagId + '-' + userId);
	Fat.fade_element(oldTag.id,null,700,'#FF0000');
	setTimeout('oldTag.parentNode.removeChild(oldTag)', 705);
}

function ajaxNewTag() {
	var newtag = document.getElementById('newtag');
	var tagString = 'tag=' + encodeURIComponent(newtag.value) + '&id=' + topicId + '&action=tag-add';
	ajaxTag.requestFile = uriBase + 'topic-ajax.php';
	ajaxTag.method = 'POST';
	ajaxTag.onLoading = newTagLoading;
	ajaxTag.onLoaded = newTagLoaded;
	ajaxTag.onInteractive = newTagInteractive;
	ajaxTag.onCompletion = newTagCompletion;
	ajaxTag.runAJAX(tagString);
}

function ajaxDelTag(tag, user, event) {
	tagId = tag;
	userId = user;
	var tagString = 'tag=' + tagId + '&user=' + userId + '&topic=' + topicId + '&action=tag-remove';
	ajaxTag.requestFile = uriBase + 'topic-ajax.php';
	ajaxTag.method = 'POST';
	ajaxTag.onLoading = newTagLoading;
	ajaxTag.onLoaded = newTagLoaded;
	ajaxTag.onInteractive = newTagInteractive;
	ajaxTag.onCompletion = delTagCompletion;
	ajaxTag.runAJAX(tagString);
	if (!event) {
		if (window.event) {
			event = window.event;
		} else {
			return;
		}
	}
	event.returnValue = false;
	event.cancelBubble = true;
	return false;
}

function FavLoading() {
	document.getElementById('favoritestoggle').innerHTML = 'Sending Data...';
}

function FavLoaded() {
	document.getElementById('favoritestoggle').innerHTML = 'Data Sent...';
}

function FavInteractive() {
	document.getElementById('favoritestoggle').innerHTML = 'Processing Data...';
}

function removeFavCompletion() {
	var id = parseInt(ajaxTag.response, 10);
	if ( 1 == id ) {
		isFav = 0;
		favoritesAddIn();
		Fat.fade_element('favoritestoggle');
	}
}

function addFavCompletion() {
	var id = parseInt(ajaxTag.response, 10);
	if ( 1 == id ) {
		isFav = 1;
		favoritesAddIn();
		Fat.fade_element('favoritestoggle');
	}
}

function FavIt(id, addFav) {
	var newtag = document.getElementById('newtag');
	var string = 'topic_id=' + id + '&user_id=' + currentUserId + '&action=';
	if ( addFav ) {
		string = string + 'favorite-add';
		ajaxTag.onCompletion = addFavCompletion;
	} else {
		string = string + 'favorite-remove';
		ajaxTag.onCompletion = removeFavCompletion;
	}
	ajaxTag.requestFile = uriBase + 'topic-ajax.php';
	ajaxTag.onLoading = FavLoading;
	ajaxTag.onLoaded = FavLoaded;
	ajaxTag.onInteractive = FavInteractive;
	ajaxTag.method = 'POST';
	ajaxTag.runAJAX(string);
}
