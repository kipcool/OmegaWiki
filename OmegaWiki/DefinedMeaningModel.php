<?php
/** @file
 */
require_once 'OmegaWikiAttributes.php';
require_once 'OmegaWikiRecordSets.php';
require_once 'OmegaWikiAttributes.php';
require_once "Transaction.php";
require_once "WikiDataAPI.php";

/**
 * A front end for the database information/ArrayRecord and any other information
 * to do with defined meanings (as per MVC)
 * Will collect code for instantiating and loading and saving DMs here for now.
 */
class DefinedMeaningModel {

	protected $record = null;
	protected $recordIsLoaded = false;
	protected $exists = null;
	protected $id = null;
	protected $viewInformation = null;
	protected $definingExpression = null; # String
	protected $dataset = null;
	protected $syntrans = [];
	// optionally, a syntransId can be given to see syntrans annotations
	// inside of the DM Editor (e.g. when viewing a DM inside an expression page).
	protected $syntransId = null;
	protected $titleObject = null;

	/**
	 * Construct a new DefinedMeaningModel for a particular DM.
	 * You need to call loadRecord() to load the actual data.
	 *
	 * @param int $definedMeaningId the database ID of the DM
	 * @param array $params optional parameters to pass to the constructor
	 * can be "viewinformation" of type ViewInformation, or "dataset" of type DataSet
	 * or "syntransid" which is an integer
	 */
	public function __construct( $definedMeaningId, $params = [] ) {
		if ( !$definedMeaningId ) {
			throw new Exception( "DM needs at least a DMID!" );
		}
		$this->setId( $definedMeaningId );

		if ( array_key_exists( "viewinformation", $params ) ) {
			$this->viewInformation = $params["viewinformation"];
		} else {
			$viewInformation = new ViewInformation();
			$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
		}

		if ( array_key_exists( "dataset", $params ) ) {
			$this->dataset = $params["dataset"];
		} else {
			$this->dataset = wdGetDataSetContext();
		}

		if ( array_key_exists( "syntransid", $params ) ) {
			$this->syntransId = $params["syntransid"];
		}
	}

	/**
	 * Checks for existence of a DM.
	 * If $this->definingExpression is set, it will also check if the spelling
	 * of the defining expression matches
	 *
	 * @param bool $searchAllDataSets If true, checks beyond the dataset context and will
	 *                return the first match. Always searches current
	 *                context first.
	 * @param bool $switchContext Switch dataset context if match outside default is found.
	 *
	 * @return DataSet|null object in which the DM was found, or null.
	 */
	public function checkExistence( $searchAllDataSets = false, $switchContext = false ) {
		global $wdCurrentContext;
		$match = $this->checkExistenceInDataSet( $this->dataset );
		if ( $match !== null ) {
			$this->exists = true;
			return $match;
		} else {
			$this->exists = false;
			if ( !$searchAllDataSets ) {
				return null;
			}
		}
		// Continue search
		$datasets = wdGetDataSets();
		foreach ( $datasets as $currentSet ) {
			if ( $currentSet->getPrefix() != $this->dataset->getPrefix() ) {
				$match = $this->checkExistenceInDataSet( $currentSet );
				if ( $match !== null ) {
					$this->exists = true;
					if ( $switchContext ) {
						$wdCurrentContext = $match;
						$this->dataset = $match;
					}
					return $match;
				}
			}
		}
		$this->exists = false;
		return null;
	}

	public function CheckIfStub() {
		$dataset = $this->getDataset();
		$id = $this->getId();
		if ( $dataset === null ) {
			throw new Exception( "DefinedMeaningModel->isStub: Dataset is null." );
		}
		if ( $id === null ) {
			throw new Exception( "DefinedMeaningModel->isStub: Id is null." );
		}
		require_once "Copy.php";
		return CopyTools::CheckIfStub( $dataset, $id );
	}

