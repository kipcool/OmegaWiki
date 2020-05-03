<?php
/** @file
 * @brief WikiLexicalData install script
 *
 * Maintenance script to create a WikiLexicalData extension for mediawiki
 * it generates the tables in a database (passed as parameter) with a defined prefix (passed as parameter)
 */

$baseDir = __DIR__ . '/../../..';
require_once $baseDir . '/maintenance/Maintenance.php';
require_once $baseDir . '/extensions/WikiLexicalData/OmegaWiki/WikiDataGlobals.php';
require_once $baseDir . '/extensions/WikiLexicalData/OmegaWiki/Transaction.php';
require_once $baseDir . '/extensions/WikiLexicalData/OmegaWiki/OmegaWikiDatabaseAPI.php';

echo "start\n";

/** @class InstallWikiLexicalData
 * @brief WikiLexicalData install class
 */
class InstallWikiLexicalData extends Maintenance {

	/** @brief public function constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Installation by creating the tables and filling them with the minimal necessary data\n"
			. 'Example usage: php install.php --prefix=uw '
			. '--template=wikidataTemplate.sql --datasetname="OmegaWiki community"' );
		$this->addOption( 'freshInstall', 'Drop all tables before creating new ones' );
		$this->addOption( 'prefix', 'The prefix to use for the relational tables. e.g. --prefix=uw' );
		$this->addOption( 'template', 'A sql template describing the relational tables. e.g. --template=databaseTemplate.sql' );
		$this->addOption( 'datasetname', 'A name for your dataset. e.g. --datasetname="OmegaWiki community"' );
	}

	/** @brief public function execute
	 */
	public function execute() {
		global $wdCurrentContext, $wgDBprefix;

		// checking that the needed parameters are given
		if ( !$this->hasOption( 'prefix' ) ) {
			$this->output( "A prefix is missing. Use for example --prefix=uw\n" );
			exit( 0 );
		}
		if ( !$this->hasOption( 'template' ) ) {
			$this->output( "A template is missing. Use for example --template=databaseTemplate.sql\n" );
			exit( 0 );
		}

		$prefix = $this->getOption( 'prefix' );
		$template = $this->getOption( 'template' );
		$datasetname = $this->getOption( 'datasetname' );
		if ( preg_match( '/\_$/', $prefix ) ) {
			$wdCurrentContext = preg_replace( '/\_$/', '', $prefix );
		} else {
			$wdCurrentContext = $prefix;
		}
		if ( !preg_match( '/\_$/', $prefix ) ) {
			$prefix .= '_';
		}

		if ( $this->hasOption( 'freshInstall' ) ) {
			$this->output( "Dropping all tables\n" );
			$this->dropTables( $wdCurrentContext );
		}

		$this->output( "Creating relational tables...\n" );

		$patterns = [
			'wld' => "/*wgWDprefix*/",
			'mw' => "/*wgDBprefix*/"
		];

		$prefixes = [
			'wld' => $prefix,
			'mw' => $wgDBprefix
		];

		$this->ReadTemplateSQLFile( $patterns, $prefixes, __DIR__ . DIRECTORY_SEPARATOR . $template );

		// entering dataset in table wikidata_sets
		/** @todo currently sets every new instances as set_dmid = 0.
		 * Unless dmid is essential in working multiple instances of OmegaWiki,
		 * it should be fine. If not, this must be worked upon later, since
		 * I am not familiar with the table usage. ~ he
		 */
		$dbw = wfGetDB( DB_MASTER );

		$this->output( "Delete and recreate empty set {$wdCurrentContext}...\n" );
		$dbw->delete(
			'wikidata_sets',
			[
				'set_prefix' => $wdCurrentContext
			],
			__METHOD__
		);

		$dbw->insert(
			'wikidata_sets',
			[
			 'set_prefix' => $wdCurrentContext,
			 'set_fallback_name' => $datasetname,
			 'set_dmid' => 0
			],
			__METHOD__
		);

		// Add language English if not already present
		$this->createLanguageEnglish();

		$this->output( "Filling table {$wdCurrentContext}_bootstrapped_defined_meanings...\n" );
		$this->bootStrappedDefinedMeanings( $wdCurrentContext );

		$this->output( "Adding some more data to enable annotations...\n" );
		$this->enableAnnotations( $wdCurrentContext );
	}

