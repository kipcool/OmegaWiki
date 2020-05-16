jQuery(document).ready(function( $ ) {

	var suggestionTimeOut = null;

	// some delegated handlers for elements added dynamically
	$("body").on('click', ".suggest-next", function(event) {
		var suggestPrefix, suggestLink, suggestOffset;

		suggestPrefix = getSuggestPrefix( this, 'next');
		suggestLink = '#' + suggestPrefix + 'link';
		suggestOffset = $(suggestLink).attr('offset');
		$(suggestLink).attr('offset', parseInt(suggestOffset) + 10);

		updateSuggestions(suggestPrefix);
	});

	$("body").on('click', ".suggest-previous", function(event) {
		var suggestPrefix, suggestLink, suggestOffset, newOffset;

		suggestPrefix = getSuggestPrefix( this, 'previous');

		suggestLink = '#' + suggestPrefix + 'link';
		suggestOffset = $(suggestLink).attr('offset');
		newOffset = Math.max( parseInt(suggestOffset) - 10, 0 );
		$(suggestLink).attr('offset', newOffset);

		updateSuggestions(suggestPrefix);
	});

	$("body").on('click', ".suggest-close", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'close');
		$("#" + suggestPrefix + "div").hide();
	});

	$("body").on('click', ".suggest-clear", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'clear');
		updateSuggestValue(suggestPrefix, "",
			"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
	});

	$("body").on('click', ".suggest-link", function(event) {
		var suggestLinkId, suggestPrefix, suggestField;
		suggestLinkId = this.id;
		// removing the "link" at the end of the Id
		suggestPrefix = getSuggestPrefix( this, 'link');

		if ( $("#" + suggestPrefix + "div").length === 0 ) {
			createSuggestStructure(this, suggestPrefix);
		}
		$("#" + suggestPrefix + "div").show();

		suggestField = document.getElementById(suggestPrefix + "text");
		if (suggestField !== null) {
			suggestField.focus();
			updateSuggestions(suggestPrefix);
		}
	});

	$("body").on('keyup', "input.suggest-text", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'text');
		scheduleUpdateSuggestions( suggestPrefix );
	});

	function getSuggestPrefix( node, postFix ) {
		var nodeId = node.id;
		return stripSuffix( nodeId, postFix );
	}

	function scheduleUpdateSuggestions( suggestPrefix ) {
		if ( suggestionTimeOut !== null ) {
			clearTimeout( suggestionTimeOut );
		}
		$("#" + suggestPrefix + "link").attr( 'offset', 0 );
		suggestionTimeOut = setTimeout(function() {
			updateSuggestions( suggestPrefix );
		}, 600);
	}

	function stripSuffix( source, suffix ) {
		return source.substr(0, source.length - suffix.length);
	}

	/*
	 * creates the suggest form, when a field to type a word
	 * and buttons next, previous and clear
	 */
	function createSuggestStructure( element, suggestPrefix ) {
		var imgPath, suggestStructure;
		imgPath =  mw.config.get( 'wgExtensionAssetsPath' ) + '/WikiLexicalData/Images/';
		suggestStructure =
			'<div class="suggest-drop-down"><div id="' + suggestPrefix + 'div" class="suggest-div">' +
				'<table><tr>' +
					'<td><input type="text" id="' + suggestPrefix + 'text" autocomplete="off" class="suggest-text"/></td>' +
					'<td id="' + suggestPrefix + 'clear" class="suggest-clear">' + mw.message('ow_suggest_clear') + '</td>' +
					'<td id="' + suggestPrefix + 'previous" class="suggest-previous">' +
						'<img src="' + imgPath + 'ArrowLeft.png" alt="' + mw.message('ow_suggest_previous') + '"/> ' +
						mw.message('ow_suggest_previous') + '</td>' +
					'<td id="' + suggestPrefix + 'next" class="suggest-next">' + mw.message('ow_suggest_next') +
						'<img src="' + imgPath + 'ArrowRight.png" alt="' + mw.message('ow_suggest_next') + '"/></td>' +
					'<td id="' + suggestPrefix + 'close" class="suggest-close">[X]</td>' +
				'</tr></table>' +
				'<table id="' + suggestPrefix + 'table"><tr><td></td></tr></table>' +
			'</div></div>';

		$(element).after( suggestStructure );
	}

	function updateSuggestValue( suggestPrefix, value, displayValue ) {
		var suggestLinkId, suggestLink, suggestDiv, suggestField, inputId, objAtt;
		suggestLinkId = suggestPrefix + "link";
		suggestLink = document.getElementById(suggestLinkId);
		suggestDiv = document.getElementById(suggestPrefix + "div");
		suggestField = document.getElementById(stripSuffix(suggestPrefix, "-suggest-"));

		suggestField.value = value;

		suggestLink.innerHTML = displayValue;
		suggestDiv.style.display = 'none';
		suggestLink.focus();

		// if an option is changed, change also the content of the option value comobobox selector
		if ( $('#' + suggestLinkId).attr('query') === 'optnAtt' ) {
			inputId = stripSuffix( suggestPrefix, '-suggest-');
			objAtt = $('#' + suggestLinkId).attr('syntransid');
			if ( ! objAtt ) {
				objAtt = $('#' + suggestLinkId).attr('definedmeaningid');
			}
			updateSelectOptions( inputId + 'Optn', objAtt, value );
		}
	}

	/*
	* suggests a list (of languages, classes...) according to the letters typed in the query field
	* or to the arrows "next" "previous"
	* suggestPrefix is of the form "add-dm-269-syntrans-423-objAtt-rel-relation-type-suggest-"
	*/
	function updateSuggestions( suggestPrefix ) {
		// table is created by the createSuggestStructure function
		var table, suggestlink, suggestQuery, suggestOffset, dataSet, suggestAttributesLevel,
			suggestDefinedMeaningId, suggestSyntransId, suggestAnnotationAttributeId,
			suggestTextVal, getParams;

		table = $('#' + suggestPrefix + 'table');
		if ( table === null ) {
			// just in case
			return;
		}

		// the following parameters are created in forms.php
		suggestlink = '#' + suggestPrefix + 'link';
		suggestQuery = $(suggestlink).attr('query');
		suggestOffset = $(suggestlink).attr('offset');
		dataSet = $(suggestlink).attr('dataset');

		suggestAttributesLevel = $(suggestlink).attr('level');
		suggestDefinedMeaningId = $(suggestlink).attr('definedMeaningId');
		suggestSyntransId = $(suggestlink).attr('syntransId');
		suggestAnnotationAttributeId = $(suggestlink).attr('annotationAttributeId');

		suggestText = $("#" + suggestPrefix + "text");
		suggestText.addClass("suggest-loading");
		suggestTextVal = suggestText.val() ; // we copy the value to compare it later to the current value

		URL = wgScript + '?title=Special:Suggest' ;

		getParams = {
			'search-text': suggestTextVal,
			prefix: suggestPrefix,
			query: suggestQuery,
			offset: suggestOffset,
			dataset: dataSet
		};
		if (suggestAttributesLevel !== null) {
			getParams.attributesLevel = suggestAttributesLevel;
		}
		if (suggestDefinedMeaningId !== null) {
			getParams.definedMeaningId = suggestDefinedMeaningId;
		}
		if (suggestSyntransId !== null) {
			getParams.syntransId = suggestSyntransId;
		}
		if (suggestAnnotationAttributeId !== null) {
			getParams.annotationAttributeId = suggestAnnotationAttributeId;
		}

		$.get( URL, getParams, function(data) {
			var newTable, langnames, searchTxt, i, searchInTxt, position;
			newTable = document.createElement('div');
			if (data !== '') {
				newTable.innerHTML = leftTrim( data );

				// put the searched text in bold within the returned string
				if ( suggestTextVal !== "" ) {
					langnames = newTable.getElementsByTagName('td') ;
					searchTxt = suggestTextVal;
					// normalizeText removes diacritics (cf. omegawiki-ajax.js)
					searchTxt = normalizeText ( searchTxt.toLowerCase() ) ;

					for ( i=0 ; i < langnames.length ; i++ ) {
						searchInTxt = normalizeText ( langnames[i].innerHTML.toLowerCase() ) ;
						position = searchInTxt.indexOf( searchTxt );
						if ( position >= 0 ) {
							langnames[i].innerHTML = langnames[i].innerHTML.substr(0,position) +
							"<b>" +
							langnames[i].innerHTML.substr( position, searchTxt.length) +
							"</b>" +
							langnames[i].innerHTML.substr( position + searchTxt.length ) ;
						}
					}
				}
				$(table).replaceWith( newTable.firstChild );
			}
			suggestText.removeClass("suggest-loading");

			// comparing the stored value send in the URL, and the actual value
			if ( suggestTextVal !== suggestText.val() ) {
				suggestionTimeOut = setTimeout( function() {
					updateSuggestions( suggestPrefix );
				}, 100);
			}
		});
	}

	function leftTrim( sString ) {
		while (sString.substring(0,1) === ' ' || sString.substring(0,1) === "\n") {
				sString = sString.substring(1, sString.length);
			}
		return sString;
	}

	// remove accents for comparison
	function normalizeText( text ) {
		text = text.replace(new RegExp("[àáâãäå]", 'g'),"a");
		text = text.replace(new RegExp("æ", 'g'),"ae");
		text = text.replace(new RegExp("ç", 'g'),"c");
		text = text.replace(new RegExp("[èéêë]", 'g'),"e");
		text = text.replace(new RegExp("[ìíîï]", 'g'),"i");
		text = text.replace(new RegExp("ñ", 'g'),"n");
		text = text.replace(new RegExp("[òóôõö]", 'g'),"o");
		text = text.replace(new RegExp("œ", 'g'),"oe");
		text = text.replace(new RegExp("[ùúûü]", 'g'),"u");
		text = text.replace(new RegExp("[ýÿ]", 'g'),"y");
		return text ;
	}

	function updateSelectOptions(id, objectId, value) {
		var URL, location;
		URL = 'index.php';
		location = "" + document.location;

		if (location.indexOf('index.php/') > 0) {
			URL = '../' + URL;
		}
		URL = URL + '/Special:Select?optnAtt=' + encodeURI(value) + '&attribute-object=' + encodeURI(objectId);

		$.get( URL, function(data) {
			var select, options, idx, option;
			select = document.getElementById(id);
			select.options.length = 0;
			options = data.split("\n");

			for ( idx in options) {
				option = options[idx].split(";");
				select.add(new Option(option[1],option[0]),null);
			}
		});
	}

	/* some more functions to load only when ajax is complete
		* because the class "suggestion-row" is not known before that
		* and it does not work if the functions are defined outside
		* of ajaxcomplete
		* alternatively we could use delegated handlers
		*/

	$(document).ajaxComplete(function() {
		$(".suggestion-row").click(function() {
			var suggestPrefix, suggestlink, idColumnsField, displayLabelField, displayLabelColumnIndices, labels, i, columnValue, idColumns, values, ids;

			// suggestPrefix is something like add-dm-1370660-def-transl-language-suggest-
			suggestPrefix = stripSuffix( $(this).closest('.suggest-div').attr('id'), 'div' );
			suggestlink = '#' + suggestPrefix + 'link';

			// idColumnsField will be 'undefined' if not specified. Normally exists only when >= 2. Otherwise assumed to be 1.
			idColumnsField = $(suggestlink).attr('id-columns' );
			displayLabelField = $(suggestlink).attr('label-columns' );
			displayLabelColumnIndices = displayLabelField.split(", " );
			labels = [];

			for ( i = 0; i < displayLabelColumnIndices.length; i++) {
				columnValue = this.getElementsByTagName('td')[displayLabelColumnIndices[i]].innerHTML;

				if (columnValue !== "") {
					// remove the bold that we added for highlight
					columnValue = columnValue.replace ("<b>","");
					columnValue = columnValue.replace ("</b>","");
					labels.push(columnValue);
				}
			}

			idColumns = 1;

			if (idColumnsField) {
				idColumns = idColumnsField;
			}

			values = this.id.split('-' );
			ids = [];

			for ( i = idColumns - 1; i >= 0; i--) {
				ids.push(values[values.length - i - 1]);
			}
			updateSuggestValue(suggestPrefix, ids.join('-'), labels.join(', '));
		});
	});
});
