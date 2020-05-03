<?php

/** OmegaWiki API's add annotation class
 * Created on April 2, 2013
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';
require_once 'extensions/WikiLexicalData/OmegaWiki/Transaction.php';

class AddAnnotation extends ApiBase {

	private $objectId, $attributeId, $optionId;

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		$this->wikipage = false;
		$typeExist = 0;
		$result = '';

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

		// The Type of Annotation
		if ( $params['type'] == 'text' ) {
			// If wikipage, use batch processing
			if ( $params['wikipage'] ) {
				$this->wikipage = true;
				$this->type = 'text';
				$text = $this->processBatch( $params['wikipage'] );
				return true;
			}

			// if not, add just one text

			// Parameter checks

			// * optional parameters if uw_objects's "table" = 'uw_syntrans'
			// not needed when uw_objects's "table" = 'uw_defined_meaning'
			if ( !isset( $params['e'] ) ) {
				$spelling = null;
			} else {
				$spelling = $params['e'];
			}
			if ( !isset( $params['lang'] ) ) {
				$language = null;
			} else {
				$language = $params['lang'];
			}
			// * required parameters
			if ( !isset( $params['dm'] ) ) {
				$this->dieUsage( 'parameter dm for text type annotation is missing', 'param dm is missing' );
			}
			if ( !isset( $params['attribute'] ) ) {
				$this->dieUsage( 'parameter attribute for text type annotation is missing', 'param attribute missing' );
			}
			if ( !isset( $params['attrib_lang'] ) ) {
				$this->dieUsage( 'parameter attrib_lang for text type annotation is missing', 'param attrib_lang missing' );
			}
			if ( !isset( $params['text'] ) ) {
				$this->dieUsage( 'nothing to add', 'param text is missing' );
			}

			$definedMeaningId = $params['dm'];
			$attribute = $params['attribute'];
			$attribLang = $params['attrib_lang'];
			$text = $params['text'];

			$result = $this->processAddTextAttributeValues( $spelling, $language, $definedMeaningId, $attribute, $attribLang, $text );

			if ( !isset( $result['error'] ) ) {
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'variables' => [
						'e' => $spelling,
						'lang' => $language,
						'dm' => $definedMeaningId,
						'attribute' => $attribute,
						'attriblang' => $attribLang
					] ]
				);
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'process' => [
						'object_id' => $this->objectId,
						'attrib_mid' => $this->attributeId,
						'text' => $text
					] ]
				);
			}
			$typeExist = 1;
		}

		if ( $params['type'] == 'option' ) {
			// If wikipage, use batch processing
			if ( $params['wikipage'] ) {
				$this->wikipage = true;
				$this->type = 'option';
				$text = $this->processBatch( $params['wikipage'] );
				return true;
			}

			// if not, add just one option

			// Parameter checks

			// * required parameters
			if ( !isset( $params['dm'] ) ) {
				$this->dieUsage( 'parameter dm for option type annotation is missing', 'param dm is missing' );
			}
			if ( !isset( $params['attribute'] ) ) {
				$this->dieUsage( 'parameter attribute for option type annotation is missing', 'param attribute missing' );
			}
			if ( !isset( $params['attrib_lang'] ) ) {
				$this->dieUsage( 'parameter attrib_lang for option type annotation is missing', 'param attrib_lang missing' );
			}
			if ( !isset( $params['option'] ) ) {
				$this->dieUsage( 'nothing to add', 'param option is missing' );
			}
			if ( !isset( $params['option_lang'] ) ) {
				$this->dieUsage( 'parameter option_lang for option type annotation is missing', 'param option_lang missing' );
			}

			$definedMeaningId = $params['dm'];
			$attribute = $params['attribute'];
			$attribLang = $params['attrib_lang'];
			$option = $params['option'];
			$optionLang = $params['option_lang'];

			// * optional parameters if uw_objects's "table" = 'uw_syntrans'
			// not needed when uw_objects's "table" = 'uw_defined_meaning'
			if ( !isset( $params['e'] ) ) {
				$spelling = null;
				$language = null;
			} else {
				if ( !isset( $params['lang'] ) ) {
					$this->dieUsage( 'parameter lang for option type annotation is missing', 'param lang is missing' );
				}
				$spelling = $params['e'];
				$language = $params['lang'];
			}

			$result = $this->processAddOptionAttributeValues(
				$spelling, $language, $definedMeaningId,
				$attribute, $attribLang,
				$option, $optionLang
			);

			if ( !isset( $result['error'] ) ) {
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'variables' => [
						'e' => $spelling,
						'lang' => $language,
						'dm' => $definedMeaningId,
						'attribute' => $attribute,
						'attriblang' => $attribLang,
						'option' => $option,
						'optionlang' => $optionLang
					] ]
				);
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'process' => [
						'object_id' => $this->objectId,
						'option_id' => $this->optionId
					] ]
				);
			}
			$typeExist = 1;
		}

		// Types to be added later.
	// if ( $params['type'] == 'url' ) {
	// $typeExist = 1;
	// }

		if ( $params['type'] == 'relation' ) {
			// If wikipage, use batch processing
			if ( $params['wikipage'] ) {
				$this->wikipage = true;
				$this->type = 'relation';
				$text = $this->processBatch( $params['wikipage'] );
			// $text = $this->processBatchRelation( $params['wikipage'] );
				return true;
			}

			// if not, add just one relation

			// Parameter checks

			// * required parameters
			if ( !isset( $params['dm'] ) ) {
				$this->dieUsage( 'parameter dm for text type annotation is missing', 'param dm is missing' );
			}
			if ( !isset( $params['attribute'] ) ) {
				$this->dieUsage( 'parameter attribute for text type annotation is missing', 'param attribute missing' );
			}
			if ( !isset( $params['attrib_lang'] ) ) {
				$this->dieUsage( 'parameter attrib_lang for text type annotation is missing', 'param attrib_lang missing' );
			}

			$definedMeaningId = $params['dm'];
			$attribute = $params['attribute'];
			$attribLang = $params['attrib_lang'];

			// * optional parameters if uw_objects's "table" = 'uw_syntrans'
			// not needed when uw_objects's "table" = 'uw_defined_meaning'
			if ( !isset( $params['e'] ) ) {
				$spelling = null;
				$language = null;
				$relation = null;
				$relationLang = null;

				// required parameter for defined meaning relation.
				if ( !isset( $params['dm_relation'] ) ) {
					$this->dieUsage( 'parameter dm_relation for relation type annotation is missing', 'param dm_relation missing' );
				}
				$relationDM = $params['dm_relation'];
			} else {
				if ( !isset( $params['lang'] ) ) {
					$this->dieUsage( 'parameter lang for relation type annotation is missing', 'param lang is missing' );
				}
				if ( !isset( $params['relation'] ) ) {
					$this->dieUsage( 'parameter relation for relation type annotation is missing', 'param relation is missing' );
				}
				if ( !isset( $params['relation_lang'] ) ) {
					$this->dieUsage( 'parameter relation_lang for relation type annotation is missing', 'param relation_lang missing' );
				}

				$spelling = $params['e'];
				$language = $params['lang'];
				$relation = $params['relation'];
				$relationLang = $params['relation_lang'];

				// optional parameter for syntrans relation,
				// Basically, when this parameter is used
				// for syntrans relations, it means that
				// $relation has a different dm id as
				// $spelling. Currently useful for attribute 'etymon'.
				// If the parameter is not used, $relationDM is
				// the same as definedMeaningId.
				if ( !isset( $params['dm_relation'] ) ) {
					$relationDM = $params['dm'];
				} else {
					$relationDM = $params['dm_relation'];
				}
			}

			$result = $this->processAddRelationAttributeValues(
				$spelling, $language, $definedMeaningId,
				$attribute, $attribLang,
				$relation, $relationLang, $relationDM
			);

			if ( !isset( $result['error'] ) ) {
				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'variables' => [
						'e' => $spelling,
						'lang' => $language,
						'dm' => $definedMeaningId,
						'attribute' => $attribute,
						'attriblang' => $attribLang,
						'relation' => $relation,
						'relationlang' => $relationLang,
						'dm_relation' => $relationDM
					] ]
				);
			}
			$typeExist = 1;
		}

		// If above types are not met
		if ( $typeExist == 0 ) {
			$this->dieUsage( 'type is either mispelled, wrong or not yet implemented', 'type does not exist' );
			return true;
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
		return true;
	}

	// Parameters.
	public function getAllowedParams() {
		return [
			'type' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'e' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'lang' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'dm' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'attribute' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'attrib_lang' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'text' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'option' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'option_lang' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'relation' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'relation_lang' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'dm_relation' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'wikipage' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'test' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

	// Get examples
	public function getExamples() {
		return [
			'',
			'Add text type syntrans annotation',
			' api.php?action=ow_add_annotation&type=text&e=acusar&lang=spa&dm=837820&attribute=hyphenation&attrib_lang=eng&text=a·cu·sar&format=xml',
			' or to test it',
			' api.php?action=ow_add_annotation&type=text&e=acusar&lang=spa&dm=837820&attribute=hyphenation&attrib_lang=eng&text=a·cu·sar&format=xml&test',
			'',
			'Add text type defined meaning annotation',
			' api.php?action=ow_add_annotation&type=text&dm=2024&attribute=chemical%20symbol&attrib_lang=eng&text=Fe&format=xml',
			' api.php?action=ow_add_annotation&type=text&dm=2024&attribute=atomic%20number&attrib_lang=eng&text=26&format=xml',
			' or to test it',
			' api.php?action=ow_add_annotation&type=text&dm=2024&attribute=chemical%20symbol&attrib_lang=eng&text=Fe&format=xml&test',
			' api.php?action=ow_add_annotation&type=text&dm=2024&attribute=atomic%20number&attrib_lang=eng&text=26&format=xml&test',
			'',
			'You can also add synonym/translation text annotations using a TSV file format saved in a Wiki Page.  The file must ',
			'contain 6 columns:',
			' defined_meaning_id (int)',
			' attribute          (string)',
			' attribute language (string)',
			' text               (string)',
			' expression         (string)',
			' language           (string)',
			'',
			'  api.php?action=ow_add_annotation&type=text&wikipage=User:Minnan.import.bot/addTextAnnotation&dm=0&format=xml',
			'  or to test it',
			'  api.php?action=ow_add_annotation&type=text&wikipage=User:Minnan.import.bot/addTextAnnotationTest&dm=0&format=xml&test',
			'','',
			'Add option type syntrans annotation',
			' api.php?action=ow_add_annotation&type=option&e=acusar&lang=spa&dm=837820&attribute=part%20of%20speech&attrib_lang=eng&option=verb&option_lang=eng&format=xml',
			' api.php?action=ow_add_annotation&type=option&e=case&lang=eng&dm=7367&attribute=usage&attrib_lang=eng&option=colloquial&option_lang=eng&format=xml',
			' or to test it',
			' api.php?action=ow_add_annotation&type=option&e=acusar&lang=spa&dm=837820&attribute=part%20of%20speech&attrib_lang=eng&option=verb&option_lang=eng&format=xml&test',
			' api.php?action=ow_add_annotation&type=option&e=case&lang=eng&dm=7367&attribute=usage&attrib_lang=eng&option=colloquial&option_lang=eng&format=xml&test',
			'',
			'Add option type defined meaning annotation',
			' api.php?action=ow_add_annotation&type=option&dm=3188&attribute=topic&attrib_lang=eng&option=biology&option_lang=eng&format=xml',
			' or to test it',
			' api.php?action=ow_add_annotation&type=option&dm=3188&attribute=topic&attrib_lang=eng&option=biology&option_lang=eng&format=xml&test',
			'',
			'You can also add synonym/translation option annotations using a TSV file format saved in a Wiki Page.  The file must ',
			'contain 7 columns:',
			' defined_meaning_id (int)',
			' attribute          (string)',
			' attribute language (string)',
			' option             (string)',
			' option language    (string)',
			' expression         (string)',
			' language           (string)',
			'',
			'  api.php?action=ow_add_annotation&type=option&wikipage=User:Minnan.import.bot/addOptionAnnotation&dm=0&format=xml',
			'  or to test it',
			'  api.php?action=ow_add_annotation&type=option&wikipage=User:Minnan.import.bot/addOptionAnnotationTest&dm=0&format=xml&test',
			'','',
			'Add relation type syntrans annotation',
			' api.php?action=ow_add_annotation&type=relation&e=jí&lang=nan-POJ&dm=5453&attribute=dialectal%20variant&attrib_lang=eng&relation=lí&relation_lang=nan-POJ',
			' or to test it',
			' api.php?action=ow_add_annotation&type=relation&e=jí&lang=nan-POJ&dm=5453&attribute=dialectal%20variant&attrib_lang=eng&relation=lí&relation_lang=nan-POJ&test',
			'',
			'Add relation type defined meaning annotation',
			' api.php?action=ow_add_annotation&type=relation&dm=2024&attribute=hypernym&attrib_lang=eng&dm_relation=2324',
			' or to test it',
			' api.php?action=ow_add_annotation&type=relation&dm=2024&attribute=hypernym&attrib_lang=eng&dm_relation=2324&test',
			'',
			'You can also add synonym/translation relation annotations using a TSV file format saved in a Wiki Page.  The file must ',
			'contain 8 columns:',
			' defined_meaning_id (int)',
			' attribute          (string)',
			' attribute language (string)',
			' relation           (string)',
			' relation language  (string)',
			' expression         (string)',
			' language           (string)',
			' relation DM        (int)',
			'',
			'  api.php?action=ow_add_annotation&type=relation&wikipage=User:Minnan.import.bot/addRelationAnnotation&dm=0&format=xml',
			'  or to test it',
			'  api.php?action=ow_add_annotation&type=relation&wikipage=User:Minnan.import.bot/addRelationAnnotationTest&dm=0&format=xml&test',
			''
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

		$process = [
			'text' => 'wikipage',
			'type' => 'batch processing',
		];

		if ( $this->test ) {
			$process['note'] = 'test run only';
		}

		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'process' => $process
			]
		);

		$inputLine = explode( "\n", $text );
		$ctr = 0;
		foreach ( $inputLine as $inputData ) {
			$this->continue = true;
			$ctr++;
			$inputData = trim( $inputData );

			// Check if TSV
			$inputMatch = preg_match( "/	/", $inputData, $match );

			if ( $inputMatch and $this->continue ) {
				$inputData = explode( "	", $inputData );
				$inputDataCount = count( $inputData );

				// set type variables
				if ( $this->type == 'text' ) {
					$noOfCol = 6;
					if ( $inputDataCount == 4 and $this->continue ) {
						$inputData[] = null;
						$inputData[] = null;
						$inputDataCount = count( $inputData );
					}
				}
				if ( $this->type == 'option' ) {
					$noOfCol = 7;
					if ( $inputDataCount == 5 and $this->continue ) {
						$inputData[] = null;
						$inputData[] = null;
						$inputDataCount = count( $inputData );
					}
				}
				if ( $this->type == 'relation' ) {
					$noOfCol = 8;
					if ( $inputDataCount == 7 and $this->continue ) {
						$inputData[7] = $inputData[0];
						$inputDataCount = count( $inputData );
					}
				}

				if ( $inputDataCount < $noOfCol or $inputDataCount > $noOfCol and $this->continue ) {
					$result = [ 'note' => "invalid column count. {$inputDataCount} instead of {$noOfCol}" ];
					$this->getResult()->addValue( null, $this->getModuleName(),
						[ 'result' . $ctr => $result ]
					);
					$this->continue = false;
				}

				if ( $this->continue ) {
					$definedMeaningId = $inputData[0];
					$attribute = preg_replace( '/(^"|"$)/', '', $inputData[1] );
					$attribLang = preg_replace( '/(^"|"$)/', '', $inputData[2] );

					if ( $this->type == 'text' ) {
						$text = preg_replace( '/(^"|"$)/', '', $inputData[3] );
						$spelling = preg_replace( '/(^"|"$)/', '', $inputData[4] );
						$language = preg_replace( '/(^"|"$)/', '', $inputData[5] );
					}
					if ( $this->type == 'option' ) {
						$option = preg_replace( '/(^"|"$)/', '', $inputData[3] );
						$optionLang = preg_replace( '/(^"|"$)/', '', $inputData[4] );
						$spelling = preg_replace( '/(^"|"$)/', '', $inputData[5] );
						$language = preg_replace( '/(^"|"$)/', '', $inputData[6] );
					}
					if ( $this->type == 'relation' ) {
						$relation = preg_replace( '/(^"|"$)/', '', $inputData[3] );
						$relationLang = preg_replace( '/(^"|"$)/', '', $inputData[4] );
						$spelling = preg_replace( '/(^"|"$)/', '', $inputData[5] );
						$language = preg_replace( '/(^"|"$)/', '', $inputData[6] );
						$relationDM = preg_replace( '/(^"|"$)/', '', $inputData[7] );
					}
				}
			} else {
				if ( $inputData == null ) {
					$result = [ 'note' => "skipped blank line" ];
					$this->getResult()->addValue( null, $this->getModuleName(),
						[ 'result' . $ctr => $result ]
					);
					$this->continue = false;
				} else {
					$result = [ 'note' => "non TSV line `{$inputData}`" ];
					$this->getResult()->addValue( null, $this->getModuleName(),
						[ 'result' . $ctr => $result ]
					);
					$this->continue = false;
				}
			}

			if ( $this->continue ) {
				if ( !is_numeric( $definedMeaningId ) ) {
					if ( $ctr == 1 ) {
						$result = [ 'note' => "$definedMeaningId is not an int or probably just the CSV header" ];
					} else {
						$result = [ 'note' => "$definedMeaningId is not an int" ];
					}
				} else {
					if ( $this->type == 'text' ) {
						$result = $this->processAddTextAttributeValues( $spelling, $language, $definedMeaningId, $attribute, $attribLang, $text );
					}
					if ( $this->type == 'option' ) {
						$result = $this->processAddOptionAttributeValues( $spelling, $language, $definedMeaningId, $attribute, $attribLang, $option, $optionLang );
					}
					if ( $this->type == 'relation' ) {
						$result = $this->processAddRelationAttributeValues( $spelling, $language, $definedMeaningId, $attribute, $attribLang, $relation, $relationLang, $relationDM );
					}
				}

				$this->getResult()->addValue( null, $this->getModuleName(),
					[ 'result' . $ctr => $result ]
				);
			}

		}
		return true;
	}

	private function processAddTextAttributeValues( $spelling = null, $language = null, $definedMeaningId, $attribute, $attribLang, $text ) {
		$dc = wdGetDataSetContext();

		// if spelling is not null, process object as syntrans
		// if null, process as defined meaning
		if ( $spelling ) {
			// Convert Iso369 to language_id
			$languageId = getLanguageIdForIso639_3( $language );
			if ( !$languageId ) {
				return [ 'error' => [
						'code' => 'param lang does not exist',
						'info' => "No lang_id found for language $language"
					]
				];
			}
			// Get Expression Id
			$expressionId = getExpressionId( $spelling, $languageId );
			if ( !$expressionId ) {
				return [ 'error' => [
						'code' => 'param e does not exist',
						'info' => "The expression (spelling: $spelling, lang: $languageId) does not exist"
					]
				];
			}
			// Get object_id (syntrans_sid)
			$this->objectId = getSynonymId( $definedMeaningId, $expressionId );
			if ( !$this->objectId ) {
				return [ 'error' => [
						'code' => 'param object does not exist',
						'info' => "The object id for (dm: $definedMeaningId, exp: $expressionId $spelling) does not exist"
					]
				];
			}
		} else {
			// Set object_id (defined_meaning_id)
			$this->objectId = $definedMeaningId;
		}

		// Convert Iso369 to language_id
		$attribLangId = getLanguageIdForIso639_3( $attribLang );
		if ( !$attribLangId ) {
			return [ 'error' => [
					'code' => 'param attrib_lang does not exist',
					'info' => "The attribute language $attribLang does not exist"
				]
			];
		}
		// Check if attribute exist
		$attributeExpressionId = getExpressionId( $attribute, $attribLangId );
		if ( !$attributeExpressionId ) {
			return [ 'error' => [
					'code' => 'not exist: attribute',
					'info' => "The attribute (att: $attribute, lang: $attribLangId) for text type annotation does not exist"
				]
			];
		}
		// Get Attribute Id from expression Id
		$this->attributeId = getTextAttributeOptionsAttributeMidFromExpressionId( $attributeExpressionId );
		if ( $this->attributeId === null ) {
			return [ 'error' => [
					'code' => 'not exist: attribute_mid',
					'info' => "attribute ($attribute) does not exist"
				]
			];
		}

		// get DM expression for clarity
		$definedMeaningLanguageId = WLD_ENGLISH_LANG_ID;
		$syntrans = null;
		if ( $spelling ) {
			$definedMeaningLanguageId = $languageId;
			$syntrans = "to expression:`{$spelling}` ";
		}
		$expression = getDefinedMeaningSpellingForLanguage( $definedMeaningId, $definedMeaningLanguageId );

		// Add values if does not exist
		$valueId = getTextAttributeValueId( $this->objectId, $this->attributeId, $text );
		if ( !$valueId ) {
			if ( !$this->test ) {
				if ( !$this->transacted ) {
					$this->transacted = true;
					startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_annotation (text)", $dc );
				}
				$valueId = addTextAttributeValue( $this->objectId, $this->attributeId, $text );
			}
			$note = [
				'status' => 'added'
			];

			if ( $valueId ) {
				$note['value_id'] = $valueId;
			}

			if ( $this->wikipage ) {
				$note['note'] = "{$attribute} `{$text}` {$syntrans}for concept {$expression}({$definedMeaningId})";
			}

			if ( $this->test && !$this->wikipage ) {
				$note['note'] = 'test run only';
			}
		} else {
			$note = [
				'status' => 'exists',
				'value_id' => $valueId
			];

			if ( $this->wikipage ) {
				$note['note'] = "{$attribute} `{$text}` {$syntrans}for concept {$expression}({$definedMeaningId})";
			}

			if ( $this->test && !$this->wikipage ) {
				$note['note'] = 'test run only';
			}
		}
		return $note;
	}

	private function processAddOptionAttributeValues( $spelling, $language, $definedMeaningId, $attribute, $attribLang, $option, $optionLang ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		// if spelling is not null, process object as syntrans
		// if null, process as defined meaning
		if ( $spelling ) {
			// Convert Iso369 to language_id
			$languageId = getLanguageIdForIso639_3( $language );
			if ( !$languageId ) {
				return [ 'error' => [
						'code' => 'param lang does not exist',
						'info' => "No lang_id found for language $language"
					]
				];
			}
			// Get Language DM Id
			$languageNameDMId = getDMIdForIso639_3( $language );

			// Get Expression Id
			$expressionId = getExpressionId( $spelling, $languageId );
			if ( !$expressionId ) {
				return [ 'error' => [
						'code' => 'param e does not exist',
						'info' => "The expression (spelling: $spelling, lang: $languageId) does not exist"
					]
				];
			}
			// Get object_id (syntrans_sid)
			$this->objectId = getSynonymId( $definedMeaningId, $expressionId );
			if ( !$this->objectId ) {
				return [ 'error' => [
						'code' => 'param object does not exist',
						'info' => "The object id for (dm: $definedMeaningId, exp: $expressionId - $spelling) does not exist"
					]
				];
			}
			$levelMeaningId = $dbr->selectField(
				"{$dc}_bootstrapped_defined_meanings",
				'defined_meaning_id',
				[ 'name' => 'SynTrans' ],
				__METHOD__
			);
		} else {
			// Set object_id (defined_meaning_id)
			$this->objectId = $definedMeaningId;
			$levelMeaningId = $dbr->selectField(
				"{$dc}_bootstrapped_defined_meanings",
				'defined_meaning_id',
				[ 'name' => 'DefinedMeaning' ],
				__METHOD__
			);
			$languageNameDMId = -1;
			$languageId = 0;
		}

		// Convert Iso369 to language_id
		$attribLang = getLanguageIdForIso639_3( $attribLang );
		if ( !$attribLang ) {
			return [ 'error' => [
					'code' => 'param attrib_lang does not exist',
					'info' => "The attribute language $attribLang does not exist"
				]
			];
		}
		// Check if attribute exist
		$attributeExpressionId = getExpressionId( $attribute, $attribLang );
		if ( !$attributeExpressionId ) {
			return [ 'error' => [
					'code' => 'not exist: attribute',
					'info' => "The attribute (att: $attribute, lang: $attribLang) for text type annotation does not exist"
				]
			];
		}
		// Convert Iso369 to language_id
		$optionLang = getLanguageIdForIso639_3( $optionLang );
		if ( !$optionLang ) {
			return [ 'error' => [
					'code' => 'param option_lang does not exist',
					'info' => "The option language $optionLang does not exist"
				]
			];
		}
		// Check if option exist
		$optionExpressionId = getExpressionId( $option, $optionLang );
		if ( !$optionExpressionId ) {
			return [ 'error' => [
					'code' => 'not exist: attribute',
					'info' => "The attribute (opt: $option, lang: $optionLang) for text type annotation does not exist"
				]
			];
		}
		// Get attribute_id from attribute expression Id
		$this->attributeId = getOptionAttributeOptionsAttributeIdFromExpressionId( $attributeExpressionId, $languageNameDMId, $levelMeaningId );

		if ( $this->attributeId === null ) {
			return [ 'error' => [
					'code' => 'not exist: attribute_id',
					'info' => "attribute ($attribute) does not exist"
				]
			];
		}
		// Get option_mid from option expression Id
		$optionMeaningId = getOptionAttributeOptionsOptionMidFromExpressionId( $optionExpressionId );

		if ( $optionMeaningId === null ) {
			return [ 'error' => [
					'code' => 'not exist: option_mid',
					'info' => "attribute ($option) does not exist"
				]
			];
		}
		// Get option_id
		$tof = optionAttributeOptionExists( $this->attributeId, $optionMeaningId, $languageId );
		if ( $tof == 0 ) {
			$tof = optionAttributeOptionExists( $this->attributeId, $optionMeaningId, 0 );
			if ( $tof == 0 ) {
				$optionIdExist = 0;
			} else {
				$optionIdExist = 2;
			}
		} else {
			$optionIdExist = 1;
		}

		if ( $optionIdExist == 1 ) {
			$this->optionId = getOptionAttributeOptionsOptionId( $this->attributeId, $optionMeaningId, $languageId );
		}
		if ( $optionIdExist == 2 ) {
			$this->optionId = getOptionAttributeOptionsOptionId( $this->attributeId, $optionMeaningId, 0 );
		}

		// get DM expression for clarity
		$definedMeaningLanguageId = WLD_ENGLISH_LANG_ID;
		$syntrans = null;
		if ( $spelling ) {
			$definedMeaningLanguageId = $languageId;
			$syntrans = "to expression:`{$spelling}` ";
		}
		$expression = getDefinedMeaningSpellingForLanguage( $definedMeaningId, $definedMeaningLanguageId );

		// Add values if does not exist
		$valueId = getOptionAttributeValueId( $this->objectId, $this->optionId );
		if ( !$valueId ) {
			if ( !$this->test ) {
				if ( !$this->transacted ) {
					$this->transacted = true;
					startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_annotation (option)", $dc );
				}
				addOptionAttributeValue( $this->objectId, $this->optionId );
				$valueId = getOptionAttributeValueId( $this->objectId, $this->optionId );
				echo $valueId . '"';
			}
			$note = [
				'status' => 'added'
			];

			if ( $valueId ) {
				$note['value_id'] = $valueId;
			}

			if ( $this->wikipage ) {
				$note['note'] = "{$attribute} `{$option}` {$syntrans}for concept {$expression}({$definedMeaningId})";
			}

			if ( $this->test && !$this->wikipage ) {
				$note['note'] = 'test run only';
			}
		} else {
			$note = [
				'status' => 'exists',
				'value_id' => $valueId
			];

			if ( $this->wikipage ) {
				$note['note'] = "{$attribute} `{$option}` {$syntrans}for concept {$expression}({$definedMeaningId})";
			}

			if ( $this->test && !$this->wikipage ) {
				$note['note'] = 'test run only';
			}
		}
		return $note;
	}

	private function processAddRelationAttributeValues( $spelling, $language, $definedMeaningId, $attribute, $attribLang, $relation, $relationLang, $relationDM ) {
		$dc = wdGetDataSetContext();

		// Check defined_meaning_id if exist
		$definedMeaningId = verifyDefinedMeaningId( $definedMeaningId );
		if ( !$definedMeaningId ) {
			return [ 'error' => [
					'code' => 'defined_meaning_id does not exist',
					'info' => "The defined_meaning_mid ($definedMeaningId) does not exist"
				]
			];
		}
		$relationDM = verifyDefinedMeaningId( $relationDM );
		if ( !$relationDM ) {
			return [ 'error' => [
					'code' => ' relation defined_meaning_id does not exist',
					'info' => "The relation's defined_meaning_mid ($relationDM) does not exist"
				]
			];
		}
		// if spelling is not null, process object as syntrans
		// if null, process as defined meaning
		if ( $spelling ) {
			// Convert Iso369 to language_id
			$languageId = getLanguageIdForIso639_3( $language );
			if ( !$languageId ) {
				return [ 'error' => [
						'code' => 'language_id does not exist',
						'info' => "No lang_id found for language $language"
					]
				];
			}
			$relationLanguageId = getLanguageIdForIso639_3( $relationLang );
			if ( !$relationLanguageId ) {
				return [ 'error' => [
						'code' => 'relation language_id does not exist',
						'info' => "No lang_id found for relation language $relationLang"
					]
				];
			}
			// Get Expression Ids
			$expressionId = getExpressionId( $spelling, $languageId );
			if ( !$expressionId ) {
				return [ 'error' => [
						'code' => 'expression_id does not exist',
						'info' => "The expression_id for (spelling: $spelling, lang_id: $languageId) does not exist"
					]
				];
			}
			$relationExpressionId = getExpressionId( $relation, $relationLanguageId );
			if ( !$relationExpressionId ) {
				return [ 'error' => [
						'code' => 'relation expression_id does not exist',
						'info' => "No expression_id found corresponding to (exp: $relation, lang: $relationLanguageId )"
					]
				];
			}
			// Get meaning1_mid (syntrans_sid)
			$meaning1Mid = getSynonymId( $definedMeaningId, $expressionId );
			if ( !$meaning1Mid ) {
				return [ 'error' => [
						'code' => 'meaning1_mid does not exist',
						'info' => "No meaning1_mid found for (dm: $definedMeaningId, exp_id: $expressionId)"
					]
				];
			}
			// Get meaning2_mid (syntrans_sid)
			$meaning2Mid = getSynonymId( $relationDM, $relationExpressionId );
			if ( !$meaning2Mid ) {
				return [ 'error' => [
						'code' => 'meaning2_mid does not exist',
						'info' => "No meaning2_mid found for (dm: $relationDM, exp_id: $relationExpressionId)"
					]
				];
			}
		} else {
			// Set meaning1_mid (defined_meaning_id)
			$meaning1Mid = $definedMeaningId;
			$meaning2Mid = $relationDM;
		}

		// Convert Iso369 to language_id
		$attribLangId = getLanguageIdForIso639_3( $attribLang );
		if ( !$attribLangId ) {
			return [ 'error' => [
					'code' => 'param attrib_lang does not exist',
					'info' => "The languageId for isocode ($attribLang) does not exist"
				]
			];
		}
		// Check if attribute exist
		$attributeExpressionId = getExpressionId( $attribute, $attribLangId );
		if ( !$attributeExpressionId ) {
			return [ 'error' => [
					'code' => 'not exist: attribute',
					'info' => "The attribute ($attribute) with lang ($attribLangId) does not exist"
				]
			];
		}
		// Get attribute's dm
		$relationtypeMid = null;

		$attributeDMids = getDefinedMeaningIdFromExpressionIdAndLanguageId( $attributeExpressionId, $attribLangId );
		foreach ( $attributeDMids as $row ) {
			$checking = verifyRelationtypeMId( $row );
			if ( $checking ) {
				$relationtypeMid = $checking;
			}
		}

		if ( !$relationtypeMid ) {
			return [ 'error' => [
					'code' => 'relationtype_mid not found',
					'info' => "No relation found corresponding to (exp: $attribute, lang: $attribLangId)"
				]
			];
		}

		// get DM expression for clarity
		$definedMeaningLanguageId = WLD_ENGLISH_LANG_ID;
		$syntrans = null;
		if ( $spelling ) {
			$definedMeaningLanguageId = $languageId;
			$syntrans = "to expression:`{$spelling}` ";
		} else {
			$expressionR = getDefinedMeaningSpellingForLanguage( $relationDM, $definedMeaningLanguageId );
		}
		$expression = getDefinedMeaningSpellingForLanguage( $definedMeaningId, $definedMeaningLanguageId );

		// Add values if does not exist
		$relationId = relationExists( $meaning1Mid, $relationtypeMid, $meaning2Mid );
		if ( !$relationId ) {
			$note['status'] = 'added';

			if ( !$this->test ) {
				if ( !$this->transacted ) {
					$this->transacted = true;
					startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_annotation (relation)", $dc );
				}
				addRelation( $meaning1Mid, $relationtypeMid, $meaning2Mid );
			} else {
				$note['note'] = 'test run only';
			}
		} else {
			$note['status'] = 'exists';

			if ( $this->wikipage ) {
				if ( $spelling ) {
					$note['note'] = "{$attribute} `{$relation}` {$syntrans}for concept {$expression}({$definedMeaningId})";
				} else {
					$note['note'] = "{$attribute} concept `{$expressionR}`({$relationDM}) {$syntrans}for concept `{$expression}`({$definedMeaningId})";
				}
			}

			if ( $this->test && !$this->wikipage ) {
				$note['note'] = 'test run only';
			}
		}

		return $note;
	}

}
