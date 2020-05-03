<?php

require_once "Editor.php";
require_once "WrappingEditor.php";
require_once "OmegaWikiAttributes.php";
require_once "WikiDataBootstrappedMeanings.php";
require_once "ContextFetcher.php";
require_once "WikiDataGlobals.php";
require_once "ViewInformation.php";

class DummyViewer extends Viewer {
	public function view( IdStack $idPath, $value ) {
		return "";
	}

	public function showsData( $value ) {
		return true;
	}
}

class ShowEditFieldForAttributeValuesChecker extends ShowEditFieldChecker {
	protected $levelDefinedMeaningName;
	protected $annotationType;
	protected $attributeIDFilter;

	public function __construct( $levelDefinedMeaningName, $annotationType, AttributeIDFilter $attributeIDFilter ) {
		$this->levelDefinedMeaningName = $levelDefinedMeaningName;
		$this->annotationType = $annotationType;
		$this->attributeIDFilter = $attributeIDFilter;
	}

	public function check( IdStack $idPath ) {
		$classAttributes = $idPath->getClassAttributes()->filterClassAttributesOnLevelAndType( $this->levelDefinedMeaningName, $this->annotationType );
		$classAttributes = $this->attributeIDFilter->filter( $classAttributes );

		return count( $classAttributes ) > 0;
	}
}

function initializeObjectAttributeEditors( ViewInformation $viewInformation ) {
	global
		$wgWldDMValueObjectAttributesEditors,
		$wgWldTextValueObjectAttributesEditors,
		$wgWldLinkValueObjectAttributesEditors,
		$wgWldTranslatedTextValueObjectAttributesEditors,
		$wgWldOptionValueObjectAttributesEditors;

	$o = OmegaWikiAttributes::getInstance( $viewInformation );

	$wgWldDMValueObjectAttributesEditors = [];
	$wgWldTextValueObjectAttributesEditors = [];
	$wgWldTranslatedTextValueObjectAttributesEditors = [];
	$wgWldLinkValueObjectAttributesEditors = [];
	$wgWldOptionValueObjectAttributesEditors = [];

	foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
		$attribute = $propertyToColumnFilter->getAttribute();
		$propertyCaption = $propertyToColumnFilter->getPropertyCaption();
		$valueCaption = $propertyToColumnFilter->getValueCaption();
		$attributeIDfilter = $propertyToColumnFilter->getAttributeIDFilter();

		$wgWldDMValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, WLD_ANNOTATION_MEANING_NAME, $attributeIDfilter );
		$wgWldTextValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, WLD_ANNOTATION_MEANING_NAME, $attributeIDfilter );
		$wgWldLinkValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, WLD_ANNOTATION_MEANING_NAME, $attributeIDfilter );
		$wgWldTranslatedTextValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, WLD_ANNOTATION_MEANING_NAME, $attributeIDfilter );
		$wgWldOptionValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, WLD_ANNOTATION_MEANING_NAME, $attributeIDfilter );
	}

	$leftOverAttributeIdFilter = $viewInformation->getLeftOverAttributeFilter();

	$wgWldDMValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $viewInformation,	WLD_ANNOTATION_MEANING_NAME, $leftOverAttributeIdFilter );
	$wgWldTextValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $viewInformation, WLD_ANNOTATION_MEANING_NAME, $leftOverAttributeIdFilter );
	$wgWldLinkValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $viewInformation, WLD_ANNOTATION_MEANING_NAME, $leftOverAttributeIdFilter );
	$wgWldTranslatedTextValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $viewInformation, WLD_ANNOTATION_MEANING_NAME, $leftOverAttributeIdFilter );
	$wgWldOptionValueObjectAttributesEditors[] = new ObjectAttributeValuesEditor( $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $viewInformation, WLD_ANNOTATION_MEANING_NAME, $leftOverAttributeIdFilter );

	foreach ( $wgWldDMValueObjectAttributesEditors as $editor ) {
		$attributeIDFilter = $editor->getAttributeIDfilter();
		$annotationLevelName = $editor->getLevelName();
		$fetcher = new ObjectIdFetcher( 0, $o->relationType );
		$controller = new RelationValuesController( $fetcher, $annotationLevelName, $attributeIDFilter );
		$editor->addEditor( getRelationEditor( $viewInformation, $controller, $annotationLevelName, $attributeIDFilter ) );
	}

	foreach ( $wgWldTextValueObjectAttributesEditors as $editor ) {
		$attributeIDFilter = $editor->getAttributeIDfilter();
		$annotationLevelName = $editor->getLevelName();
		$fetcher = new ObjectIdFetcher( 0, $o->textAttributeId );
		$controller = new TextAttributeValuesController( $fetcher, $annotationLevelName, $attributeIDFilter );
		$editor->addEditor( getTextAttributeValuesEditor( $viewInformation, $controller, $annotationLevelName, $attributeIDFilter ) );
	}

	foreach ( $wgWldLinkValueObjectAttributesEditors as $editor ) {
		$attributeIDFilter = $editor->getAttributeIDfilter();
		$annotationLevelName = $editor->getLevelName();
		$fetcher = new ObjectIdFetcher( 0, $o->linkAttributeId );
		$controller = new LinkAttributeValuesController( $fetcher, $annotationLevelName, $attributeIDFilter );
		$editor->addEditor( getLinkAttributeValuesEditor( $viewInformation, $controller, $annotationLevelName, $attributeIDFilter ) );
	}

	foreach ( $wgWldTranslatedTextValueObjectAttributesEditors as $editor ) {
		$attributeIDFilter = $editor->getAttributeIDfilter();
		$annotationLevelName = $editor->getLevelName();
		$fetcher = new ObjectIdFetcher( 0, $o->translatedTextAttributeId );
		$controller = new TranslatedTextAttributeValuesController( $fetcher, $annotationLevelName, $attributeIDFilter );
		$editor->addEditor( getTranslatedTextAttributeValuesEditor( $viewInformation, $controller, $annotationLevelName, $attributeIDFilter ) );
	}

	foreach ( $wgWldOptionValueObjectAttributesEditors as $editor ) {
		$attributeIDFilter = $editor->getAttributeIDfilter();
		$annotationLevelName = $editor->getLevelName();
		$fetcher = new ObjectIdFetcher( 0, $o->optionAttributeId );
		$controller = new OptionAttributeValuesController( $fetcher, $annotationLevelName, $attributeIDFilter );
		$editor->addEditor( getOptionAttributeValuesEditor( $viewInformation, $controller, $annotationLevelName, $attributeIDFilter ) );
	}
}

