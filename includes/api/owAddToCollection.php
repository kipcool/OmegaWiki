<?php

/** OmegaWiki API's add to collection class
 * Created on October 11, 2013
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';
require_once 'extensions/WikiLexicalData/OmegaWiki/Transaction.php';

class AddToCollection extends ApiBase {

	private $objectId, $attributeId, $optionId;

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		$result = [];
		global $params;

		// limit access to bots
		if ( !$this->getUser()->isAllowed( 'bot' ) ) {
			$this->dieUsage( 'you must have a bot flag to use this API function', 'bot_only' );
		}

		// keep blocked bots out
		if ( $this->getUser()->isBlocked() ) {
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

		// If wikipage, use batch processing
		if ( $params['wikipage'] ) {
			$text = $this->processBatch( $params['wikipage'] );
			return true;
		}

		// if not, add just one syntrans

		// Parameter checks

		// * optional parameter
		if ( !isset( $params['int_memb_id'] ) ) {
			$this->internalMemberId = '';
		} else {
			$this->internalMemberId = $params['int_memb_id'];
		}

		// * required parameters
		if ( !isset( $params['dm'] ) ) {
			$this->dieUsage( 'parameter dm to add is missing', 'param dm is missing' );
		}
		if ( !isset( $params['coll_mid'] ) ) {
			$this->dieUsage( 'parameter col_mid to add to is missing', 'param col_mid missing' );
		}
		$this->definedMeaningId = $params['dm'];
		$this->collectionMid = $params['coll_mid'];

		if ( !is_numeric( $this->collectionMid ) || !is_numeric( $this->definedMeaningId ) ) {
			if ( $ctr == 1 ) {
				$result = [ 'note' => "either $this->collectionMid or $this->definedMeaningId is not an int or probably just the CSV header" ];
			} else {
				$result = [ 'note' => "either $this->collectionMid or $this->definedMeaningId is not an int" ];
			}
		} else {
			$result = $this->processAddToCollection();
		}

		if ( !isset( $result['error'] ) ) {
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
		return true;
	}

	// Parameters.
	public function getAllowedParams() {
		return [
			'dm' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'coll_mid' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'int_memb_id' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'wikipage' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'test' => [
				ApiBase::PARAM_TYPE => 'string'
			],
		];
	}

	// Get examples
	public function getExamples() {
		return [
			'Add a defined meaning id to a collection',
			'api.php?action=ow_add_to_collection&dm=194&coll_mid=725301&format=xml',
			'or to test it',
			'api.php?action=ow_add_to_collection&dm=837820&coll_mid=725301&format=xml&test',
			'You can also add defined meaning id using a CSV file format',
			'saved in a wikiPage. The file must contain at least 3 columns:',
			' dm_id       (int)',
			' coll_mid    (int)',
			' int_memb_id (int)',
			'api.php?action=ow_add_to_collection&wikipage=User:Hiong3-eng5/addToCollection.csv&format=xml',
			'or to test it',
			'api.php?action=ow_add_to_collection&wikipage=User:Hiong3-eng5/addToCollection.csv&format=xml&test'
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

			$inputDataArray = explode( ',', $inputData );
			$inputDataCount = count( $inputDataArray );
			if ( $inputDataCount == 1 ) {
				$result = [ 'note' => "skipped blank line" ];
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'result' . $ctr => $result ]
				);
				continue;
			}

			if ( $inputDataCount <> 3 ) {
				$result = [ 'note' => "The line `$inputData` can not be processed because it has $inputDataCount columns instead of 3." ];
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'result' . $ctr => $result ]
				);
				continue;
			}
			$this->definedMeaningId = $inputDataArray[0];
			$this->collectionMid = $inputDataArray[1];
			$this->internalMemberId = $inputDataArray[2];

			if ( !is_numeric( $this->collectionMid ) || !is_numeric( $this->definedMeaningId ) ) {
				if ( $ctr == 1 ) {
					$result = [ 'note' => "either $this->collectionMid or $this->definedMeaningId is not an int or probably just the CSV header" ];
				} else {
					$result = [ 'note' => "either $this->collectionMid or $this->definedMeaningId is not an int" ];
				}
			} else {
				$result = $this->processAddToCollection();
			}
			$this->getResult()->addValue( null, $this->getModuleName(),
				[ 'result' . $ctr => $result ]
			);

		}
		return true;
	}

	private function processAddToCollection() {
		$dc = wdGetDataSetContext();

		// Get CollectionId
		$this->collectionId = getCollectionId( $this->collectionMid );
		if ( !$this->collectionId ) {
			return [ 'error' => [
					'code' => 'param coll_mid does not exist',
					'info' => "No coll_mid found"
				]
			];
		}

		// check that defined_meaning_id exists
		if ( !verifyDefinedMeaningId( $this->definedMeaningId ) ) {
			return [ 'error' => [
					'code' => 'param dm does not exist',
					'info' => "No dm found"
				]
			];
		}

		$collection = getDefinedMeaningSpellingForLanguage( $this->collectionMid, WLD_ENGLISH_LANG_ID );
		$expression = getDefinedMeaningSpellingForLanguage( $this->definedMeaningId, WLD_ENGLISH_LANG_ID );
		if ( !$expression ) {
			$expression = getDefinedMeaningSpellingForAnyLanguage( $this->definedMeaningId, WLD_ENGLISH_LANG_ID );
			if ( !$expression ) {
				return [ 'error' => [
						'code' => 'param dm does not have any expression associated with it',
						'info' => "No dm with expression found"
					]
				];
			}
		}
		// Check if defined meaning id already exist in the collection
		// If so add else warn
		if ( !definedMeaningInCollection( $this->definedMeaningId, $this->collectionId ) ) {
			$note = [ 'result' => [
				'status' => "added `$expression` (dm:$this->definedMeaningId) to `$collection`."
			] ];

			if ( !$this->test ) {
				if ( !$this->transacted ) {
					$this->transacted = true;
					startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_to_collection", $dc );
				}
				addDefinedMeaningToCollection( $this->definedMeaningId, $this->collectionId, $this->internalMemberId );
			} else {
				$note['result']['note'] = 'test run only';
			}

			return $note;
		} else {
			$note = [ 'result' => [
					'status' => "`$expression` exists in `$collection`."
				]
			];
			if ( $this->test ) {
				$note['result']['note'] = 'test run only';
			}

			return $note;
		}
	}

}
