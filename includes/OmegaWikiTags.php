<?php
// OmegaWiki Tags
// Created November 18, 2013

class OmegaWikiTags {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'ow_stats', [ self::class, 'owStatsTag' ] );
	}

	public static function owStatsTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		$result = '';
		foreach ( $args as $name => $value ) {
			if ( $name == 'exp' ) {
				$result = owExpStats( $input );
			}
			if ( $name == 'dm' ) {
				$result = owDefinedMeaningStats( $input );
			}
			if ( $name == 'lang' ) {
				$result = wldLanguageStats( $input );
			}
		}
		return $result;
	}

	public static function owExpStats( $input ) {
		$cache = new CacheHelper();

		$cache->setCacheKey( [ 'ow_stats_exp' ] );
		$number = $cache->getCachedValue( function () {
			$Expressions = new Expressions;
			return $Expressions->getNumberOfExpressions();
		} );
		$cache->setExpiry( 86400 );
		$cache->saveCache();

		$number = preg_replace( '/\D $/', '', "$number " );
		return htmlspecialchars( $number . $input );
	}

	public static function owDefinedMeaningStats( $input ) {
		$cache = new CacheHelper();

		$cache->setCacheKey( [ 'ow_stats_dm' ] );
		$number = $cache->getCachedValue( function () {
			return getNumberOfDefinedMeanings();
		} );
		$cache->setExpiry( 86400 );
		$cache->saveCache();

		$number = preg_replace( '/\D $/', '', "$number " );
		return htmlspecialchars( $number . $input );
	}

	public static function wldLanguageStats( $input ) {
		$cache = new CacheHelper();

		$cache->setCacheKey( [ 'wld_stats_lang' ] );
		$number = $cache->getCachedValue( function () {
			return getNumberOfLanguages();
		} );
		$cache->setExpiry( 86400 );
		$cache->saveCache();

		$number = preg_replace( '/\D $/', '', "$number " );
		return htmlspecialchars( $number . $input );
	}

	/**
	 * returns the total number of "Defined Meaning Ids"
	 *
	 */
	public static function getNumberOfDefinedMeanings() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$nbdm = $dbr->selectField(
			"{$dc}_syntrans",
			'COUNT(DISTINCT defined_meaning_id)',
			[ 'remove_transaction_id' => null ],
			__METHOD__
		);
		return $nbdm;
	}

	/**
	 * returns the total number of "Languages"
	 *
	 */
	public static function getNumberOfLanguages() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$nbdm = $dbr->selectField(
			"{$dc}_expression",
			'COUNT(DISTINCT language_id)',
			[ 'remove_transaction_id' => null ],
			__METHOD__
		);

		return $nbdm;
	}
} // Class OmegaWikiTags

