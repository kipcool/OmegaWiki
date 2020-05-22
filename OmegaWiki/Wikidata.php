<?php
/** @file
 */
require_once "forms.php";
require_once "Transaction.php";
require_once "OmegaWikiAttributes.php";
require_once "WikiDataAPI.php";
require_once "Utilities.php";

class DefaultWikidataApplication {
	protected $title;

	protected $showRecordLifeSpan;
	protected $transaction;
	protected $queryTransactionInformation;
	protected $showCommunityContribution;

	// The following member variables control some application specific preferences
	protected $showClassicPageTitles = false;	// Show classic page titles instead of prettier page titles

	protected $propertyToColumnFilters = [];
	protected $viewInformation;

	// Show a panel to select expressions from available data-sets
	protected $showDataSetPanel = false;

	public function __construct( $title ) {
		global $wgPropertyToColumnFilters;

		$this->title = $title;
		$this->showClassicPageTitles = false;
		if ( isset( $wgPropertyToColumnFilters ) ) {
			$this->propertyToColumnFilters = $wgPropertyToColumnFilters;
		}
	}

	public function getTitle() {
		return $this->title;
	}

	protected function outputViewHeader() {
		global $wgOut;

		if ( $this->showDataSetPanel ) {
			$wgOut->addHTML( $this->getDataSetPanel() );
		}
	}

	public function view() {
		global $wgOut;

		$wgOut->enableClientCache( true );

		$myTitle = $this->title->getPrefixedText();

		if ( !$this->showClassicPageTitles ) {
			$myTitle = $this->title;
		}

		$wgOut->setPageTitle( $myTitle );

		$this->queryTransactionInformation = new QueryLatestTransactionInformation();

		$viewInformation = new ViewInformation();
		$viewInformation->showRecordLifeSpan = false;
		$viewInformation->queryTransactionInformation = $this->queryTransactionInformation;
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );

		$this->viewInformation = $viewInformation;

		// Not clear why this is here. Works well without.
		// initializeOmegaWikiAttributes( $viewInformation );
		// initializeObjectAttributeEditors( $viewInformation );
	}

	protected function getDataSetPanel() {
		$dc = wdGetDataSetContext();
		$ow_datasets = wfMessage( "ow_datasets" )->text();
		$html = "<div class=\"dataset-panel\">";
		$html .= "<table border=\"0\"><tr><th class=\"dataset-panel-heading\">$ow_datasets</th></tr>";
		$dataSets = wdGetDataSets();
		foreach ( $dataSets as $dataset ) {
			$active = ( $dataset->getPrefix() == $dc->getPrefix() );
			$name = $dataset->fetchName();
			$prefix = $dataset->getPrefix();

			$class = $active ? 'dataset-panel-active' : 'dataset-panel-inactive';
			$slot = $active ? "$name" : Linker::link(
				$this->title,
				$name,
				[],
				[ 'dataset' => $prefix ]
			);
			$html .= "<tr><td class=\"$class\">$slot</td></tr>";
		}
		$html .= "</table>";
		$html .= "</div>";
		return $html;
	}

	protected function save( $referenceQueryTransactionInformation ) {
		$viewInformation = new ViewInformation();
		$viewInformation->queryTransactionInformation = $referenceQueryTransactionInformation;
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );
		$viewInformation->viewOrEdit = "edit";

		$this->viewInformation = $viewInformation;

		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $this->viewInformation );
	}

	public function saveWithinTransaction() {
		global $wgUser, $wgRequest;

		$summary = $wgRequest->getText( 'summary' );

		// Insert transaction information into the DB
		startNewTransaction( $wgUser->getID(), $wgRequest->getIP(), $summary );

		// Perform regular save
		$this->save( new QueryAtTransactionInformation( $wgRequest->getInt( 'transaction' ), false ) );

		// Update page caches
		$this->title->invalidateCache();

		// Add change to RC log
		$now = wfTimestampNow();
		RecentChange::notifyEdit( $now, $this->title, false, $wgUser, $summary, 0, $now, false );
	}

	/**
	 * @return true if permission to edit, false if not
	 */
	public function edit() {
		global $wgOut, $wgRequest, $wgUser;

		$wgOut->enableClientCache( false );

		if ( $wgUser->isBlockedFrom( $this->getTitle(), false ) ) {
			throw new UserBlockedError( $wgUser->getBlock() );
		}

		$dc = wdGetDataSetContext();
		if ( !$wgUser->isAllowed( 'editwikidata-' . $dc ) ) {
			$wgOut->addWikiMsg( "ow_noedit", $dc->fetchName() );
			$wgOut->setPageTitle( wfMessage( "ow_noedit_title" )->text() );
			return false;
		}

		if ( $wgRequest->getText( 'save' ) != '' ) {
			$this->saveWithinTransaction();
		}

		$viewInformation = new ViewInformation();
		$viewInformation->showRecordLifeSpan = false;
		$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
		$viewInformation->viewOrEdit = "edit";
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );

		$this->viewInformation = $viewInformation;

		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $this->viewInformation );

		return true;
	}

	public function history() {
		global $wgOut, $wgRequest;

		$wgOut->enableClientCache( false );

		$title = $this->title->getPrefixedText();

		if ( !$this->showClassicPageTitles ) {
			$title = $this->title;
		}

		$wgOut->setPageTitle( wfMessage( "ow_history", $title )->text() );

		# Plain filter for the lifespan info about each record
		if ( isset( $_GET['show'] ) ) {
			$this->showRecordLifeSpan = isset( $_GET["show-record-life-span"] );
			$this->transaction = (int)$_GET["transaction"];
		} else {
			$this->showRecordLifeSpan = true;
			$this->transaction = 0;
		}

		# Up to which transaction to view the data
		if ( $this->transaction == 0 ) {
			$this->queryTransactionInformation = new QueryHistoryTransactionInformation();
		} else {
			$this->queryTransactionInformation = new QueryAtTransactionInformation( $this->transaction, $this->showRecordLifeSpan );
		}

		$transactionId = $wgRequest->getInt( 'transaction' );

		$wgOut->addHTML( getOptionPanel(
			[
				wfMessage( 'ow_history_transaction' )->text() => getSuggest( 'transaction', 'transaction', [], $transactionId, getTransactionLabel( $transactionId ), [ 0, 2, 3 ] ),
				wfMessage( 'ow_history_show_life_span' )->text() => getCheckBox( 'show-record-life-span', $this->showRecordLifeSpan )
			],
			'history'
		) );

		$viewInformation = new ViewInformation();
		$viewInformation->showRecordLifeSpan = $this->showRecordLifeSpan;
		$viewInformation->queryTransactionInformation = $this->queryTransactionInformation;
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );

		$this->viewInformation = $viewInformation;

		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $viewInformation );
	}

	protected function outputEditHeader() {
		global $wgOut, $wgUser;

		$title = $this->title->getPrefixedText();

		if ( !$this->showClassicPageTitles ) {
			$title = $this->title;
		}

		$wgOut->setPageTitle( $title );
		$wgOut->setPageTitle( wfMessage( "editing", $title )->text() );
		if ( $wgUser->isAnon() ) {
			$wgOut->wrapWikiMsg( "<div id=\"mw-anon-edit-warning\">\n$1</div>", 'anoneditwarning' );
		}

		$wgOut->addHTML(
			'<form method="post" action="">' .
			'<input type="hidden" name="transaction" value="' . getLatestTransactionId() . '"/>'
		);
	}

	protected function outputEditFooter() {
		global $wgOut, $wgUser;

		$wgOut->addHTML(
			'<div class="option-panel">' .
				'<table cellpadding="0" cellspacing="0"><tr>' .
					'<th>' . wfMessage( "summary" )->text() . '</th>' .
					'<td class="option-field">' . getTextBox( "summary" ) . '</td>' .
				'</tr></table>' .
				getSubmitButton( "save", wfMessage( "ow_save" )->text() ) .
			'</div>'
		);

		$wgOut->addHTML( '</form>' );

		if ( $wgUser->isAnon() ) {
			$wgOut->wrapWikiMsg( "<div id=\"mw-anon-edit-warning\">\n$1</div>", 'anoneditwarning' );
		}
	}
}

