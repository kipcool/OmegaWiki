<?php

/** OmegaWiki API's Express class
 *
 * Returns an array of datas similar to the one given by the Expression Namespace.
 *
 * PURPOSE
 * To provide a way for Developers and/or contributors of Wiktionary
 * to access OmegaWiki's data that can be easily parsed.
 *
 * HISTORY
 * - 2014-03-07 cache enabled. displays dm_{$definedMeaningId} instead. dm understood as
 *		dmid.
 * - 2014-03-03 ver 1.1 displays dmid{$definedMeaningId} instead of ow_define_{$ctr}
 * - 2013-05-21 Creation Date ~ he
 *
 * TODO
 * - Improve definition by improving owDefine.php
 *		Need to make Class Define return output similar to what
 *		OmegaWiki outputs in the DefinedMeaning Namespace.
 * - Add param lang (optional). To set the primary language.
 * - Add param limit_lang (optional). To limit search to just a language or more.
 * - Add param options (optional). To limit the display to only
 *		data one is interested in. Example:
 *		& o p t i o n s = p i n y i n | I P A | h y p h e n a t i o n
 */

require_once 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php';
require_once 'owDefine.php';

class Express extends Define {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action, null );
	}

	public function execute() {
		// Get the parameters
		$params = $this->extractRequestParams();

		// Parameter checks
		if ( !isset( $params['search'] ) ) {
			$this->dieUsage( 'parameter search is missing', 'param search is missing' );
		}

		$spelling = $params['search'];

		$options = [];
		if ( isset( $params['ver'] ) ) {
			$options['ver'] = $params['ver'];
		} else {
			$options['ver'] = null;
		}

		// Check if spelling exist
		if ( existSpelling( $spelling ) ) {
			$express = $this->cacheExpress( $spelling, $options );
			$this->getResult()->addValue( null, $this->getModuleName(), $express );

		} else {
			$this->dieUsage( 'the search word does not exist.', 'non-existent spelling' );
		}

		return true;
	}

	/** Cache!
	 */
	protected function cacheExpress( $spelling, $options = [] ) {
		$expressCacheKey = 'API:ow_express:dm=' . $spelling;
		if ( isset( $options['ver'] ) ) {
			$expressCacheKey .= ":ver={$options['ver']}";
		}

		$cache = new CacheHelper();

		$cache->setCacheKey( [ $expressCacheKey ] );
		$express = $cache->getCachedValue(
			function ( $spelling, $options = [] ) {
				$dmlist = getExpressionMeaningIds( $spelling );
				$options['e'] = $spelling;
				// There are duplicates using getExpressionMeaningIds !!!
				$dmlist = array_unique( $dmlist );
				$express['expression'] = $spelling;
				$dmlistCtr = 1;
				foreach ( $dmlist as $dmrow ) {
					$defining = $this->definingForAnyLanguage( $dmrow, $options );
					foreach ( $defining as $definingRow ) {
						if ( !$options['ver'] ) {
							$express['ow_define_' . $dmlistCtr] = $definingRow;
						}
						if ( $options['ver'] == '1.1' ) {
							$express[ 'dm_' . $definingRow['dmid']] = $definingRow;
							unset( $express[ 'dmid' . $definingRow['dmid']]['dmid'] );
						}
					}
					$dmlistCtr += 1;
				}
				return $express;
			}, [ $spelling, $options ]
		);
		$cache->setExpiry( 10800 ); // 3 hours
		$cache->saveCache();

		return $express;
	}

	// Parameters.
	public function getAllowedParams() {
		return [
			'search' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'ver' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	// Get examples
	public function getExamples() {
		return [
			'The following examples return information about the concept and definition of the given expression.',
			'api.php?action=ow_express&search=acusar&format=xml',
			'api.php?action=ow_express&search=pig&format=xml',
			'api.php?action=ow_express&search=咱&format=xml',
			'',
			'Version 1.1 returns information that is better for a javascript client. To use this, just add ver=1.1',
			'api.php?action=ow_express&search=acusar&format=xml&ver=1.1',
		];
	}

}
