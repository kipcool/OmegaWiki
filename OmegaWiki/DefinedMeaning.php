<?php
/** @file
 */
require_once 'IdStack.php';
require_once 'Wikidata.php';
require_once 'OmegaWikiRecordSets.php';
require_once 'OmegaWikiEditors.php';
require_once 'DefinedMeaningModel.php';

/**
 * @brief The Defined Meaning Namespace class
 */
class DefinedMeaning extends DefaultWikidataApplication {
	protected $definedMeaningModel;

	public function view() {
		global
			$wgOut, $wgTitle, $wgRequest;

		$this->requireDBAPI();

		// Split title into defining expression and ID
		$titleText = $wgTitle->getText();
		$dmInfo = DefinedMeaningModel::splitTitleText( $titleText );

		// WikiData compatibility. Using title with dmid only
		if ( $dmInfo === null && is_numeric( $titleText ) ) {
			$dmInfo = [
				"expression" => null,
				"id" => $titleText
			];
		}

		$dmNumber = $dmInfo["id"];

		// Title doesn't have an ID in it (or ID 0)
		if ( $dmInfo === null || !$dmNumber ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}
		parent::view();
		$this->definedMeaningModel = new DefinedMeaningModel( $dmNumber, [ "viewinformation" => $this->viewInformation ] );

		if ( !empty( $dmInfo["expression"] ) ) {
			$this->definedMeaningModel->setDefiningExpression( $dmInfo["expression"] );
		}

		// check that the constructed DM actually exists in the database
		$match = $this->definedMeaningModel->checkExistence( true, true );

		// The defining expression is likely incorrect for some reason. Let's just
		// try looking up the number.
		if ( $match === null && !empty( $dmInfo["expression"] ) ) {
			$this->definedMeaningModel->setDefiningExpression( null );
			$dmInfo["expression"] = null;
			$match = $this->definedMeaningModel->checkExistence( true, true );
		}

		// The defining expression is either bad or missing. Let's redirect
		// to the correct URL.
		if ( empty( $dmInfo["expression"] ) && $match !== null ) {
			$this->definedMeaningModel->loadRecord();
			$title = Title::newFromText( $this->definedMeaningModel->getWikiTitle() );
			$url = $title->getFullURL();
			$wgOut->disable();
			header( "Location: $url" );
			return false;
		}

		// Bad defining expression AND bad ID! :-(
		if ( $match === null ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_missing' );
			return false;
		}

		$this->definedMeaningModel->loadRecord();
		$this->showDataSetPanel = false;

		# Raw mode
		$view_as = $wgRequest->getText( 'view_as' );
		if ( $view_as == "raw" ) {
			$wgOut->addHTML( "<pre>" . $this->definedMeaningModel->getRecord() . "</pre>" );
			# $wgOut->disable();
			return;
		}

		$this->outputViewHeader();
		// concept panel is annoying and useless
		// $wgOut->addHTML( $this->getConceptPanel() );
		$expressionTranslated = OwDatabaseAPI::getDefinedMeaningExpression( $this->definedMeaningModel->getId() );

		// @note Since the defined meaning expression can be misleading and
		// the software is now able to redirect using the dm id only, it seems logical
		// to replace the full dm title and replace it with the id. ~he
		$wgOut->setPageTitle( "DefinedMeaning:{$dmInfo['id']} - $expressionTranslated" );

		$editor = getDefinedMeaningEditor( $this->viewInformation );
		$idStack = $this->getIdStack( $this->definedMeaningModel->getId() );
		$html = $editor->view( $idStack, $this->definedMeaningModel->getRecord() );
		$wgOut->addHTML( $html );
	}

	public function edit() {
		global
			$wgOut, $wgTitle;

		if ( !parent::edit() ) {
			return false;
		}

		$definedMeaningId = $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );

		// Title doesn't have an ID in it (or ID 0)
		if ( !$definedMeaningId ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}

		$this->outputEditHeader();
		$dmModel = new DefinedMeaningModel( $definedMeaningId, [ "viewinformation" => $this->viewInformation ] );

		// check that the constructed DM actually exists in the database
		$match = $dmModel->checkExistence( true, true );
		if ( $match === null ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_missing' );
			return false;
		}

		if ( $dmModel->getRecord() === null ) {
			$wgOut->addHTML( wfMessage( "ow_db_consistency__not_found" )->text() . " ID:$definedMeaningId" );
			return;
		}

