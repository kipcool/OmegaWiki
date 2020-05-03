<?php

require_once "Attribute.php";
require_once "WikiDataGlobals.php";
require_once "ViewInformation.php";
require_once "Utilities.php";

/**
 * This file models the structure of the OmegaWiki database in a
 * database-independent fashion. To do so, it follows a simplified
 * relational model, consisting of Attribute objects which are hierarchically
 * grouped together using Structure objects. See Attribute.php for details.
 *
 * The actual data is stored in Records, grouped together as RecordSets.
 * See Record.php and RecordSet.php for details.
 *
 * OmegawikiAttributes2.php was running out of date already, so
 * merging here.
 *
 * TODO:
 * - Records and RecordSets are currently capable of storing most (not all)
 * data, but can't actually commit them to the database again. To achieve
 * proper separation of architectural layers, the Records should learn
 * to talk directly with the DB layer.
 * # - This is not a pure singleton, because it relies on the existence of
 * #   of viewInformation, and a message cache. We now defer lookups in these
 * #   to as late as possible, to make sure these items are actually initialized.
 */
function initializeOmegaWikiAttributes( ViewInformation $viewInformation ) {
	$init_and_discard_this = OmegaWikiAttributes::getInstance( $viewInformation );
}

class OmegaWikiAttributes {

	/** pseudo-Singleton, if viewinformation changes, will construct new instance */
	static function getInstance( ViewInformation $viewInformation = null ) {
		static $instance = [];
		if ( $viewInformation !== null ) {
			if ( !array_key_exists( $viewInformation->hashCode(), $instance ) ) {
				$instance["last"] = new OmegaWikiAttributes( $viewInformation );
				$instance[$viewInformation->hashCode()] = $instance["last"];
			}
		}
		if ( !array_key_exists( "last", $instance ) ) {
			$instance["last"] = new OmegaWikiAttributes( new ViewInformation() );
		}
		return $instance["last"];
	}

	protected $attributes = [];
	protected $setup_completed = false;
	protected $in_setup = false; # for use by functions doing the setup itself (currently hardValues)

	/**
	 * @var ViewInformation
	 */
	protected $viewInformation;

	function __construct( ViewInformation $viewInformation ) {
		$this->setup( $viewInformation );
	}

	protected function setup( ViewInformation $viewInformation = null ) {
		if ( $this->in_setup or $this->setup_completed ) {
			return true;
		}

		if ( $viewInformation !== null ) {
			$this->viewInformation = $viewInformation;
		}
		$viewInformation = $this->viewInformation;

		if ( $viewInformation !== null ) {
			if ( !$this->setup_completed ) {
				$this->hardValues( $viewInformation );
			}
			$this->setup_completed = true;
			return true;
		}
		return false;
	}