function getTransactionEditor( Attribute $attribute ) {
	$o = OmegaWikiAttributes::getInstance();

	$transactionEditor = new RecordTableCellEditor( $attribute );
	$transactionEditor->addEditor( createUserViewer( $o->user ) );
	$transactionEditor->addEditor( new TimestampEditor( $o->timestamp, new SimplePermissionController( false ), true ) );

	return $transactionEditor;
}

function createTableLifeSpanEditor( Attribute $attribute ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new RecordTableCellEditor( $attribute );
	$result->addEditor( getTransactionEditor( $o->addTransaction ) );
	$result->addEditor( getTransactionEditor( $o->removeTransaction ) );

	return $result;
}

function getDefinitionEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = new RecordDivListEditor( $o->definition );
	$editor->addEditor( getTranslatedTextEditor(
		$o->translatedText,
		new DefinedMeaningDefinitionController(),
		$viewInformation
	) );

	foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
		$attribute = $propertyToColumnFilter->getAttribute();
		$editor->addEditor( new PopUpEditor(
			createDefinitionObjectAttributesEditor(
				$viewInformation,
				$attribute,
				$propertyToColumnFilter->getPropertyCaption(),
				$propertyToColumnFilter->getValueCaption(),
				$o->definedMeaningId,
				WLD_DEFINITION_MEANING_NAME,
				$propertyToColumnFilter->getAttributeIDFilter()
			),
			$attribute->name
		) );
	}

	$editor->addEditor( new PopUpEditor(
		createDefinitionObjectAttributesEditor( $viewInformation, $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $o->definedMeaningId, WLD_DEFINITION_MEANING_NAME, $viewInformation->getLeftOverAttributeFilter() ),
		wfMessage( "ow_PopupAnnotation" )->text()
	) );

	return $editor;
}