/**
 * @note 2014-03-19 added $mwPrefix and function setDBprefix
 * for mw prefix compatibility. Also used db select instead of query ~he
 */
class DataSet {

	private $mwPrefix;
	private $dataSetPrefix;
	private $isValidPrefix = false;
	private $fallbackName = '';
	private $dmId = 0; # the dmId of the dataset name

	public function getPrefix() {
		return $this->dataSetPrefix;
	}

	public function isValidPrefix() {
		return $this->isValidPrefix;
	}

	public function setDefinedMeaningId( $dmid ) {
		$this->dmId = $dmid;
	}

	public function getDefinedMeaningId() {
		return $this->dmId;
	}

	public function setValidPrefix( $isValid = true ) {
		$this->isValidPrefix = $isValid;
	}

	public function setPrefix( $cp ) {
		$fname = "DataSet::setPrefix";

		$dbs = wfGetDB( DB_REPLICA );
		$this->dataSetPrefix = $cp;
		$res = $dbs->select(
			"wikidata_sets",
			[
				'set_prefix',
				'set_fallback_name',
				'set_dmid'
			],
			[
				'set_prefix' => $cp
			], __METHOD__
		);

		// invalid unless proven valid :) ~he
		$this->setValidPrefix( false );
		foreach ( $res as $row ) {
			$this->setValidPrefix();
			$this->setDefinedMeaningId( $row->set_dmid );
			$this->setFallbackName( $row->set_fallback_name );
		}
	}

	public function setDBprefix( $cp ) {
		$fname = "DataSet::setDBprefix";
		$this->mwPrefix = $cp;
	}

	// Fetch!
	function fetchName() {
		global $wgLang, $wdTermDBDataSet;
		if ( $wdTermDBDataSet ) {
			$userLanguage = $wgLang->getCode();
			// @note any user/lang global problem here is solved by the getSpellingForUserLanguage() function.
			$spelling = getSpellingForUserLanguage( $this->dmId, $userLanguage, 'en', $wdTermDBDataSet );
			if ( $spelling ) {
				return $spelling;
			}
		}
		return $this->getFallbackName();
	}

	public function getFallbackName() {
		return $this->fallbackName;
	}

	public function setFallbackName( $name ) {
		$this->fallbackName = $name;
	}

	function __toString() {
		return $this->getPrefix();
	}

}
