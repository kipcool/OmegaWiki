<?php

/** OmegaWiki API - add definition class
 * Created on Februar 27, 2014
 * @Author Purodha
 * @Ingroup Api
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';
require_once 'extensions/WikiLexicalData/OmegaWiki/Transaction.php';

class AddDefinition extends ApiBase {

	public $definition, $dm, $languageId, $result, $fp;

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		$user = $this->getUser();

		// keep blocked user out
		if ( $user->isBlocked() ) {
			$this->dieUsage( 'your account is blocked.', 'blocked' );
		}

		// Get the parameters
		$params = $this->extractRequestParams();

		// set test status
		$this->test = false;
		$this->transacted = false;

		if ( isset( $params['test'] ) ) {
			if ( $params['test'] == '1' or $params['test'] == null ) {
				$this->test = true;
			}
		}

		// limit non-test access to bots
		if ( !( $this->test or $user->isAllowed( 'bot' ) ) ) {
			$this->dieUsage( 'you must have a bot flag to use this API function', 'bot_only' );
		}

		// If wikipage, use batch processing
		if ( $params['wikipage'] ) {
			$text = $this->processBatch( $params['wikipage'] );
			return true;
		}

		// if not, add just one definition

		// Parameter checks
		if ( !isset( $params['d'] ) ) {
			$this->dieUsage( 'parameter d for adding a definition is missing', 'param d is missing' );
		}
		if ( !isset( $params['dm'] ) ) {
			$this->dieUsage( 'parameter dm for adding a definition is missing', 'param dm is missing' );
		}
		if ( !isset( $params['lang'] ) ) {
			$this->dieUsage( 'parameter lang for adding a definition is missing', 'param lang is missing' );
		}

		$definition = $params['d'];
		$definedMeaningId = $params['dm'];
		$languageId = $params['lang'];
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'definition' => $definition,
			'dmid' => $definedMeaningId,
			'lang' => $languageId,
			]
		);
		$result = $this->owAddDefinition( $definition, $languageId, $definedMeaningId );
		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $result ]
		);
		return true;
	}

	// Parameters.
	public function getAllowedParams() {
		return [
			'd' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'dm' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'lang' => [
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
		];
	}

	// Get examples
	public function getExamples() {
	return [
		'Add a definition in another language to the defined meaning.',
		'If a definition is already present for the language, nothing happens.',
		'api.php?action=ow_add_definition&d=Not%20where%20something%20or%20someone%20usually%20is.&dm=334562&lang=85&format=xml',
		'You can also add definitions from a CSV file.  The file must ',
		'contain 3 non-empty columns:',
		' definition		 (string)',
		' language_id		(int)',
		' defined_meaning_id (int)',
		'api.php?action=ow_add_definition&wikipage=User:Purodha/uploads/2&format=xml',
		'or to test it',
		'api.php?action=ow_add_definition&wikipage=User:Purodha/uploads/2&format=xml&test'
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
				$definition = $match2[1];
				$inputData = $match2[2];
				$inputData = explode( ',', $inputData );
				$inputDataCount = count( $inputData );
				$languageId = $inputData[0];
				$definedMeaningId = $inputData[1];
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
				$definition = $inputData[0];
				$languageId = $inputData[1];
				$definedMeaningId = $inputData[2];
			}
			if ( $definition === '' ) {
				$result = [ 'note' => 'skipped empty definition' ];
			} elseif ( !is_numeric( $languageId ) || !is_numeric( $definedMeaningId ) ) {
				if ( $ctr == 1 ) {
					$result = [ 'note' => "either $languageId or $definedMeaningId is not an int or probably just the CSV header" ];
				} else {
					$result = [ 'note' => "either $languageId or $definedMeaningId is not an int" ];
				}
			} else {
				$result = $this->owAddDefinition( $definition, $languageId, $definedMeaningId );
			}

			$this->getResult()->addValue( null, $this->getModuleName(),
				[ 'result' . $ctr => $result ]
			);
		}
		return true;
	}

	public function owAddDefinition( $definition, $languageId, $definedMeaningId ) {
		$dc = wdGetDataSetContext();

		// check that the language_id exists
		if ( !verifyLanguageId( $languageId ) ) {
			return [
				'WARNING' => 'Nonexisting language id(' . $languageId . ').'
			];
		}

		// check that defined_meaning_id exists
		if ( !verifyDefinedMeaningId( $definedMeaningId ) ) {
			return [
				'WARNING' => 'Nonexisting definedmeaning id (' . $definedMeaningId . ').'
			];
		}

		// trim definition
		$definition = trim( $definition );

		$concept = getDefinedMeaningSpellingForLanguage( $definedMeaningId, WLD_ENGLISH_LANG_ID );
		// check if a definition already exists for this language
		$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
		if ( translatedTextExists( $definitionId, $languageId ) ) {
			$note = [
				'status' => 'existing definition kept',
				'in' => "$concept DM($definedMeaningId)",
				'd' => $definition,
				'langid' => $languageId,
				'dm' => $definedMeaningId,
			];
			if ( $this->test ) {
				$note['note'] = 'test run only';
			}
			return $note;
		}
		// add the definition
		$note = [
			'status' => 'added',
			'to' => "$concept DM($definedMeaningId)",
			'd' => $definition,
			'langid' => $languageId,
			'dm' => $definedMeaningId,
		];

		if ( !$this->test ) {
			if ( !$this->transacted ) {
				$this->transacted = true;
				startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_definitiion", $dc );
			}
			addDefinedMeaningDefinition( $definedMeaningId, $languageId, $definition );
		} else {
			$note['note'] = 'test run only';
		}

		return $note;
	}
}