function createPropertyToColumnFilterEditors( ViewInformation $viewInformation, Attribute $idAttribute, $levelName ) {
	$result = [];

	foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
		$result[] = createObjectAttributesEditor(
			$viewInformation,
			$propertyToColumnFilter->getAttribute(),
			$propertyToColumnFilter->getPropertyCaption(),
			$propertyToColumnFilter->getValueCaption(),
			$idAttribute,
			$levelName,
			$propertyToColumnFilter->getAttributeIDFilter()
		);
	}

	return $result;
}

function addPropertyToColumnFilterEditors( Editor $editor, ViewInformation $viewInformation, Attribute $idAttribute, $levelName ) {
	foreach ( createPropertyToColumnFilterEditors( $viewInformation, $idAttribute, $levelName ) as $propertyToColumnEditor ) {
		$attribute = $propertyToColumnEditor->getAttribute();
		$editor->addEditor( new PopUpEditor( $propertyToColumnEditor, $attribute->name ) );
	}
}

function getTranslatedTextEditor( Attribute $attribute, UpdateController $updateController, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = new RecordSetTableEditor( $attribute, new SimplePermissionController( true ), new ShowEditFieldChecker( true ), new AllowAddController( true ), true, true, $updateController );

	$editor->addEditor( new LanguageEditor( $o->language, new SimplePermissionController( false ), true ) );
	$editor->addEditor( new TextEditor( $o->text, new SimplePermissionController( true ), true ) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function addObjectAttributesEditors( ObjectAttributeValuesEditor $objectAttributesEditor, ViewInformation $viewInformation, ContextFetcher $annotatedObjectIdFetcher ) {
	$attributeIDFilter = $objectAttributesEditor->getAttributeIDfilter();
	$annotationLevelName = $objectAttributesEditor->getLevelName();

	$objectAttributesEditor->addEditor( getRelationEditor( $viewInformation, new RelationValuesController( $annotatedObjectIdFetcher, $annotationLevelName, $attributeIDFilter ), $annotationLevelName, $attributeIDFilter ) );
	$objectAttributesEditor->addEditor( getTextAttributeValuesEditor( $viewInformation, new TextAttributeValuesController( $annotatedObjectIdFetcher, $annotationLevelName, $attributeIDFilter ), $annotationLevelName, $attributeIDFilter ) );
	$objectAttributesEditor->addEditor( getTranslatedTextAttributeValuesEditor( $viewInformation, new TranslatedTextAttributeValuesController( $annotatedObjectIdFetcher, $annotationLevelName, $attributeIDFilter ), $annotationLevelName, $attributeIDFilter ) );
	$objectAttributesEditor->addEditor( getLinkAttributeValuesEditor( $viewInformation, new LinkAttributeValuesController( $annotatedObjectIdFetcher, $annotationLevelName, $attributeIDFilter ), $annotationLevelName, $attributeIDFilter ) );
	$objectAttributesEditor->addEditor( getOptionAttributeValuesEditor( $viewInformation, new OptionAttributeValuesController( $annotatedObjectIdFetcher, $annotationLevelName, $attributeIDFilter ), $annotationLevelName, $attributeIDFilter ) );
}

function createObjectAttributesEditor( ViewInformation $viewInformation, Attribute $attribute, $propertyCaption, $valueCaption, Attribute $idAttribute, $levelName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, $levelName, $attributeIDFilter );

	addObjectAttributesEditors(
		$result,
		$viewInformation,
		new ObjectIdFetcher( 0, $idAttribute )
	);

	return $result;
}

function createDefinitionObjectAttributesEditor( ViewInformation $viewInformation, Attribute $attribute, $propertyCaption, $valueCaption, Attribute $idAttribute, $levelName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = new ObjectAttributeValuesEditor( $attribute, $propertyCaption, $valueCaption, $viewInformation, $levelName, $attributeIDFilter );

	addObjectAttributesEditors(
		$result,
		$viewInformation,
		new DefinitionObjectIdFetcher( 0, $idAttribute )
	);

	return $result;
}

function getAlternativeDefinitionsEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = new RecordSetTableEditor(
		$o->alternativeDefinitions,
		new SimplePermissionController( true ),
		new ShowEditFieldChecker( true ),
		new AllowAddController( true ),
		true,
		false,
		new DefinedMeaningAlternativeDefinitionsController()
	);

	$editor->addEditor( getTranslatedTextEditor(
		$o->alternativeDefinition,
		new DefinedMeaningAlternativeDefinitionController(),
		$viewInformation )
	);
	$editor->addEditor( new DefinedMeaningReferenceEditor( $o->source, new SimplePermissionController( false ), true ) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

/**
 * Attribute is $o->expression
 */
function getExpressionTableCellEditor( Attribute $attribute, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = new RecordTableCellEditor( $attribute );
	$editor->addEditor( new LanguageEditor( $o->language, new SimplePermissionController( false ), true ) );
	$editor->addEditor( new SpellingEditor( $o->spelling, new SimplePermissionController( false ), true ) );
	return $editor;
}

function getClassAttributesEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$allowRemove = true;
	$isAddField = false;
	$tableEditor = new RecordSetTableEditor(
		$o->classAttributes,
		new SimplePermissionController( true ),
		new ShowEditFieldForClassesChecker( 0, $o->definedMeaningId ),
		new AllowAddController( true ),
		$allowRemove,
		$isAddField,
		new ClassAttributesController()
	);

	// the four columns of the table
	$tableEditor->addEditor( new ClassAttributesLevelDefinedMeaningEditor( $o->classAttributeLevel, new SimplePermissionController( false ), true ) );
	$tableEditor->addEditor( new DefinedMeaningReferenceEditor( $o->classAttributeAttribute, new SimplePermissionController( false ), true ) );
	$tableEditor->addEditor( new ClassAttributesTypeEditor( $o->classAttributeType, new SimplePermissionController( false ), true ) );
	$tableEditor->addEditor( new PopupEditor( getOptionAttributeOptionsEditor(), wfMessage( 'ow_OptionAttributeOptions' )->text() ) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$tableEditor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $tableEditor;
}

/**
 * the corresponding recordSet function is getSynonymAndTranslationRecordSet
 */
function getSynonymsAndTranslationsEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	// defining the language + expression editor (syntrans)
	$tableEditor = new RecordSetTableEditor(
		$o->synonymsAndTranslations,
		new SimplePermissionController( true ),
		new ShowEditFieldChecker( true ),
		new AllowAddController( true ),
		true,
		false,
		new SynonymTranslationController()
	);

	// defining the identicalMeaning Editor (first column)
	$attribute = $o->identicalMeaning;
	$permissionController = new SimplePermissionController( true );
	$isAddField = true;
	$identicalMeaningEditor = new IdenticalMeaningEditor(
		$attribute, $permissionController, $isAddField
	);
	$tableEditor->addEditor( $identicalMeaningEditor );

	// expression Editor (second and third column)
	$tableEditor->addEditor( getExpressionTableCellEditor( $o->expression, $viewInformation ) );

	// not sure what this does
	addPropertyToColumnFilterEditors( $tableEditor, $viewInformation, $o->syntransId, WLD_SYNTRANS_MEANING_NAME );

	// Add annotation editor on the rightmost column.
	$objectAttributesEditor = createObjectAttributesEditor(
		$viewInformation,
		$o->objectAttributes,
		wfMessage( "ow_Property" )->text(),
		wfMessage( "ow_Value" )->text(),
		$o->syntransId,
		WLD_SYNTRANS_MEANING_NAME,
		$viewInformation->getLeftOverAttributeFilter()
	);
	$tableEditor->addEditor( new SyntransPopupEditor(
		$objectAttributesEditor,
		wfMessage( "ow_PopupAnnotation" )->text(),
		$viewInformation
	) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$tableEditor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $tableEditor;
}

function getDefinedMeaningClassMembershipEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$allowRemove = true;
	$editor = new RecordSetTableEditor( $o->classMembership, new SimplePermissionController( true ), new ShowEditFieldChecker( true ), new AllowAddController( true ), $allowRemove, false, new DefinedMeaningClassMembershipController() );
	$editor->addEditor( new ClassReferenceEditor( $o->class, new SimplePermissionController( false ), true ) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function getDefinedMeaningCollectionMembershipEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$editor = new RecordSetTableEditor( $o->collectionMembership, new SimplePermissionController( true ), new ShowEditFieldChecker( true ), new AllowAddController( true ), true, false, new DefinedMeaningCollectionController() );
	$editor->addEditor( new CollectionReferenceEditor( $o->collectionMeaning, new SimplePermissionController( false ), true ) );
	$editor->addEditor( new ShortTextEditor( $o->sourceIdentifier, new SimplePermissionController( false ), true ) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function addPopupEditors( Editor $editor, array &$columnEditors ) {
	foreach ( $columnEditors as $columnEditor ) {
		$editor->addEditor( new PopUpEditor( $columnEditor, $columnEditor->getAttribute()->name ) );
	}
}

/**
 * getRelationEditor could be also called getDefinedMeaningAttributeValuesEditor
 * the corresponding field in the database is meaning_relations
 */
function getRelationEditor( ViewInformation $viewInformation, UpdateController $controller, $levelDefinedMeaningName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	// relationLevel should match one of the levels given in class
	// ClassAttributesTypeEditor in Editor.php
	$relationLevel = "";

	if ( $levelDefinedMeaningName == WLD_DM_MEANING_NAME ) {
		// DM-DM relations
		$relationLevel = "DM";
	} elseif ( $levelDefinedMeaningName == WLD_SYNTRANS_MEANING_NAME ) {
		// Syntrans-Syntrans relations
		$relationLevel = "SYNT";
	}

	if ( $relationLevel == "" ) {
		// should not happen?? in any case, we dont want to display it.
		$showEditFieldChecker = new ShowEditFieldChecker( false );
		// empty dummy editor (do we have something simpler?)
		$editor = new RecordSetTableEditor( $o->relations, new SimplePermissionController( true ), $showEditFieldChecker, new AllowAddController( true ), true, false, $controller );
		return $editor;
	}

	$showEditFieldChecker = new ShowEditFieldForAttributeValuesChecker( $levelDefinedMeaningName, $relationLevel, $attributeIDFilter );

	$editor = new RecordSetTableEditor( $o->relations, new SimplePermissionController( true ), $showEditFieldChecker, new AllowAddController( true ), true, false, $controller );

	// add the editor combobox where one selects the relation (antonym, hyponym, etc.)
	$editor->addEditor( new RelationTypeEditor( $o->relationType, new SimplePermissionController( false ), true, $attributeIDFilter, $levelDefinedMeaningName ) );

	// add the editor combobox where one selects the second object to which the relation is
	// it could be a DM or a Syntrans depending on the relation
	if ( $relationLevel == "DM" ) {
		$editor->addEditor( new DefinedMeaningReferenceEditor( $o->otherObject, new SimplePermissionController( false ), true ) );
	} else {
		$editor->addEditor( new SyntransReferenceEditor( $o->otherObject, new SimplePermissionController( false ), true ) );
	}

	// what is this for? Seems to work even when commented out
	// addPopupEditors( $editor, $wgWldDMValueObjectAttributesEditors );

	// adds transaction history viewer, if needed
	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}
	return $editor;
}

function getDefinedMeaningReciprocalRelationsEditor( ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$permissionController = new SimplePermissionController( true );
	$showEditFieldChecker = new ShowEditFieldChecker( true );
	$allowAddController = new AllowAddController( false );
	$allowRemove = true;
	$isAddField = false;
	$updateController = new IncomingRelationsController();

	$editor = new RecordSetTableEditor( $o->reciprocalRelations, $permissionController, $showEditFieldChecker, $allowAddController, $allowRemove, $isAddField, $updateController );

	$editor->addEditor( new DefinedMeaningReferenceEditor( $o->otherObject, new SimplePermissionController( false ), true ) );
	$editor->addEditor( new RelationTypeReferenceEditor( $o->relationType, new SimplePermissionController( false ), true ) );

	addPropertyToColumnFilterEditors( $editor, $viewInformation, $o->relationId, WLD_RELATION_MEANING_NAME );

	$editor->addEditor( new PopUpEditor(
		createObjectAttributesEditor( $viewInformation, $o->objectAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $o->relationId, WLD_RELATION_MEANING_NAME, $viewInformation->getLeftOverAttributeFilter() ),
		wfMessage( "ow_PopupAnnotation" )->text()
	) );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function getTextAttributeValuesEditor( ViewInformation $viewInformation, UpdateController $controller, $levelDefinedMeaningName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	$showEditFieldChecker = new ShowEditFieldForAttributeValuesChecker( $levelDefinedMeaningName, "TEXT", $attributeIDFilter );

	$editor = new RecordSetTableEditor( $o->textAttributeValues, new SimplePermissionController( true ), $showEditFieldChecker, new AllowAddController( true ), true, false, $controller );
	$editor->addEditor( new TextAttributeEditor( $o->textAttribute, new SimplePermissionController( false ), true, $attributeIDFilter, $levelDefinedMeaningName ) );
	$editor->addEditor( new TextEditor( $o->text, new SimplePermissionController( true ), true ) );

	// What does this do?
	// addPopupEditors( $editor, $wgWldTextValueObjectAttributesEditors );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function getLinkAttributeValuesEditor( ViewInformation $viewInformation, UpdateController $controller, $levelDefinedMeaningName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	$showEditFieldChecker = new ShowEditFieldForAttributeValuesChecker( $levelDefinedMeaningName, "URL", $attributeIDFilter );

	$editor = new RecordSetTableEditor( $o->linkAttributeValues, new SimplePermissionController( true ), $showEditFieldChecker, new AllowAddController( true ), true, false, $controller );
	$editor->addEditor( new LinkAttributeEditor( $o->linkAttribute, new SimplePermissionController( false ), true, $attributeIDFilter, $levelDefinedMeaningName ) );

	if ( $viewInformation->viewOrEdit == "view" ) {
		$linkEditor = new LinkEditor( $o->link, new SimplePermissionController( true ), true );
	} else {
		$linkEditor = new RecordTableCellEditor( $o->link );
		$linkEditor->addEditor( new ShortTextEditor( $o->linkURL, new SimplePermissionController( true ), true ) );
		$linkEditor->addEditor( new ShortTextEditor( $o->linkLabel, new SimplePermissionController( true ), true ) );
	}

	$editor->addEditor( $linkEditor );

	// what does this do?
	// global $wgWldLinkValueObjectAttributesEditors;
	// addPopupEditors( $editor, $wgWldLinkValueObjectAttributesEditors );

	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function getTranslatedTextAttributeValuesEditor( ViewInformation $viewInformation, UpdateController $controller, $levelDefinedMeaningName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	$showEditFieldChecker = new ShowEditFieldForAttributeValuesChecker( $levelDefinedMeaningName, "TRNS", $attributeIDFilter );

	$editor = new RecordSetTableEditor( $o->translatedTextAttributeValues, new SimplePermissionController( true ), $showEditFieldChecker, new AllowAddController( true ), true, false, $controller );
	$editor->addEditor( new TranslatedTextAttributeEditor( $o->translatedTextAttribute, new SimplePermissionController( false ), true, $attributeIDFilter, $levelDefinedMeaningName ) );
	$editor->addEditor( getTranslatedTextEditor(
		$o->translatedTextValue,
		new TranslatedTextAttributeValueController(),
		$viewInformation
	) );

	// what does this do?
	// addPopupEditors( $editor, $wgWldTranslatedTextValueObjectAttributesEditors );
	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function getOptionAttributeValuesEditor( ViewInformation $viewInformation, UpdateController $controller, $levelDefinedMeaningName, AttributeIDFilter $attributeIDFilter ) {
	$o = OmegaWikiAttributes::getInstance();

	$showEditFieldChecker = new ShowEditFieldForAttributeValuesChecker( $levelDefinedMeaningName, "OPTN", $attributeIDFilter );

	$editor = new RecordSetTableEditor( $o->optionAttributeValues, new SimplePermissionController( true ), $showEditFieldChecker, new AllowAddController( true ), true, false, $controller );
	$editor->addEditor( new OptionAttributeEditor( $o->optionAttribute, new SimplePermissionController( false ), true, $attributeIDFilter, $levelDefinedMeaningName ) );
	$editor->addEditor( new OptionSelectEditor( $o->optionAttributeOption, new SimplePermissionController( false ), true ) );

	// what does this do?
	// addPopupEditors( $editor, $wgWldOptionValueObjectAttributesEditors );
	if ( $viewInformation->showRecordLifeSpan ) {
		$editor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
	}

	return $editor;
}

function getOptionAttributeOptionsEditor() {
	$o = OmegaWikiAttributes::getInstance();

	$editor = new RecordSetTableEditor( $o->optionAttributeOptions, new SimplePermissionController( true ), new ShowEditFieldChecker( true ), new AllowAddController( true ), true, false, new OptionAttributeOptionsController() );
	$editor->addEditor( new DefinedMeaningReferenceEditor( $o->optionAttributeOption, new SimplePermissionController( false ), true ) );
	$editor->addEditor( new LanguageEditor( $o->language, new SimplePermissionController( false ), true ) );

	return $editor;
}

/**
 * The corresponding RecordSet function is getExpressionMeaningsRecordSet
 */
function getExpressionMeaningsEditor( Attribute $attribute, $allowAdd, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$insideExpression = true;
	$definedMeaningEditor = getDefinedMeaningEditor( $viewInformation, $insideExpression );

	$definedMeaningCaptionEditor = new DefinedMeaningHeaderEditor( $o->definedMeaningId, 0 );
	$definedMeaningCaptionEditor->setAddText( wfMessage( 'ow_NewExactMeaning' )->text() );

	$expressionMeaningsEditor = new RecordSetListEditor( $attribute, new SimplePermissionController( true ), new ShowEditFieldChecker( true ), new AllowAddController( $allowAdd ), false, $allowAdd, new ExpressionMeaningController(), 3, false );
	$expressionMeaningsEditor->setCaptionEditor( $definedMeaningCaptionEditor );
	$expressionMeaningsEditor->setValueEditor( $definedMeaningEditor );

	return $expressionMeaningsEditor;
}

/**
 * the corresponding RecordSet function is getExpressionMeaningsRecord
 * returns the main expressions editor, which has two parts
 * first, the list of exact meaning expressions
 * second, the list of approximate meaning expressions
 */
function getExpressionsEditor( $spelling, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$headerLevel = 3;
	$expressionMeaningsRecordEditor = new RecordUnorderedListEditor( $o->expressionMeanings, $headerLevel );
	$expressionMeaningsRecordEditor->setCollapsible( false );

	$allowAdd = true;
	$exactMeaningsEditor = getExpressionMeaningsEditor( $o->expressionExactMeanings, $allowAdd, $viewInformation );
	$exactMeaningsEditor->setDisplayHeader( false );
	$expressionMeaningsRecordEditor->addEditor( $exactMeaningsEditor );

	// add an approximate meaning editor (identicalMeaning = 0):
	$approximateMeaningsEditor = getExpressionMeaningsEditor( $o->expressionApproximateMeanings, false, $viewInformation );
	$expressionMeaningsRecordEditor->addEditor( $approximateMeaningsEditor );

	// show all languages
	$showAttributeNames = false;
	$expressionEditor = new RecordSpanEditor( $o->expression, ': ', ' - ', $showAttributeNames );
	$expressionEditor->addEditor( new TabLanguageEditor( $o->language, new SimplePermissionController( false ), true ) );

	$expressionsEditor = new RecordSetListEditor(
		$o->expressions,
		new SimplePermissionController( true ),
		new ShowEditFieldChecker( true ),
		new AllowAddController( true ),
		false,
		false,
		new ExpressionController( $spelling ),
		2, // headerLevel
		true // childrenExpanded
	);
	$expressionsEditor->setCollapsible( false );
	$expressionsEditor->setCaptionEditor( $expressionEditor );
	$expressionsEditor->setValueEditor( $expressionMeaningsRecordEditor );

	return $expressionsEditor;
}

/**
 * The corresponding RecordSet function is loadRecord in DefinedMeaningModel.php
 * @param ViewInformation $viewInformation
 * @param bool|null $insideExpression indicates if the DM Editor is called inside an Expression Editor
 */
function getDefinedMeaningEditor( ViewInformation $viewInformation, $insideExpression = null ) {
	global $wdDefinedMeaningAttributesOrder, $wgUser;

	$o = OmegaWikiAttributes::getInstance();

	$definitionEditor = getDefinitionEditor( $viewInformation );
	$synonymsAndTranslationsEditor = getSynonymsAndTranslationsEditor( $viewInformation );
	$reciprocalRelationsEditor = getDefinedMeaningReciprocalRelationsEditor( $viewInformation );
	$classMembershipEditor = getDefinedMeaningClassMembershipEditor( $viewInformation );
	$collectionMembershipEditor = getDefinedMeaningCollectionMembershipEditor( $viewInformation );

	$availableEditors = new AttributeEditorMap();
	$availableEditors->addEditor( $definitionEditor );
	// Kip: alternative definitions disabled until we find a use for that field
	// $availableEditors->addEditor( getAlternativeDefinitionsEditor( $viewInformation ) );

	if ( $wgUser->isAllowed( 'editClassAttributes' ) ) {
		$classAttributesEditor = getClassAttributesEditor( $viewInformation );
		$availableEditors->addEditor( $classAttributesEditor );
	}

	$availableEditors->addEditor( $synonymsAndTranslationsEditor );
	$availableEditors->addEditor( $reciprocalRelationsEditor );
	$availableEditors->addEditor( $classMembershipEditor );
	$availableEditors->addEditor( $collectionMembershipEditor );

	foreach ( createPropertyToColumnFilterEditors( $viewInformation, $o->definedMeaningId, WLD_DM_MEANING_NAME ) as $propertyToColumnEditor ) {
		$availableEditors->addEditor( $propertyToColumnEditor );
	}

	$availableEditors->addEditor( createObjectAttributesEditor( $viewInformation, $o->definedMeaningAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $o->definedMeaningId, WLD_DM_MEANING_NAME, $viewInformation->getLeftOverAttributeFilter() ) );

	// if we come from Expression, or a syntransId is given, also add a syntrans annotations editor
	if ( $insideExpression ) {
		$syntransAttributesEditor = new ObjectAttributeValuesEditor( $o->syntransAttributes, wfMessage( "ow_Property" )->text(), wfMessage( "ow_Value" )->text(), $viewInformation, WLD_SYNTRANS_MEANING_NAME, $viewInformation->getLeftOverAttributeFilter() );

		addObjectAttributesEditors(
			$syntransAttributesEditor,
			$viewInformation,
			new ObjectIdFetcher( 0, $o->objectId )
		);
		// we need to wrap the editor to geth the "objectId" (i.e. syntransId) in the idPath
		// Otherwise, saving new data does not work
		$syntransAttributesWrappedEditor = new ObjectContextEditor( $syntransAttributesEditor );

		$availableEditors->addEditor( $syntransAttributesWrappedEditor );
	}

	$definedMeaningEditor = new RecordUnorderedListEditor( $o->definedMeaning, 4 );

	// put all of the above editors in the right order.
	// the default order is defined in WikiDataGlobals.php but can be reconfigured
	foreach ( $wdDefinedMeaningAttributesOrder as $attributeId ) {
		$editor = $availableEditors->getEditorForAttributeId( $attributeId );

		if ( $editor != null ) {
			$definedMeaningEditor->addEditor( $editor );
		}
	}

	return new DefinedMeaningContextEditor( $definedMeaningEditor );
}

function createTableViewer( $attribute ) {
	return new RecordSetTableEditor(
		$attribute,
		new SimplePermissionController( false ),
		new ShowEditFieldChecker( true ),
		new AllowAddController( false ),
		false,
		false,
		null
	);
}

function createLanguageViewer( $attribute ) {
	return new LanguageEditor( $attribute, new SimplePermissionController( false ), false );
}

function createLongTextViewer( $attribute ) {
	$result = new TextEditor( $attribute, new SimplePermissionController( false ), false );

	return $result;
}

function createShortTextViewer( $attribute ) {
	return new ShortTextEditor( $attribute, new SimplePermissionController( false ), false );
}

function createLinkViewer( $attribute ) {
	return new LinkEditor( $attribute, new SimplePermissionController( false ), false );
}

function createBooleanViewer( $attribute ) {
	return new BooleanEditor( $attribute, new SimplePermissionController( false ), false, false );
}

function createDefinedMeaningReferenceViewer( $attribute ) {
	return new DefinedMeaningReferenceEditor( $attribute, new SimplePermissionController( false ), false );
}

function createSuggestionsTableViewer( $attribute ) {
	$result = createTableViewer( $attribute );
	$result->setHideEmptyColumns( false );
	$result->setRowHTMLAttributes( [
		"class" => "suggestion-row"
	] );

	return $result;
}

function createUserViewer( $attribute ) {
	return new UserEditor( $attribute, new SimplePermissionController( false ), false );
}

function createTranslatedTextViewer( $attribute ) {
	$o = OmegaWikiAttributes::getInstance();

	$result = createTableViewer( $attribute );
	$result->addEditor( createLanguageViewer( $o->language ) );
	$result->addEditor( createLongTextViewer( $o->text ) );

	return $result;
}
