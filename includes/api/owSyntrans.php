<?php

/** O m e g a W i k i   A P I ' s   S y n t r a n s   c l a s s
 *
 * PARAMETERS
 *	 @param	req'd	int	dm	'the defined meaning id'
 *	 @param	opt'l	int	lang	'the defined meaning's language id'
 *	 @param	opt'l	int	e	'the defined meaning's expression'
 *	 @param	opt'l	str	part	'synonym or translation'
 *	 @param	opt'l	str	ver		'the module version'
 *
 * HISTORY
 * - 2014-03-05: version 1.1 display added. substitute '{$ctr}.' with 'sid$sid'.
 *		added ow_syntrans[dmid] and ow_syntrans[lang] when available.
 *		also added langid and im to each sid.
 *		And should I say it? We are now using Cache!
 * - 2013-06-13: Minimized data output, corrections. Error were
 *		generated last time.
 * - 2013-06-11:
 *		* simplified synTrans function
 *		* renamed part_lang_id to lang
 *		* added option to exclude an expression from synonyms
 * - 2013-06-08: Added
 *		 @param	opt'l	str	part	'synonym or translation'
 *		 @param	opt'l	int	prtlangid	'the param part's language id'
 * - 2013-06-04: Added basic structure
 *		 @param	req'd	int	dm	'the defined meaning id'
 * - 2013-06-04: Creation date ~he
 *
 * TODO
 * - Integrate with Define Class
 * - Transfer getSynonymAndTranslation function to WikiDataAPI when ready
 *
 * QUESTION
 * - Is caching the parameters better than non at all?
 * - how long should the cache stay?
 * - a developer parameter to skip the cache. Useful for contributors who wants
 *		to see if their contribution was included.
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';

class SynonymTranslation extends ApiBase {

	public $languageId, $text, $spelling, $spellingLanguageId;

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		$options = [];

		// Get the parameters
		$params = $this->extractRequestParams();

		// Required parameter
		// Check if dm is valid
		if ( !isset( $params['dm'] ) ) {
			$this->dieUsage( 'parameter dm for adding syntrans is missing', 'param dm is missing' );
		} else {
			// check that defined_meaning_id exists
			if ( !verifyDefinedMeaningId( $params['dm'] ) ) {
				$this->dieUsage( 'Non existent dm id (' . $params['dm'] . ').', "dm not found." );
			}
		}

		// Optional parameter
		$options = [];

		if ( isset( $params['ver'] ) ) {
			$options['ver'] = $params['ver'];
		}

		if ( !isset( $params['part'] ) ) {
			$params['part'] = 'all';
		}
		if ( $params['part'] == 'all' ) {
			$partIsValid = true;
		}

		// error if $params['part'] is empty
		if ( $params['part'] == '' ) {
			$this->dieUsage( 'parameter part for adding syntrans is empty', 'param part is empty' );
		}

		// get syntrans
		// When returning synonyms or translation only
		if ( $params['part'] == 'syn' or $params['part'] == 'trans' ) {
			if ( !isset( $params['lang'] ) ) {
				$this->dieUsage( 'parameter lang for adding syntrans is missing', 'param lang is missing' );
			}
			$options['part'] = $params['part'];
			$partIsValid = true;
		}

		// error message if part is invalid
		if ( !$partIsValid ) {
			$this->dieUsage( 'parameter part for adding syntrans is neither syn, trans nor all', 'invalid param part value' );
		}

		if ( $params['lang'] ) {
			$trueOrFalse = LanguageIdExist( $params['lang'] );
			if ( $trueOrFalse == true ) {
				$options['lang'] = $params['lang'];
			} else {
				if ( $params['part'] == 'syn' or $params['part'] == 'trans' ) {
					$this->dieUsage( 'parameter lang for adding syntrans does not exist', 'param lang does not exist' );
				}
			}
		} else {
			if ( $params['part'] == 'syn' or $params['part'] == 'trans' ) {
				$this->dieUsage( 'parameter lang for adding syntrans is empty', 'param lang empty' );
			}
		}

		if ( $params['e'] ) {
			$trueOrFalse = getExpressionId( $params['e'], $params['lang'] );
			if ( $trueOrFalse == true ) {
				$options['e'] = $params['e'];
			}
		}

		// When only dm is given
		if ( $params['e'] && !isset( $options['e'] ) ) {
			$this->dieUsage( 'parameter e for adding syntrans does not exist', 'param e does not exist' );
		}

	// NOTE: I am thinking about adding a developer parameter to skip the
	// cache system. To check if their latest contribution is seem.
	// also, I am not sure how long should the cache stay, and if there is
	// really any benefit to cache it in the first place. ~ he
	// $syntrans = $this->synTrans( $params['dm'], $options );
		$syntrans = $this->cacheSynTrans( $params['dm'], $options );

		$this->getResult()->addValue( null, $this->getModuleName(), $syntrans );
		return true;
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
			'part' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'ver' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	// Get examples
	public function getExamples() {
		return [
			' Returns all syntrans of the concept 8218 (alphabet)',
			' api.php?action=ow_syntrans&dm=8218&ver=1.1&format=json',
			' Returns the words (synonyms) that express the concept 8218 (alphabet) in Spanish (lang=87)',
			' api.php?action=ow_syntrans&dm=8218&ver=1.1&part=syn&lang=87',
			' Returns the translations of that Spanish concept ',
			' api.php?action=ow_syntrans&dm=8218&ver=1.1&part=trans&lang=87',
			'',
			'In case the expression is also given with the language id,',
			'the expression is excluded from the list of syntrans.',
			'',
			' Get the synonyms and translations of a defined meaning id with lang',
			' api.php?action=ow_syntrans&dm=8218&ver=1.1&e=字母&lang=107',
			' Get the synonyms of a defined meaning id',
			' api.php?action=ow_syntrans&dm=8218&ver=1.1&part=syn&e=aksara&lang=231',
		];
	}

	// Additional Functions

	/** Cache!
	 */
	protected function cacheSynTrans( $dmid, $options = null ) {
		$synTransCacheKey = 'API:ow_syntrans:dm=' . $dmid;
		if ( isset( $options['lang'] ) ) {
			$synTransCacheKey .= ":lang={$options['lang']}";
		}
		if ( isset( $options['e'] ) ) {
			$synTransCacheKey .= ":e={$options['e']}";
		}
		if ( isset( $options['part'] ) ) {
			$synTransCacheKey .= ":part={$options['part']}";
		}
		if ( isset( $options['ver'] ) ) {
			$synTransCacheKey .= ":ver={$options['ver']}";
		}

		$cache = new CacheHelper();

		$cache->setCacheKey( [ $synTransCacheKey ] );
		$syntrans = $cache->getCachedValue(
			function ( $dmid, $options = null ) {
				return $this->synTrans( $dmid, $options );
			}, [ $dmid, $options ]
		);
		$cache->setExpiry( 3600 );
		$cache->saveCache();

		return $syntrans;
	}

	/**
	 * Returns an array of syntrans via defined meaning id
	 * Returns array() when empty
	 */
	protected function synTrans( $definedMeaningId, $options = [] ) {
		$syntrans = [];
		$stList = getSynonymAndTranslation( $definedMeaningId );

		$ctr = 1;
		$dot = '.';
		$syntrans['dmid'] = $definedMeaningId;
		if ( isset( $options['lang'] ) ) {
			$syntrans['lang'] = $options['lang'];
		}
		foreach ( $stList as $row ) {
			$language = getLanguageIdLanguageNameFromIds( $row[1], WLD_ENGLISH_LANG_ID );

			if ( isset( $options['ver'] ) ) {
				if ( $options['ver'] == '1.1' ) {
					$ctr = 'sid_' . $row[3];
					$dot = '';
				}
			}

			$syntransRow = [
				'langid' => $row[1],
				'lang' => $language,
				'e' => $row[0],
				'im' => $row[2]
			];

			if ( isset( $options['part'] ) ) {
				if ( $options['part'] == 'syn' and $options['lang'] == $row[1] ) {
					if ( isset( $options['e'] ) ) {
						// skip the expression for the language id
						if ( $options['lang'] == $row[1] && $options['e'] == $row[0] ) {
						} else {
							$syntrans["{$ctr}{$dot}"] = $syntransRow;
							$ctr++;
						}
					} else {
						$syntrans["{$ctr}{$dot}"] = $syntransRow;
						$ctr++;
					}
				}

				if ( $options['part'] == 'trans' and $options['lang'] != $row[1] ) {
					$syntrans["{$ctr}{$dot}"] = $syntransRow;
					$ctr++;
				}
			} else {
				if ( isset( $options['lang'] ) && isset( $options['e'] ) ) {
					// skip the expression for the language id
					if ( $options['lang'] == $row[1] && $options['e'] == $row[0] ) {
					} else {
						$syntrans["{$ctr}{$dot}"] = $syntransRow;
						$ctr++;
					}
				} else {
					$syntrans["{$ctr}{$dot}"] = $syntransRow;
					$ctr++;
				}
			}
		}

		return $this->returns( $syntrans, [ 'error' => 'no result' ] );
	}

	protected function returns( $returning, $else ) {
		if ( $returning ) {
			return $returning;
		}
		return $else;
	}
}

/** getSynonymAndTranslation function
 *
 * @param int $definedMeaningId req'd
 * @param int|null $excludeSyntransId opt'l
 *
 * returns an array of the following:
 * - spelling
 * - language_id
 * - identical_meaning
 *
 * returns null if not exist
 */
function getSynonymAndTranslation( $definedMeaningId, $excludeSyntransId = null ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );

	$result = $dbr->select(
		[
			'e' => "{$dc}_expression",
			'st' => "{$dc}_syntrans"
		],
		[
			'spelling',
			'language_id',
			'identical_meaning',
			'syntrans_sid'
		],
		[
			'defined_meaning_id' => $definedMeaningId,
			'st.expression_id = e.expression_id',
			'st.remove_transaction_id' => null

		], __METHOD__,
		[
			'ORDER BY' => [
				'identical_meaning DESC',
				'language_id',
				'spelling'
			]
		]
	);

	foreach ( $result as $row ) {
		$syntrans[] = [
			0 => $row->spelling,
			1 => $row->language_id,
			2 => $row->identical_meaning,
			3 => $row->syntrans_sid
		];
	}

	if ( $syntrans ) {
		return $syntrans;
	}
	return null;
}