		$wgOut->addHTML(
			getDefinedMeaningEditor( $this->viewInformation )->edit(
				$this->getIdStack( $dmModel->getId() ),
				$dmModel->getRecord()
			)
		);
		$this->outputEditFooter();
	}

	public function history() {
		global $wgOut, $wgTitle;

		$definedMeaningId = $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );
		// Title doesn't have an ID in it (or ID 0)
		if ( !$definedMeaningId ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}

		parent::history();

		$dmModel = new DefinedMeaningModel( $definedMeaningId, [ "viewinformation" => $this->viewInformation ] );

		// check that the constructed DM actually exists in the database
		$match = $dmModel->checkExistence( true, true );
		if ( $match === null ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_missing' );
			return false;
		}

		$dmIdStack = $this->getIdStack( $definedMeaningId );
		$dmRecord = $dmModel->getRecord();
		$dmEditor = getDefinedMeaningEditor( $this->viewInformation );
		$wgOut->addHTML( $dmEditor->view( $dmIdStack, $dmRecord ) );
	}

	protected function save( $referenceQueryTransactionInformation ) {
		global
			$wgTitle;

		parent::save( $referenceQueryTransactionInformation );

		$definedMeaningId = $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );
		if ( !$definedMeaningId ) {
			// Title doesn't have an ID in it (or ID 0)
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}

		$dmModel = new DefinedMeaningModel( $definedMeaningId, [ "viewinformation" => $this->viewInformation ] );

		getDefinedMeaningEditor( $this->viewInformation )->save(
			$this->getIdStack( $definedMeaningId ),
			$dmModel->getRecord()
		);
	}

	public function getTitle() {
		global $wgTitle;
		return $wgTitle->getFullText();
	}

	protected function getIdStack( $definedMeaningId ) {
		$o = OmegaWikiAttributes::getInstance();

		$definedMeaningIdStructure = new Structure( $o->definedMeaningId );
		$definedMeaningIdRecord = new ArrayRecord( $definedMeaningIdStructure, $definedMeaningIdStructure );
		$definedMeaningIdRecord->definedMeaningId = $definedMeaningId;

		$idStack = new IdStack( WLD_DEFINED_MEANING );
		$idStack->pushKey( $definedMeaningIdRecord );

		return $idStack;
	}

	protected function getDefinedMeaningIdFromTitle( $title ) {
		// get id from title: DefinedMeaning:expression (id)
		$bracketPosition = strrpos( $title, "(" );
		$definedMeaningId = substr( $title, $bracketPosition + 1, strlen( $title ) - $bracketPosition - 2 );
		return $definedMeaningId;
	}

	public function getDefinedMeaningId() {
		global
			$wgTitle;
		return $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );
	}

	/**
	 * Creates sidebar HTML for indicating concepts which exist
	 * in multiple datasets, and providing a link to add new
	 * mappings.
	 *
	 * Potential refactor candidate!
	 */
	protected function getConceptPanel() {
		global $wdShowCopyPanel;
		$dmId = $this->getDefinedMeaningId();
		$dc = wdGetDataSetContext();
		$ow_conceptpanel = wfMessage( "ow_concept_panel" )->text();

		$html = "<div class=\"dataset-panel\">";
		$html .= "<table border=\"0\"><tr><th class=\"dataset-panel-heading\">$ow_conceptpanel</th></tr>";
		$meanings = getDefinedMeaningDataAssociatedByConcept( $dmId, $dc );
		if ( $meanings ) {
			foreach ( $meanings as $dm ) {
				$dataset = $dm->getDataset();
				$active = ( $dataset->getPrefix() == $dc->getPrefix() );
				$name = $dataset->fetchName();
				$prefix = $dataset->getPrefix();

				$class = $active ? 'dataset-panel-active' : 'dataset-panel-inactive';
				$slot = $active ? "$name" : Linker::link(
					$dm->getTitleObject(),
					$name,
					[],
					[ 'dataset' => $prefix ]
				);
				$html .= "<tr><td class=\"$class\">$slot</td></tr>";
			}
		} else {
				$name = $dc->fetchName();
				$html .= "<tr><td class=\"dataset-panel-active\">$name</td></tr>";
		}
		$cmtitle = Title::newFromText( "Special:ConceptMapping" );
		$cmlink = Linker::link(
			$cmtitle,
			"<small>" . wfMessage( "ow_add_concept_link" )->text() . "</small>",
			[],
			[ "set_$dc" => $dmId, 'suppressWarnings' => true ]
		);
		$html .= "<tr><td>$cmlink</td></tr>\n";
		if ( $wdShowCopyPanel ) {
			$html .= "<tr><td>" . $this->getCopyPanel() . "<td><tr>";
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
		return $html;
	}

	/** @return user interface html for copying Defined Meanings
	 * between datasets. returns an empty string if the user
	 * actually doesn't have permission to edit.
	 */
	protected function getCopyPanel() {
		# mostly same code as in SpecialAddCollection... possibly might
		# make a nice separate function

		global
			$wgUser;
		if ( !$wgUser->isAllowed( 'wikidata-copy' ) ) {
			return "";
		}

		$datasets = wdGetDatasets();
		$datasetarray[''] = wfMessage( 'ow_none_selected' )->text();
		foreach ( $datasets as $datasetid => $dataset ) {
			$datasetarray[$datasetid] = $dataset->fetchName();
		}

		/* Deprecated for now

		$html= getOptionPanel( array (
			'Copy to' => getSelect('CopyTo', $datasetarray)
		));
		*/
		$html = $this->getCopyPanel2();
		return $html;
	}

	/** links to futt bugly alternate copy mechanism, the
	 * latter being something that actually is somewhat
	 * understandable (though not yet refactored into
	 * something purdy and maintainable)
	 */
	protected function getCopyPanel2() {
		global $wgCommunity_dc;

		$html = "Copy to:<br />\n";
		$datasets = wdGetDatasets();
		$dataset = $datasets[$wgCommunity_dc];
		$dmid = $this->definedMeaningModel->getId();
		$dc1 = $this->definedMeaningModel->getDataSet();
		$name = $dataset->fetchName();
		$dc2 = $wgCommunity_dc;
		$html .= "<a href='index.php?title=Special:Copy&action=copy&dmid=$dmid&dc1=$dc1&dc2=$dc2'>$name</a><br />\n";

		return $html;
	}

	/** safety net to ensure that the OmegaWiki Database API is called.
	 */
	protected function requireDBAPI() {
		require_once 'OmegaWikiDatabaseAPI.php';
	}
}