	/** @brief Adds the English language to enable editing
	 */
	protected function createLanguageEnglish() {
		$dbw = wfGetDB( DB_MASTER );
		global $wgDBtype;

		// check if already exists
		$alreadyExists = $dbw->selectField(
			'language',
			'language_id',
			[ 'language_id' => WLD_ENGLISH_LANG_ID ],
			__METHOD__
		);

		if ( $alreadyExists ) {
			$this->output( "English language already exist...\n" );
			return;
		}

		// not already exist: create
		$this->output( "Adding language English...\n" );

		$options = [];
		if ( $wgDBtype == 'sqlite' ) {
			$options = [ 'IGNORE' => true ];
		}

		$langname = "English";
		$langiso6392 = "en";
		$langiso6393 = "eng";
		$langwmf = "en";
		$langid = WLD_ENGLISH_LANG_ID;

		$dbw->insert(
			'language',
			[
				'language_id' => $langid,
				'iso639_2' => $langiso6392,
				'iso639_3' => $langiso6393,
				'wikimedia_key' => $langwmf
			], __METHOD__,
			$options
		);

		$dbw->insert(
			'language_names',
			[
				'language_id' => $langid,
				'name_language_id' => $langid,
				'language_name' => $langname
			], __METHOD__,
			$options
		);
	}

	/** @brief Fills table bootstrapped_defined_meanings with relevant data
	 *
	 * @param string $dc The database being accessed.
	 */
	protected function bootStrappedDefinedMeanings( $dc ) {
		// Admin user
		$userId = 1;
		$dbw = wfGetDB( DB_MASTER );
		global $wgDBprefix;

		// check that it is really a fresh install
		$hasCollection = $dbw->selectField(
			"{$dc}_collection",
			'collection_id',
			'', __METHOD__
		);
		if ( $hasCollection ) {
			echo "Table {$wgDBprefix}{$dc}_collection not empty.\n";
			echo "\nERROR: It appears that Wikidata is at least already partially installed on your system\n";
			echo "\nIf you would like to do a fresh install, drop the following tables, and run the install script again:\n";
			$this->printDropTablesCommand( $dc );
			exit( 0 );
		}

		startNewTransaction( $userId, 0, "Script bootstrap class attribute meanings", $dc );
		$collectionId = bootstrapCollection( "Class attribute levels", WLD_ENGLISH_LANG_ID, "LEVL", $dc );

		$definedMeaningMeaningName = "DefinedMeaning";
		$definitionMeaningName = "Definition";
		$relationMeaningName = "Relation";
		$synTransMeaningName = "SynTrans";
		$annotationMeaningName = "Annotation";

		$meanings = [];
		$meanings[$definedMeaningMeaningName] = $this->bootstrapDefinedMeaning( $definedMeaningMeaningName, WLD_ENGLISH_LANG_ID, "The combination of an expression and definition in one language defining a concept." );
		$meanings[$definitionMeaningName] = $this->bootstrapDefinedMeaning( $definitionMeaningName, WLD_ENGLISH_LANG_ID, "A paraphrase describing a concept." );
		$meanings[$synTransMeaningName] = $this->bootstrapDefinedMeaning( $synTransMeaningName, WLD_ENGLISH_LANG_ID, "A translation or a synonym that is equal or near equal to the concept defined by the defined meaning." );
		$meanings[$relationMeaningName] = $this->bootstrapDefinedMeaning( $relationMeaningName, WLD_ENGLISH_LANG_ID, "The association of two defined meanings through a specific relation type." );
		$meanings[$annotationMeaningName] = $this->bootstrapDefinedMeaning( $annotationMeaningName, WLD_ENGLISH_LANG_ID, "Characteristic information of a concept." );

		foreach ( $meanings as $internalName => $meaningId ) {
			addDefinedMeaningToCollection( $meaningId, $collectionId, $internalName );

			$dbw->insert(
				"{$dc}_bootstrapped_defined_meanings",
				[ 'name' => $internalName,
					'defined_meaning_id' => $meaningId
				], __METHOD__
			);

		}
	}

