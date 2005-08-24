var ajaxTag = new sack();
var newtag;
var tagId;
var userId;
var thread;
var posts = new Array();
var postContent = false;
var reg_color = false;
var alt_color = false;
var postsToBeDeleted = new Array();
 
function getPostsAndColors() {
	thread = document.getElementById('thread');
	var liList = thread.getElementsByTagName('li');
	for (var i = 0; i < liList.length; i++ ) {
		if (!liList[i].id.match('post-')) continue;
		else if (!alt_color && liList[i].className.match(/(^| )alt($| )/)) alt_color = Fat.get_bgcolor(liList[i].id);
		else if (!reg_color) reg_color = Fat.get_bgcolor(liList[i].id);
		posts.push(liList[i].id);
	}
}

function newTagAddIn() {
	newtag = document.getElementById('tag');
	newtag.setAttribute('autocomplete', 'off');
	newtag.onkeypress = ajaxNewTagKeyPress;

	var newtagSub = document.getElementById('tagformsub');
	newtagSub.type = 'button';
	newtagSub.onclick = ajaxNewTag;
}

function favoritesAddIn() {
	var favoritesToggle = document.getElementById('favoritestoggle');
	if ('no' == isFav) return;
	if ( 1 == isFav ) favoritesToggle.innerHTML = 'This topic is one of your <a href="' + favoritesLink + '">favorites</a> [<a href="#" onclick="FavIt(topicId, 0); return false;">x</a>]';
	else  favoritesToggle.innerHTML = '<a href="#" onclick="FavIt(topicId, 1); return false;">Add this topic to your favorites</a> (<a href="' + favoritesLink + '">?</a>)';
}

function resolutionAddIn() {
	var resolvedSub = document.getElementById('resolvedformsub');
	resolvedSub.type = 'button';
	resolvedSub.onclick = resolveTopic;
}

function newPostAddIn() {
	postContent = document.getElementById('post_content');
	if (postContent) {
		var postFormSub = document.getElementById('postformsub');
		postFormSub.type = 'button';
		postFormSub.onclick = ajaxNewPost;
	}
}

addLoadEvent(newTagAddIn);
addLoadEvent(favoritesAddIn);
addLoadEvent(resolutionAddIn);
addLoadEvent(getPostsAndColors);
addLoadEvent(newPostAddIn);

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
	if (id == '-1') {
		p.innerHTML = "You don't have permission to do that.";
		return;
	}
	if (id == '0') {
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
	var exists = document.getElementById('tag-' + tagId + '-' + userId);
	if (exists) {
		Fat.fade_element(exists.id);
		newtag.value = '';
		return;
	}
	var newLi = document.createElement('li');
	var yourTagList = document.getElementById('yourtaglist');
	newLi.innerHTML = '<a href="' + tagLinkBase + cooked + '">' + raw + '</a> [<a href="#" onclick="if ( confirm(\'Are you sure you want to remove the &quot;' + raw.replace("'", "\\'").replace('"', '&quot;') + '&quot; tag?\') ) { ajaxDelTag(' + tagId + ', ' + userId + '); } return false;">x</a>]';
	newLi.id = 'tag-' + tagId + '-' + userId;
	newLi.className = 'fade';
	yourTagList.appendChild(newLi);
	Fat.fade_all();
	newLi.className = '';
	newtag.value = '';
}