	/**
	 * @param DataSet $dc where to look
	 * @return DataSet|null
	 * @see checkExistence
	 */
	public function checkExistenceInDataSet( DataSet $dc ) {
		$definingExpression = $this->definingExpression;
		$id = $this->getId();
		$dbr = wfGetDB( DB_REPLICA );
		$dmRow = $dbr->selectRow(
			[ 'dm' => "{$dc}_defined_meaning" ],
			[
				'defined_meaning_id',
				'expression_id'
			],
			[
				'defined_meaning_id' => $this->id,
				'dm.remove_transaction_id' => null
			], __METHOD__
		);

		if ( !$dmRow || !$dmRow->defined_meaning_id ) {
			return null;
		}
		if ( $definingExpression === null ) {
			return $dc;
		} else {
			$expid = (int)$dmRow->expression_id;
			$storedExpression = getExpression( $expid, $dc );
			if ( $storedExpression === null ) {
				return null;
			}
			if ( $storedExpression->spelling != $definingExpression ) {
				// Defining expression does not match, but check was requested!
				return null;
			} else {
				return $dc;
			}
		}
		return $dc;
	}

	/**
	 * Load the associated record object.
	 *
	 * @return Boolean indicating success.
	 */
	public function loadRecord() {
		if ( $this->exists === null ) {
			$this->checkExistence();
		}

		if ( !$this->exists ) {
			return false;
		}

		$id = $this->getId();
		$view = $this->getViewInformation();
		/** @todo FIXME: Records should be loaded using helpers rather than
		 * global functions!
		 */
		$o = OmegaWikiAttributes::getInstance();

		$record = new ArrayRecord( $o->definedMeaning->type );
		$record->definedMeaningId = $id;
		$record->definedMeaningCompleteDefiningExpression = getDefiningExpressionRecord( $id );
		$record->definition = getDefinedMeaningDefinitionRecord( $id, $view );
		$record->classAttributes = getClassAttributesRecordSet( $id, $view );
		// Kip: alternative definitions disabled until we find a use for that field
		// $record->alternativeDefinitions = getAlternativeDefinitionsRecordSet( $id, $view );

		// exclude the current syntrans from the list of Synonyms
		$excludeSyntransId = null;
		if ( $this->syntransId ) {
			$excludeSyntransId = $this->syntransId;
		}
		$record->synonymsAndTranslations = getSynonymAndTranslationRecordSet( $id, $view, $excludeSyntransId );
		$record->reciprocalRelations = getDefinedMeaningReciprocalRelationsRecordSet( $id, $view );
		$record->classMembership = getDefinedMeaningClassMembershipRecordSet( $id, $view );
		$record->collectionMembership = getDefinedMeaningCollectionMembershipRecordSet( $id, $view );
		// Adds Annotation at a DM level
		$objectAttributesRecord = getObjectAttributesRecord( $id, $view, null, "DM" );
		$record->definedMeaningAttributes = $objectAttributesRecord;
		// what this does is not clear...
		// applyPropertyToColumnFiltersToRecord( $record, $objectAttributesRecord, $view );

		// if syntransAttributes should be displayed, get them
		if ( $this->syntransId ) {
			$record->syntransAttributes = getObjectAttributesRecord( $this->syntransId, $view, null, "SYNT" );
		}

		$this->record = $record;
		$this->recordIsLoaded = true;
		return true;
	}

	/**
	 * @todo FIXME - work in progress
	 */
	public function save() {
		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $this->viewInformation );

		# Nice try sherlock, but we really need to get our DMID from elsewhere
		# $definedMeaningId = $this->getId();

		# Need 3 steps: copy defining expression, create new dm, then update

		$expression = $this->dupDefiningExpression();
		var_dump( $expression );
		# to make the expression really work, we may need to call
		# more here?
		$expression->createNewInDatabase();

		# shouldn't this stuff be protected?
		$expressionId = $expression->id;
		$languageId = $expression->languageId;

		$this->hackDC(); // XXX
		$text = $this->getDefiningExpression();
		$this->unhackDC(); // XXX

		# here we assume the DM is not there yet.. not entirely wise
		# in the long run.
		echo "id: $expressionId lang: $languageId";
		$newDefinedMeaningId = createNewDefinedMeaning( $expressionId, $languageId, $text );

		getDefinedMeaningEditor( $this->viewInformation )->save(
			$this->getIdStack( $newDefinedMeaningId ),
			$this->getRecord()
		);

