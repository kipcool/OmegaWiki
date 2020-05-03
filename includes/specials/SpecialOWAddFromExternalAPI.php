<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

require_once __DIR__ . "/../../OmegaWiki/OmegaWikiDatabaseAPI.php";
/** @file
 * @brief Special Page to add data from external sources via APIs
 *
 * This special page consist of the special page to process data and a
 *	web api that is used by its accompanying js script ( omegawiki-addExtAPI.js ).
 *	Currently only adds synonyms via an external Wordnik API.
 *
 *	First release: September 2014.
 *
 * @note he: This is not as simple as I thought. So phase one will only be adding synonyms.
 *
 * @todo phase 2: text annotations
 *
 * @note he: limit SP to editwikidata-uw and exclude blocked users. Is this enough
 *	or too much?
 */
class SpecialOWAddFromExternalAPI extends SpecialPage {

	private $saveResult;	// string Used to output JSON via this class' web API (POST).
	private $saveType;	// string the type to save. see save function.

	function __construct() {
		global $wgWldProcessExternalAPIClasses;
		if ( $wgWldProcessExternalAPIClasses ) {
			parent::__construct( 'ow_addFromExtAPI' );
		} else {
			parent::__construct( 'ow_addFromExtAPI', 'UnlistedSpecialPage' );
		}
	}

	public function doesWrites() {
		return true;
	}

	/** @brief This function is used as a web API by omegawiki-addExtAPI.js
	 *	This function directs the program to the needed save function via $this->saveType.
	 *	Afterwards, outputs a JSON string.
	 */
	private function save() {
		switch ( $this->saveType ) {
			case 'synonym':
				$this->saveSynonym();
				break;
		}

		// disable wgOut in order to output only the JSON string.
		global $wgOut;
		$wgOut->disable();
		echo json_encode( $this->saveResult );
	}

	/** @brief This is the save function that handles adding Synonyms.
	 */
	private function saveSynonym() {
		$definedMeaningId = $_POST['dm-id'] ?? '';
		$languageId = $_POST['lang-id'] ?? '';
		$spelling = $_POST['e'] ?? '';
		$identicalMeaning = $_POST['im'] ?? 1;
		$transactionId = $_POST['tid'] ?? '';
		$transacted = $_POST['transacted'] ?? false;
		$source = $_POST['src'] ?? '';

		// @todo create checks for correctness
		if ( $identicalMeaning === true ) {
			$identicalMeaning = 1;
		}
		if ( $identicalMeaning === false ) {
			$identicalMeaning = 0;
		}
		if ( $identicalMeaning === '' ) {
			$identicalMeaning = 1;
		}

		$options = [
			'ver' => '1.1',
		// 'test' => true,
			'dc' => 'uw',
			'transacted' => $transacted,
			'addedBy' => 'SpecialPage SpecialOWAddFromExternalAPI'
		// 'tid',
		// 'updateId'
		];

		if ( $transactionId ) {
			$options['tid'] = $transactionId;
			$options['updateId'] = $transactionId;
		}
		$this->saveResult = Syntrans::addWithNotes( $spelling, $languageId, $definedMeaningId, $identicalMeaning, $options );
	}

	private function process() {
		global $wgWldProcessExternalAPIClasses;

		// limit access to wikidata editors
		if ( !$this->getUser()->isAllowed( 'editwikidata-uw' ) ) {
			$this->dieUsage( 'you must have a WikiLexicalData edit flag to use this page', 'wld_edit_only' );
		}

		// keep blocked users out
		if ( $this->getUser()->isBlocked() ) {
			$this->dieUsage( 'your account is blocked.', 'blocked' );
		}

		$sourceLanguageId = $_GET['from-lang'] ?? '';
		$source = $_GET['api'] ?? '';
		$search = $_GET['search-ext'] ?? '';
		$collectionId = $_GET['collection'] ?? '';

		switch ( $source ) {
			case 'Wordnik':
				$this->requireWordnik();
				break;
			case 'Wordnik-Wiktionary':
				$this->requireWordnik();
				break;
			case 'Wordnik-Wordnet':
				$this->requireWordnik();
				break;
		}

		$externalResourceClass = 'ExternalResources';
		if ( $source ) {
			foreach ( $wgWldProcessExternalAPIClasses as $sourceClass => $sourceValue ) {
				if ( $source === $sourceClass ) {
					$externalResourceClass = $sourceClass;
				}
			}
		}

		$handlerInstance = new $externalResourceClass(
			wfMessage( 'ow_addFromExtAPI_title' )->text(),
			$source,
			$sourceLanguageId,
			$search,
			$collectionId
		);
		$handlerInstance->execute();
	}