	/** @brief Add some more data to enable annotations
	 *
	 * @param string $dc The database being accessed.
	 */
	protected function enableAnnotations( $dc ) {
		// Admin user
		$userId = 1;

		startNewTransaction( $userId, 0, "Script bootstrap class attribute meanings", $dc );

		// a collection of classes. A word added to that collection becomes a class
		$classCollectionId = bootstrapCollection( "Community class", WLD_ENGLISH_LANG_ID, "CLAS", $dc );

		// a collection of iso639-3 codes, to enable translation of the interface
		// and language specific annotations
		$iso6393CollectionId = bootstrapCollection( "ISO 639-3 codes", WLD_ENGLISH_LANG_ID, "LANG", $dc );

		// DM lexical item, a class by default for every word
		$lexicalItemDMId = $this->bootstrapDefinedMeaning( "lexical item", WLD_ENGLISH_LANG_ID, "Lexical item is used as a class by default." );
		addDefinedMeaningToCollection( $lexicalItemDMId, $classCollectionId, "" );

		// DM English, a class by default for English words
		$englishDMId = $this->bootstrapDefinedMeaning( "English", WLD_ENGLISH_LANG_ID,
			"A West-Germanic language originating in England but now spoken in all parts of the British Isles,"
			. " the Commonwealth of Nations, the United States of America, and other parts of the world."
		);
		addDefinedMeaningToCollection( $englishDMId, $iso6393CollectionId, "eng" );

		echo "**\n";
		echo "Add to your LocalSettings.php: \n";
		echo '$wgDefaultClassMids = array(' . $lexicalItemDMId . ");\n";
		echo '$wgIso639_3CollectionId = ' . $iso6393CollectionId . ";\n";
		echo "**\n";
	}

	protected function bootstrapDefinedMeaning( $spelling, $languageId, $definition ) {
		$expression = findOrCreateExpression( $spelling, $languageId );
		$definedMeaningId = createNewDefinedMeaning( $expression->id, $languageId, $definition );

		return $definedMeaningId;
	}

	/** @brief Drop Tables
	 *
	 * @param string $dc The database being accessed.
	 */
	protected function dropTables( $dc ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'page',
			[ 'page_namespace' => NS_EXPRESSION ],
			__METHOD__
		);
		$dbw->delete(
			'page',
			[ 'page_namespace' => NS_DEFINEDMEANING ],
			__METHOD__
		);

		$owTableNames = $this->getWLDtableNamesAddtl();
		foreach ( $owTableNames as $drop ) {
			$dbw->dropTable( "{$drop}", __METHOD__ );
		}