		return $newDefinedMeaningId;
	}

	/**
	 * @todo FIXME - work in progress
	 */
	protected function getIdStack( $definedMeaningId ) {
		$o = OmegaWikiAttributes::getInstance();

		$definedMeaningIdStructure = new Structure( $o->definedMeaningId );
		$definedMeaningIdRecord = new ArrayRecord( $definedMeaningIdStructure, $definedMeaningIdStructure );
		$definedMeaningIdRecord->definedMeaningId = $definedMeaningId;

		$idStack = new IdStack( WLD_DEFINED_MEANING );
		$idStack->pushKey( $definedMeaningIdRecord );

		return $idStack;
	}

	/**
	 * @todo FIXME - work in progress
	 */
	public function saveWithinTransaction() {
		# global
		# 	$wgTitle, $wgUser, $wgRequest;

		global
			$wgUser, $wgOut;

		if ( !$wgUser->isAllowed( 'wikidata-copy' ) ) {
			$wgOut->addWikiMsg( "ow_noedit", $dc->fetchName() );
			$wgOut->setPageTitle( wfMessage( "ow_noedit_title" )->text() );
			return false;
		}
		# $summary = $wgRequest->getText('summary');

		// Insert transaction information into the DB
		startNewTransaction( 0, "0.0.0.0", "copy operation" );

		// Perform regular save
		# $this->save(new QueryAtTransactionInformation($wgRequest->getInt('transaction'), false));
		$newDefinedMeaningId = $this->save();

		// Update page caches
		# Title::touchArray(array($wgTitle));

		// Add change to RC log
		# $now = wfTimestampNow();
		# RecentChange::notifyEdit($now, $wgTitle, false, $wgUser, $summary, 0, $now, false, '', 0, 0, 0);
		return $newDefinedMeaningId;
	}

	/**
	 * @return associated record object or null. Loads it if necessary.
	 */
	public function getRecord() {
		if ( !$this->recordIsLoaded ) {
			$this->hackDC(); // XXX don't do this at home
			$this->loadRecord();
			$this->unhackDC(); // XXX
		}
		if ( !$this->recordIsLoaded ) {
			return null;
		}
		return $this->record;
	}

	public function setViewInformation( ViewInformation $viewInformation ) {
		$this->viewInformation = $viewInformation;
	}

	public function getViewInformation() {
		return $this->viewInformation;
	}

	/** Attempts to save defining expression if it does not exist "here"
	 * (This works right now because we override the datasetcontext in
	 * SaveDM.php . dc should be handled more solidly)
	 */
	protected function dupDefiningExpression() {
		$record = $this->getRecord();
		$expression = $record->expression;
		$spelling = $expression->definedMeaningDefiningExpression;
		$language = $expression->language;
		return findOrCreateExpression( $spelling, $language );
	}

	/** Copy this defined meaning to specified dataset-context
	 * Warning: This is somewhat new  code, which still needs
	 * shoring up.
	 * @param string $dataset dataset to copy to.
	 * @return 	defined meaning id in the new dataset
	 */
	public function copyTo( $dataset ) {
		# $definedMeaningID=$this->getId();
		echo "copy to:$dataset   ";
		# $from_dc=$this->getDataset();
		$to_dc = $dataset;
		# TODO We should actually thoroughly check that everything
		# is present before proceding, and throw some exceptions
		# if not.

		global
			$wdCurrentContext;

		$concept_map = []; # while we're here, let's map the concepts.

		$from_dc = $this->getDataSet();
		$oldId = $this->getId();
		$concept_map["$from_dc"] = $oldId;

		$wdCurrentContext = $to_dc;	# set global override (DIRTY!)
		$newDefinedMeaningId = $this->saveWithinTransaction();
		$concept_map["$to_dc"] = $newDefinedMeaningId;
		$wdCurrentContext = null;		# unset override, we probably should
						# use proper OO for this.

		createConceptMapping( $concept_map );
	}

	/* XXX
	 * 2 very dirty functions, as a placeholder to make things work
	 * this very instant.
	 * Take the already evil global context, and twist it to our
	 * own nefarious ends, then we put it back ASAP and hope nobody
	 * notices.
	 * Of course, one day they will.
	 * Before then, this should be refactored out.
	 * Probably by next week friday
	 * Note that there is no stack, so you can't nest these!
	 * Nor will we implement one, global dc must die.
	 * XXX */
	private $_saved_dc = null; # the dirty secret

	public function hackDC( $to_dataset = null ) {
		global
			$wdCurrentContext;

		if ( $to_dataset == null ) {
			$to_dataset = $this->dataset;
		}

		$this->_saved_dc = $wdCurrentContext;
		$wdCurrentContext = $to_dataset;
		return $wdCurrentContext;
	}

	public function unhackDC() {
		global
			$wdCurrentContext;
		$wdCurrentContext = $this->_saved_dc;
		return $wdCurrentContext;
	}

	/**
	 * Return one of the syntrans entries of this defined meaning,
	 * specified by language code. Caches the syntrans records
	 * in an array.
	 *
	 * @param string $languageCode Language code of the synonym/translation to look for
	 * @param string $fallbackCode Fallback to use if not found
	 * @return Spelling or null if not found at all
	 *
	 * @todo make fallback optional
	 */
	public function getSyntransByLanguageCode( $languageCode, $fallbackCode = WLD_ENGLISH_LANG_WMKEY ) {
		if ( array_key_exists( $languageCode, $this->syntrans ) ) {
		  return $this->syntrans[$languageCode];
		}

		$syntrans = getSpellingForLanguage( $this->getId(), $languageCode, $fallbackCode, $this->dataset );
		if ( $syntrans !== null ) {
			$this->syntrans[$languageCode] = $syntrans;
		}
		return $syntrans;
	}

	/**
	 * @return the page title object associated with this defined meaning
	 * First time from DB lookup. Subsequently from cache
	 */
	public function getTitleObject() {
		if ( $this->titleObject == null ) {
			$definingExpression = $this->getDefiningExpression();
			$id = $this->getId();

			if ( $definingExpression === null or $id === null ) {
				return null;
			}

			$definingExpressionAsTitle = str_replace( " ", "_", $definingExpression );
			$text = "DefinedMeaning:" . $definingExpressionAsTitle . "_($id)";
			$titleObject = Title::newFromText( $text );
			$this->titleObject = $titleObject;
		}
		return $this->titleObject;
	}

	/**
	 * @param string $languageCode Language code of synonym/translation to show
	 * @param string $fallbackCode Fallback code
	 * @throws Exception If title object is missing
	 * @return string HTML link including the wrapping tag
	 */
	public function getHTMLLink( $languageCode, $fallbackCode = WLD_ENGLISH_LANG_WMKEY ) {
		$titleObject = $this->getTitleObject();
		if ( $titleObject == null ) {
			throw new Exception( "Need title object to create link" );
		}

		$dataset = $this->getDataset();
		$prefix = $dataset->getPrefix();
		$name = $this->getSyntransByLanguageCode( $languageCode, $fallbackCode );
		return Linker::link(
			$titleObject,
			$name,
			[],
			[ 'dataset' => $prefix ]
		);
	}

	/**
	 * Splits title of the form "Abc (123)" into text and number
	 * components.
	 *
	 * @param string $titleText the title to analyze
	 * @return Array of the two components or null.
	 */
	public static function splitTitleText( $titleText ) {
		$bracketPosition = strrpos( $titleText, "(" );
		if ( $bracketPosition === false ) {
			return null; # Defined Meaning ID is missing from title string
		}
		$rv = [];
		if ( $bracketPosition > 0 ) {
			$definingExpression = substr( $titleText, 0, $bracketPosition - 1 );
			$definingExpression = str_replace( "_", " ", $definingExpression );
		} else {
			$definingExpression = null;
		}
		$definedMeaningId = substr( $titleText, $bracketPosition + 1, strlen( $titleText ) - $bracketPosition - 2 );

		$rv["expression"] = $definingExpression;
		$rv["id"] = (int)$definedMeaningId;
		return $rv;
	}

	/**
	 * @return full text representation of title
	 */
	public function getTitleText() {
		$title = $this->getTitleObject();
		return $title->getFullText();
	}

	public function setId( $id ) {
		$this->id = $id;
	}

	public function getId() {
		return $this->id;
	}

	/**
	 * Fetch from DB if necessary
	 */
	public function getDefiningExpression() {
		if ( $this->definingExpression === null ) {
			return OwDatabaseAPI::definingExpression( $this->getId(), $this->getDataset() );
		}
		return $this->definingExpression;
	}

	public function getWikiTitle() {
		$dmEx = $this->getDefiningExpression();
		$dmId = $this->getId();
		$dmTitle = "DefinedMeaning:$dmEx ($dmId)";
		$dmTitle = str_replace( " ", "_", $dmTitle );
		return $dmTitle;
	}

	public function setDefiningExpression( $definingExpression ) {
		$this->definingExpression = $definingExpression;
	}

	public function setDataset( &$dataset ) {
		$this->dataset = $dataset;
	}

	public function getDataset() {
		return $this->dataset;
	}

	public function exists() {
		return $this->exists;
	}

}
