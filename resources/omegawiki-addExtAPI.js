// hide these buttons even before the document is ready.
$('#ext-data').hide();
$('#owl-data').hide();
$('#owl_def').hide('');
$('#ext_def').hide('');
$('#flexible_form').hide();
$('#inputSelectButton').hide();
$('#inputSkipSelectButton').hide();

jQuery(document).ready( function( $ ) {

	$('#flexible_form').slideDown( 'slow' );
	createExternalAPIDataChoice();

	// process the selected items at flexible forms
	$('#selectChecks').on('click', function(event) {
		var selectValue = $('#inputSelectButton').val();
		if ( selectValue === 'process' ) {
			processSelectChecksPhaseOne();
		}

		if ( selectValue === 'submit' ) {
			processSelectChecksPhaseTwo();
		}
	});

	// skip the selected items at flexible forms
	$('#skipChecks').on('click', function(event) {
		createExternalAPIDataChoice();

		$('input.choices').on('click', function(event) {
			checkInputChoices( this );
		});

		$('input.choosing').on('click', function(event) {
			checkInputChoices( this );
		});

	});

	// toggle input.choices' value ( null or the value of $(this).attr('name') )
	$('input.choices').on('click', function(event) {
		checkInputChoices( this );
	});

	// toggle input.choosing' value ( null or the value of $(this).attr('name') )
	$('input.choosing').on('click', function(event) {
		checkInputChoices( this );
	});

	function checkInputChoices( selectorName ) {
		if ( $(selectorName).val() === '' ) {
			if( !$(selectorName).attr('name') ) {
				$(selectorName).val( 'selected' );
			} else {
				$(selectorName).val( $(selectorName).attr('name') );
			}
		} else {
			$(selectorName).val( '' );
		}
		console.log( 'value changed to ' + $(selectorName).val() );
	}

	// adds the necessary output to the screen
	function createExternalAPIDataChoice() {
		$('#owl_def').slideUp('');
		$('#ext_def').slideUp('');
		var owlExists = nextOwlExists(); // check if there are still DM to process

		if( owlExists === true ) {
			var	owlLineProcessed = false;
			var addOwlHtml = '';
			var addExtHtml = '<table><tr>';

			$('#inputSelectButton').attr( 'value', 'process' ).show();
			$('#inputSkipSelectButton').attr( 'value', 'next' ).show();

			// get owlLexicalData
			var owlLexicalData = getOwlData();

			var ctr = 0;
			owlLexicalData.forEach( function( oLine ) {
			//	if( owlLineProcessed === false && owlLexicalData[ctr]['processed'] === null ) {
				if( owlLineProcessed === false && owlLexicalData[ctr].processed === null ) {
					owlLineProcessed = true;
				//	owlLexicalData[ctr]['processed'] = true;
					owlLexicalData[ctr].processed = true;
					if ( oLine.syn === null ) {
						oLineSyn = '';
					} else {
						oLineSyn = JSON.stringify( oLine.syn );
					}
					addOwlHtml =
						'<h2>DefinedMeaning:' + oLine.e + ' (' + oLine.dm_id + ')</h2>' + oLine.text +
						'<span id="owl_def_syn" style="visibility:hidden;display:none;">' +
						oLineSyn + '</span><hr/>\n'
					;
					$('#owl_def').attr( 'dm_id', oLine.dm_id );
					$('#owl_def').attr( 'lang_id', oLine.lang_id );
					$('#owl_def').html( addOwlHtml );
					$('#owl_def').slideDown('slow');

				}
				ctr++;
			});

			// get extLexicalData
			var extLexicalData = getExtData();

			ctr = 0;
			extLexicalData.forEach( function( eLine ) {
				addExtHtml = addExtHtml + '<td><input class="choices" name="' + ctr + '" value="" type="checkbox"/>' +
					eLine.src + '</td><td>' + eLine.partOfSpeech + '</td><td>' + eLine.text + '</td></tr><tr>\n';
				ctr++;
			});
			addExtHtml = addExtHtml + '</tr></table>';

			// refresh flexible_form id
			$('#ext_def').html( addExtHtml );
			$('#ext_def').slideDown('slow');

			// refresh owl-data id
			owlData = JSON.stringify( owlLexicalData );
			$('#owl-data').text( owlData );
		} else {
			resetFlexibleForm();
			$('#owl_def').text( 'Finished. No more DefinedMeaning to process.' ).slideDown('slow');
		}
		return true;
	}

	// @return array either the external data or an empty array
	function getExtData() {
		var thisData = [];
		var extData = $('#ext-data').text();
		if ( extData ) {
			thisData = JSON.parse( extData );
		}
		return thisData;
	}

	/**
	 * @return string HTML string of synonyms from external source to choose from.
	 */
	function getExtSynonym( rwWords, dmId, langId, src ) {
		var includedWord = '';
		rwWords.forEach(function( theWord ){
			var owlDefSyn = $('#owl_def_syn').text();
			var includeTheWord = true;
			if ( owlDefSyn !== '' ) {
				owlDefSyn = JSON.parse( owlDefSyn );
				owlDefSyn.forEach( function( owlSynonym ) {
					if ( theWord === owlSynonym[0] ) {
						includeTheWord = false;
					}
				});
			}
			if ( includeTheWord === true ) {
				includedWord +=
					'<td><input class="choosing" value="" type="checkbox" ' +
					'action="owAddSyntrans" ' +
					'e="' + theWord + '" ' +
					'dm="' + dmId + '" ' +
					'lang="' + langId + '" ' +
					'src="' + src + '" ' +
					'im="' + '' + '" ' + // he: future implementation?
				'/>' +
				'add synonym' + '</td><td>' + theWord + '</td><td>~ ' + src + '</td></tr>\n<tr>';
			}
		});
		return includedWord;
	}

	// @return array either the OmegaWikiLexical data or an empty array
	function getOwlData() {
		var thisData = [];
		var owlData = $('#owl-data').text();
		if ( owlData ) {
			thisData = JSON.parse( owlData );
		}
		return thisData;
	}

	// checks if there are still DMs to process
	function nextOwlExists() {
		owlData = getOwlData();
		owlExists = false;
		owlData.forEach( function( data ) { if ( data.processed === null) {
			owlExists = true;
		}});
		return owlExists;
	}

	function processSelectChecksPhaseOne() {
		var extDef = $('#ext_def').html();
		var owlDef = $('#owl_def').html();
		var dmId = $('#owl_def').attr('dm_id');
		var langId = $('#owl_def').attr('lang_id');
		var extData = getExtData();
		var myChoices = extDef.match( /value="\d+"/gm );

		if ( !myChoices ) {
			alert( 'No definition selected.' );
		} else {
			var addExtHtml = '';

			var ctr = 0;
			extData.forEach(function( definition ) {
				if( definition.relatedWords ) {
					definition.relatedWords.forEach( function( rw ) {
						myChoices.forEach( function( myChoiceLine ) {
							ctrChoice = myChoiceLine.match( /\d+/ ) + '.';
							if ( ctr + '.' === ctrChoice ) {
								if( rw.relationshipType === 'synonym' ) {
								//	console.log( rw['words'] );
									addExtHtml += getExtSynonym( rw.words, dmId, langId, definition.src );
								}
							}
						});
					});
				}
				ctr++;
			});

			if( addExtHtml !== '' ) {
				$('#ext_def').slideUp();
				$('#inputSelectButton').attr( 'value', 'submit' );
				$('#inputSkipSelectButton').attr( 'value', 'skip' );
				addExtHtml = '<table><tr>' + addExtHtml + '</tr></table>';
				$('#ext_def').html( addExtHtml );
				$('#ext_def').slideDown();
			} else {
				if ( nextOwlExists() === true ) {
					alert( 'Nothing to add.\nSkipping to next definition.');
				} else {
					alert( 'Nothing to add.\nNo more DefinedMeaning left.');
				}
				createExternalAPIDataChoice();
			}

		}

		$('input.choices').on('click', function(event) {
			checkInputChoices( this );
		});

		$('input.choosing').on('click', function(event) {
			checkInputChoices( this );
		});

	}

	function processSelectChecksPhaseTwo() {
		var URL = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScript' );
		URL = URL + '/Special:Ow_addFromExtAPI';
		console.log( 'Website:' + URL );
		var myChoices = [];
		console.log( 'choosing search result: ' + $('#ext_def').html().search( /<input class="choosing".+value="selected".+type="checkbox">/gm ) );
		if ( $('#ext_def').html().search( /<input class="choosing".+value="selected".+type="checkbox">/gm ) !== -1 ) {
			console.log( 'found choosing' );
			myChoices = $('#ext_def').html().match( /<input class="choosing".+value="selected".+type="checkbox">/gm );
		} else {
			alert( 'nothing selected' );
		}

		var tid = null;
		var processHowMany = myChoices.length;
		var processedAll = 0;
		var processStatement = '';

		myChoices.forEach( function( line ){
			console.log( line + ' :' + typeof line  );
			var exp = line.match( / e=".+" dm/ ).shift().match( /".+"/ ).shift().slice( 1, -1 );
			var dmId = line.match( / dm="\d+"/ ).shift().match( /\d+/ ).shift();
			var langId = line.match( / lang="\d+"/ ).shift().match( /\d+/ ).shift();
			var src = line.match( / src=".+" im/ ).shift().match( /".+"/ ).shift().slice( 1, -1 );

			saveData = {
				'e': exp,
				'dm-id': dmId,
				'lang-id': langId,
				'src': src,
				'save-data':'synonym',
				'printable':'yes'
			};

			if ( tid !== null ) {
				saveData.tid = '' + tid;
				saveData.transacted = true;
			}

			$.post( URL, saveData, function( data, status ) {
				console.log( 'Data: ' + data + '\nStatus: ' + status );
			//	data = JSON.parse( '{' + data + '}' );
				data = JSON.parse( data );
				if ( data.note !== 'test run only' ) {
					tid = data.tid;
				}
				if ( !data.note ) {
					data.note = '';
				} else {
					data.note += '.';
				}

				processStatement += data.status + ' ';
				if ( data.status === 'exists' ) {
					processStatement = '<br>\n' + data.e + ' ' + processStatement + 'in ' + data.in;
				//	processStatement += ' in ' + data.in;
				} else {
					processStatement =  '<br>\n' + processStatement + data.e +' to ' + data.to;
				}
				processStatement += '. ' + data.note + '<br/>\n'
					;
				processedAll++;

				if ( processedAll === processHowMany ) {
					processStatement += '<br\n>';
					console.log( processStatement );
					// remove ext_def and replace with statement
					$('#ext_def').html( processStatement );
					$('#inputSelectButton').hide();
					$('#inputSkipSelectButton').attr( 'value', 'next' );
				}

			});

		});


		$('input.choices').on('click', function(event) {
			checkInputChoices( this );
		});

		$('input.choosing').on('click', function(event) {
			checkInputChoices( this );
		});

	}

	// basically, blanks the output divs
	function resetFlexibleForm() {
		$('#owl_def').text('');
		$('#ext_def').text('');
		$('#owl-data').text('');
		$('#ext-data').text('');
		$('#inputSelectButton').hide();
		$('#inputSkipSelectButton').hide();
	}

});
