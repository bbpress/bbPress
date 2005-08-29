var ajaxTag;
var ajaxFav;
var ajaxRes;
var newtag;
var tagId;
var userId;

var ajaxPost;
var thread;
var posts = new Array();
var postContent = false;
var reg_color = false;
var alt_color = false;
var postsToBeDeleted = new Array();
 
function getPostsAndColors() {
	if (thread) return;
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
	if (!newtag) return;
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
	if (!resolvedSub) return;
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
addLoadEvent(newPostAddIn);

function getResponseElement(type) {
	switch (type) {
	case 'post-add':
		var s = document.getElementById('ajaxpostresponse');
		if (!s) {
			s = document.createElement('span');
			document.getElementById('postformsub').parentNode.appendChild(s);
			s.id = 'ajaxpostresponse';
		}
		return s;
		break;
	case 'tag':
		var p = document.getElementById('ajaxtagresponse');
		if (!p) {
			p = document.createElement('p');
			document.getElementById('tags-bad-ie').appendChild(p);
			p.id = 'ajaxtagresponse';
		}
		return p;
		break;
	case 'fav':
		return document.getElementById('favoritestoggle');
		break;
	case 'res':
		var s = document.getElementById('ajaxresresponse');
		if (!s) {
			var s = document.createElement('span');
			document.getElementById('resolutionflipper').appendChild(s);
			s.id = 'ajaxtagresponse';
		}
		return s;
		break;
	case 'post-del':
		var l = document.getElementById('ajaxpostdelresponse');
		if (!s) {
			l = document.createElement('li');
			document.getElementById('favoritestoggle').parentNode.appendChild(l);
			l.id = 'ajaxpostdelresponse';
		}
		return l;
		break;
	}
}

function newTagCompletion() {
	var id = parseInt(ajaxTag.response, 10);
	var tagId = ajaxTag.responseXML.getElementsByTagName('id')[0].firstChild.nodeValue;
	var userId = ajaxTag.responseXML.getElementsByTagName('user')[0].firstChild.nodeValue;
	var raw = ajaxTag.responseXML.getElementsByTagName('raw')[0].firstChild.nodeValue;
	var cooked = ajaxTag.responseXML.getElementsByTagName('cooked')[0].firstChild.nodeValue;
	if (id == '-1') {
		ajaxTagmyResponseElement.innerHTML = "You don't have permission to do that.";
		return;
	}
	if (id == '0') {
		ajaxTag.myResponseElement.innerHTML = "Tag not added. Try something else.";
		return;
	}
	ajaxTag.myResponseElement.parentNode.removeChild(ajaxTag.myResponseElement);
	var yourTags = document.getElementById('yourtags');
	if (!yourTags) {
		var tags = document.getElementById('tags-bad-ie');
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
	var id = parseInt(ajaxTag.response, 10);
	var tagId = ajaxTag.responseXML.getElementsByTagName('id')[0].firstChild.nodeValue;
	var userId = ajaxTag.responseXML.getElementsByTagName('user')[0].firstChild.nodeValue;
	if (id == '-1') {
		ajaxTag.myResponseElement.innerHTML = "You don't have permission to do that.";
		return;
	}
	if (id == '0') {
		ajaxTag.myResponseElement.innerHTML = "Tag not removed. Try something else.";
		return;
	}
	ajaxTag.myResponseElement.parentNode.removeChild(ajaxTag.myResponseElement);
	oldTag = document.getElementById('tag-' + tagId + '-' + userId);
	Fat.fade_element(oldTag.id,null,700,'#FF3333');
	setTimeout('oldTag.parentNode.removeChild(oldTag)', 705);
}

function ajaxNewTag() {
	ajaxTag = new sack(uriBase + 'topic-ajax.php');
	ajaxTag.myResponseElement = getResponseElement('tag');
	ajaxTag.encodeURIString = false;
	ajaxTag.method = 'POST';
	ajaxTag.onLoading = function() { ajaxTag.myResponseElement.innerHTML = 'Sending Data...'; };
	ajaxTag.onLoaded = function() { ajaxTag.myResponseElement.innerHTML = 'Data Sent...'; };
	ajaxTag.onInteractive = function() { ajaxTag.myResponseElement.innerHTML = 'Processing Data...'; };
	ajaxTag.onCompletion = newTagCompletion;
	ajaxTag.runAJAX('tag=' + encodeURIComponent(newtag.value) + '&id=' + topicId + '&action=tag-add');
}

function ajaxDelTag(tag, user, tagName) {
	if (!confirm('Are you sure you want to remove the "' + tagName + '" tag?')) return false;
	ajaxTag = new sack(uriBase + 'topic-ajax.php');
	if (ajaxTag.failed) return true;
	ajaxTag.myResponseElement = getResponseElement('tag');
	tagId = tag;
	userId = user;
	ajaxTag.method = 'POST';
	ajaxTag.onLoading = function() { ajaxTag.myResponseElement.innerHTML = 'Sending Data...'; };
	ajaxTag.onLoaded = function() { ajaxTag.myResponseElement.innerHTML = 'Data Sent...'; };
	ajaxTag.onInteractive = function() { ajaxTag.myResponseElement.innerHTML = 'Processing Data...'; };
	ajaxTag.onCompletion = delTagCompletion;
	ajaxTag.runAJAX('tag=' + tagId + '&user=' + userId + '&topic=' + topicId + '&action=tag-remove');
	return false;
}

function FavIt(id, addFav) {
	ajaxFav = new sack(uriBase + 'topic-ajax.php');
	ajaxFav.myResponseElement = getResponseElement('fav');
	if (addFav) string = 'favorite-add';
	else string = 'favorite-remove';
	ajaxFav.onLoading = function() { ajaxFav.myResponseElement.innerHTML = 'Sending Data...'; };
	ajaxFav.onLoaded = function() { ajaxFav.myResponseElement.innerHTML = 'Data Sent...'; };
	ajaxFav.onInteractive = function() { ajaxFav.myResponseElement.innerHTML = 'Processing Data...'; };
	ajaxFav.onCompletion = function() {
		var id = parseInt(ajaxFav.response, 10);
		if (1 == id) {
			if (addFav) isFav = 1;
			else isFav = 0;
			favoritesAddIn();
			Fat.fade_element('favoritestoggle');
		} else {
			ajaxFav.myResponseElement.innerHTML = 'Something odd happened.';
		}
	}
	ajaxFav.method = 'POST';
	ajaxFav.runAJAX('topic_id=' + id + '&user_id=' + currentUserId + '&action=' + string);
}

function resolveTopic(event) {
	ajaxRes = new sack(uriBase + 'topic-ajax.php');
	ajaxRes.myResponseElement = getResponseElement('res');
	var resolvedSel = document.getElementById('resolvedformsel');
	ajaxRes.onLoading = function() { ajaxRes.myResponseElement.innerHTML = '<br />Sending Data...'; };
	ajaxRes.onLoaded = function() { ajaxRes.myResponseElement.innerHTML = '<br />Data Sent...'; };
	ajaxRes.onInteractive = function() { ajaxRes.myResponseElement.innerHTML = '<br />Processing Data...'; };
	ajaxRes.onCompletion = function() {
		var id = parseInt(ajaxRes.response, 10);
		if (1 == id) {
			ajaxRes.myResponseElement.parentNode.removeChild(ajaxRes.myResponseElement);
			Fat.fade_element('resolutionflipper');
			Fat.fade_element('resolvedformsel');
		} else {
			ajaxRes.myResponseElement.innerHTML = '<br />Something odd happened.';
		}
	}
	ajaxRes.method = 'POST';
	ajaxRes.runAJAX('id=' + topicId + '&resolved=' + resolvedSel.value + '&action=topic-resolve');
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

function ajaxPostDelete(postId, postAuthor) {
	if (!confirm('Are you sure you wanna delete this post by "' + postAuthor + '"?')) return false;
	ajaxPost = new sack(uriBase + 'topic-ajax.php');
	if (ajaxPost.failed) return true;
	getPostsAndColors();
	ajaxPost.myResponseElement = getResponseElement('post-del');
	ajaxPost.onLoading = function() { ajaxPost.myResponseElement.innerHTML = 'Sending Data...'; };
	ajaxPost.onLoaded = function() { ajaxPost.myResponseElement.innerHTML = 'Data Sent...'; };
	ajaxPost.onInteractive = function() { ajaxPost.myResponseElement.innerHTML = 'Processing Data...'; };
	ajaxPost.onCompletion = function() {
		var id = parseInt(ajaxPost.response, 10);
		if (1 == id) deletePost('post-' +postId);
		else if (ajaxPost.responseXML) mergeThread();
		else { ajaxPost.myResponseElement.innerHTML = 'Something odd happened.'; return; }
		ajaxPost.myResponseElement.parentNode.removeChild(ajaxPost.myResponseElement);
	}
	ajaxPost.method = 'POST';
	ajaxPost.runAJAX('id=' + postId + '&page=' + page + '&last_mod=' + lastMod + '&action=post-delete');
	return false;
}

function ajaxNewPost() {
	getPostsAndColors();
	ajaxPost = new sack(uriBase + 'topic-ajax.php');
	ajaxPost.myResponseElement = getResponseElement('post-add');
	var string = 'topic_id=' + topicId + '&post_content=' + encodeURIComponent(postContent.value) + '&page=' + page + '&last_mod=' + lastMod + '&action=post-add';
	ajaxPost.encodeURIString = false;
	ajaxPost.onLoading = function() { ajaxPost.myResponseElement.innerHTML = 'Sending Data...'; };
	ajaxPost.onLoaded = function() { ajaxPost.myResponseElement.innerHTML = 'Data Sent...'; };
	ajaxPost.onInteractive = function() { ajaxPost.myResponseElement.innerHTML = 'Processing Data...'; };
	ajaxPost.onCompletion = function() {
		var id = parseInt(ajaxPost.response, 10);
		if ( 0 == id ||  -1 == id || -2 == id || -3 == id ) { ajaxPost.myResponseElement.innerHTML = 'Something odd (#' + id + ') happened.'; return; }
		if ( ajaxPost.responseXML.getElementsByTagName('thread')[0] ) mergeThread();
		else appendPost();
		ajaxPost.myResponseElement.parentNode.removeChild(ajaxPost.myResponseElement);
		postContent.value = '';
	}
	ajaxPost.method = 'POST';
	ajaxPost.runAJAX(string);
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
	postId = ajaxPost.responseXML.getElementsByTagName('id')[0].firstChild.data;
	newPost.id = 'post-' + postId;
	newPost.innerHTML = ajaxPost.responseXML.getElementsByTagName('templated')[0].firstChild.data;
	thread.appendChild(newPost);
	posts.push(newPost.id);
	recolorPosts(posts.length - 1,null,'#FFFF33');
}

function mergeThread() {
	newThread = ajaxPost.responseXML.getElementsByTagName('thread')[0];
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