		$owTableNames = $this->getWLDtableNames();
		foreach ( $owTableNames as $drop ) {
			$dbw->dropTable( "{$dc}{$drop}", __METHOD__ );
		}
		$this->printDropTablesCommand( $dc );
	}

	/** @brief Displays drop tables commands.
	 *
	 * @param string $dc The database being accessed.
	 */
	protected function printDropTablesCommand( $dc ) {
		global $wgDBprefix;
		$dropCommand = "drop table ";

		$owTableNames = $this->getWLDtableNamesAddtl();
		foreach ( $owTableNames as $drop ) {
			$dropCommand .= "{$wgDBprefix}{$drop}, ";
		}

		$owTableNames = $this->getWLDtableNames();
		foreach ( $owTableNames as $drop ) {
			$dropCommand .= $wgDBprefix . $dc . "$drop, ";
		}
		$dropCommand = preg_replace( '/, $/', '', $dropCommand );
		echo "\n\n$dropCommand\n\n";
	}

	/** @brief Read, interpret and execute the database template
	 *
	 * @param array $pattern An array of pattern to find.
	 * @param array $prefix An array of prefix to replace \a find with.
	 * @param string $filename the database template name.
	 */
	protected function ReadTemplateSQLFile( $pattern, $prefix, $filename ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_MASTER );
		global $wgDBtype;

		$fp = fopen( $filename, 'r' );
		if ( false === $fp ) {
			return "Could not open \"{$filename}\".\n";
		}

		$cmd = "";
		$done = false;

		// Check if language index exist
		while ( !feof( $fp ) ) {
			$line = trim( fgets( $fp, 1024 ) );
			$sl = strlen( $line ) - 1;

			if ( $sl < 0 ) {
				continue;
			}
			if ( '-' == $line [ 0 ] && '-' == $line [ 1 ] ) {
				continue;
			}

			if ( ';' == $line [ $sl ] && ( $sl < 2 || ';' != $line [ $sl - 1 ] ) ) {
				$done = true;
				$line = substr( $line, 0, $sl );
			}

			if ( '' != $cmd ) {
				$cmd .= ' ';
			}
			$cmd .= "$line\n";

			if ( preg_match( '/CREATE INDEX/', $cmd ) ) {
				// Check if the current instance of OmegaWiki has been installed.
				if (
					preg_match( '/versioned_end_meaning/', $cmd ) &&
					preg_match( '/alt_meaningtexts/', $cmd )
				) {
					$indexResult = $dbr->indexInfo(
						"{$prefix['wld']}alt_meaningtexts",
						"{$prefix['mw']}{$prefix['wld']}versioned_end_meaning",
						__METHOD__
					);

					$indexExist = false;
					if ( $indexResult ) {
						foreach ( $indexResult as $yay ) {
							if ( $yay ) {
								$indexExist = true;
							}
						}
					}

					if ( $indexExist ) {
						echo "\nERROR: It appears that WikiDataLexical is at least already partially installed on your system. "
						. "If you would like to do a fresh install, use the fresh install parameter.\n\n"
						. 'Example usage: php install.php --prefix=uw '
						. '--template=databaseTemplate.sql --datasetname="OmegaWiki community"  --freshInstall' . "\n\n";
						die;
					}

				}

				if ( preg_match( '/language_names/', $cmd ) ) {
					/** @todo This check is for MySQL. Needs an alternative way of checking if SQLite,
					 * or check if it is not needed by SQLite. Not a priority since I am the only one
					 * using this for now ~he
					 */
					$indexResult = $dbr->indexInfo(
						'language_names',
						"{$prefix['mw']}ilanguage_id",
						__METHOD__
					);

					$indexExist = false;
					if ( $indexResult ) {
						foreach ( $indexResult as $yay ) {
							if ( $yay ) {
								$indexExist = true;
							}
						}
					}

					if ( $indexExist ) {
						$dbr->query(
							"DROP INDEX {$prefix['mw']}ilanguage_id
							ON {$prefix['mw']}language_names"
						);
					}
				}
			}

			if ( $wgDBtype == 'sqlite' ) {
				$cmd = $this->sqliteLineReplace( $cmd );
			}

			if ( $done ) {
				$cmd = str_replace( ';;', ";", $cmd );
				$cmd = trim( str_replace( "{$pattern["wld"]}", "{$prefix["wld"]}", $cmd ) );
				$cmd = trim( str_replace( "{$pattern["mw"]}", "{$prefix["mw"]}", $cmd ) );

				$res = $dbw->query( $cmd );

				if ( false === $res ) {
					return "Query \"{$cmd}\" failed with error code \".\n";
				}

				$cmd = '';
				$done = false;
			}
		}
		fclose( $fp );
		return true;
	}

	/** @brief SQLite compatibility
	 *
	 * @param string $string The string to parse.
	 */
	protected function sqliteLineReplace( $string ) {
		$string = preg_replace( '/ int(eger|) /i', ' INTEGER ', $string );
		$string = preg_replace( '/ int(eger|)\(/i', " INTEGER(", $string );
		$string = str_replace( ' auto_increment', " AUTO_INCREMENT", $string );

		$string = str_replace( 'CREATE INDEX', "CREATE INDEX IF NOT EXISTS", $string );
		if ( preg_match( '/CREATE INDEX /', $string ) ) {
			if ( preg_match( '/`user` ON/', $string ) ) {
				$string = str_replace( "`user`", "`user_id`", $string );
			}
			$string = preg_replace( '/(\(\d+\))/', ' ', $string );
		}
		$string = str_replace( ' unsigned', '', $string );
		$string = preg_replace( '/INTEGER\(\d+\)/', 'INTEGER', $string );
		$string = str_replace( 'AUTO_INCREMENT', "AUTOINCREMENT", $string );
		$string = str_replace( 'collate utf8_bin', "collate binary", $string );
		$string = str_replace( ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci', ")", $string );
		return ( $string );
	}

	/** @brief Returns an array of WikiLexicalData table Names;
	 */
	protected function getWLDtableNames() {
		return [
			"_alt_meaningtexts",
			"_bootstrapped_defined_meanings",
			"_class_attributes",
			"_class_membership",
			"_collection",
			"_collection_contents",
			"_collection_language",
			"_defined_meaning",
			"_expression",
			"_meaning_relations",
			"_objects",
			"_option_attribute_options",
			"_option_attribute_values",
			"_script_log",
			"_syntrans",
			"_text",
			"_text_attribute_values",
			"_transactions",
			"_translated_content",
			"_translated_content_attribute_values",
			"_url_attribute_values"
		];
	}

	/** @brief Returns Additional WikiLexicalData table names
	 */
	protected function getWLDtableNamesAddtl() {
		return [ "language", "language_names", "wikidata_sets" ];
	}
}

$maintClass = 'InstallWikiLexicalData';
require_once RUN_MAINTENANCE_IF_MAIN;
