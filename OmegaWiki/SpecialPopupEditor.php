<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}
/**
 * Creates and return the html code corresponding to a Popup editor
 * typically for annotations
 */
class SpecialPopUpEditor extends SpecialPage {

	// o = OmegaWikiAttributes::getInstance()
	private $o;

	// html that is output
	private $output;

	// request variable from MediaWiki
	private $request;

	// action is view or edit or history
	private $action;

	function __construct() {
		parent::__construct( 'PopupEditor', 'UnlistedSpecialPage' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $par ) {
		require_once 'WikiDataGlobals.php';
		require_once 'ViewInformation.php';
		require_once 'OmegaWikiAttributes.php';
		require_once 'OmegaWikiEditors.php';
		require_once 'OmegaWikiRecordSets.php';
		require_once 'Transaction.php';
		require_once 'WikiDataTables.php';

		$this->o = OmegaWikiAttributes::getInstance();

		// disable standard output
		$this->getOutput()->disable();
		$this->output = '';

		// get the variables from request
		$this->request = $this->getRequest();

		// view or edit or history, default: view
		$this->action = $this->request->getVal( 'action', 'view' );

		// get type of popup
		$popupType = $this->request->getVal( 'type', '' );
		if ( $popupType === '' ) {
			echo "type undefined\n";
			die();
		}

		if ( $popupType == 'annotation' ) {
			$this->annotation();
		}
	}

	protected function annotation() {
		// exists if we edit Syntrans attributes
		// for the moment we do Syntrans attributes, so we test its existence
		$syntransId = $this->request->getVal( 'syntransid', '' );
		if ( $syntransId === '' ) {
			echo "syntransId undefined\n";
			die();
		}

		// always exists
		$definedMeaningId = $this->request->getVal( 'dmid', '' );
		if ( $definedMeaningId === '' ) {
			echo "definedMeaningId undefined\n";
			die();
		}

		// do we need $idPathFlat or can we put a dummy string?
		// $idPathLocal = new IdStack( '' );
		// for the moment we need it because we use the "old" functions to save
		$idPathFlat = $this->request->getVal( 'idpathflat', '' );
		if ( $idPathFlat === '' ) {
			echo "idPathFlat undefined\n";
			die();
		}

		// create new basic idStack
		$idPathLocal = $this->getDmSyntIdStack( $definedMeaningId, $syntransId );

		// start building the output
		$id = 'popup-' . $idPathFlat . '-toggleable';
		$this->output .= Html::openElement( 'div', [ 'class' => "popupToggleable", 'id' => $id ] );
		if ( $this->action !== 'history' ) {
			// show edit, save, cancel buttons in view and edit mode
			// but not in history mode
			$this->output .= $this->getHtmlButtons();
		}

		// ViewInformation and attributes
		$viewInformation = new ViewInformation();

		switch ( $this->action ) {
			case 'view':
				// $viewInformation->viewOrEdit = 'view';
				$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
				break;
			case 'edit':
				$viewInformation->viewOrEdit = 'edit';
				$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
				break;
			case 'save':
				$viewInformation->viewOrEdit = 'edit';
				$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
				break;
			case 'history':
				// $viewInformation->viewOrEdit = 'view';
				$viewInformation->queryTransactionInformation = new QueryHistoryTransactionInformation();
				$viewInformation->showRecordLifeSpan = true;
				break;
		} // switch action

		// Creating the editor
		$syntransAttributesEditor = $this->getSyntransAttributesEditor( $viewInformation );

		// the values (ArrayRecord) to fill the editor
		$recordArray = getObjectAttributesRecord( $syntransId, $viewInformation, null, "SYNT" );

		if ( $this->action === 'edit' ) {
			// EDIT
			$this->output .= $syntransAttributesEditor->edit( $idPathLocal, $recordArray );

			// in edit mode, add buttons at the bottom as well
			$this->output .= $this->getHtmlButtons();

		} elseif ( $this->action === 'save' ) {
			// SAVE
			// we don't call startNewTransaction here, it is called directly
			// inside the function when something to save is found.
			$syntransAttributesEditor->save( $idPathLocal, $recordArray );

			// Add change to RC log
			$this->updateRecentChange( $syntransId );

			// switch to view mode and refresh the values, according to what was saved
			$viewInformation->viewOrEdit = 'view';
			$syntransAttributesEditor = $this->getSyntransAttributesEditor( $viewInformation );
			$recordArray = getObjectAttributesRecord( $syntransId, $viewInformation, null, "SYNT" );

			// and view the result
			$this->output .= $syntransAttributesEditor->view( $idPathLocal, $recordArray );

		} else {
			// VIEW (default)
			$this->output .= $syntransAttributesEditor->view( $idPathLocal, $recordArray );
		}

		$this->output .= Html::closeElement( 'div' );

		echo $this->output;
	}

	/**
	 * create back the idStack
	 */
	protected function getDmSyntIdStack( $definedMeaningId, $syntransId ) {
		// the result should look like dm-1487027-syntrans-1487033-objAtt

		// level1: DM
		$definedMeaningIdStructure = new Structure( $this->o->definedMeaningId );
		$definedMeaningIdRecord = new ArrayRecord( $definedMeaningIdStructure );
		$definedMeaningIdRecord->definedMeaningId = $definedMeaningId;

		$idStack = new IdStack( WLD_DEFINED_MEANING );
		$idStack->pushKey( $definedMeaningIdRecord );

		$idStack->pushDefinedMeaningId( $definedMeaningId );
		$idStack->pushClassAttributes( new ClassAttributes( $definedMeaningId ) );

		// level2: Syntrans
		$syntransIdStructure = new Structure( $this->o->syntransId );
		$syntransIdRecord = new ArrayRecord( $syntransIdStructure );
		$syntransIdRecord->syntransId = $syntransId;
// $idStack->pushAttribute( $this->o->syntransId );
		$idStack->pushAttribute( $this->o->synonymsAndTranslations );

// $idStack->pushKey( project( $syntransIdRecord, $this->o->synonymsTranslationsStructure ) );
		$idStack->pushKey( $syntransIdRecord );
		$idStack->pushAttribute( $this->o->objectAttributes );

		return $idStack;
	}

	/**
	 * creates the hierarchical structure editor
	 * for view or edit mode according to viewInformation
	 */
	protected function getSyntransAttributesEditor( $viewInformation ) {
		// the editor is of the class ObjectAttributeValuesEditor defined in WrappingEditor.php
		// it wraps a RecordUnorderedListEditor defined in Editor.php
		$syntransAttributesEditor = createObjectAttributesEditor(
			$viewInformation,
			$this->o->objectAttributes,
			wfMessage( "ow_Property" )->text(),
			wfMessage( "ow_Value" )->text(),
			$this->o->syntransId,
			WLD_SYNTRANS_MEANING_NAME,
			$viewInformation->getLeftOverAttributeFilter()
		);
		return $syntransAttributesEditor;
	}

	/**
	 * returns the html for the buttons edit, save, cancel
	 */
	protected function getHtmlButtons() {
		$htmlButtons = Html::openElement( 'div', [ 'class' => "popupButtons" ] );

		$editButton = '[' . wfMessage( 'edit' )->plain() . ']';
		$saveButton = '[' . wfMessage( 'ow_save' )->plain() . ']';
		$cancelButton = '[' . wfMessage( 'cancel' )->plain() . ']';
		$htmlButtons .= Html::rawElement( 'span', [ 'class' => "owPopupEdit" ], $editButton );
		$htmlButtons .= Html::rawElement( 'span', [ 'class' => "owPopupSave" ], $saveButton );
		$htmlButtons .= Html::rawElement( 'span', [ 'class' => "owPopupCancel" ], $cancelButton );

		$htmlButtons .= Html::closeElement( 'div' );

		return $htmlButtons;
	}

	/**
	 * adds a line in recentchanges to notify that annotations were edited
	 */
	protected function updateRecentChange( $syntransId ) {
		$now = wfTimestampNow();
		$summary = 'Edited annotations via popup';
		$user = $this->getUser();

		$expressionId = getExpressionIdFromSyntrans( $syntransId );
		$expression = getExpression( $expressionId );
		$spelling = $expression->spelling;
		$expressionTitle = Title::makeTitle( NS_EXPRESSION, $spelling );

		RecentChange::notifyEdit( $now, $expressionTitle, false, $user, $summary, 0, $now, false );
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
