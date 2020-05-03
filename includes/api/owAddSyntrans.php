<?php

/** OmegaWiki API's add syntrans class
 * Created on March 19, 2013
 *
 * HISTORY
 * - 2014-06-06: version 1.1.
 *		added a way to get transaction id. So a bot can batch add Syntrans
 *		using only one transaction id at one go. Conserves database space.
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';
require_once 'extensions/WikiLexicalData/OmegaWiki/Transaction.php';

class AddSyntrans extends ApiBase {

	public $spelling,				// < spelling ( string )
		$definedMeaningId,		// < defined meaning id ( integer )
		$languageId,			// < language id ( integer )
		$identicalMeaning,		// < identical meaning id ( boolean )
		$identicalMeaningStr,	// < identical meaning ( string ) true or false
		$ver = '1',				// < API version ( string )
		$test = false,			// < test status ( boolean )
		$transacted = false,	// < transacted status ( boolean )
		$tid = null,			// < transaction id ( integer )
		$options = [
			'updateId' => -1
		];						// < options ( array )

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		$user = $this->getUser();

		// limit access to bots
		if ( !$user->isAllowed( 'bot' ) ) {
			$this->dieUsage( 'you must have a bot flag to use this API function', 'bot_only' );
		}

		// keep blocked bots out
		if ( $user->isBlocked() ) {
			$this->dieUsage( 'your account is blocked.', 'blocked' );
		}

		// Get the parameters
		$this->params = $this->extractRequestParams();

		if ( isset( $this->params['test'] ) ) {
			if ( $this->params['test'] == '1' or $this->params['test'] == null ) {
				$this->test = true;
			}
		}

		// reset transacted status if transaction id is provided
		if ( isset( $this->params['tid'] ) ) {
			if ( $this->params['tid'] ) {
				$this->transacted = true;
				$this->tid = $this->params['tid'];
				$this->options['updateId'] = $this->tid;
			}
		}

		if ( isset( $this->params['ver'] ) ) {
			$this->ver = $this->params['ver'];
		}

		// If wikipage, use batch processing
		if ( $this->params['wikipage'] ) {
			$text = $this->processBatch( $this->params['wikipage'] );
			return true;
		}

		// if not, add just one syntrans

		// Parameter checks
		if ( !isset( $this->params['e'] ) ) {
			$this->dieUsage( 'parameter e for adding syntrans is missing', 'param e is missing' );
		}
		if ( !isset( $this->params['dm'] ) ) {
			$this->dieUsage( 'parameter dm for adding syntrans is missing', 'param dm is missing' );
		}
		if ( !isset( $this->params['lang'] ) ) {
			$this->dieUsage( 'parameter lang for adding syntrans is missing', 'param lang is missing' );
		}
		if ( !isset( $this->params['im'] ) ) {
			$this->dieUsage( 'parameter im for adding syntrans is missing', 'param im is missing' );
		}

		$this->getSpelling();
		$this->definedMeaningId = $this->params['dm'];
		$this->languageId = $this->params['lang'];
		$this->identicalMeaning = $this->params['im'];
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'spelling' => $this->spelling,
			'dmid' => $this->definedMeaningId,
			'lang' => $this->languageId,
			'im' => $this->identicalMeaning
			]
		);
		$result = $this->owAddSynonymOrTranslation();
		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $result ]
		);
		return true;
	}

	// Parameters.
	public function getAllowedParams() {
		return [
			'e' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'dm' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'lang' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'im' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'file' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'wikipage' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'test' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'tid' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'ver' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	// Get examples
	public function getExamples() {
	return [
		'Add a synonym/translation to the defined meaning definition',
		'If the expression is already present. Nothing happens',
		'api.php?action=ow_add_syntrans&e=欠席&dm=334562&lang=387&im=1&ver=1.1&format=xml',
		'or to test it',
		'api.php?action=ow_add_syntrans&e=欠席&dm=334562&lang=387&im=1&ver=1.1&format=xml&test',
		'You can also add synonym/translation using a CSV file.  The file must ',
		'contain at least 3 columns (and 1 optional column):',
		' spelling           (string)',
		' language_id        (int)',
		' defined_meaning_id (int)',
		' identical meaning  (boolean 0 or 1, optional)',
		'api.php?action=ow_add_syntrans&wikipage=User:MinnanBot/addSyntrans130124.csv&ver=1.1&format=xml',
		'or to test it',
		'api.php?action=ow_add_syntrans&wikipage=User:MinnanBot/addSyntrans130124.csv&ver=1.1&format=xml&test'
		];
	}

	public function processBatch( $wikiPage ) {
		$csvWikiPageTitle = Title::newFromText( $wikiPage );
		$csvWikiPage = new WikiPage( $csvWikiPageTitle );

		if ( !$wikiText = $csvWikiPage->getContent( Revision::RAW ) ) {
			return $this->getResult()->addValue( null, $this->getModuleName(),
				[ 'result' => [
					'error' => "WikiPage ( $csvWikiPageTitle ) does not exist"
				] ]
			);
		}

		$text = $wikiText->getNativeData();

		// Check if the page is redirected,
		// then adjust accordingly.
		preg_match( "/REDIRECT \[\[(.+)\]\]/", $text, $match2 );
		if ( isset( $match2[1] ) ) {
			$redirectedText = $match2[1];
			$csvWikiPageTitle = Title::newFromText( $redirectedText );
			$csvWikiPage = new WikiPage( $csvWikiPageTitle );
			$wikiText = $csvWikiPage->getContent( Revision::RAW );
			$text = $wikiText->getNativeData();
		}

		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'process' => [
			'text' => 'wikipage',
			'type' => 'batch processing'
			] ]
		);

		$inputLine = explode( "\n", $text );
		$ctr = 0;
		while ( $inputData = array_shift( $inputLine ) ) {
			$ctr = $ctr + 1;
			$inputData = trim( $inputData );
			if ( $inputData == "" ) {
				$result = [ 'note' => "skipped blank line" ];
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'result' . $ctr => $result ]
				);
				continue;
			}

			$inputMatch = preg_match( "/^\"(.+)/", $inputData, $match );
			if ( $inputMatch == 1 ) {
				$inputData = $match[1];
				preg_match( "/(.+)\",(.+)/", $inputData, $match2 );
				$this->spelling = $match2[1];
				$inputData = $match2[2];
				$inputData = explode( ',', $inputData );
				$inputDataCount = count( $inputData );
				$this->languageId = $inputData[0];
				$this->definedMeaningId = $inputData[1];
				if ( $inputDataCount == 3 ) {
					$this->identicalMeaning = $inputData[2];
				}
				if ( $inputDataCount == 2 ) {
					$this->identicalMeaning = 1;
				}
			} else {
				$inputData = explode( ',', $inputData );
				$inputDataCount = count( $inputData );
				if ( $inputDataCount == 1 ) {
					$result = [ 'note' => "skipped blank line" ];
					$this->getResult()->addValue( null, $this->getModuleName(),
						[ 'result' . $ctr => $result ]
					);
					continue;
				}
				$this->spelling = $inputData[0];
				$this->languageId = $inputData[1];
				$this->definedMeaningId = $inputData[2];
				if ( $inputDataCount == 4 ) {
					$this->identicalMeaning = $inputData[3];
				}
				if ( $inputDataCount == 3 ) {
					$this->identicalMeaning = 1;
				}
			}

			if ( !is_numeric( $this->languageId ) || !is_numeric( $this->definedMeaningId ) ) {
				if ( $ctr == 1 ) {
					$result = [ 'note' => 'either ' . $this->languageId . 'or ' . $this->definedMeaningId . 'is not an int or probably just the CSV header' ];
				} else {
					$result = [ 'note' => 'either ' . $this->languageId . 'or ' . $this->definedMeaningId . 'is not an int' ];
				}
			} else {
				$result = $this->owAddSynonymOrTranslation();
			}

			$this->getResult()->addValue( null, $this->getModuleName(),
				[ 'result' . $ctr => $result ]
			);
		}
		return true;
	}

	public function owAddSynonymOrTranslation() {
		$dc = wdGetDataSetContext();

		// check that the language_id exists
		if ( !verifyLanguageId( $this->languageId ) ) {
			return [
				'WARNING' => 'Non existent language id(' . $this->languageId . ').'
			];
		}

		// check that defined_meaning_id exists
		if ( !verifyDefinedMeaningId( $this->definedMeaningId ) ) {
			return [
				'WARNING' => 'Non existent dm id (' . $this->definedMeaningId . ').'
			];
		}

		if ( $this->identicalMeaning == 1 ) {
			$this->identicalMeaningStr = "true";
			if ( $this->ver == '1' ) { $this->identicalMeaning = "true";
   }
		} else {
			$this->identicalMeaningStr = "false";
			$this->identicalMeaning = 0;
			if ( $this->ver == '1' ) { $this->identicalMeaning = "false";
   }
		}

		// first check if it exists, then create the transaction and put it in db
		$expression = findExpression( $this->spelling, $this->languageId, $this->options );
		$concept = getDefinedMeaningSpellingForLanguage( $this->definedMeaningId, WLD_ENGLISH_LANG_ID );
		if ( $expression ) {
			// the expression exists, check if it has this syntrans
			$bound = expressionIsBoundToDefinedMeaning( $this->definedMeaningId, $expression->id );
			if ( $bound == true ) {
				$synonymId = getSynonymId( $this->definedMeaningId, $expression->id );
				$note = [
					'status' => 'exists',
					'in' => $concept . ' DM(' . $this->definedMeaningId . ')',
					'sid' => $synonymId,
					'e' => $this->spelling,
					'langid' => $this->languageId,
					'dm' => $this->definedMeaningId,
					'im' => $this->identicalMeaning
				];
				if ( $this->test ) {
					$note['note'] = 'test run only';
				}

				return $note;
			}
		}

		// adding the expression
		$expressionId = getExpressionId( $this->spelling, $this->languageId );
		$synonymId = getSynonymId( $this->definedMeaningId, $expressionId );
		$note = [
			'status' => 'added',
			'to' => $concept . ' DM(' . $this->definedMeaningId . ')',
			'sid' => $synonymId,
			'e' => $this->spelling,
			'langid' => $this->languageId,
			'dm' => $this->definedMeaningId,
			'im' => $this->identicalMeaning
		];

		// add note['tid'] from $this->tid (transaction id), if null, get value
		// from $this->options['updateId'].
		if ( $this->ver == '1.1' ) {
			if ( $this->tid ) {
				$note['tid'] = $this->tid;
			} else {
				$note['tid'] = $this->options['updateId'];
			}
		}
		if ( !$this->test ) {
			if ( !$this->transacted ) {
				$this->transacted = true;
				$this->tid = startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_syntrans", $dc );
				if ( $this->ver == '1.1' ) {
					$note['tid'] = $this->tid;
				}
			}
			OwDatabaseAPI::addSynonymOrTranslation( $this->spelling, $this->languageId, $this->definedMeaningId, $this->identicalMeaningStr, $this->options );
		} else {
			$note['note'] = 'test run only';
		}

		return $note;
	}

	protected function getSpelling() {
		$this->spelling = trim( $this->params['e'] );
	}

}
