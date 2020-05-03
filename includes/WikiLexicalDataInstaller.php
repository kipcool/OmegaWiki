<?php
/** @file
 */

/** @brief WikiLexicalData's ExtensionDatabaseUpdater extension
 */
class WikiLexicalDataDatabaseUpdater extends ExtensionDatabaseUpdater {

	public $lexicalItemDMId, $iso6393CollectionId;

	/** Initial WikiLexical Install setup
	 */
	public function setWikiLexicalDataSettings( $dc, $fallbackName = null, $text = null, $languageId = WLD_ENGLISH_LANG_ID ) {
		global $wgWldOwScriptPath;
		// require_once( $wgWldOwScriptPath . 'OmegaWikiDatabaseAPI.php' );
		require_once $wgWldOwScriptPath . 'languages.php';

		$this->dc = $dc;
		$this->fallbackName = $fallbackName;
		$this->text = $text;
		$this->languageId = $languageId;

		$this->setDc();
		$this->createLanguageEnglish();
		$this->bootStrappedDefinedMeanings();
		$this->enableAnnotations();
	}

	/** @brief logs the WikiLexicalData details to script_log
	 */
	public function log( $scriptName, $comment ) {
		$this->dbr->insert(
			$this->dc . '_script_log',
			[
				'time' => wfTimestampNow(),
				'script_name' => $scriptName,
				'comment' => $comment
			], __METHOD__
		);
	}

	/** @brief setup the dataset
	 */
	protected function setDc() {
		$alreadySet = $this->dbr->selectField(
			'wikidata_sets',
			'set_prefix',
			[
			'set_prefix' => $this->dc
			], __METHOD__
		);

		if ( $alreadySet ) {
			$this->output( '...dataset ' . $this->dc . ' already set.' );
			return;
		}

		// if not, insert it.
		$this->dbw->insert(
			'wikidata_sets',
			[
			 'set_prefix' => $this->dc,
			 'set_fallback_name' => $this->fallbackName,
			 'set_dmid' => 0 	// he: temporarily set to zero. Since I do not
			],					// it would be hard to enter expression and definition
			__METHOD__			// when the dc is not set.
		);

		// enter expression and definition
		$definedMeaningId = $this->bootstrapDefinedMeaning(
			$this->fallbackName,
			$this->languageId,
			$this->text
		);

		// update the dmid
		$this->dbw->update(
			'wikidata_sets',
			[ 'set_dmid' => $definedMeaningId ],
			[ 'set_prefix' => $this->dc ], __METHOD__
		);

		$this->output( '...dataset ' . $this->dc . ' is set.' );
	}

	/** @brief add English Language
	 */
	protected function createLanguageEnglish() {
		// check if already exists
		$alreadyExists = getLanguageIdForCode( WLD_ENGLISH_LANG_WMKEY );

		if ( $alreadyExists ) {
			$this->output( "...English language already exist." );
			return;
		}

		// not already exist: create
		$this->output( '...adding language. English.' );
		$this->createLanguage( 'English', WLD_ENGLISH_LANG_WMKEY, 'eng', WLD_ENGLISH_LANG_WMKEY, WLD_ENGLISH_LANG_ID );
	}

	/** @adds language details to tables language and language_names
	 */
	protected function createLanguage( $language, $iso6392, $iso6393, $wmf, $languageId ) {
		global $wgDBtype;
		$options = [];
		if ( $wgDBtype == 'sqlite' ) {
			$options = [ 'IGNORE' => true ];
		}

		$this->dbw->insert(
			'language',
			[
				'language_id' => $languageId,
				'iso639_2' => $iso6392,
				'iso639_3' => $iso6393,
				'wikimedia_key' => $wmf
			], __METHOD__,
			$options
		);

		$this->dbw->insert(
			'language_names',
			[
				'language_id' => $languageId,
				'name_language_id' => $languageId,
				'language_name' => $language
			], __METHOD__,
			$options
		);
	}