	/** execute the special page.
	 *
	 * separates the save from the process functions
	 */
	function execute( $par ) {
		$this->saveType = $_POST['save-data'] ?? '';
		if ( $this->saveType ) {
			$this->save();
		} else {
			$this->process();
		}
	}

	protected function requireWordik() {
		require_once 'ExternalWordnik.php';
	}

	protected function getGroupName() {
		return 'maintenance';
	}
}

/** @brief This class handles External Resources.
 *
 * This class is the base of individual external resources
 *
 * @note: To extend this class, the extended class needs its own __construct, execute,
 *	checkExternalDefinition, setExternalDefinition functions.
 */
class ExternalResources {

	protected $wgOut;
	protected $externalLexicalData = [];
	protected $owlLexicalData = [];
	protected $spTitle;
	protected $source;
	protected $sourceLanguageId;
	protected $collectionId;
	protected $externalDefinition;
	protected $externalExists = false;		// bool
	protected $owlDefinition;
	protected $owlExists = false;			// bool

	/**
	 * @param string $spTitle Special Page Title
	 * @param string $source the source dictionary
	 * @param int $sourceLanguageId The languageId of the source dictionary
	 * @param string $search The expression/spelling( word ) to be searched
	 * @param int $collectionId The Collection Id
	 */
	function __construct( $spTitle, $source, $sourceLanguageId, $search, $collectionId ) {
		global $wgOut;
		$this->wgOut = $wgOut;
		$this->spTitle = $spTitle;
		$this->source = $source;
		$this->sourceLabel = $source;
		$this->sourceLanguageId = $sourceLanguageId;
		$this->search = $search;
		$this->collectionId = $collectionId;
	}

	public function execute() {
		$this->outputTitle();
		$this->getOptionPanel();
		$this->checkConnectionStatus();

		// inline css ( for future refactoring )
		// removes from data when finished testing
		$this->wgOut->addHTML( $this->temporaryCodeSpace() );

		if ( $this->source ) {
			switch ( $this->connection ) {
				case true:
					$this->checkExternalDefinition();
					$this->checkOmegaWikiDefinition();
					if ( $this->externalExists and $this->owlExists ) {
						$this->setExternalDefinition();
						$this->setOmegaWikiDefinition();
						$this->createChoice();
					}
					break;
				case false:
					$this->wgOut->addHtml( 'Sorry, there is a problem with the connection. Can not find ' . $this->search );
					break;
			}
		}
	}

	private function temporaryCodeSpace() {
		return '<style>' .
		'#ext-data {visibility: hidden; display: none }' .
		'#owl-data {visibility: hidden; display: none }' .
		'</style>';
	}

	private function createChoice() {
		$owlLineProcessed = false;
		$ctr = 0;
		$this->wgOut->addHTML(
			'<form id="flexible_form">' .
			'<input type="hidden" name="title" value="Special:Ow addFromExtAPI"/>'
		);
		$this->wgOut->addHTML(
			'<div><div id="owl_def"></div><div id="ext_def"></div><span  id="selectChecks"><input type="button" id="inputSelectButton" value="process"/></span><span  id="skipChecks"><input type="button" id="inputSkipSelectButton" value="next"/></span></div>'
		);
		$this->wgOut->addHTML(
			'</form>'
		);
	}

	private function outputTitle() {
		$this->wgOut->setPageTitle( $this->spTitle );
	}

