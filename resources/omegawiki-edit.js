jQuery(document).ready(function( $ ) {

	// automatically fills in the label when a url is pasted.
	$("body").on('paste', "td.url", function() {
		var tdurl = this ;
		// timeout is needed, otherwise the paste is not finished when .val() is called.
		setTimeout(function () {
			// remove any ?uselang=... or whatever, in label but also in url
			var labelurl = $(tdurl).children("input").val().replace( /\?.*$/gi, "" );
			$(tdurl).children("input").val(labelurl);

			// remove everything before the last "/"
			labelurl = labelurl.replace( /^http.*\/([^\/]+)/i, "$1" );
			labelurl = labelurl.replace( /_/g, " " );
			labelurl = labelurl.replace( /^File:/g, "" );
			labelurl = labelurl.replace( /^Image:/g, "" );
			labelurl = labelurl.replace( /.jpg$/gi, "" );
			// change the %C3 and others to their utf8 values
			labelurl = decodeURI(labelurl) ;
			// put the obtained label in the "label" input field
			$(tdurl).next("td").children("input").val(labelurl);
		}, 100);
	});

	$("body").on('click', "td.addemptyrow", function(event) {
		addEmptyRow( this.parentNode.id );
	});

	$("body").on('click', ".remove-checkbox", function(event) {
		$(this).closest("tr").toggleClass('to-be-removed');
	});

});

// add a new row for translation or definition
window.addEmptyRow = function (elementId) {
  var element, container, new_element, textAreaList, i;

  element = document.getElementById( elementId );
  container = element.parentNode ;

  // create a clone to work on
  new_element = element.cloneNode(true);
  // removes the green button for the old row
  element.firstChild.removeChild ( element.firstChild.firstChild );
  // all new textareas field should be set empty
  textAreaList = new_element.getElementsByTagName('textarea' );
  for (i=0; i<textAreaList.length ; i++)
  {
    if (textAreaList[i].type === 'text') { textAreaList[i].value = ''; }
  }

// for the spelling, it is not a textarea but an input type=text.
// (some other input fields, hidden, are needed, so we should not clear all <input> )
  var inputList = new_element.getElementsByTagName('input' );
  for ( i=0; i<inputList.length ; i++)
  {
    if (inputList[i].type === 'text') { inputList[i].value = ''; }
    if (inputList[i].name === 'onUpdate') { inputList[i].value = inputList[i].value.replace("add-","add-X-"); }
  }
  recursiveChangeId(new_element);
  // add the element as the last one (null)
  container.appendChild(new_element );
};

window.recursiveChangeId = function (element) {
  if (element === null) { return; }
  if (element.hasChildNodes()) {
    var children = element.childNodes, i;
    for ( i=0; i<children.length ; i++) { recursiveChangeId (children[i]); }
  }
  if (element.id) { element.id = element.id.replace("add-","add-X-"); }
  if (element.name) { element.name = element.name.replace("add-","add-X-"); }
  return ;
};