	/** Hardcoded schema for now. Later refactor to load from file or DB
	 *
	 * Naming: keys are previous name minus -"Attribute"
	 * 	(-"Structure" is retained, -"Attributes" is retained)
	 */
	private function hardValues( ViewInformation $viewInformation ) {
		global $wgWlddefinedMeaningReferenceType;

		$this->in_setup = true;

		// *** DEFINING THE SIMPLE ATTRIBUTES ***
		// i.e. the ones where the type is a string (and not a structure)
		$this->attributeObjectId = new Attribute( "attributeObjectId", "Attribute object", "object-id" );
		$this->classAttributeId = new Attribute( "class-attribute-id", "Class attribute identifier", "object-id" );
		$this->classAttributeType = new Attribute( "class-attribute-type", wfMessage( "ow_ClassAttributeType" )->plain(), "short-text" );
		$this->classMembershipId = new Attribute( "class-membership-id", "Class membership id", "integer" );
		$this->collectionId = new Attribute( "collection", "Collection", "collection-id" );

		$this->definedMeaningAttributes = new Attribute( WLD_DM_ATTRIBUTES, wfMessage( 'ow_AnnotationDefinedMeaning' )->plain(), "will-be-specified-below" );
		$this->definedMeaningDefiningExpression = new Attribute( "defined-meaning-defining-expression", "Defined meaning defining expression", "short-text" );
		$this->definedMeaningLabel = new Attribute( "defined-meaning-label", "Defined meaning label", "short-text" );
		$this->definedMeaningId = new Attribute( "defined-meaning-id", "Defined meaning identifier", "defined-meaning-id" );

		$this->definitionId = new Attribute( "definition-id", "Definition identifier", "integer" );
		$this->expressionId = new Attribute( "expression-id", "Expression Id", "expression-id" );
		$this->id = new Attribute( "id", wfMessage( 'ow_ID' )->plain(), "id" );

		// instead of " ", could be wfMessage( "ow_IdenticalMeaning" ), but then the header is too long
		// and the column in the table is too large
		$this->identicalMeaning = new Attribute( WLD_IDENTICAL_MEANING, " ", "identmeaning" );

		$this->language = new Attribute( "language", wfMessage( "ow_Language" )->plain(), "language" );

		$this->linkAttributeId = new Attribute( "link-attribute-id", "Attribute identifier", "object-id" );
		$this->linkAttributeObject = new Attribute( "link-attribute-object-id", "Attribute object", "object-id" );
		$this->linkLabel = new Attribute( "label", wfMessage( 'ow_Label' )->plain(), "short-text" );
		$this->linkURL = new Attribute( "url", wfMessage( 'ow_URL' )->plain(), "url" );

		$this->objectAttributes = new Attribute( WLD_OBJECT_ATTRIBUTES, wfMessage( "ow_Annotation" )->plain(), "will-be-specified-below" );
		$this->objectId = new Attribute( "object-id", "Object identifier", "object-id" );

		$this->optionAttributeId = new Attribute( "option-attribute-id", "Attribute identifier", "object-id" );
		$this->optionAttributeObject = new Attribute( "option-attribute-object-id", "Attribute object", "object-id" );
		$this->optionAttributeOptionId = new Attribute( "option-attribute-option-id", "Option identifier", "object-id" );

		$this->relationId = new Attribute( "relation-id", "Relation identifier", "object-id" );
		$this->sourceIdentifier = new Attribute( "source-identifier", wfMessage( "ow_SourceIdentifier" )->plain(), "short-text" );
		$this->spelling = new Attribute( "spelling", wfMessage( "ow_Spelling" )->plain(), "spelling" );
		$this->syntransId = new Attribute( "syntrans-id", "Syntrans identifier", "integer" );
		$this->text = new Attribute( "text", wfMessage( "ow_Text" )->plain(), "text" );
		$this->textAttributeId = new Attribute( "text-attribute-id", "Attribute identifier", "object-id" );
		$this->textAttributeObject = new Attribute( "text-attribute-object-id", "Attribute object", "object-id" );
		$this->translatedTextAttributeId = new Attribute( "translated-text-attribute-id", "Attribute identifier", "object-id" );
		$this->translatedTextId = new Attribute( "translated-text-id", "Translated text ID", "integer" );
		$this->translatedTextValueId = new Attribute( "translated-text-value-id", "Translated text value identifier", "translated-text-value-id" );

		// *** STRUCTURES AND STRUCTURE-TYPE ATTRIBUTES ***
		$this->expressionStructure = new Structure( WLD_EXPRESSION, $this->language, $this->spelling );
		$this->expression = new Attribute( WLD_EXPRESSION, wfMessage( "ow_Expression" )->plain(), $this->expressionStructure );

		$this->definedMeaningCompleteDefiningExpressionStructure =
			new Structure( "defined-meaning-complete-defining-expression",
				  $this->definedMeaningDefiningExpression,
				  $this->expressionId,
				  $this->language
			);
		# try this
		$this->definedMeaningCompleteDefiningExpressionStructure->setStructureType( WLD_EXPRESSION );
		$this->definedMeaningCompleteDefiningExpression = new Attribute( null, "Defining expression", $this->definedMeaningCompleteDefiningExpressionStructure );

		$this->definedMeaningReferenceStructure = new Structure( WLD_DEFINED_MEANING, $this->definedMeaningId, $this->definedMeaningLabel, $this->definedMeaningDefiningExpression );
		$wgWlddefinedMeaningReferenceType = $this->definedMeaningReferenceStructure; // global variable

		$this->definedMeaningReference = new Attribute( null, wfMessage( "ow_DefinedMeaningReference" )->plain(), $this->definedMeaningReferenceStructure );

		$this->collectionMeaning = new Attribute( "collection-meaning", wfMessage( "ow_Collection" )->plain(), $this->definedMeaningReferenceStructure );

		$this->gotoSourceStructure = new Structure( "goto-source", $this->collectionId, $this->sourceIdentifier );
		$this->gotoSource = new Attribute( null, wfMessage( "ow_GotoSource" )->plain(), $this->gotoSourceStructure );

		$this->collectionMembershipStructure = new Structure( WLD_COLLECTION_MEMBERSHIP, $this->collectionId, $this->collectionMeaning, $this->sourceIdentifier );
		$this->collectionMembership = new Attribute( null, wfMessage( "ow_CollectionMembership" )->plain(), $this->collectionMembershipStructure );

		$this->class = new Attribute( "class", wfMessage( 'ow_Class' )->plain(), $this->definedMeaningReferenceStructure );
		$this->classMembershipStructure = new Structure( WLD_CLASS_MEMBERSHIP, $this->classMembershipId, $this->class );
		$this->classMembership = new Attribute( null, wfMessage( "ow_ClassMembership" )->plain(), $this->classMembershipStructure );

		// the type of relation is a DM. e.g. for the relation "antonym" it would be the DM that defines "antony"
		$this->relationType = new Attribute( "relation-type", wfMessage( "ow_RelationType" )->plain(), $this->definedMeaningReferenceStructure );

		// otherObject is what the relation links to. It could be a DM or a Syntrans or anything else.
		$this->otherObject = new Attribute( WLD_OTHER_OBJECT, wfMessage( "ow_RelatedTo" )->plain(), $this->objectId );

		$this->relationStructure = new Structure( WLD_RELATIONS, $this->relationId, $this->relationType, $this->otherObject );
		$this->relations = new Attribute( WLD_RELATIONS, wfMessage( "ow_Relations" )->plain(), $this->relationStructure );

		$this->reciprocalRelations = new Attribute( WLD_INCOMING_RELATIONS, wfMessage( "ow_IncomingRelations" )->plain(), $this->relationStructure );

		$this->translatedTextStructure = new Structure( WLD_TRANSLATED_TEXT, $this->language, $this->text );

		$this->alternativeDefinition = new Attribute( WLD_ALTERNATIVE_DEF, wfMessage( "ow_AlternativeDefinition" )->plain(), $this->translatedTextStructure );

		$this->source = new Attribute( "source-id", wfMessage( "ow_Source" )->plain(), $this->definedMeaningReferenceStructure );

		$this->alternativeDefinitionsStructure = new Structure( WLD_ALTERNATIVE_DEFINITIONS, $this->definitionId, $this->alternativeDefinition, $this->source );
		$this->alternativeDefinitions = new Attribute( null, wfMessage( "ow_AlternativeDefinitions" )->plain(), $this->alternativeDefinitionsStructure );

		$this->synonymsTranslationsStructure = new Structure( WLD_SYNONYMS_TRANSLATIONS, $this->identicalMeaning, $this->syntransId, $this->expression );
		$this->synonymsAndTranslations = new Attribute( null, wfMessage( 'ow_SynonymsAndTranslations' )->plain(), $this->synonymsTranslationsStructure );

		// alternative full syntrans structure with expression already decomposed into language and spelling
		// $this->synTransExpressionStructure = new Structure( WLD_SYNONYMS_TRANSLATIONS, $this->identicalMeaning, $this->syntransId, $this->expressionId, $this->language, $this->spelling );

		$this->translatedTextAttribute = new Attribute( "translated-text-attribute", wfMessage( "ow_TranslatedTextAttribute" )->plain(), $this->definedMeaningReferenceStructure );

		$this->translatedTextValue = new Attribute( "translated-text-value", wfMessage( "ow_TranslatedTextAttributeValue" )->plain(), $this->translatedTextStructure );

		$this->translatedTextAttributeValuesStructure = new Structure( "translated-text-attribute-values", $this->translatedTextAttributeId, $this->attributeObjectId, $this->translatedTextAttribute, $this->translatedTextValueId, $this->translatedTextValue );
		$this->translatedTextAttributeValues = new Attribute( null, wfMessage( "ow_TranslatedTextAttributeValues" )->plain(), $this->translatedTextAttributeValuesStructure );

		$this->textAttribute = new Attribute( "text-attribute", wfMessage( "ow_TextAttribute" )->plain(), $this->definedMeaningReferenceStructure );
		$this->textAttributeValuesStructure = new Structure( WLD_TEXT_ATTRIBUTES_VALUES, $this->textAttributeId, $this->textAttributeObject, $this->textAttribute, $this->text );
		$this->textAttributeValues = new Attribute( null, wfMessage( "ow_TextAttributeValues" )->plain(), $this->textAttributeValuesStructure );

		$this->linkStructure = new Structure( $this->linkLabel, $this->linkURL );
		$this->link = new Attribute( "link", wfMessage( 'ow_Link' )->plain(), $this->linkStructure );

		$this->linkAttribute = new Attribute( WLD_LINK_ATTRIBUTE, wfMessage( "ow_LinkAttribute" )->plain(), $this->definedMeaningReferenceStructure );
		$this->linkAttributeValuesStructure = new Structure( WLD_LINK_ATTRIBUTE_VALUES, $this->linkAttributeId, $this->linkAttributeObject, $this->linkAttribute, $this->link );
		$this->linkAttributeValues = new Attribute( null, wfMessage( "ow_LinkAttributeValues" )->plain(), $this->linkAttributeValuesStructure );

		$this->optionAttribute = new Attribute( WLD_OPTION_ATTRIBUTE, wfMessage( "ow_OptionAttribute" )->plain(), $this->definedMeaningReferenceStructure );
		$this->optionAttributeOption = new Attribute( WLD_OPTION_ATTRIBUTE_OPTION, wfMessage( "ow_OptionAttributeOption" )->plain(), $this->definedMeaningReferenceStructure );
		$this->optionAttributeValuesStructure = new Structure( WLD_OPTION_ATTRIBUTE_VALUES, $this->optionAttributeId, $this->optionAttribute, $this->optionAttributeObject, $this->optionAttributeOption );
		$this->optionAttributeValues = new Attribute( null, wfMessage( "ow_OptionAttributeValues" )->plain(), $this->optionAttributeValuesStructure );
		$this->optionAttributeOptionsStructure = new Structure( "option-attribute-options", $this->optionAttributeOptionId, $this->optionAttribute, $this->optionAttributeOption, $this->language );
		$this->optionAttributeOptions = new Attribute( null, wfMessage( "ow_OptionAttributeOptions" )->plain(), $this->optionAttributeOptionsStructure );

		$this->translatedText = new Attribute( WLD_TRANSLATED_TEXT, wfMessage( "ow_TranslatedText" )->plain(), $this->translatedTextStructure );

		$this->definitionStructure = new Structure( WLD_DEFINITION, $this->translatedText );
		$this->definition = new Attribute( null, wfMessage( "ow_Definition" )->plain(), $this->definitionStructure );

		$this->classAttributeAttribute = new Attribute( "class-attribute-attribute", wfMessage( "ow_ClassAttributeAttribute" )->plain(), $this->definedMeaningReferenceStructure );
		$this->classAttributeLevel = new Attribute( "class-attribute-level", wfMessage( "ow_ClassAttributeLevel" )->plain(), $this->definedMeaningReferenceStructure );
		$this->classAttributesStructure = new Structure( WLD_CLASS_ATTRIBUTES, $this->classAttributeId, $this->classAttributeAttribute, $this->classAttributeLevel, $this->classAttributeType, $this->optionAttributeOptions );
		$this->classAttributes = new Attribute( null, wfMessage( "ow_ClassAttributes" )->plain(), $this->classAttributesStructure );

		$this->objectAttributesStructure = new Structure( "object-attributes",
			$this->objectId,
			$this->relations,
			$this->textAttributeValues,
			$this->translatedTextAttributeValues,
			$this->linkAttributeValues,
			$this->optionAttributeValues
		);

		$this->objectAttributes->setAttributeType( $this->objectAttributesStructure );
		$this->definedMeaningAttributes->setAttributeType( $this->objectAttributesStructure );

		// this is only Syntrans Attributes that are displayed at the DM level
		// the other syntrans attributes (column in translation table) are in fact objectAttributes
		// TODO: maybe change that?
		$this->syntransAttributes = new Attribute( WLD_SYNT_ATTRIBUTES, wfMessage( 'ow_AnnotationSyntrans' )->plain(), $this->objectAttributesStructure );

		$this->definedMeaningStructure = new Structure(
			WLD_DEFINED_MEANING,
			$this->definedMeaningId,
			$this->definedMeaningCompleteDefiningExpression,
			$this->definition,
			$this->classAttributes,
			$this->alternativeDefinitions,
			$this->synonymsAndTranslations,
			$this->reciprocalRelations,
			$this->classMembership,
			$this->collectionMembership,
			$this->definedMeaningAttributes,
			$this->syntransAttributes
		);
		$this->definedMeaning = new Attribute( null, wfMessage( "ow_DefinedMeaning" )->plain(), $this->definedMeaningStructure );

		$this->expressionMeaningStructure = new Structure( $this->definedMeaningId, $this->text, $this->definedMeaning );
		$this->expressionExactMeanings = new Attribute( WLD_EXPRESSION_EXACT_MEANINGS, wfMessage( "ow_ExactMeanings" )->plain(), $this->expressionMeaningStructure );
		$this->expressionApproximateMeanings = new Attribute( WLD_EXPRESSION_APPROX_MEANINGS, wfMessage( "ow_ApproximateMeanings" )->plain(), $this->expressionMeaningStructure );
		$this->expressionMeaningsStructure = new Structure( WLD_EXPRESSION_MEANINGS, $this->expressionExactMeanings, $this->expressionApproximateMeanings );
		$this->expressionMeanings = new Attribute( null, wfMessage( "ow_ExpressionMeanings" )->plain(), $this->expressionMeaningsStructure );
		$this->expressionsStructure = new Structure( "expressions", $this->expressionId, $this->expression, $this->expressionMeanings );
		$this->expressions = new Attribute( null, wfMessage( "ow_Expressions" )->plain(), $this->expressionsStructure );

		$annotatedAttributes = [
			$this->definedMeaning,
			$this->definition,
			$this->synonymsAndTranslations,
			$this->relations,
			$this->reciprocalRelations,
			$this->textAttributeValues,
			$this->linkAttributeValues,
			$this->translatedTextAttributeValues,
			$this->optionAttributeValues
		];

		foreach ( $annotatedAttributes as $annotatedAttribute ) {
			$annotatedAttribute->type->addAttribute( $this->objectAttributes );
		}
		foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
			$attribute = $propertyToColumnFilter->getAttribute();
			$attribute->setAttributeType( $this->objectAttributesStructure );

			foreach ( $annotatedAttributes as $annotatedAttribute ) {
				$annotatedAttribute->type->addAttribute( $attribute );
			}
		}