/** @brief PHP API class for Defined Meaning
 */
class DefinedMeanings {

	public function __construct() {
		require_once 'OmegaWikiDatabaseAPI.php';
	}

	/** @brief Returns the spelling of an expression used as
	 * the definedMeaning namespace of a given DM
	 *
	 * @param int $definedMeaningId
	 * @param string|null $dc
	 *
	 * @return string expression
	 * @return if not exists, null
	 *
	 * @see use OwDatabaseAPI::definingExpression instead
	 */
	public static function definingExpression( $definedMeaningId, $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		// no exp.remove_transaction_id because definingExpression could have been deleted
		// but is still needed to form the DM page title.
		$spelling = $dbr->selectField(
			[
				'dm' => "{$dc}_defined_meaning",
				'exp' => "{$dc}_expression"
			],
			'spelling',
			[
				'dm.defined_meaning_id' => $definedMeaningId,
				'exp.expression_id = dm.expression_id',
				'dm.remove_transaction_id' => null
			], __METHOD__
		);

		if ( $spelling ) {
			return $spelling;
		}
		return null;
	}

	/** @brief spelling via the defined meaning and/or language id
	 * @return spelling empty string if not exists
	 * @see use OwDatabaseAPI::getDefinedMeaningSpelling instead
	 */
	public static function getSpelling( $definedMeaning, $languageId = null, $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$tables = [
			'exp' => "{$dc}_expression",
			'synt' => "{$dc}_syntrans"
		];
		$vars = 'spelling';
		$conds = [
			"synt.defined_meaning_id" => $definedMeaning,
			"exp.expression_id = synt.expression_id",
			'exp.remove_transaction_id' => null,
			"synt.remove_transaction_id" => null
		];
		if ( $languageId ) {
			$conds['exp.language_id'] = $languageId;
		}

		$spelling = $dbr->selectField( $tables, $vars, $conds, __METHOD__ );

		if ( $spelling ) {
			return $spelling;
		}
		return "";
	}

	public static function getSpellingForUserLanguage( $definedMeaningId, $dc = null ) {
		$languageId = OwDatabaseAPI::getUserLanguageId();

		// try to find something with lang_id
		if ( $languageId ) {
			$spelling = OwDatabaseAPI::getDefinedMeaningSpellingForLanguage( $definedMeaningId, $languageId );
		}

		if ( !$spelling ) {
			// nothing found, try in English
			$spelling = OwDatabaseAPI::getDefinedMeaningSpellingForLanguage( $definedMeaningId, WLD_ENGLISH_LANG_ID );
		}

		if ( $spelling ) {
			return $spelling;
		}
		return null;
	}

