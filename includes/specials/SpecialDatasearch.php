<?php
/** @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

global $wgWldOwScriptPath;
require_once $wgWldOwScriptPath . "WikiDataAPI.php";
require_once $wgWldOwScriptPath . "forms.php";
require_once $wgWldOwScriptPath . "type.php";
require_once $wgWldOwScriptPath . "ViewInformation.php";
require_once $wgWldOwScriptPath . "OmegaWikiAttributes.php";
require_once $wgWldOwScriptPath . "OmegaWikiRecordSets.php";
require_once $wgWldOwScriptPath . "OmegaWikiEditors.php";
require_once $wgWldOwScriptPath . "OmegaWikiDatabaseAPI.php";

class SpecialDatasearch extends SpecialPage {
	protected $externalIdentifierAttribute;
	protected $collectionAttribute;
	protected $collectionMemberAttribute;
	protected $externalIdentifierMatchStructure;

	protected $spellingAttribute;
	protected $languageAttribute;

	protected $expressionStructure;
	protected $expressionAttribute;

	protected $definedMeaningAttribute;
	protected $definitionAttribute;

	protected $meaningStructure;
	protected $meaningAttribute;

	private $withinWords;
	private $collectionId;
	private $languageId;
	private $withinExternalIdentifiers;
	private $languageName;
	private $searchText;
	private $show;

	private $limit = 100;
	private $offset = 0;

	private $resultCount = 0;

	function SpecialDatasearch() {
		parent::__construct( 'ow_data_search' );

		$request = $this->getRequest();
		$this->collectionId = $request->getInt( "collection" ); // default is 0
		$this->languageId = $request->getInt( "language" );
		$this->withinWords = $request->getBool( "within-words" ); // default is false
		$this->withinExternalIdentifiers = $request->getBool( "within-external-identifiers" );
		$this->languageName = languageIdAsText( $this->languageId );
		$this->searchText = $request->getText( 'search-text', null );
		$this->show = $request->getBool( 'show' );
		$this->offset = $request->getInt( 'offset', 0 );
	}

	function execute( $parameter ) {
		global $definedMeaningReferenceType;

		$output = $this->getOutput();
		$output->setPageTitle( wfMessage( 'search' )->text() );

		$this->spellingAttribute = new Attribute( "found-word", wfMessage( 'datasearch_found_word' )->text(), "short-text" );
		$this->languageAttribute = new Attribute( "language", wfMessage( 'ow_Language' )->text(), "language" );

		$this->expressionStructure = new Structure( $this->spellingAttribute, $this->languageAttribute );
		$this->expressionAttribute = new Attribute( "expression", wfMessage( 'ow_Expression' )->text(), $this->expressionStructure );

		$this->definedMeaningAttribute = new Attribute( WLD_DEFINED_MEANING, wfMessage( 'ow_DefinedMeaning' )->text(), $definedMeaningReferenceType );
		$this->definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "definition" );

		$this->meaningStructure = new Structure( $this->definedMeaningAttribute, $this->definitionAttribute );
		$this->meaningAttribute = new Attribute( "meaning", wfMessage( 'datasearch_meaning' )->text(), $this->meaningStructure );

		$this->externalIdentifierAttribute = new Attribute( "external-identifier", wfMessage( 'datasearch_ext_identifier' )->text(), "short-text" );
		$this->collectionAttribute = new Attribute( "collection", wfMessage( 'ow_Collection' )->text(), $definedMeaningReferenceType );
		$this->collectionMemberAttribute = new Attribute( "collection-member", wfMessage( 'ow_CollectionMember' )->text(), $definedMeaningReferenceType );

		$this->externalIdentifierMatchStructure = new Structure(
			$this->externalIdentifierAttribute,
			$this->collectionAttribute,
			$this->collectionMemberAttribute
		);

		$this->displayForm();

		if ( $this->show ) {
			$this->search();
		}
	}

	/**
	 * the basic form, with fields to fill in, and checkboxes to click
	 */
	function displayForm() {
		global $wgWldSearchExternalIDDefault,
			$wgWldSearchWordsDefault,
			$wgWldSearchWordsOption;

		if ( !$this->withinWords && !$this->withinExternalIdentifiers ) {
			$this->withinWords = $wgWldSearchWordsDefault;
			$this->withinExternalIdentifiers = $wgWldSearchExternalIDDefault;
		}

		$options = [];
		$options[wfMessage( 'datasearch_search_text' )->text()] = getTextBox( 'search-text', $this->searchText );

		$options[wfMessage( 'datasearch_language' )->text()]
			= getSuggest( 'language', "language", [], $this->languageId, $this->languageName );

		$options[wfMessage( 'ow_Collection_colon' )->text()]
			= getSuggest( 'collection', 'collection', [], $this->collectionId, collectionIdAsText( $this->collectionId ) );

		if ( $wgWldSearchWordsOption ) {
			$options[wfMessage( 'datasearch_within_words' )->text()] = getCheckBox( 'within-words', $this->withinWords );
		} else {
			$this->withinWords = $wgWldSearchWordsDefault;
		}

		// external ID search disabled for now
		/* if ( $wgWldSearchExternalIDOption ) {
			$options[wfMessage( 'datasearch_within_ext_ids' )->plain()]
				= getCheckBox( 'within-external-identifiers', $this->withinExternalIdentifiers );
		} else {
			$this->withinExternalIdentifiers = $wgWldSearchExternalIDDefault;
		} */

		$this->getOutput()->addHTML( getOptionPanel( $options ) );
	}

	/**
	 * outputs the html of the search result as a table
	 */
	function search() {
		$output = $this->getOutput();
		if ( $this->withinWords ) {
			if ( strlen( $this->searchText ) ) {
				if ( $this->languageId != 0 && $this->languageName != "" ) {
					$headerText = wfMessage( 'datasearch_match_words_lang', $this->languageName, $this->searchText )->text();
				} else {
					$headerText = wfMessage( 'datasearch_match_words', $this->searchText )->text();
				}
			} else {
				if ( $this->languageId != 0 && $this->languageName != "" ) {
					$headerText = wfMessage( 'datasearch-match-expr-lang', $this->languageName )->text();
				} else {
					$headerText = wfMessage( 'datasearch-match-expr' )->text();
				}
			}
			$output->addHTML( Html::rawElement( 'h1', [], $headerText ) );

			$searchResultHtmlTable = $this->searchWords();

			$ptext = wfMessage( 'showingresultsnum', '', $this->offset, $this->resultCount )->text();
			$output->addHTML( Html::rawElement( 'p', [], $ptext ) );

			// resultCount is not the total theoretical number of results
			// but only the number of results displayed.
			// (searching for the theoretical number is too long)
			// If equal to the limit, there might be more.
			if ( ( $this->resultCount >= $this->limit ) || ( $this->offset > 0 ) ) {
				// the number of results is more than the limit of displayed results
				$prevNextLinks = $this->getPrevNextLinkHtml();

				// links "previous" and "next" on top
				$output->addHTML( $prevNextLinks );
			}

			// the actual output of the words that were found
			$output->addHTML( $searchResultHtmlTable );

			if ( ( $this->resultCount >= $this->limit ) || ( $this->offset > 0 ) ) {
				if ( $this->resultCount > 0 ) {
					// links "previous" and "next" at the bottom
					$output->addHTML( $prevNextLinks );
				}
			}
		}

/* disabled for the moment
		if ( $this->withinExternalIdentifiers ) {
			$headerText = wfMessage( 'datasearch_match_ext_ids', $this->searchText )->plain();
			$output->addHTML( Html::rawElement( 'h1', array(), $headerText ) );

			$resultCount = $this->searchExternalIdentifiersCount();
			$text = wfMessage( 'datasearch_showing_only', $this->limit, $resultCount)->text();
			$output->addHTML( Html::rawElement( 'p', array(), $text ) );

			$output->addHTML( $this->searchExternalIdentifiers() );
		}
*/
	}

	function getSpellingRestriction( $spelling, $tableColumn ) {
		$dbr = wfGetDB( DB_REPLICA );

		if ( trim( $spelling ) != '' ) {
			return " AND " . $tableColumn . " LIKE " . $dbr->addQuotes( "%$spelling%" );
		} else {
			return "";
		}
	}

	function getSpellingOrderBy( $spelling ) {
		if ( trim( $spelling ) != '' ) {
			return "position ASC, ";
		} else {
			return "";
		}
	}

	function getPositionSelectColumn( $spelling, $tableColumn ) {
		$dbr = wfGetDB( DB_REPLICA );

		if ( trim( $spelling ) != '' ) {
			return "INSTR(LCASE(" . $tableColumn . "), LCASE(" . $dbr->addQuotes( "$spelling" ) . ")) as position, ";
		} else {
			return "";
		}
	}

	/**
	 * performs the search according to the given parameters
	 * returns a html table with the result
	 * and fills in the class variable resultCount
	 */
	function searchWords() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$tables = [
			'exp' => "{$dc}_expression",
			'synt' => "{$dc}_syntrans"
		];

		$fields = [
			'defined_meaning_id' => 'synt.defined_meaning_id',
			'spelling' => 'exp.spelling',
			'language_id' => 'exp.language_id'
		];

		$whereClause = [
			'exp.remove_transaction_id' => null
		];

		// default, order by is changed below in the case of a searchText
		$options = [
			'ORDER BY' => 'exp.spelling ASC',
			'LIMIT' => $this->limit,
			'STRAIGHT_JOIN' // mysql only
		];

		$join = [];

		$join['synt'] = [ 'JOIN', [
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null
		] ];

		if ( $this->offset > 0 ) {
			$options['OFFSET'] = $this->offset;
		}

		// now a few changes to the standard query according to what parameters have been supplied.

		// if a search text was given
		if ( trim( $this->searchText ) != '' ) {
			// to have first the words starting with the given searchText
			$fields['position'] = "INSTR(LCASE( exp.spelling ), LCASE('" . $this->searchText . "'))";

			$like = $dbr->buildLike( $dbr->anyString(), $this->searchText, $dbr->anyString() );
			$whereClause[] = "spelling $like";

			$options['ORDER BY'] = 'position ASC, exp.spelling ASC';
		}

		// if a language was given
		if ( $this->languageId > 0 ) {
			$whereClause['exp.language_id'] = $this->languageId;
		}

		// if a collection was given
		if ( $this->collectionId > 0 ) {
			$tables['colcont'] = "{$dc}_collection_contents";

			// without straight join, the query takes too long
			$join['colcont'] = [ 'JOIN', [
				'colcont.member_mid = synt.defined_meaning_id',
				'colcont.collection_id' => $this->collectionId,
				'colcont.remove_transaction_id' => null
			] ];
		}

		// The query for the search itself! Uses limit and offset
		$queryResult = $dbr->select(
			$tables,
			$fields,
			$whereClause,
			__METHOD__,
			$options,
			$join
		);

		$this->resultCount = $queryResult->numRows();

		$recordSet = $this->getWordsSearchResultAsRecordSet( $queryResult );

		$editor = $this->getWordsSearchResultEditor();

		$output = $editor->view( new IdStack( "words" ), $recordSet );

		return $editor->view( new IdStack( "words" ), $recordSet );
	}

	function getWordsSearchResultAsRecordSet( $queryResult ) {
		$o = OmegaWikiAttributes::getInstance();

		$dbr = wfGetDB( DB_REPLICA );
		$recordSet = new ArrayRecordSet( new Structure( $o->definedMeaningId, $this->expressionAttribute, $this->meaningAttribute ), new Structure( $o->definedMeaningId ) );

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$expressionRecord = new ArrayRecord( $this->expressionStructure );
			$expressionRecord->setAttributeValue( $this->spellingAttribute, $row->spelling );
			$expressionRecord->setAttributeValue( $this->languageAttribute, $row->language_id );

			$meaningRecord = new ArrayRecord( $this->meaningStructure );
			$meaningRecord->setAttributeValue( $this->definedMeaningAttribute, getDefinedMeaningReferenceRecord( $row->defined_meaning_id ) );
			$meaningRecord->setAttributeValue( $this->definitionAttribute, getDefinedMeaningDefinition( $row->defined_meaning_id ) );

			$recordSet->addRecord( [ $row->defined_meaning_id, $expressionRecord, $meaningRecord ] );
		}

		return $recordSet;
	}

	function getWordsSearchResultEditor() {
		$expressionEditor = new RecordTableCellEditor( $this->expressionAttribute );
		$expressionEditor->addEditor( new SpellingEditor( $this->spellingAttribute, new SimplePermissionController( false ), false ) );
		$expressionEditor->addEditor( new LanguageEditor( $this->languageAttribute, new SimplePermissionController( false ), false ) );

		$meaningEditor = new RecordTableCellEditor( $this->meaningAttribute );
		$meaningEditor->addEditor( new DefinedMeaningReferenceEditor( $this->definedMeaningAttribute, new SimplePermissionController( false ), false ) );
		$meaningEditor->addEditor( new TextEditor( $this->definitionAttribute, new SimplePermissionController( false ), false, true, 75 ) );

		$editor = createTableViewer( null );
		$editor->addEditor( $expressionEditor );
		$editor->addEditor( $meaningEditor );

		return $editor;
	}

	function searchExternalIdentifiers() {
		global $wgDBprefix;
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$sql =
			"SELECT " .
			$this->getPositionSelectColumn(
				$this->searchText,
				"{$wgDBprefix}{$dc}_collection_contents.internal_member_id"
			) .
			" {$wgDBprefix}{$dc}_collection_contents.member_mid AS member_mid, {$wgDBprefix}{$dc}_collection_contents.internal_member_id AS external_identifier, {$wgDBprefix}{$dc}_collection.collection_mid AS collection_mid " .
			"FROM {$wgDBprefix}{$dc}_collection_contents, {$wgDBprefix}{$dc}_collection ";

			$sql .=
			"WHERE {$wgDBprefix}{$dc}_collection.collection_id={$dc}_collection_contents.collection_id " .
			" AND " . getLatestTransactionRestriction( "{$wgDBprefix}{$dc}_collection" ) .
			" AND " . getLatestTransactionRestriction( "{$wgDBprefix}{$dc}_collection_contents" ) .
			$this->getSpellingRestriction( $this->searchText, "{$wgDBprefix}{$dc}_collection_contents.internal_member_id" );

		if ( $this->collectionId > 0 ) {
			$sql .=
				" AND {$wgDBprefix}{$dc}_collection.collection_id={$this->collectionId} ";
		}

		$sql .=
			" ORDER BY " . $this->getSpellingOrderBy( $this->searchText ) . "{$wgDBprefix}{$dc}_collection_contents.internal_member_id ASC limit {$this->limit}";

		$queryResult = $dbr->query( $sql );
		$recordSet = $this->getExternalIdentifiersSearchResultAsRecordSet( $queryResult );
		$editor = $this->getExternalIdentifiersSearchResultEditor();

		return $editor->view( new IdStack( "external-identifiers" ), $recordSet );
	}

	function searchExternalIdentifiersCount() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$table = [
			'colcont' => "{$dc}_collection_contents",
			'col' => "{$dc}_collection"
		];

		$wherecond = [
			'col.collection_id = colcont.collection_id',
			'col.remove_transaction_id' => null,
			'colcont.remove_transaction_id' => null
		];

		if ( $this->searchText ) {
			$wherecond[] = 'colcont.internal_member_id ' . $dbr->buildLike( $this->searchText, $dbr->anyString() );
		}

		if ( $this->collectionId > 0 ) {
			$wherecond['colcont.collection_id'] = $this->collectionId;
		}

		$queryResultCount = $dbr->selectField(
			$table,
			'COUNT(*)',
			$wherecond,
			__METHOD__
		);

		return $queryResultCount;
	}

	function getExternalIdentifiersSearchResultAsRecordSet( $queryResult ) {
		$dbr = wfGetDB( DB_REPLICA );

		$externalIdentifierMatchStructure = new Structure( $this->externalIdentifierAttribute, $this->collectionAttribute, $this->collectionMemberAttribute );
		$recordSet = new ArrayRecordSet( $externalIdentifierMatchStructure, new Structure( $this->externalIdentifierAttribute ) );

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$record = new ArrayRecord( $this->externalIdentifierMatchStructure );
			$record->setAttributeValue( $this->externalIdentifierAttribute, $row->external_identifier );
			$record->setAttributeValue( $this->collectionAttribute, $row->collection_mid );
			$record->setAttributeValue( $this->collectionMemberAttribute, $row->member_mid );

			$recordSet->add( $record );
		}

		expandDefinedMeaningReferencesInRecordSet( $recordSet, [ $this->collectionAttribute, $this->collectionMemberAttribute ] );

		return $recordSet;
	}

	function getExternalIdentifiersSearchResultEditor() {
		$editor = createTableViewer( null );
		$editor->addEditor( createShortTextViewer( $this->externalIdentifierAttribute ) );
		$editor->addEditor( createDefinedMeaningReferenceViewer( $this->collectionMemberAttribute ) );
		$editor->addEditor( createDefinedMeaningReferenceViewer( $this->collectionAttribute ) );

		return $editor;
	}

	/**
	 * $this->resultCount is a number of results displayed on the page
	 * that must be compared to "limit" to see if there are more results
	 */
	function getPrevNextLinkHtml() {
		$linksHtml = Html::openElement( 'p' );
		// currentQuery, an array of the parameters passed in the url
		$currentQuery = $this->getRequest()->getValues();

		// PREVIOUS
		$prevText = $this->msg( 'prevn' )->numParams( $this->limit )->escaped();
		$linksHtml .= '(';
		if ( $this->offset > 0 ) {
			$prevQuery = $currentQuery;
			$prevOffset = max( $this->offset - $this->limit, 0 );
			if ( $prevOffset > 0 ) {
				$prevQuery['offset'] = $prevOffset;
			} else {
				unset( $prevQuery['offset'] );
			}
			$prevLinkTitle = $this->getPageTitle();
			$prevLink = Linker::linkKnown(
				$prevLinkTitle,
				$prevText,
				[],
				$prevQuery
			);
			$linksHtml .= $prevLink;
		} else {
			// no link
			$linksHtml .= $prevText;
		}
		$linksHtml .= ')';

		// NEXT
		$nextText = $this->msg( 'nextn' )->numParams( $this->limit )->escaped();
		$linksHtml .= '(';
		if ( $this->limit > $this->resultCount ) {
			// no link
			$linksHtml .= $nextText;
		} else {
			// add a link
			// here is also the case where limit == resultCount, since resultCount
			// is only the number of results displayed
			$nextQuery = $currentQuery;
			$nextOffset = $this->offset + $this->limit;
			$nextQuery['offset'] = $nextOffset;
			$nextLinkTitle = $this->getPageTitle();
			$nextLink = Linker::linkKnown(
				$nextLinkTitle,
				$nextText,
				[],
				$nextQuery
			);
			$linksHtml .= $nextLink;
		}
		$linksHtml .= ')';
		$linksHtml .= Html::closeElement( 'p' );

		return $linksHtml;
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