		// Attributes and Structure about transactions
		$this->transactionId = new Attribute( 'transaction-id', 'Transaction ID', 'integer' );
		$this->user = new Attribute( 'user', wfMessage( 'ow_User' )->plain(), 'user' );
		$this->userIP = new Attribute( 'user-ip', 'User IP', 'IP' );
		$this->timestamp = new Attribute( 'timestamp', wfMessage( 'ow_Time' )->plain(), 'timestamp' );
		$this->summary = new Attribute( 'summary', 'Summary', 'text' );
		$this->transactionStructure = new Structure( $this->transactionId, $this->user, $this->userIP, $this->timestamp, $this->summary );
		$this->transaction = new Attribute( 'transaction', 'Transaction', $this->transactionStructure );

		$this->addTransaction = new Attribute( 'add-transaction', wfMessage( 'ow_added' )->plain(), $this->transactionStructure );
		$this->removeTransaction = new Attribute( 'remove-transaction', wfMessage( 'ow_removed' )->plain(), $this->transactionStructure );

		$this->recordLifeSpanStructure = new Structure( $this->addTransaction, $this->removeTransaction );
		$this->recordLifeSpan = new Attribute( 'record-life-span', wfMessage( 'ow_RecordLifeSpan' )->plain(), $this->recordLifeSpanStructure );

		// setup finished
		$this->in_setup = false;
	}

	/**
	 * @return ViewInformation
	 */
	public function getViewInformation() {
		return $this->viewInformation;
	}

	public function __set( $key, $value ) {
		if ( !$this->setup() ) {
			throw new Exception( "OmegaWikiAttributes accessed, but was not properly initialized" );
		}
		$attributes =& $this->attributes;
		$attributes[$key] = $value;
	}

	public function __get( $key ) {
		if ( !$this->setup() ) {
			throw new MwException( "OmegaWikiAttributes accessed, but was not properly initialized" );
		}
		$attributes =& $this->attributes;
		if ( !array_key_exists( $key, $attributes ) ) {
			throw new MwException( "Key does not exist: " . $key );
		}
		return $attributes[$key];
	}
}