	/**
	 * @brief Returns the defined_meaning table's DefinedMeaning id via translatedContentId
	 *
	 * @param int $translatedContentId req'd The object id
	 * @param array $options opt'l An optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return array( int defined_meaning_id )
	 * @return if not exists, array()
	 *
	 * @note options parameter can be used to extend this function.
	 * Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getTranslatedContentIdDefinedMeaningId instead.
	 * Also note that this function currently includes all data, even removed ones.
	 */
	public static function getTranslatedContentIdDefinedMeaningId( $translatedContentId, $options, $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$test = false;
		if ( isset( $options['test'] ) ) {
			$test = true;
		}

		$definedMeaningId = $dbr->selectRow(
			"{$dc}_defined_meaning",
			'defined_meaning_id',
			[
				'meaning_text_tcid' => $translatedContentId
			], __METHOD__
		);

		if ( $definedMeaningId ) {
			if ( $test ) {
				var_dump( $definedMeaningId );
				die;
			}
			return $definedMeaningId;
		}
		if ( $test ) {
			echo 'array()';
			die;
		}
		return [];
	}

	/**
	 * @param int $languageId req'd The language id
	 * @param array $options opt'l An optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return an array of "Defined Meaning Id" objects for a language
	 * @return if not exists, null
	 */
	public static function getLanguageIdDefinedMeaningId( $languageId, $options = [], $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY'] = $options['ORDER BY'];
		} else {
			$cond['ORDER BY'] = 'defined_meaning_id';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT'] = $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET'] = $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$queryResult = $dbr->select(
			[
				'synt' => "{$dc}_syntrans",
				'exp' => "{$dc}_expression"
			],
			'defined_meaning_id',
			[
				'synt.expression_id = exp.expression_id',
				'language_id' => $languageId,
				'synt.remove_transaction_id' => null,
				'exp.remove_transaction_id' => null
			],
			__METHOD__,
			$cond
		);

		$definedMeaningId = [];
		foreach ( $queryResult as $dm ) {
			$definedMeaningId[] = $dm->defined_meaning_id;
		}
		$queryResult = [];

		if ( $definedMeaningId ) {
			return $definedMeaningId;
		}
		return null;
	}

	/** @brief Returns one spelling of an expression corresponding to a given DM
	 * 	- in a given language if it exists
	 * 	- or else in English
	 * 	- or else in any language
	 *
	 * @param int $definedMeaningId
	 * @return string spelling
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getDefinedMeaningExpression instead.
	 */
	public static function getExpression( $definedMeaningId, $dc = null ) {
		$userLanguageId = OwDatabaseAPI::getUserLanguageId();

		$spelling = '';

		if ( $userLanguageId > 0 ) {
			$spelling = self::getExpressionForLanguage( $definedMeaningId, $userLanguageId );
		}

		list( $definingExpressionId, $definingExpression, $definingExpressionLanguage ) = definingExpressionRow( $definedMeaningId );

		if ( $spelling == "" ) {
			// if no spelling exists for the specified language : look for an spelling in English
			$spelling = self::getExpressionForLanguage( $definedMeaningId, WLD_ENGLISH_LANG_ID );

			if ( $spelling == "" ) {
				$spelling = self::getExpressionForAnyLanguage( $definedMeaningId );

				if ( $spelling == "" ) {
					$spelling = $definingExpression;
				}
			}
		}

		return $spelling;
	}

	/** @brief Returns one spelling of an expression corresponding to a given DM in a given language
	 *
	 * @param int $definedMeaningId
	 * @param int $languageId
	 * @return string spelling
	 * @return if not exists, ""
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getDefinedMeaningExpressionForLanguage instead.
	 */
	public static function getExpressionForLanguage( $definedMeaningId, $languageId ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$spelling = $dbr->selectField(
			[
				'exp' => "{$dc}_expression",
				'synt' => "{$dc}_syntrans"
			],
			'spelling',
			[
				'defined_meaning_id' => $definedMeaningId,
				'exp.expression_id = synt.expression_id',
				'exp.language_id' => $languageId,
				'synt.identical_meaning' => 1,
				'synt.remove_transaction_id' => null,
				'exp.remove_transaction_id' => null
			], __METHOD__
		);

		if ( $spelling ) {
			return $spelling;
		}
		return "";
	}

	/** @brief Returns one spelling of an expression corresponding to a given DM in any language
	 *
	 * @param int $definedMeaningId
	 * @return string spelling
	 * @return if not exists, ""
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getDefinedMeaningExpressionForAnyLanguage instead.
	 */
	public static function getExpressionForAnyLanguage( $definedMeaningId ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$spelling = $dbr->selectField(
			[
				'exp' => "{$dc}_expression",
				'synt' => "{$dc}_syntrans"
			],
			'spelling',
			[
				'defined_meaning_id' => $definedMeaningId,
				'exp.expression_id = synt.expression_id',
				'synt.identical_meaning' => 1,
				'synt.remove_transaction_id' => null,
				'exp.remove_transaction_id' => null
			], __METHOD__
		);

		if ( $spelling ) {
			return $spelling;
		}
		return "";
	}

}
