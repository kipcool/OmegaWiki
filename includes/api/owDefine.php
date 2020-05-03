<?php

/** O m e g a W i k i   A P I ' s   D e f i n e   c l a s s
 *
 * HISTORY
 * - 2014-03-07: Cache output
 * - 2013-06-12: Add optional translation list option. &syntrans= (syn, trans or all)
 * 		added ability to add syntrans.
 * - 2013-06-05: Readjusted defining and definingByAnyLanguage functions into
 *		class Define. Express Class now extends Define Class.
 * - 2013-05-31: Readjusted the array produced. The Language Name added.
 * 		Language name defaults to the language of their respective
 *		expression/definition.  When the language name does not exist,
 *		the language name is given in English. language_id is retained
 *		so that those who are using the API might be able to use it in case
 *		the language name changes from its current unpredictable ways.
 *		( Who knows, the script currently calls Spanish as Castillian in English,
 *		but may use a different one in the future ). ~he
 * - 2013-05-23: Created separate defining and definingByAnyLanguage functions.
 *		Can be useful for E x p r e s s   c l a s s  ~he
 * - 2013-03-14: Creation date ~he
 *
 * TODO
 * - Add optional translation limit option. &trans_lang=385|85
 * - Add optional translation limit option. &trans_lang_iso=nan-POJ|eng
 * - Add optional lang parameter. &lang_iso=cmn-Hant
 *
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';

class Define extends SynonymTranslation {

	public $languageId, $text, $spelling, $spellingLanguageId;

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		$options = [];

		// Get the parameters
		$params = $this->extractRequestParams();
		$defined = $this->cacheDefine( $params );

		$this->getResult()->addValue( null, $this->getModuleName(), $defined );
		return true;
	}

	/** Cache the function
	 *  Note: dieUsage must be used outside the cache lest the cache will return empty the
	 * 		next time it is accessed.
	 */
	protected function cacheDefine( $params ) {
		$defineCacheKey = 'API:ow_define:dm=' . $params['dm'];
		if ( isset( $params['lang'] ) ) {
			$defineCacheKey .= ":ver={$params['lang']}";
		}
		if ( isset( $params['syntrans'] ) ) {
			$defineCacheKey .= ":ver={$params['syntrans']}";
		}
		if ( isset( $params['e'] ) ) {
			$defineCacheKey .= ":ver={$params['e']}";
		}
		if ( isset( $params['ver'] ) ) {
			$defineCacheKey .= ":ver={$params['ver']}";
		}

		$cache = new CacheHelper();

		$cache->setCacheKey( [ $defineCacheKey ] );
		$define = $cache->getCachedValue(
			function ( $params ) {
				// Required parameter
				// Check if dm is valid
				if ( isset( $params['dm'] ) ) {
					// check that defined_meaning_id exists
					if ( !verifyDefinedMeaningId( $params['dm'] ) ) {
						return [ 'error' => 'dm not found' ];
					}
				}

				// Optional parameter
				$options = [];
				$partIsValid = false;

				if ( isset( $params['syntrans'] ) ) {
					$options['part'] = $params['syntrans'];
				} else {
					$options['part'] = 'off';
					$partIsValid = true;
				}

				// error if $params['part'] is empty
				if ( $options['part'] == '' ) {
					return [ 'error' => 'nullsyntrans' ];
				}

				// get syntrans
				// When returning synonyms or translation only
				if ( $options['part'] == 'syn' or $options['part'] == 'trans' or $options['part'] == 'all' ) {
					$partIsValid = true;
					if ( !isset( $params['lang'] ) ) {
						return [ 'error' => 'nolang' ];
					}
				}

				if ( !$partIsValid ) {
					return [ 'error' => 'invalidsyntrans' ];
				}

				if ( $params['e'] ) {
					$trueOrFalse = getExpressionId( $params['e'], $params['lang'] );
					if ( $trueOrFalse == true ) {
						$options['e'] = $params['e'];
					}
				}

				if ( $params['e'] && !isset( $options['e'] ) ) {
					return [ 'error' => 'e not found' ];
				}

				if ( $params['lang'] ) {
					$trueOrFalse = LanguageIdExist( $params['lang'] );
					if ( $trueOrFalse == true ) {
						$options['lang'] = $params['lang'];
						$defined = $this->defining( $params['dm'], $params['lang'], $options, $this->getModuleName() );
					} else {
						return [ 'error' => 'lang not found' ];
					}
				} else {
					if ( $options['part'] == 'syn' or $options['part'] == 'trans' or $options['part'] == 'all' ) {
						return [ 'error' => 'nulllang' ];
					}
					$defined = $this->definingForAnyLanguage( $params['dm'], $options, $this->getModuleName() );
				}

				return $defined[ $this->getModuleName() ];
			}, [ $params ]
		);
		$cache->setExpiry( 10800 ); // 3 hours
		$cache->saveCache();

		// catch errors here
		if ( isset( $define['error'] ) ) {
			$defineErrCode = [
				'nolang' => 'The lang parameter must be set',
				'dm not found' => 'Non existent dm id (' . $params['dm'] . ')',
				'e not found' => 'Non existent e id (' . $params['e'] . ')',
				'lang not found' => 'Non existent lang id (' . $params['lang'] . ')',
				'nullsyntrans' => 'parameter syntrans for adding syntrans is empty',
				'nulllang' => 'parameter lang for adding syntrans is empty',
				'invalidsyntrans' => 'parameter syntrans is neither syn, trans nor all',
			];
			$this->dieUsage( $defineErrCode["{$define['error']}"], $define['error'] );
		}

		// if no error was found, return the result
		return $define;
	}

	// Parameters.
	public function getAllowedParams() {
		return [
			'dm' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'lang' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'e' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'syntrans' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	// Get examples
	public function getExamples() {
		return [
			'Get a definition from a defined meaning id only.',
			'api.php?action=ow_define&dm=8218',
			'',
			'Get a definition from a defined meaning id and a language id.',
			'api.php?action=ow_define&dm=8218&lang=87',
			'When a definition is not available for a language id, ',
			'the definition will default to English',
			'api.php?action=ow_define&dm=8218&lang=107',
			'',
			'When you want to include synonyms',
			'api.php?action=ow_define&dm=8218&lang=87&syntrans=syn',
			'When you want to include translations',
			'api.php?action=ow_define&dm=8218&lang=87&syntrans=trans',
			'When you want to include both synonyms and translations',
			'api.php?action=ow_define&dm=8218&lang=87&syntrans=all',
			'',
			'NOTE: You can not include syntrans without the language id.',
			'',
			'In case the expression is also given with the language id,',
			'the expression is excluded from the list of syntrans.',
			'',
			'Get the synonyms and translations of a defined meaning id with lang',
			'api.php?action=ow_define&dm=8218&syntrans=all&e=字母&lang=107',
			'Get the synonyms of a defined meaning id',
			'api.php?action=ow_define&dm=8218&syntrans=syn&e=aksara&lang=231',
		];
	}

	/**
	 * Define expression when the language is not specified.
	 */
	protected function defining( $definedMeaningId, $languageId, $options = [], $moduleName = null ) {
		$syntrans = [];

		if ( $moduleName === null ) {
			$moduleName = 'ow_define';
		}

		if ( isset( $options['e'] ) ) {
			$spelling = $options['e'];
		} else {
			$spelling = getDefinedMeaningSpellingForLanguage( $definedMeaningId, $languageId );
		}
		$spellingLanguageId = $languageId;

		// get language name. Use English as fall back.
		$spellingLanguage = getLanguageIdLanguageNameFromIds( $spellingLanguageId, $spellingLanguageId );
		if ( !$spellingLanguage ) {
			$spellingLanguage = getLanguageIdLanguageNameFromIds( $spellingLanguageId, WLD_ENGLISH_LANG_ID );
		}

		$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $spellingLanguageId );

		if ( !$text ) {
			$languageId = WLD_ENGLISH_LANG_ID;
			$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
		}

		$definitionLanguageId = getDefinedMeaningDefinitionLanguageIdForDefinition( $definedMeaningId, $text );
		$definitionLanguage = getLanguageIdLanguageNameFromIds( $definitionLanguageId, $definitionLanguageId );
		$definitionSpelling = getSpellingForLanguageId( $definedMeaningId, $definitionLanguageId, WLD_ENGLISH_LANG_ID );

		if ( isset( $options['part'] ) ) {
			if ( $options['part'] == 'all' ) {
				$options['part'] = null;
			}
			$syntrans = $this->synTrans( $definedMeaningId, $options );
		}

		return [
			$moduleName => [
				'dmid' => $definedMeaningId,
				'spelling' => $spelling,
				'langid' => $spellingLanguageId,
				'lang' => $spellingLanguage,
				'definition' => [
					'spelling' => $definitionSpelling,
					'langid' => $definitionLanguageId,
					'lang' => $definitionLanguage,
					'text' => $text
				],
				'syntrans' => $syntrans
			]
		];
	}

	/**
	 * Define expression when the language is not specified.
	 */
	protected function definingForAnyLanguage( $definedMeaningId, $options = [], $moduleName = null ) {
		$languageId = null;
		$language = null;

		$remove_langIdArray = 0;
		if ( $moduleName === null ) {
			$moduleName = 'ow_define';
		}

		if ( isset( $options['e'] ) ) {
			$spelling = $options['e'];
			$languageId = getLanguageIdForDefinedMeaningAndExpression( $definedMeaningId, $spelling );
			$language = getLanguageIdLanguageNameFromIds( $languageId, $languageId );
			if ( !$language ) {
				$language = getLanguageIdLanguageNameFromIds( $languageId, WLD_ENGLISH_LANG_ID );
			}
		} else {
			$remove_langIdArray = 1;
		}

		$text = getDefinedMeaningDefinition( $definedMeaningId );
		$definitionLanguageId = getDefinedMeaningDefinitionLanguageIdForDefinition( $definedMeaningId, $text );
		$definitionLanguage = getLanguageIdLanguageNameFromIds( $definitionLanguageId, $definitionLanguageId );
		$definitionSpelling = getSpellingForLanguageId( $definedMeaningId, $definitionLanguageId, WLD_ENGLISH_LANG_ID );

		$definition = [
			$moduleName => [
				'dmid' => $definedMeaningId,
				'langid' => $languageId,
				'lang' => $language,
				'definition' => [
					'spelling' => $definitionSpelling,
					'langid' => $definitionLanguageId,
					'lang' => $definitionLanguage,
					'text' => $text
				]
			]
		];

		if ( $remove_langIdArray == 1 ) {
			unset( $definition[$moduleName]['langid'] );
			unset( $definition[$moduleName]['lang'] );
		}

		return $definition;
	}
}