function ajaxNewTagKeyPress(e) {
	if (!e) {
		if (window.event) e = window.event;
		else return;
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
	if (id == '-1') {
		p.innerHTML = "You don't have permission to do that.";
		return;
	}
	if (id == '0') {
		p.innerHTML = "Tag not removed. Try something else.";
		return;
	}
	p.parentNode.removeChild(p);
	oldTag = document.getElementById('tag-' + tagId + '-' + userId);
	Fat.fade_element(oldTag.id,null,700,'#FF3333');
	setTimeout('oldTag.parentNode.removeChild(oldTag)', 705);
}

function ajaxNewTag() {
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
	if (1 == id) {
		isFav = 0;
		favoritesAddIn();
		Fat.fade_element('favoritestoggle');
	}
}

function addFavCompletion() {
	var id = parseInt(ajaxTag.response, 10);
	if (1 == id) {
		isFav = 1;
		favoritesAddIn();
		Fat.fade_element('favoritestoggle');
	}
}

function FavIt(id, addFav) {
	var string = 'topic_id=' + id + '&user_id=' + currentUserId + '&action=';
	if (addFav) {
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

function resolveTopic(event) {
	var resolvedSel = document.getElementById('resolvedformsel');
	var string = 'id=' + topicId + '&resolved=' + resolvedSel.value + '&action=topic-resolve';
	ajaxTag.requestFile = uriBase + 'topic-ajax.php';
	ajaxTag.onLoading = function() {};
	ajaxTag.onLoaded = function() {};
	ajaxTag.onInteractive = function() {};
	ajaxTag.onCompletion = function() {
		var id = parseInt(ajaxTag.response, 10);
		if (1 == id) {
			Fat.fade_element('resolutionflipper');
			Fat.fade_element('resolvedformsel');
		}
	}
	ajaxTag.method = 'POST';
	ajaxTag.runAJAX(string);
//	if (!event) {
//		if (window.event) event = window.event;
//		else return;
//	}
//	event.returnValue = false;
//	event.cancelBubble = true;
//	return false;
}

function recolorPosts(post_pos,dur,from) {
	if (!post_pos) post_pos = 0;

	if (!from) {
		reg_from = alt_color;
		alt_from = reg_color;
	} else {
		reg_from = from;
		alt_from = from;
	}

	for (var i = post_pos; i < posts.length; i++) {
		if (i % 2 == 0) Fat.fade_element(posts[i],null,dur,reg_from,reg_color);
		else Fat.fade_element(posts[i],null,dur,alt_from,alt_color);
	}
}

function getPostPos(id) {
	for (var i = 0; i < posts.length; i++) {
		if (id == posts[i]) {
			post_pos = i;
			break;
		}
	}
	return post_pos;
}	

function ajaxPostDelete(postId) {
	var string = 'id=' + postId + '&page=' + page + '&last_mod=' + lastMod + '&action=post-delete';
	ajaxTag.requestFile = uriBase + 'topic-ajax.php';
	ajaxTag.onLoading = function() {};
	ajaxTag.onLoaded = function() {};
	ajaxTag.onInteractive = function() {};
	ajaxTag.onCompletion = function() {
		var id = parseInt(ajaxTag.response, 10);
		if (1 == id) deletePost('post-' +postId);
		else if (ajaxTag.responseXML) mergeThread();
	}
	ajaxTag.method = 'POST';
	ajaxTag.runAJAX(string);
}

function ajaxNewPost() {
	var string = 'topic_id=' + topicId + '&post_content=' + encodeURIComponent(postContent.value) + '&page=' + page + '&last_mod=' + lastMod + '&action=post-add';
	ajaxTag.requestFile = uriBase + 'topic-ajax.php';
	ajaxTag.onLoading = function() {};
	ajaxTag.onLoaded = function() {};
	ajaxTag.onInteractive = function() {};
	ajaxTag.onCompletion = function() {
		var id = parseInt(ajaxTag.response, 10);
		if ( 0 == id ||  -1 == id || -2 == id || -3 == id ) alert(id);
		if ( ajaxTag.responseXML.getElementsByTagName('thread')[0] ) mergeThread();
		else appendPost();
	}
	ajaxTag.method = 'POST';
	ajaxTag.runAJAX(string);
}

function deletePost(id,norecolor) {
	if (!norecolor) norecolor = false;
	var post = document.getElementById(id);
	postsToBeDeleted.push(post);
	Fat.fade_element(id,null,700,'#FF3333');
	var post_pos = getPostPos(id);
	posts.splice(post_pos,1);
	if (norecolor)	setTimeout('thread.removeChild(postsToBeDeleted.pop())', 710);
	else		setTimeout('thread.removeChild(postsToBeDeleted.pop()); recolorPosts(post_pos,1000)', 710);
}

function appendPost() {
	var thread = document.getElementById('thread');
	var newPost = document.createElement('li');
	postId = ajaxTag.responseXML.getElementsByTagName('id')[0].firstChild.data;
	newPost.id = 'post-' + postId;
	newPost.innerHTML = ajaxTag.responseXML.getElementsByTagName('templated')[0].firstChild.data;
	thread.appendChild(newPost);
	posts.push(newPost.id);
	recolorPosts(posts.length - 1,null,'#FFFF33');
}

function mergeThread() {
	newThread = ajaxTag.responseXML.getElementsByTagName('thread')[0];
	newPosts = newThread.getElementsByTagName('post');
	newPostList = new Array();
	for (var i = 0; i < newPosts.length; i++) {
		var newPostId = newPosts[i].firstChild.firstChild.data
		var newPostContent = newPosts[i].getElementsByTagName('templated')[0].firstChild.data;
		exists = document.getElementById('post-' + newPostId);
		if (exists) {
			var oldPos = getPostPos(exists.id);
			exists.innerHTML = newPostContent;
			newPostList.push(exists.id);
			if (i % 2 == 0 && oldPos % 2 == 1) {
				Fat.fade_element(exists.id,null,1000,alt_color,reg_color);
			} else {
				if ( i % 2 == 1 && oldPos % 2 == 0 ) Fat.fade_element(exists.id,null,1000,reg_color,alt_color);
			}
		} else {
			var newPost = document.createElement('li');
			newPost.id = 'post-' + newPostId;
			newPost.innerHTML = newPostContent;
			thread.appendChild(newPost);
			newPostList.push(newPost.id);
			if ( i % 2 == 0 ) {
				Fat.fade_element(newPost.id);
			} else {
				Fat.fade_element(newPost.id,null,null,null,alt_color);
			}
		}
	}

	for (var i = 0; i < posts.length; i++) {
		var postDNE = true;
		for (var j = 0; j < newPostList.length; j++) {
			if (posts[i] == newPostList[j]) {
				postDNE = false;
				break;
			}
		}
		if (postDNE) {
			deletePost(posts[i--],true);
			continue;
		}
	}
	posts = newPostList;
}