	/** @brief Filling table bootstrapped_defined_meaning
	 */
	protected function bootStrappedDefinedMeanings() {
		// Admin user
		$userId = 1;

		// check that it is really a fresh install
		$hasCollection = $this->dbw->selectField(
			$this->dc . '_collection',
			'collection_id',
			'', __METHOD__
		);
		if ( $hasCollection ) {
			$this->output( $this->dc . '_bootstrapped_defined_meanings already populated.' );
			return;
		}

		$this->output( '...Filling table ' . $this->dc . '_bootstrapped_defined_meanings...' );

		startNewTransaction( $userId, 0, 'Script bootstrap class attribute meanings', $this->dc );
		$collectionId = bootstrapCollection( 'Class attribute levels', WLD_ENGLISH_LANG_ID, 'LEVL', $this->dc );

		$definedMeaningMeaningName = 'DefinedMeaning';
		$definitionMeaningName = 'Definition';
		$relationMeaningName = 'Relation';
		$synTransMeaningName = 'SynTrans';
		$annotationMeaningName = 'Annotation';

		$meanings = [];
		$meanings[$definedMeaningMeaningName] = $this->bootstrapDefinedMeaning( $definedMeaningMeaningName, WLD_ENGLISH_LANG_ID, 'The combination of an expression and definition in one language defining a concept.' );
		$meanings[$definitionMeaningName] = $this->bootstrapDefinedMeaning( $definitionMeaningName, WLD_ENGLISH_LANG_ID, 'A paraphrase describing a concept.' );
		$meanings[$synTransMeaningName] = $this->bootstrapDefinedMeaning( $synTransMeaningName, WLD_ENGLISH_LANG_ID, 'A translation or a synonym that is equal or near equal to the concept defined by the defined meaning.' );
		$meanings[$relationMeaningName] = $this->bootstrapDefinedMeaning( $relationMeaningName, WLD_ENGLISH_LANG_ID, 'The association of two defined meanings through a specific relation type.' );
		$meanings[$annotationMeaningName] = $this->bootstrapDefinedMeaning( $annotationMeaningName, WLD_ENGLISH_LANG_ID, 'Characteristic information of a concept.' );

		foreach ( $meanings as $internalName => $meaningId ) {
			addDefinedMeaningToCollection( $meaningId, $collectionId, $internalName );

			$this->dbw->insert(
				$this->dc . '_bootstrapped_defined_meanings',
				[ 'name' => $internalName,
					'defined_meaning_id' => $meaningId
				], __METHOD__
			);

			$this->output( '... with ' . $internalName . '.' );
		}
	}

	/** @brief adds Expression with the corresponding definition.
	 */
	protected function bootstrapDefinedMeaning( $spelling, $languageId, $definition ) {
		$expression = findOrCreateExpression( $spelling, $languageId );
		$definedMeaningId = createNewDefinedMeaning( $expression->id, $languageId, $definition );

		return $definedMeaningId;
	}

	/** @brief Enable Annotations
	 */
	protected function enableAnnotations() {
		// Admin user
		$userId = 1;

		$this->output( 'Adding some more data to enable annotations...' );
		startNewTransaction( $userId, 0, "Script bootstrap class attribute meanings", $this->dc );

		$this->output(
			'...a collection of classes. A word added to that collection becomes a class'
		);
		$classCollectionId = bootstrapCollection( "Community class", WLD_ENGLISH_LANG_ID, "CLAS", $this->dc );

		$this->output(
			'...a collection of iso639-3 codes, to enable translation' .
			' of the interface and language specific annotations'
		);
		$this->iso6393CollectionId = bootstrapCollection( "ISO 639-3 codes", WLD_ENGLISH_LANG_ID, "LANG", $this->dc );

		$this->output(
			'...DM lexical item, a class by default for every word'
		);
		$this->lexicalItemDMId = $this->bootstrapDefinedMeaning( "lexical item", WLD_ENGLISH_LANG_ID, "Lexical item is used as a class by default." );
		addDefinedMeaningToCollection( $this->lexicalItemDMId, $classCollectionId, "" );

		$this->output(
			'...DM English, a class by default for English words'
		);
		$englishDMId = $this->bootstrapDefinedMeaning( "English", WLD_ENGLISH_LANG_ID,
			"A West-Germanic language originating in England but now spoken in all parts of the British Isles,"
			. " the Commonwealth of Nations, the United States of America, and other parts of the world."
		);
		addDefinedMeaningToCollection( $englishDMId, $this->iso6393CollectionId, "eng" );
	}

}
