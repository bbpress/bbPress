jQuery( function($) { // In here $ is jQuery

var handleText = 'drag';
var handle = "<strong class='sort-handle'>[" + handleText + "]&nbsp;</strong>";
var sortCfg = {
	accept: 'forum',
	handle: 'strong.sort-handle',
	opacity: .3,
	helperclass: 'helper',
	onStop: function() {
		place = null;
		bbSortRecolor();
	}
}
var editText = '';
var saveText = '';
var place; // The id of the list item it's currently hovering before
var placed; // The id of the list item it's been made a child of

// Save the raquo!
var div = document.createElement('div'); div.innerHTML = 'Save Forum Order &#187;';
saveText = div.childNodes[0].nodeValue;
div = null;

// overwrite with more advanced function
jQuery.iSort.checkhover = function(e,o) {
	if (!jQuery.iDrag.dragged)
		return;

	if ( e.dropCfg.el.size() > 0 ) {
		var bottom = jQuery.grep(e.dropCfg.el, function(i) { // All the list items whose bottom edges are inside the draggable
			return i.pos.y + i.pos.hb > jQuery.iDrag.dragged.dragCfg.ny && i.pos.y + i.pos.hb < jQuery.iDrag.dragged.dragCfg.ny + 30 && i.pos.x < jQuery.iDrag.dragged.dragCfg.nx;
		} );

		if ( bottom.length > 0 ) { // Use the lowest one one the totem pole
			if ( placed != bottom[bottom.length-1].id || bottom[bottom.length-1].pos.x + 30 > jQuery.iDrag.dragged.dragCfg.nx ) { // Testing to see if still placed in shifted box
				placed = null;
				jQuery(bottom[bottom.length-1]).after(jQuery.iSort.helper.get(0));
			}
			bbCheckHover(bottom[bottom.length-1], bottom[bottom.length-1].pos.x + 30 < jQuery.iDrag.dragged.dragCfg.nx); // If far enough right, shift it over
			return;
		}

		// Didn't find anything by checking bottems.  Look at tops
		var top = jQuery.grep(e.dropCfg.el, function(i) { // All the list items whose top edges are inside the draggable
			return i.pos.y > jQuery.iDrag.dragged.dragCfg.ny && i.pos.y < jQuery.iDrag.dragged.dragCfg.ny + 30 && i.pos.x < jQuery.iDrag.dragged.dragCfg.nx;
		} );

		if ( top.length ) { // Use the highest one (should be only one)
			jQuery(top[0]).before(jQuery.iSort.helper.get(0));
			bbCheckHover(top[0], false);
			return;
		}
	}
	jQuery.iSort.helper.get(0).style.display = 'block';
}

function bbSortRecolor() {
	$('#the-list li:gt(0)').css( 'background-color', '' ).filter(':even').removeClass('alt').end().filter(':odd').addClass('alt');
}

function bbCheckHover(el, doit) {
	if ( place == el.id && doit )
		return;

	if ( !doit ) {
		place = null;
		return;
	}

	place = el.id;
	if ( $('#' + place).children('ul[li]').size() ) // Don't shift over if there's already a UL with stuff in it
		return;

	var id = 'forum-root-' + place.split('-')[1];
	$('#' + place).not('[ul]').append("<ul id='" + id + "' class='list-block holder'><ul>").end().children('ul').append(jQuery.iSort.helper.get(0)); // Place in shifted box
	placed = 'forum-' + place.split('-')[1];
}

function bbSerialize() {
	h = '';
	$('#the-list, #the-list ul').each( function() {
		var i = this.id;
		$('#' + i + '> .forum').each( function () {
			if (h.length > 0)
				h += '&';
			var root = 'the-list' == i ? 0 : i.split('-')[2];
			h += 'root[' + root + '][]=' + this.id.split('-')[1];
		} );
	} );
	return h;
}

$('#the-list').after("<p class='submit'><input type='button' id='forum-order-edit' value='Edit Forum Order &#187;' /></p>");
editText = $('#forum-order-edit').val();

$('#add-forum').submit( function() {
	theList.alt = 'alt';
	theList.showLink = 0;
	theList.addComplete = function() {
		var last = $('#the-list li:last').children('div').prepend(handle).end()[0];
		$('#the-list').SortableAddItem(last);
	}

	theList.ajaxAdder( 'forum', 'add-forum' );
	return false;
} );

$('#forum-order-edit').toggle( function() {
	$(this).val(saveText);
	$('#the-list li:gt(0)').children('div').prepend(handle);
	$('#the-list').Sortable( sortCfg );
}, function() {
	$(this).val(editText);
	$('.sort-handle').remove();

	var hash = bbSerialize();
	hash += '&' + $.SortSerialize('the-list').hash.replace(/the-list/g, 'order').replace(/forum-/g, '')
	$('#the-list').SortableDestroy();

	$.post(
		'admin-ajax.php',
		'action=order-forums&cookie=' + encodeURIComponent(document.cookie) + '&' + hash
	);
} );

} );
