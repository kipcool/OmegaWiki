<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

require_once "Wikidata.php";
require_once "Utilities.php";

/** @file
 *
 * This Special Page is currently unused.
 *
 * @note In case this Special Page is used, kindly convert
 * the database query functions to select functions. Thanks ~he
 */
class SpecialTransaction extends SpecialPage {
	function SpecialTransaction() {
		parent::__construct( 'Transaction' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $parameter ) {
		global $wgOut;

		require_once "WikiDataTables.php";
		require_once "OmegaWikiAttributes.php";
		require_once "OmegaWikiRecordSets.php";
		require_once "OmegaWikiEditors.php";
		require_once "RecordSetQueries.php";
		require_once "Transaction.php";
		require_once "Editor.php";
		require_once "WrappingEditor.php";
		require_once "Controller.php";
		require_once "type.php";
		require_once "ViewInformation.php";

		initializeOmegaWikiAttributes( new ViewInformation() );
		initializeAttributes();

		@$fromTransactionId = (int)$_GET['from-transaction']; # FIXME - check parameter
		@$transactionCount = (int)$_GET['transaction-count']; # FIXME - check parameter
		@$userName = "" . $_GET['user-name']; # FIXME - check parameter
		@$showRollBackOptions = isset( $_GET['show-roll-back-options'] ); # FIXME - check parameter

		if ( isset( $_POST['roll-back'] ) ) {
			$fromTransactionId = (int)$_POST['from-transaction'];
			$transactionCount = (int)$_POST['transaction-count'];
			$userName = "" . $_POST['user-name'];

			if ( $fromTransactionId != 0 ) {
				$recordSet = getTransactionRecordSet( $fromTransactionId, $transactionCount, $userName );
				rollBackTransactions( $recordSet );
				$fromTransactionId = 0;
				$userName = "";
			}
		}

		if ( $fromTransactionId == 0 ) {
			$fromTransactionId = getLatestTransactionId();
		}

		if ( $transactionCount == 0 ) {
			$transactionCount = 10;
		} else {
			$transactionCount = min( $transactionCount, 20 );
		}

		$wgOut->setPageTitle( wfMessage( 'recentchanges' )->text() );
		$wgOut->addHTML( getFilterOptionsPanel( $fromTransactionId, $transactionCount, $userName, $showRollBackOptions ) );

		if ( $showRollBackOptions ) {
			$wgOut->addHTML(
				'<form method="post" action="">' .
				'<input type="hidden" name="from-transaction" value="' . $fromTransactionId . '"/>' .
				'<input type="hidden" name="transaction-count" value="' . $transactionCount . '"/>' .
				'<input type="hidden" name="user-name" value="' . $userName . '"/>'
			);
		}

		$recordSet = getTransactionRecordSet( $fromTransactionId, $transactionCount, $userName );

		$wgOut->addHTML( getTransactionOverview( $recordSet, $showRollBackOptions ) );

		if ( $showRollBackOptions ) {
			$wgOut->addHTML(
				'<div class="option-panel">' .
					'<table cellpadding="0" cellspacing="0">' .
						'<tr>' .
							'<th>' . wfMessage( "summary" )->text() . ': </th>' .
							'<td class="option-field">' . getTextBox( "summary" ) . '</td>' .
						'</tr>' .
						'<tr><th/><td>' . getSubmitButton( "roll-back", wfMessage( 'ow_transaction_rollback_button' )->text() ) . '</td></tr>' .
					'</table>' .
				'</div>' .
				'</form>'
			);
		}
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}

function getFilterOptionsPanel( $fromTransactionId, $transactionCount, $userName, $showRollBackOptions ) {
	$countOptions = [];

	for ( $i = 1; $i <= 20; $i++ ) {
		$countOptions[$i] = $i;
	}

	return getOptionPanel(
		[
			wfMessage( 'ow_transaction_from_transaction' )->text() =>
				getSuggest(
					'from-transaction',
					'transaction',
					[],
					$fromTransactionId,
					getTransactionLabel( $fromTransactionId ),
					[ 0, 2, 3 ]
				),
			wfMessage( 'ow_transaction_count' )->text() =>
				getSelect( 'transaction-count',
					$countOptions,
					$transactionCount
				),
			wfMessage( 'ow_transaction_user' )->text() => getTextBox( 'user-name', $userName ),
			wfMessage( 'ow_transaction_show_rollback' )->text() => getCheckBox( 'show-roll-back-options', $showRollBackOptions )
		]
	);
}

function initializeAttributes() {
	# malafaya: probably all these attributes need localization
	$o = OmegaWikiAttributes::getInstance();
	$o->operation = new Attribute( 'operation', wfMessage( 'ow_transaction_operation' )->text(), 'text' );
	$o->isLatest = new Attribute( 'is-latest', wfMessage( 'ow_transaction_is_latest' )->text(), 'boolean' );

	$o->rollBackStructure = new Structure( $o->isLatest, $o->operation );
	$o->rollBack = new Attribute( 'roll-back', wfMessage( 'ow_transaction_rollback_header' )->text(), $o->rollBackStructure );

	$o->addTransactionId = new Attribute( 'add-transaction-id', 'Add transaction ID', 'identifier' );

	$o->translatedContentHistoryStructure = new Structure( $o->addTransactionId, $o->text, $o->recordLifeSpan );
	$o->translatedContentHistoryKeyStructure = new Structure( $o->addTransactionId );
	$o->translatedContentHistory = new Attribute( 'translated-content-history', 'History', $o->translatedContentHistoryStructure );
	$o->translatedContentId = new Attribute( 'translated-content-id', 'Translated content ID', 'object-id' );

	$o->rollBackTranslatedContentStructure = new Structure( $o->isLatest, $o->operation, $o->translatedContentHistory );
	$o->rollBackTranslatedContent = new Attribute( 'roll-back', wfMessage( 'ow_transaction_rollback_header' )->text(), $o->rollBackTranslatedContentStructure );

	$o->updatedDefinitionStructure = new Structure(
		$o->rollBackTranslatedContent,
		$o->definedMeaningId,
		$o->definedMeaningReference,
		$o->translatedContentId,
		$o->language,
		$o->text,
		$o->operation,
		$o->isLatest
	);

	$o->updatedDefinition = new Attribute( 'updated-definition', wfMessage( 'ow_Definition' )->text(), $o->updatedDefinitionStructure );

	$o->updatedSyntransesStructure = new Structure(
		$o->syntransId,
		$o->definedMeaningId,
		$o->definedMeaningReference,
		$o->expressionId,
		$o->expression,
		$o->identicalMeaning,
		$o->operation
	);

	$o->updatedSyntranses = new Attribute( 'updated-syntranses', wfMessage( 'ow_SynonymsAndTranslations' )->text(), $o->updatedSyntransesStructure );

	$o->firstMeaning = new Attribute( 'first-meaning', wfMessage( 'ow_transaction_first_dm' )->text(), $o->definedMeaningReferenceStructure );
	$o->secondMeaning = new Attribute( 'second-meaning', wfMessage( 'ow_transaction_second_dm' )->text(), $o->definedMeaningReferenceStructure );

	$o->updatedRelationsStructure = new Structure(
		$o->rollBack,
		$o->relationId,
		$o->firstMeaning,
		$o->relationType,
		$o->secondMeaning,
		$o->operation,
		$o->isLatest
	);

	$o->updatedRelations = new Attribute( 'updated-relations', wfMessage( 'ow_Relations' )->text(), $o->updatedRelationsStructure );

	$o->classMember = new Attribute( 'class-member', wfMessage( 'ow_transaction_class_member' )->text(), $o->definedMeaningReferenceStructure );

	$o->updatedClassMembershipStructure = new Structure(
		$o->rollBack,
		$o->classMembershipId,
		$o->class,
		$o->classMember,
		$o->operation,
		$o->isLatest
	);

	$o->updatedClassMembership = new Attribute( 'updated-class-membership', wfMessage( 'ow_ClassMembership' )->text(), $o->updatedClassMembershipStructure );

	$o->collectionMember = new Attribute( 'collection-member', wfMessage( 'ow_CollectionMember' )->text(), $o->definedMeaningReferenceStructure );
	$o->collectionMemberId = new Attribute( 'collection-member-id', 'Collection member identifier', 'defined-meaning-id' );

	$o->updatedCollectionMembershipStructure = new Structure(
		$o->rollBack,
		$o->collectionId,
		$o->collectionMeaning,
		$o->collectionMemberId,
		$o->collectionMember,
		$o->sourceIdentifier,
		$o->operation
	);

	$o->updatedCollectionMembership = new Attribute( 'updated-collection-membership', wfMessage( 'ow_CollectionMembership' )->text(), $o->updatedCollectionMembershipStructure );

	$o->objectId = new Attribute( 'object-id', wfMessage( 'ow_transaction_object' )->text(), 'object-id' );
	$o->valueId = new Attribute( 'value-id', 'Value identifier', 'object-id' );
	$o->attribute = new Attribute( 'attribute', wfMessage( 'ow_ClassAttributeAttribute' )->text(), $o->definedMeaningReferenceStructure );

	$o->updatedLinkStructure = new Structure(
		$o->rollBack,
		$o->valueId,
		$o->objectId,
		$o->attribute,
		$o->link,
		$o->operation,
		$o->isLatest
	);

	$o->updatedLink = new Attribute( 'updated-link', 'Link properties', $o->updatedLinkStructure );

	$o->updatedTextStructure = new Structure(
		$o->rollBack,
		$o->valueId,
		$o->objectId,
		$o->attribute,
		$o->text,
		$o->operation,
		$o->isLatest
	);

	$o->updatedText = new Attribute( 'updated-text', 'Unstructured text properties', $o->updatedTextStructure );

	$o->translatedTextText = new Attribute( 'translated-text-property-text', 'Text', $o->translatedTextStructure );

	$o->updatedTranslatedTextPropertyStructure = new Structure(
		$o->rollBack,
		$o->valueId,
		$o->objectId,
		$o->attribute,
		$o->translatedContentId,
		$o->translatedTextText,
		$o->operation,
		$o->isLatest
	);

	$o->updatedTranslatedTextProperty = new Attribute( 'updated-translated-text-property', 'Text properties', $o->updatedTranslatedTextPropertyStructure );

	$o->updatedTranslatedTextStructure = new Structure(
		$o->rollBackTranslatedContent,
		$o->valueId,
		$o->objectId,
		$o->attribute,
		$o->translatedContentId,
		$o->language,
		$o->text,
		$o->operation,
		$o->isLatest
	);

	$o->updatedTranslatedText = new Attribute( 'updated-translated-text', 'Texts', $o->updatedTranslatedTextStructure );

	$o->classId = new Attribute( 'class-attribute-id', 'Class attribute id', 'object-id' );
	$o->level = new Attribute( 'level', wfMessage( 'ow_ClassAttributeLevel' )->text(), $o->definedMeaningReferenceStructure );
	$o->type = new Attribute( 'type', wfMessage( 'ow_ClassAttributeType' )->text(), 'text' );

	$o->updatedClassAttributesStructure = new Structure(
		$o->rollBack,
		$o->classId,
		$o->class,
		$o->level,
		$o->type,
		$o->attribute,
		$o->operation,
		$o->isLatest
	);

	$o->updatedClassAttributes = new Attribute( 'updated-class-attributes', wfMessage( 'ow_ClassAttributes' )->text(), $o->updatedClassAttributesStructure );

	$o->alternativeDefinitionText = new Attribute( 'alternative-definition-text', wfMessage( 'ow_Definition' )->text(), $o->translatedTextStructure );
	$o->source = new Attribute( 'source', wfMessage( 'ow_Source' )->text(), $o->definedMeaningReferenceStructure );

	$o->updatedAlternativeDefinitionsStructure = new Structure(
		$o->rollBack,
		$o->definedMeaningId,
		$o->translatedContentId,
		$o->alternativeDefinitionText,
		$o->definedMeaningReference,
		$o->source,
		$o->operation,
		$o->isLatest
	);

	$o->updatedAlternativeDefinitions = new Attribute( 'updated-alternative-definitions', wfMessage( 'ow_AlternativeDefinitions' )->text(), $o->updatedAlternativeDefinitionsStructure );

	$o->updatedAlternativeDefinitionTextStructure = new Structure(
		$o->rollBackTranslatedContent,
		$o->definedMeaningId,
		$o->definedMeaningReference,
		$o->translatedContentId,
		$o->source,
		$o->language,
		$o->text,
		$o->operation,
		$o->isLatest
	);

	$o->updatedAlternativeDefinitionText = new Attribute( 'updated-alternative-definition-text', 'Alternative definition text', $o->updatedAlternativeDefinitionTextStructure );

	$updatesInTransactionStructure = new Structure(
		$o->updatedDefinition,
		$o->updatedSyntranses,
		$o->updatedRelations,
		$o->updatedClassMembership,
		$o->updatedLink,
		$o->updatedText,
		$o->updatedTranslatedText,
		$o->updatedAlternativeDefinitions
	);

	$o->updatesInTransaction = new Attribute( 'updates-in-transaction', 'Updates in transaction', $updatesInTransactionStructure );
}

function getTransactionRecordSet( $fromTransactionId, $transactionCount, $userName ) {
	global
		  $dataSet, $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$queryTransactionInformation = new QueryLatestTransactionInformation();

	$restrictions = [ "transaction_id <= $fromTransactionId" ];

	if ( $userName != "" ) {
		$restrictions[] = "EXISTS (SELECT user_name FROM {$wgDBprefix}user WHERE user.user_id={$wgDBprefix}{$dc}_transactions.user_id AND {wgDBprefix}user.user_name='" . $userName . "')";
	}

	$recordSet = queryRecordSet(
		'transaction-id',
		$queryTransactionInformation,
		$o->transactionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( [ 'transaction_id' ], $o->transactionId )
		),
		$dataSet->transactions,
		$restrictions,
		[ 'transaction_id DESC' ],
		$transactionCount
	);

	$recordSet->getStructure()->addAttribute( $o->transactionId );
	expandTransactionIDsInRecordSet( $recordSet );
	$recordSet->getStructure()->addAttribute( $o->updatesInTransaction );
	expandUpdatesInTransactionInRecordSet( $recordSet );

	return $recordSet;
}

function getTransactionOverview( $recordSet, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$captionEditor = new RecordSpanEditor( $o->transaction, ': ', ', ', false );
	$captionEditor->addEditor( new TimestampEditor( $o->timestamp, new SimplePermissionController( false ), false ) );
	$captionEditor->addEditor( new UserEditor( $o->user, new SimplePermissionController( false ), false ) );
	$captionEditor->addEditor( new TextEditor( $o->summary, new SimplePermissionController( false ), false ) );

	$valueEditor = new RecordUnorderedListEditor( $o->updatesInTransaction, 5 );
	$valueEditor->addEditor( getUpdatedDefinedMeaningDefinitionEditor( $o->updatedDefinition, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedAlternativeDefinitionsEditor( $o->updatedAlternativeDefinitions, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedAlternativeDefinitionTextEditor( $o->updatedAlternativeDefinitionText, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedSyntransesEditor( $o->updatedSyntranses, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedRelationsEditor( $o->updatedRelations, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedClassAttributesEditor( $o->updatedClassAttributes, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedClassMembershipEditor( $o->updatedClassMembership, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedCollectionMembershipEditor( $o->updatedCollectionMembership, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedLinkEditor( $o->updatedLink, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedTextEditor( $o->updatedText, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedTranslatedTextPropertyEditor( $o->updatedTranslatedTextProperty, $showRollBackOptions ) );
	$valueEditor->addEditor( getUpdatedTranslatedTextEditor( $o->updatedTranslatedText, $showRollBackOptions ) );

	$editor = new RecordSetListEditor( null, new SimplePermissionController( false ), new ShowEditFieldChecker( true ), new AllowAddController( false ), false, false, null, 4, false );
	$editor->setCaptionEditor( $captionEditor );
	$editor->setValueEditor( $valueEditor );

	return $editor->view( new IdStack( "transaction" ), $recordSet );
}

function expandUpdatesInTransactionInRecordSet( $recordSet ) {
	$o = OmegaWikiAttributes::getInstance();

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$record->updatesInTransaction = getUpdatesInTransactionRecord( $record->transactionId );
	}
}

function getUpdatesInTransactionRecord( $transactionId ) {
	$o = OmegaWikiAttributes::getInstance();

	$record = new ArrayRecord( $o->updatesInTransaction->type );
	$record->updatedDefinition = getUpdatedDefinedMeaningDefinitionRecordSet( $transactionId );
	$record->updatedAlternativeDefinitions = getUpdatedAlternativeDefinitionsRecordSet( $transactionId );
	$record->updatedAlternativeDefinitionText = getUpdatedAlternativeDefinitionTextRecordSet( $transactionId );
	$record->updatedSyntranses = getUpdatedSyntransesRecordSet( $transactionId );
	$record->updatedRelations = getUpdatedRelationsRecordSet( $transactionId );
	$record->updatedClassMembership = getUpdatedClassMembershipRecordSet( $transactionId );
	$record->updatedCollectionMembership = getUpdatedCollectionMembershipRecordSet( $transactionId );
	$record->updatedLink = getUpdatedLinkRecordSet( $transactionId );
	$record->updatedText = getUpdatedTextRecordSet( $transactionId );
	$record->updatedTranslatedTextProperty = getUpdatedTranslatedTextPropertyRecordSet( $transactionId );
	$record->updatedTranslatedText = getUpdatedTranslatedTextRecordSet( $transactionId );
	$record->updatedClassAttributes = getUpdatedClassAttributesRecordSet( $transactionId );

	return $record;
}

function getTranslatedContentHistory( $translatedContentId, $languageId, $isLatest ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$recordSet = new ArrayRecordSet( $o->translatedContentHistoryStructure, $o->translatedContentHistoryKeyStructure );

	if ( $isLatest ) {
		$dbr = wfGetDB( DB_REPLICA );
		$queryResult = $dbr->query(
			"SELECT text_text, add_transaction_id, remove_transaction_id " .
			" FROM {$wgDBprefix}{$dc}_translated_content, {$wgDBprefix}{$dc}_text" .
			" WHERE {$wgDBprefix}{$dc}_translated_content.translated_content_id=$translatedContentId" .
			" AND {$wgDBprefix}{$dc}_translated_content.language_id=$languageId " .
			" AND {$wgDBprefix}{$dc}_translated_content.text_id={$wgDBprefix}{$dc}_text.text_id " .
			" ORDER BY add_transaction_id DESC"
		);

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$record = new ArrayRecord( $o->translatedContentHistoryStructure );
			$record->text = $row->text_text;
			$record->addTransactionId = (int)$row->add_transaction_id;
			$record->recordLifeSpan = getRecordLifeSpanTuple( (int)$row->add_transaction_id, (int)$row->remove_transaction_id );

			$recordSet->add( $record );
		}
	}

	return $recordSet;
}

function getUpdatedTextRecord( $text, $history ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new ArrayRecord( $o->updatedTextStructure );
	$result->text = $text;
	$result->translatedContentHistory = $history;

	return $result;
}

function getUpdatedDefinedMeaningDefinitionRecordSet( $transactionId ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();

	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT defined_meaning_id, translated_content_id, language_id, text_text, " .
			getOperationSelectColumn( "{$wgDBprefix}{$dc}_translated_content", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_translated_content", [ 'translated_content_id', 'language_id' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_defined_meaning, {$wgDBprefix}{$dc}_translated_content, {$wgDBprefix}{$dc}_text " .
		" WHERE {$wgDBprefix}{$dc}_defined_meaning.meaning_text_tcid={$wgDBprefix}{$dc}_translated_content.translated_content_id " .
		" AND {$wgDBprefix}{$dc}_translated_content.text_id={$wgDBprefix}{$dc}_text.text_id " .
		" AND " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_translated_content", $transactionId ) .
		" AND " . getAtTransactionRestriction( "{$wgDBprefix}{$dc}_defined_meaning", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedDefinitionStructure, new Structure( $o->definedMeaningId, $o->language ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedDefinitionStructure );
		$record->definedMeaningId = $row->defined_meaning_id;
		$record->definedMeaningReference = getDefinedMeaningReferenceRecord( $row->defined_meaning_id );
		$record->translatedContentId = $row->translated_content_id;
		$record->language = $row->language_id;
		$record->text = $row->text_text;
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBackTranslatedContent = simpleRecord( $o->rollBackTranslatedContentStructure, [ $row->is_latest, $row->operation, getTranslatedContentHistory( $row->translated_content_id, $row->language_id, $row->is_latest ) ] );
		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedAlternativeDefinitionsRecordSet( $transactionId ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT meaning_mid, meaning_text_tcid, source_id, " .
			getOperationSelectColumn( "{$wgDBprefix}{$dc}_alt_meaningtexts", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_alt_meaningtexts", [ 'meaning_text_tcid' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_alt_meaningtexts " .
		" WHERE " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_alt_meaningtexts", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedAlternativeDefinitionsStructure, new Structure( $o->definedMeaningId, $o->translatedContentId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedAlternativeDefinitionsStructure );
		$record->definedMeaningId = $row->meaning_mid;
		$record->definedMeaningReference = getDefinedMeaningReferenceRecord( $row->meaning_mid );
		$record->translatedContentId = $row->meaning_text_tcid;
		$record->source = getDefinedMeaningReferenceRecord( $row->source_id );
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	$viewInformation = new ViewInformation();
	$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
	expandTranslatedContentsInRecordSet( $recordSet, $o->translatedContentId, $o->alternativeDefinitionText, $viewInformation );

	return $recordSet;
}

function getUpdatedAlternativeDefinitionTextRecordSet( $transactionId ) {
	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT meaning_mid, translated_content_id, source_id, language_id, text_text, " .
			getOperationSelectColumn( "{$wgDBprefix}{$dc}_translated_content", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_translated_content", [ 'translated_content_id', 'language_id' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_alt_meaningtexts, {$wgDBprefix}{$dc}_translated_content, {$wgDBprefix}{$dc}_text " .
		" WHERE {$wgDBprefix}{$dc}_alt_meaningtexts.meaning_text_tcid={$wgDBprefix}{$dc}_translated_content.translated_content_id " .
		" AND {$wgDBprefix}{$dc}_translated_content.text_id={$dc}_text.text_id " .
		" AND " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_translated_content", $transactionId ) .
		" AND " . getAtTransactionRestriction( "{$wgDBprefix}{$dc}_alt_meaningtexts", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedAlternativeDefinitionTextStructure, new Structure( $o->translatedContentId, $o->language ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedAlternativeDefinitionTextStructure );
		$record->definedMeaningId = $row->meaning_mid;
		$record->definedMeaningReference = getDefinedMeaningReferenceRecord( $row->meaning_mid );
		$record->translatedContentId = $row->translated_content_id;
		$record->source = getDefinedMeaningReferenceRecord( $row->source_id );
		$record->language = $row->language_id;
		$record->text = $row->text_text;
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBackTranslatedContent = simpleRecord( $o->rollBackTranslatedContentStructure, [ $row->is_latest, $row->operation, getTranslatedContentHistory( $row->translated_content_id, $row->language_id, $row->is_latest ) ] );
		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedSyntransesRecordSet( $transactionId, $dc = null ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext( $dc );

	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT syntrans_sid, defined_meaning_id, {$wgDBprefix}{$dc}_syntrans.expression_id, language_id, spelling, identical_meaning, " .
			getOperationSelectColumn( "{$wgDBprefix}{$dc}_syntrans", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_syntrans", [ 'syntrans_sid' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_syntrans, {$wgDBprefix}{$dc}_expression " .
		" WHERE {$wgDBprefix}{$dc}_syntrans.expression_id={$wgDBprefix}{$dc}_expression.expression_id " .
		" AND " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_syntrans", $transactionId ) .
		" AND " . getAtTransactionRestriction( "{$wgDBprefix}{$dc}_expression", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedSyntransesStructure, new Structure( $o->syntransId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$expressionRecord = new ArrayRecord( $o->expressionStructure );
		$expressionRecord->language = $row->language_id;
		$expressionRecord->spelling = $row->spelling;

		$record = new ArrayRecord( $o->updatedSyntransesStructure );
		$record->syntransId = $row->syntrans_sid;
		$record->definedMeaningId = $row->defined_meaning_id;
		$record->expressionId = $row->expression_id;
		$record->definedMeaningReference = getDefinedMeaningReferenceRecord( $row->defined_meaning_id );
		$record->expression = $expressionRecord;
		$record->identicalMeaning = $row->identical_meaning;
		$record->isLatest = $row->is_latest;
		$record->operation = $row->operation;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getIsLatestSelectColumn( $table, $idFields, $transactionId ) {
	$idSelectColumns = [];
	$idRestrictions = [];

	foreach ( $idFields as $idField ) {
		$idSelectColumns[] = "latest_$table.$idField";
		$idRestrictions[] = "$table.$idField=latest_$table.$idField";
	}

	return "($table.add_transaction_id=$transactionId AND $table.remove_transaction_id IS NULL) OR ($table.remove_transaction_id=$transactionId AND NOT EXISTS(" .
			"SELECT " . implode( ', ', $idSelectColumns ) .
			" FROM $table AS latest_$table" .
			" WHERE " . implode( ' AND ', $idRestrictions ) .
			" AND (latest_$table.add_transaction_id >= $transactionId) " .
		")) AS is_latest ";
}

function getUpdatedRelationsRecordSet( $transactionId ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();

	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT relation_id, meaning1_mid, meaning2_mid, relationtype_mid, " .
			getOperationSelectColumn( "{$dc}_meaning_relations", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$dc}_meaning_relations", [ 'relation_id' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_meaning_relations " .
		" WHERE " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_meaning_relations", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedRelationsStructure, new Structure( $o->relationId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedRelationsStructure );
		$record->relationId = $row->relation_id;
		$record->firstMeaning = getDefinedMeaningReferenceRecord( $row->meaning1_mid );
		$record->secondMeaning = getDefinedMeaningReferenceRecord( $row->meaning2_mid );
		$record->relationType = getDefinedMeaningReferenceRecord( $row->relationtype_mid );
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedClassMembershipRecordSet( $transactionId ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();

	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT class_membership_id, class_mid, class_member_mid, " .
		getOperationSelectColumn( "{$wgDBprefix}{$dc}_class_membership", $transactionId ) . ', ' .
		getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_class_membership", [ 'class_membership_id' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_class_membership " .
		" WHERE " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_class_membership", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedClassMembershipStructure, new Structure( $o->classMembershipId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedClassMembershipStructure );
		$record->classMembershipId = $row->class_membership_id;
		$record->class = getDefinedMeaningReferenceRecord( $row->class_mid );
		$record->classMember = getDefinedMeaningReferenceRecord( $row->class_member_mid );
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedCollectionMembershipRecordSet( $transactionId ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT {$wgDBprefix}{$dc}_collection_contents.collection_id, collection_mid, member_mid, internal_member_id, " .
			getOperationSelectColumn( "{$wgDBprefix}{$dc}_collection_contents", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_collection_contents", [ 'collection_id', 'member_mid' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_collection_contents, {$wgDBprefix}{$dc}_collection " .
		" WHERE {$wgDBprefix}{$dc}_collection_contents.collection_id={$wgDBprefix}{$dc}_collection.collection_id " .
		" AND " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_collection_contents", $transactionId ) .
		" AND " . getAtTransactionRestriction( "{$wgDBprefix}{$dc}_collection", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedCollectionMembershipStructure, new Structure( $o->collectionId, $o->collectionMemberId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedCollectionMembershipStructure );
		$record->collectionId = $row->collection_id;
		$record->collectionMeaning = getDefinedMeaningReferenceRecord( $row->collection_mid );
		$record->collectionMemberId = $row->member_mid;
		$record->collectionMember = getDefinedMeaningReferenceRecord( $row->member_mid );
		$record->sourceIdentifier = $row->internal_member_id;
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedClassAttributesRecordSet( $transactionId ) {
	global $wgDBprefix;

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT object_id, class_mid, level_mid, attribute_mid, attribute_type, " .
			getOperationSelectColumn( "{$wgDBprefix}{$dc}_class_attributes", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$wgDBprefix}{$dc}_class_attributes", [ 'object_id' ], $transactionId ) .
		" FROM {$wgDBprefix}{$dc}_class_attributes " .
		" WHERE " . getInTransactionRestriction( "{$wgDBprefix}{$dc}_class_attributes", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedClassAttributesStructure, new Structure( $o->classAttributeId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedClassAttributesStructure );
		$record->classAttributeId = $row->object_id;
		$record->class = getDefinedMeaningReferenceRecord( $row->class_mid );
		$record->level = getDefinedMeaningReferenceRecord( $row->level_mid );
		$record->attribute = getDefinedMeaningReferenceRecord( $row->attribute_mid );
		$record->type = $row->attribute_type;
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function createLinkRecord( $url, $label ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new ArrayRecord( $o->link->type );
	$result->linkLabel = $label;
	$result->linkURL = $url;

	return $result;
}

function getUpdatedLinkRecordSet( $transactionId ) {
	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT value_id, object_id, attribute_mid, url, label, " .
		getOperationSelectColumn( "{$dc}_url_attribute_values", $transactionId ) . ', ' .
		getIsLatestSelectColumn( "{$dc}_url_attribute_values", [ 'value_id' ], $transactionId ) .
		" FROM {$dc}_url_attribute_values " .
		" WHERE " . getInTransactionRestriction( "{$dc}_url_attribute_values", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedLinkStructure, new Structure( $o->valueId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedLinkStructure );
		$record->valueId = $row->value_id;
		$record->objectId = $row->object_id;
		$record->attribute = getDefinedMeaningReferenceRecord( $row->attribute_mid );
		$record->link = createLinkRecord( $row->url, $row->label );
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedTextRecordSet( $transactionId ) {
	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT value_id, object_id, attribute_mid, text, " .
		getOperationSelectColumn( "{$dc}_text_attribute_values", $transactionId ) . ', ' .
		getIsLatestSelectColumn( "{$dc}_text_attribute_values", [ 'value_id' ], $transactionId ) .
		" FROM {$dc}_text_attribute_values " .
		" WHERE " . getInTransactionRestriction( "{$dc}_text_attribute_values", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedTextStructure, new Structure( $o->valueId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedTextStructure );
		$record->valueId = $row->value_id;
		$record->objectId = $row->object_id;
		$record->attribute = getDefinedMeaningReferenceRecord( $row->attribute_mid );
		$record->text = $row->text;
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getUpdatedTranslatedTextPropertyRecordSet( $transactionId ) {
	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT value_id, object_id, attribute_mid, value_tcid, " .
			getOperationSelectColumn( "{$dc}_translated_content_attribute_values", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$dc}_translated_content_attribute_values", [ 'value_id' ], $transactionId ) .
		" FROM {$dc}_translated_content_attribute_values " .
		" WHERE " . getInTransactionRestriction( "{$dc}_translated_content_attribute_values", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedTranslatedTextPropertyStructure, new Structure( $o->valueId ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedTranslatedTextPropertyStructure );
		$record->valueId = $row->value_id;
		$record->objectId = $row->object_id;
		$record->translatedContentId = $row->value_tcid;
		$record->attribute = getDefinedMeaningReferenceRecord( $row->attribute_mid );
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBack = simpleRecord( $o->rollBackStructure, [ $row->is_latest, $row->operation ] );

		$recordSet->add( $record );
	}

	$viewInformation = new ViewInformation();
	$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
	expandTranslatedContentsInRecordSet( $recordSet, $o->translatedContentId, $o->translatedTextText, $viewInformation );

	return $recordSet;
}

function getUpdatedTranslatedTextRecordSet( $transactionId ) {
	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT value_id, object_id, attribute_mid, translated_content_id, language_id, text_text, " .
			getOperationSelectColumn( "{$dc}_translated_content", $transactionId ) . ', ' .
			getIsLatestSelectColumn( "{$dc}_translated_content", [ 'translated_content_id', 'language_id' ], $transactionId ) .
		" FROM {$dc}_translated_content_attribute_values, {$dc}_translated_content, {$dc}_text " .
		" WHERE {$dc}_translated_content_attribute_values.value_tcid={$dc}_translated_content.translated_content_id " .
		" AND {$dc}_translated_content.text_id={$dc}_text.text_id " .
		" AND " . getInTransactionRestriction( "{$dc}_translated_content", $transactionId ) .
		" AND " . getAtTransactionRestriction( "{$dc}_translated_content_attribute_values", $transactionId )
	);

	$recordSet = new ArrayRecordSet( $o->updatedTranslatedTextStructure, new Structure( $o->valueId, $o->language ) );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $o->updatedTranslatedTextStructure );
		$record->valueId = $row->value_id;
		$record->objectId = $row->object_id;
		$record->attribute = getDefinedMeaningReferenceRecord( $row->attribute_mid );
		$record->translatedContentId = $row->translated_content_id;
		$record->language = $row->language_id;
		$record->text = $row->text_text;
		$record->operation = $row->operation;
		$record->isLatest = $row->is_latest;
		$record->rollBackTranslatedContent = simpleRecord( $o->rollBackTranslatedContentStructure, [ $row->is_latest, $row->operation, getTranslatedContentHistory( $row->translated_content_id, $row->language_id, $row->is_latest ) ] );
		$recordSet->add( $record );
	}

	return $recordSet;
}

function getTranslatedContentHistorySelector( $attribute ) {
	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();

	$result = createSuggestionsTableViewer( $attribute );
	$result->addEditor( createLongTextViewer( $o->text ) );
	$result->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );

	$result = new RecordSetRecordSelector( $result );

	return $result;
}

function getUpdatedDefinedMeaningDefinitionEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();
	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$rollBackEditor = new RollbackEditor( $o->rollBackTranslatedContent, true );
		$rollBackEditor->setSuggestionsEditor( getTranslatedContentHistorySelector( $o->translatedContentHistory ) );

		$editor->addEditor( $rollBackEditor );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->definedMeaningReference ) );
	$editor->addEditor( createLanguageViewer( $o->language ) );
	$editor->addEditor( createLongTextViewer( $o->text ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedAlternativeDefinitionsEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->definedMeaningReference ) );
	$editor->addEditor( createTranslatedTextViewer( $o->alternativeDefinitionText ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->source ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedAlternativeDefinitionTextEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$rollBackEditor = new RollbackEditor( $o->rollBackTranslatedContent, true );
		$rollBackEditor->setSuggestionsEditor( getTranslatedContentHistorySelector( $o->translatedContentHistory ) );

		$editor->addEditor( $rollBackEditor );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->definedMeaningReference ) );
	$editor->addEditor( createLanguageViewer( $o->language ) );
	$editor->addEditor( createLongTextViewer( $o->text ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->source ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedSyntransesEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$viewInformation = new ViewInformation();
	$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->definedMeaningReference ) );
	$editor->addEditor( getExpressionTableCellEditor( $o->expression, $viewInformation ) );
	$editor->addEditor( new BooleanEditor( $o->identicalMeaning, new SimplePermissionController( false ), false, false ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedRelationsEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->firstMeaning ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->relationType ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->secondMeaning ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedClassMembershipEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->class ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->classMember ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedCollectionMembershipEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->collectionMeaning ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->collectionMember ) );
	$editor->addEditor( createShortTextViewer( $o->sourceIdentifier ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedLinkEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( new ObjectPathEditor( $o->objectId ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->attribute ) );
	$editor->addEditor( createLinkViewer( $o->link ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedTextEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( new ObjectPathEditor( $o->objectId ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->attribute ) );
	$editor->addEditor( createLongTextViewer( $o->text ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedTranslatedTextPropertyEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( new ObjectPathEditor( $o->objectId ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->attribute ) );
	$editor->addEditor( createTranslatedTextViewer( $o->translatedTextText ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedTranslatedTextEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$o = OmegaWikiAttributes::getInstance();
	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$rollBackEditor = new RollbackEditor( $o->rollBackTranslatedContent, true );
		$rollBackEditor->setSuggestionsEditor( getTranslatedContentHistorySelector( $o->translatedContentHistory ) );

		$editor->addEditor( $rollBackEditor );
	}

	$editor->addEditor( new ObjectPathEditor( $o->objectId ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->attribute ) );
	$editor->addEditor( createLanguageViewer( $o->language ) );
	$editor->addEditor( createLongTextViewer( $o->text ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function getUpdatedClassAttributesEditor( $attribute, $showRollBackOptions ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = createTableViewer( $attribute );

	if ( $showRollBackOptions ) {
		$editor->addEditor( new RollbackEditor( $o->rollBack, false ) );
	}

	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->class ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->level ) );
	$editor->addEditor( createDefinedMeaningReferenceViewer( $o->attribute ) );
	$editor->addEditor( createShortTextViewer( $o->type ) );
	$editor->addEditor( createShortTextViewer( $o->operation ) );
	$editor->addEditor( createBooleanViewer( $o->isLatest ) );

	return $editor;
}

function simpleRecord( $structure, $values ) {
	$attributes = $structure->getAttributes();
	$result = new ArrayRecord( $structure );

	for ( $i = 0; $i < count( $attributes ); $i++ ) {
		$result->setAttributeValue( $attributes[$i], $values[$i] );
	}

	return $result;
}

function rollBackTransactions( $recordSet ) {
	$o = OmegaWikiAttributes::getInstance();
	global $wgRequest;

	$summary = $wgRequest->getText( 'summary' );
	startNewTransaction( $this->getUser()->getID(), $wgRequest->getIP(), $summary );

	$idStack = new IdStack( 'transaction' );
	$transactionKeyStructure = $recordSet->getKey();

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$transactionRecord = $recordSet->getRecord( $i );

		$transactionId = $transactionRecord->transactionId;
		$idStack->pushKey( simpleRecord( $transactionKeyStructure, [ $transactionId ] ) );

		$updatesInTransaction = $transactionRecord->updatesInTransaction;
		$idStack->pushAttribute( $o->updatesInTransaction );

		$updatedDefinitions = $updatesInTransaction->updatedDefinition;
		$idStack->pushAttribute( $o->updatedDefinition );
		rollBackDefinitions( $idStack, $updatedDefinitions );
		$idStack->popAttribute();

		$updatedRelations = $updatesInTransaction->updatedRelations;
		$idStack->pushAttribute( $o->updatedRelations );
		rollBackRelations( $idStack, $updatedRelations );
		$idStack->popAttribute();

		$updatedClassMemberships = $updatesInTransaction->updatedClassMembership;
		$idStack->pushAttribute( $o->updatedClassMembership );
		rollBackClassMemberships( $idStack, $updatedClassMemberships );
		$idStack->popAttribute();

		$updatedClassAttributes = $updatesInTransaction->updatedClassAttributes;
		$idStack->pushAttribute( $o->updatedClassAttributes );
		rollBackClassAttributes( $idStack, $updatedClassAttributes );
		$idStack->popAttribute();

		$updatedTranslatedTexts = $updatesInTransaction->updatedTranslatedText;
		$idStack->pushAttribute( $o->updatedTranslatedText );
		rollBackTranslatedTexts( $idStack, $updatedTranslatedTexts );
		$idStack->popAttribute();

		$updatedTranslatedTextProperties = $updatesInTransaction->updatedTranslatedTextProperty;
		$idStack->pushAttribute( $o->updatedTranslatedTextProperty );
		rollBackTranslatedTextProperties( $idStack, $updatedTranslatedTextProperties );
		$idStack->popAttribute();

		$o->updatedLinks = $updatesInTransaction->updatedLink;
		$idStack->pushAttribute( $o->updatedLink );
		rollBackLinkAttributes( $idStack, $o->updatedLinks );
		$idStack->popAttribute();

		$o->updatedTexts = $updatesInTransaction->updatedText;
		$idStack->pushAttribute( $o->updatedText );
		rollBackTextAttributes( $idStack, $o->updatedTexts );
		$idStack->popAttribute();

		$updatedSyntranses = $updatesInTransaction->updatedSyntranses;
		$idStack->pushAttribute( $o->updatedSyntranses );
		rollBackSyntranses( $idStack, $updatedSyntranses );
		$idStack->popAttribute();

		$updatedAlternativeDefinitionTexts = $updatesInTransaction->updatedAlternativeDefinitionText;
		$idStack->pushAttribute( $o->updatedAlternativeDefinitionText );
		rollBackAlternativeDefinitionTexts( $idStack, $updatedAlternativeDefinitionTexts );
		$idStack->popAttribute();

		$updatedAlternativeDefinitions = $updatesInTransaction->updatedAlternativeDefinitions;
		$idStack->pushAttribute( $o->updatedAlternativeDefinitions );
		rollBackAlternativeDefinitions( $idStack, $updatedAlternativeDefinitions );
		$idStack->popAttribute();

		$updatedCollectionMemberships = $updatesInTransaction->updatedCollectionMembership;
		$idStack->pushAttribute( $o->updatedCollectionMembership );
		rollBackCollectionMemberships( $idStack, $updatedCollectionMemberships );
		$idStack->popAttribute();

		$idStack->popAttribute();
		$idStack->popKey();
	}
}

function getRollBackAction( $idStack, $rollBackAttribute ) {
	$idStack->pushAttribute( $rollBackAttribute );
	$result = $_POST[$idStack->getId()];
	$idStack->popAttribute();

	return $result;
}

function getMeaningId( $record, $referenceAttribute ) {
	$o = OmegaWikiAttributes::getInstance();

	return $record->getAttributeValue( $referenceAttribute )->definedMeaningId;
}

function rollBackDefinitions( $idStack, $definitions ) {
	$o = OmegaWikiAttributes::getInstance();

	$definitionsKeyStructure = $definitions->getKey();

	for ( $i = 0; $i < $definitions->getRecordCount(); $i++ ) {
		$definitionRecord = $definitions->getRecord( $i );

		$definedMeaningId = $definitionRecord->definedMeaningId;
		$languageId = $definitionRecord->language;
		$isLatest = $definitionRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $definitionsKeyStructure, [ $definedMeaningId, $languageId ] ) );

			rollBackTranslatedContent(
				$idStack,
				getRollBackAction( $idStack, $o->rollBackTranslatedContent ),
				$definitionRecord->translatedContentId,
				$languageId,
				$definitionRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackTranslatedTexts( $idStack, $translatedTexts ) {
	$o = OmegaWikiAttributes::getInstance();

	$translatedTextsKeyStructure = $translatedTexts->getKey();

	for ( $i = 0; $i < $translatedTexts->getRecordCount(); $i++ ) {
		$translatedTextRecord = $translatedTexts->getRecord( $i );

		$valueId = $translatedTextRecord->valueId;
		$languageId = $translatedTextRecord->language;
		$isLatest = $translatedTextRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $translatedTextsKeyStructure, [ $valueId, $languageId ] ) );

			rollBackTranslatedContent(
				$idStack,
				getRollBackAction( $idStack, $o->rollBackTranslatedContent ),
				$translatedTextRecord->translatedContentId,
				$languageId,
				$translatedTextRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackTranslatedContent( $idStack, $rollBackAction, $translatedContentId, $languageId, $operation ) {
	$o = OmegaWikiAttributes::getInstance();

	if ( $rollBackAction == 'previous-version' ) {
		$idStack->pushAttribute( $o->rollBackTranslatedContent );
		$idStack->pushAttribute( $o->translatedContentHistory );

		$version = (int)$_POST[$idStack->getId()];

		if ( $version > 0 ) {
			rollBackTranslatedContentToVersion( $translatedContentId, $languageId, $version );
		}

		$idStack->popAttribute();
		$idStack->popAttribute();
	} elseif ( $rollBackAction == 'remove' ) {
		removeTranslatedText( $translatedContentId, $languageId );
	}
}

function getTranslatedContentFromHistory( $translatedContentId, $languageId, $addTransactionId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );
	$queryResult = $dbr->query(
		"SELECT text_text " .
		" FROM {$dc}_translated_content, {$dc}_text " .
		" WHERE {$dc}_translated_content.translated_content_id=$translatedContentId " .
		" AND {$dc}_translated_content.text_id={$dc}_text.text_id " .
		" AND {$dc}_translated_content.add_transaction_id=$addTransactionId" );

	$row = $dbr->fetchObject( $queryResult );

	return $row->text_text;
}

function rollBackTranslatedContentToVersion( $translatedContentId, $languageId, $addTransactionId ) {
	removeTranslatedText( $translatedContentId, $languageId );
	addTranslatedText(
		$translatedContentId,
		$languageId,
		getTranslatedContentFromHistory( $translatedContentId, $languageId, $addTransactionId )
	);
}

function rollBackRelations( $idStack, $relations ) {
	$o = OmegaWikiAttributes::getInstance();

	$relationsKeyStructure = $relations->getKey();

	for ( $i = 0; $i < $relations->getRecordCount(); $i++ ) {
		$relationRecord = $relations->getRecord( $i );

		$relationId = $relationRecord->relationId;
		$isLatest = $relationRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $relationsKeyStructure, [ $relationId ] ) );

			rollBackRelation(
				getRollBackAction( $idStack, $o->rollBack ),
				$relationId,
				getMeaningId( $relationRecord, $o->firstMeaning ),
				getMeaningId( $relationRecord, $o->relationType ),
				getMeaningId( $relationRecord, $o->secondMeaning ),
				$relationRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function shouldRemove( $rollBackAction, $operation ) {
	return $operation == 'Added' && $rollBackAction == 'remove';
}

function shouldRestore( $rollBackAction, $operation ) {
	return $operation == 'Removed' && $rollBackAction == 'previous-version';
}

function rollBackRelation( $rollBackAction, $relationId, $firstMeaningId, $relationTypeId, $secondMeaningId, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeRelationWithId( $relationId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		addRelation( $firstMeaningId, $relationTypeId, $secondMeaningId );
	}
}

function rollBackClassMemberships( $idStack, $classMemberships ) {
	$o = OmegaWikiAttributes::getInstance();

	$classMembershipsKeyStructure = $classMemberships->getKey();

	for ( $i = 0; $i < $classMemberships->getRecordCount(); $i++ ) {
		$classMembershipRecord = $classMemberships->getRecord( $i );

		$classMembershipId = $classMembershipRecord->classMembershipId;
		$isLatest = $classMembershipRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $classMembershipsKeyStructure, [ $classMembershipId ] ) );

			rollBackClassMembership(
				getRollBackAction( $idStack, $o->rollBack ),
				$classMembershipId,
				getMeaningId( $classMembershipRecord, $o->class ),
				getMeaningId( $classMembershipRecord, $o->classMember ),
				$classMembershipRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackClassMembership( $rollBackAction, $classMembershipId, $classId, $classMemberId, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeClassMembershipWithId( $classMembershipId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		addClassMembership( $classMemberId, $classId );
	}
}

function rollBackClassAttributes( $idStack, $classAttributes ) {
	$o = OmegaWikiAttributes::getInstance();

	$o->classsKeyStructure = $o->classs->getKey();

	for ( $i = 0; $i < $o->classs->getRecordCount(); $i++ ) {
		$o->classRecord = $o->classs->getRecord( $i );

		$o->classId = $o->classRecord->classIdAttribute;
		$isLatest = $o->classRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $o->classsKeyStructure, [ $o->classId ] ) );

			rollBackClassAttribute(
				getRollBackAction( $idStack, $o->rollBack ),
				$o->classId,
				getMeaningId( $o->classRecord, $o->class ),
				getMeaningId( $o->classRecord, $o->level ),
				getMeaningId( $o->classRecord, $o->attribute ),
				$o->classRecord->type,
				$o->classRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackClassAttribute( $rollBackAction, $classAttributeId, $classId, $levelId, $attributeId, $type, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeClassAttributeWithId( $classAttributeId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		addClassAttribute( $classId, $levelId, $attributeId, $type );
	}
}

function rollBackTranslatedTextProperties( $idStack, $translatedTextProperties ) {
	$o = OmegaWikiAttributes::getInstance();

	$translatedTextPropertiesKeyStructure = $translatedTextProperties->getKey();

	for ( $i = 0; $i < $translatedTextProperties->getRecordCount(); $i++ ) {
		$translatedTextPropertyRecord = $translatedTextProperties->getRecord( $i );

		$valueId = $translatedTextPropertyRecord->valueId;
		$isLatest = $translatedTextPropertyRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $translatedTextPropertiesKeyStructure, [ $valueId ] ) );

			rollBackTranslatedTextProperty(
				getRollBackAction( $idStack, $o->rollBack ),
				$valueId,
				$translatedTextPropertyRecord->objectId,
				getMeaningId( $translatedTextPropertyRecord, $o->attribute ),
				$translatedTextPropertyRecord->translatedContentId,
				$translatedTextPropertyRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackTranslatedTextProperty( $rollBackAction, $valueId, $objectId, $attributeId, $translatedContentId, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeTranslatedTextAttributeValue( $valueId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		createTranslatedTextAttributeValue( $valueId, $objectId, $attributeId, $translatedContentId );
	}
}

function rollBackLinkAttributes( $idStack, $linkAttributes ) {
	$o = OmegaWikiAttributes::getInstance();

	$o->linksKeyStructure = $o->links->getKey();

	for ( $i = 0; $i < $o->links->getRecordCount(); $i++ ) {
		$o->linkRecord = $o->links->getRecord( $i );

		$valueId = $o->linkRecord->valueId;
		$isLatest = $o->linkRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $o->linksKeyStructure, [ $valueId ] ) );
			$link = $o->linkRecord->link;

			rollBackLinkAttribute(
				getRollBackAction( $idStack, $o->rollBack ),
				$valueId,
				$o->linkRecord->objectId,
				getMeaningId( $o->linkRecord, $o->attribute ),
				$link->linkURL,
				$link->linkLabel,
				$o->linkRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackLinkAttribute( $rollBackAction, $valueId, $objectId, $attributeId, $url, $label, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeLinkAttributeValue( $valueId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		createLinkAttributeValue( $valueId, $objectId, $attributeId, $url, $label );
	}
}

function rollBackTextAttributes( $idStack, $textAttributes ) {
	$o = OmegaWikiAttributes::getInstance();

	$textAttributesKeyStructure = $textAttributes->getKey();

	for ( $i = 0; $i < $textAttributes->getRecordCount(); $i++ ) {
		$textAttributeRecord = $textAttributes->getRecord( $i );

		$valueId = $textAttributeRecord->valueId;
		$isLatest = $textAttributeRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $textAttributesKeyStructure, [ $valueId ] ) );

			rollBackTextAttribute(
				getRollBackAction( $idStack, $o->rollBack ),
				$valueId,
				$textAttributeRecord->objectId,
				getMeaningId( $textAttributeRecord, $o->attribute ),
				$textAttributeRecord->text,
				$textAttributeRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackTextAttribute( $rollBackAction, $valueId, $objectId, $attributeId, $text, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeTextAttributeValue( $valueId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		createTextAttributeValue( $valueId, $objectId, $attributeId, $text );
	}
}

function rollBackSyntranses( $idStack, $syntranses ) {
	$o = OmegaWikiAttributes::getInstance();

	$syntransesKeyStructure = $syntranses->getKey();

	for ( $i = 0; $i < $syntranses->getRecordCount(); $i++ ) {
		$syntransRecord = $syntranses->getRecord( $i );

		$syntransId = $syntransRecord->syntransId;
		$isLatest = $syntransRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $syntransesKeyStructure, [ $syntransId ] ) );

			rollBackSyntrans(
				getRollBackAction( $idStack, $o->rollBack ),
				$syntransId,
				$syntransRecord->definedMeaningId,
				$syntransRecord->expressionId,
				$syntransRecord->identicalMeaning,
				$syntransRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackSyntrans( $rollBackAction, $syntransId, $definedMeaningId, $expressionId, $identicalMeaning, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeSynonymOrTranslationWithId( $syntransId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		createSynonymOrTranslation( $definedMeaningId, $expressionId, $identicalMeaning );
	}
}

function rollBackAlternativeDefinitionTexts( $idStack, $alternativeDefinitionTexts ) {
	$o = OmegaWikiAttributes::getInstance();

	$alternativeDefinitionTextsKeyStructure = $alternativeDefinitionTexts->getKey();

	for ( $i = 0; $i < $alternativeDefinitionTexts->getRecordCount(); $i++ ) {
		$alternativeDefinitionTextRecord = $alternativeDefinitionTexts->getRecord( $i );

		$translatedContentId = $alternativeDefinitionTextRecord->translatedContentId;
		$languageId = $alternativeDefinitionTextRecord->language;
		$isLatest = $alternativeDefinitionTextRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $alternativeDefinitionTextsKeyStructure, [ $translatedContentId, $languageId ] ) );

			rollBackTranslatedContent(
				$idStack,
				getRollBackAction( $idStack, $o->rollBackTranslatedContent ),
				$translatedContentId,
				$languageId,
				$alternativeDefinitionTextRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackAlternativeDefinitions( $idStack, $alternativeDefinitions ) {
	$o = OmegaWikiAttributes::getInstance();

	$alternativeDefinitionsKeyStructure = $alternativeDefinitions->getKey();

	for ( $i = 0; $i < $alternativeDefinitions->getRecordCount(); $i++ ) {
		$alternativeDefinitionRecord = $alternativeDefinitions->getRecord( $i );

		$definedMeaningId = $alternativeDefinitionRecord->definedMeaningId;
		$translatedContentId = $alternativeDefinitionRecord->translatedContentId;
		$isLatest = $alternativeDefinitionRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $alternativeDefinitionsKeyStructure, [ $definedMeaningId, $translatedContentId ] ) );

			rollBackAlternativeDefinition(
				getRollBackAction( $idStack, $o->rollBack ),
				$definedMeaningId,
				$translatedContentId,
				getMeaningId( $alternativeDefinitionRecord, $o->source ),
				$alternativeDefinitionRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackAlternativeDefinition( $rollBackAction, $definedMeaningId, $translatedContentId, $sourceId, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeDefinedMeaningAlternativeDefinition( $definedMeaningId, $translatedContentId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		createDefinedMeaningAlternativeDefinition( $definedMeaningId, $translatedContentId, $sourceId );
	}
}

function rollBackCollectionMemberships( $idStack, $collectionMemberships ) {
	$o = OmegaWikiAttributes::getInstance();

	$collectionMembershipsKeyStructure = $collectionMemberships->getKey();

	for ( $i = 0; $i < $collectionMemberships->getRecordCount(); $i++ ) {
		$collectionMembershipRecord = $collectionMemberships->getRecord( $i );

		$collectionId = $collectionMembershipRecord->collectionId;
		$collectionMemberId = $collectionMembershipRecord->collectionMemberId;
		$isLatest = $collectionMembershipRecord->isLatest;

		if ( $isLatest ) {
			$idStack->pushKey( simpleRecord( $collectionMembershipsKeyStructure, [ $collectionId, $collectionMemberId ] ) );

			rollBackCollectionMembership(
				getRollBackAction( $idStack, $o->rollBack ),
				$collectionId,
				$collectionMemberId,
				$collectionMembershipRecord->sourceIdentifier,
				$collectionMembershipRecord->operation
			);

			$idStack->popKey();
		}
	}
}

function rollBackCollectionMembership( $rollBackAction, $collectionId, $collectionMemberId, $sourceIdentifier, $operation ) {
	if ( shouldRemove( $rollBackAction, $operation ) ) {
		removeDefinedMeaningFromCollection( $collectionMemberId, $collectionId );
	} elseif ( shouldRestore( $rollBackAction, $operation ) ) {
		addDefinedMeaningToCollection( $collectionMemberId, $collectionId, $sourceIdentifier );
	}
}