	private function getOptionPanel() {
		global $wgWldProcessExternalAPIClasses, $wgWldExtenalResourceLanguages;
		$forms = new OmegaWikiForms;
		$this->wgOut->addHTML( getOptionPanel(
			[
				wfMessage( 'ow_api_source' )->text()                => $forms->getSelect( 'api',        $wgWldProcessExternalAPIClasses, $this->source ),
				wfMessage( 'ow_needs_xlation_source_lang' )->text() => $forms->getSelect( 'from-lang',  $wgWldExtenalResourceLanguages,  $this->sourceLanguageId ),
				wfMessage( 'datasearch_search_text' )->text()       => getTextBox( 'search-ext', $this->search ),
			]
		) );
	}

	function checkConnectionStatus() {
		$this->connection = false;
		if ( connection_status() === CONNECTION_NORMAL ) {
			$this->connection = true;
		}
	}

	protected function outputResult() {
		if ( $this->externalLexicalData ) {
			$this->wgOut->addHTML( json_encode( $this->externalLexicalData ) . '<br/><br/>.' );
		} else {
		}
		if ( $this->owlLexicalData ) {
			$this->wgOut->addHTML( json_encode( $this->owlLexicalData ) . '<br/><br/>' );
		} else {
		}
	}

	protected function checkOmegaWikiDefinition() {
		if ( existSpelling( $this->search, $this->sourceLanguageId ) ) {
			$this->owlExists = true;
		}
	}

	protected function setOmegaWikiDefinition() {
		// If expression exist in the source language, then proceed.
		if ( existSpelling( $this->search, $this->sourceLanguageId ) ) {
			$this->expressionId = OwDatabaseAPI::getTheExpressionId( $this->search, $this->sourceLanguageId );
			$dmList = OwDatabaseAPI::getExpressionMeaningIdsForLanguages( $this->search, $this->sourceLanguageId );
			foreach ( $dmList as $dmLine ) {
				$text = getDefinedMeaningDefinitionForLanguage( $dmLine, $this->sourceLanguageId );
				if ( !$text ) {
					$text = getDefinedMeaningDefinitionForLanguage( $dmLine, WLD_ENGLISH_LANG_ID );
				}

				$synonyms = OwDatabaseAPI::getSynonyms( $dmLine, $this->sourceLanguageId, $this->search );

				$this->owlLexicalData[] = [
					'processed' => null,
					'e' => $this->search,
					'dm_id' => $dmLine,
					'lang_id' => $this->sourceLanguageId,
					'text' => $text,
					'syn' => $synonyms
				];
			}
		}

		$this->owlLexicalDataJSON = json_encode( $this->owlLexicalData );
		// Line below for testing. When there's no internet connection
/*
		$this->owlLexicalDataJSON = <<<JSON
[
	{
		"processed":null,
		"dm_id":"5836",
		"lang_id":"85",
		"text":"A common, four-legged animal (Sus scrofa) that has cloven hooves, bristles and a nose adapted for digging and is farmed by humans for its meat.",
		"syn":null
	},
	{
		"processed":null,
		"dm_id":"1499810",
		"lang_id":"85",
		"text":"(Pejorative) A fat or overweight person.",
		"syn":[
			["butterball","85","1","1499814"],
			["chubster","85","1","1499816"],
			["chunker","85","1","1499818"],
			["fat-ass","85","1","1499825"],
			["fatass","85","1","1499827"],
			["fatfuck","85","1","1499820"],
			["fatshit","85","1","1499829"],
			["fatso","85","1","1499811"],
			["fattie","85","1","1499822"],
			["fatty","85","1","1499823"],
			["lardass","85","1","1499831"],
			["lardo","85","1","1499833"],
			["obeast","85","1","1499837"],
			["oinker","85","1","1499835"],
			["podge","85","1","1499840"],
			["porker","85","1","1499842"],
			["pudge","85","1","1499844"],
			["salad dodger","85","1","1499846"],
			["tub of lard","85","1","1499848"]
		]
	},
	{
		"processed":null,
		"dm_id":"583600",
		"lang_id":"85",
		"text":"A common, four-legged animal (Sus scrofa) that has cloven hooves, bristles and a nose adapted for digging and is farmed by humans for its meat.",
		"syn":null
	}
]
JSON;
*/

		$this->wgOut->addHTML(
			'<div id="owl-data">' . $this->owlLexicalDataJSON . '</div>'
		);
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
