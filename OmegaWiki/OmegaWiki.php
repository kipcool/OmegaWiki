<?php

require_once 'Wikidata.php';
require_once 'Transaction.php';
require_once 'WikiDataAPI.php';
require_once 'forms.php';
require_once 'Attribute.php';
require_once 'type.php';
require_once 'languages.php';
require_once 'HTMLtable.php';
require_once 'OmegaWikiRecordSets.php';
require_once 'OmegaWikiEditors.php';
require_once 'ViewInformation.php';
require_once 'WikiDataGlobals.php';

/**
 * Load and modify content in a OmegaWiki-enabled
 * namespace.
 */
class OmegaWiki extends DefaultWikidataApplication {
	public function view() {
		global $wgOut;

		// some initializations, including viewInformation
		parent::view();

		// adds dataset panel, if activated
		$this->outputViewHeader();

		$spelling = $this->getTitle()->getText();
		if ( existSpelling( $spelling ) ) {
			$recordset = getExpressionsRecordSet( $spelling, $this->viewInformation );
			$editor = getExpressionsEditor( $spelling, $this->viewInformation );
			$wgOut->addHTML( $editor->view( $this->getIdStack(), $recordset ) );
		}
	}

	public function history() {
		global $wgOut;

		parent::history();

		$spelling = $this->getTitle()->getText();

		$wgOut->addHTML(
			getExpressionsEditor( $spelling, $this->viewInformation )->view(
				$this->getIdStack(),
				getExpressionsRecordSet( $spelling, $this->viewInformation )
			)
		);
	}

	protected function save( $referenceQueryTransactionInformation ) {
		parent::save( $referenceQueryTransactionInformation );

		$spelling = $this->getTitle()->getText();

		getExpressionsEditor( $spelling, $this->viewInformation )->save(
			$this->getIdStack(),
			getExpressionsRecordSet( $spelling, $this->viewInformation )
		);
	}

	public function edit() {
		global $wgOut;

		if ( !parent::edit() ) {
			return false;
		}
		$this->outputEditHeader();

		$spelling = $this->getTitle()->getText();

		$wgOut->addHTML(
			getExpressionsEditor( $spelling, $this->viewInformation )->edit(
				$this->getIdStack(),
				getExpressionsRecordSet( $spelling, $this->viewInformation )
			)
		);

		$this->outputEditFooter();
	}

	protected function getIdStack() {
		return new IdStack( WLD_EXPRESSION );
	}
}
