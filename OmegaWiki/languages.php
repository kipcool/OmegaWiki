<?php
/** @file
 * @todo create a language class for OwDatabaseAPI class
 */
require_once 'WikiDataGlobals.php';

/** @brief PHP API class for WikiLexicalData Extension's Language
 */
class WLDLanguage {

	function __construct() {
		require_once 'WikiDataGlobals.php';
	}

	/** @brief returns the languageId
	 * @param array $options req'd
	 * 	- options:
	 * 		- sid   str return the language Id using the syntrans id
	 * 		- wmkey str return the language Id for the wikimedia key
	 * 		- dc    str the dataset prefix
	 * @see use OwDatabaseAPI::getLanguageId instead
	 */
	static function getId( $options ) {
		$dbr = wfGetDB( DB_REPLICA );

		$vars = 'language_id';

		if ( isset( $options['wmkey'] ) ) {
			$tables = 'language';
			$conds = [ 'wikimedia_key' => $options['wmkey'] ];
		}

		if ( isset( $options['sid'] ) ) {
			$tables = [
				'synt' => $options['dc'] . '_syntrans',
				'exp' => $options['dc'] . '_expression'
			];
			$conds = [
				'synt.syntrans_sid' => $options['sid'],
				'synt.remove_transaction_id' => null,
				'exp.expression_id = synt.expression_id',
				'exp.remove_transaction_id' => null
			];
		}

		$query = [ $tables, $vars, $conds, __METHOD__ ];

		$languageId = $dbr->selectField( $tables, $vars, $conds, __METHOD__ );

		if ( $languageId ) {
			return $languageId;
		}
		return null;
	}

	/* @return Return an array containing all language names translated into the language
	 *	indicated by $code, with fallbacks in English where the language names
	 *	aren't present in that language.
	 * @see use OwDatabaseAPI::getOwLanguageNames instead
	 */
	static function getNames( $code ) {
		$dbr = wfGetDB( DB_REPLICA );
		$names = [];
		list( $table, $vars, $conds, $join_conds ) = self::getParametersForNames( $code );
		$lang_res = $dbr->select(
			$table, $vars, $conds, __METHOD__, null, $join_conds
		);
		while ( $lang_row = $dbr->fetchObject( $lang_res ) ) {
			$names[$lang_row->row_id] = $lang_row->language_name;
		}
		return $names;
	}

	/**
	 * @param int $iso639_3 OmegaWiki's improvised iso
	 * @return the wikimedia code corresponding to the iso639_3 $code
	 * @see use OwDatabaseAPI::getLanguageCodeForIso639_3 instead
	 */
	static function getCodeForIso639_3( $iso639_3 ) {
		$dbr = wfGetDB( DB_REPLICA );
		$wikimediaKey = $dbr->selectField(
			'language',
			'wikimedia_key',
			[
				"iso639_3 LIKE '$iso639_3%'",
				'wikimedia_key <> ' . "''"
			], __METHOD__
		);

		if ( $wikimediaKey ) {
			return $wikimediaKey;
		}
		return null;
	}

	/**
	 * Returns the SQL parameters needed for fetching language names in a given language.
	 * @param string $langCode the language in which to retrieve the language names
	 * @param int[] $lang_subset an array in the form ( 85, 89, ...) that restricts the language_id that are returned
	 * this array can be generated with ViewInformation->getFilterLanguageList() according to user preferences
	 * @return array
	 */
	static function getParametersForNames( $langCode, $lang_subset = [] ) {
		/* Use a simpler query if the user's language is English. */
		/* getLanguageIdForCode( 'en' ) = 85 */
		$dbr = wfGetDB( DB_REPLICA );
		$langId = getLanguageIdForCode( $langCode );

		if ( $langCode == WLD_ENGLISH_LANG_WMKEY || $langId === null ) {
			$cond = [ 'name_language_id' => WLD_ENGLISH_LANG_ID ];
			if ( !empty( $lang_subset ) ) {
				$cond['language_id'] = $lang_subset;
			}
			$table = 'language_names';
			$vars = [ 'row_id' => 'language_id', 'language_name' ];
			$join_conds = null;
		} else {
			/* Fall back on English in cases where a language name is not present in the
			user's preferred language. */
			$cond = [ 'eng.name_language_id' => WLD_ENGLISH_LANG_ID ];

			if ( !empty( $lang_subset ) ) {
				$cond['eng.language_id'] = $lang_subset;
			}

			$table = [ 'eng' => 'language_names', 'ln2' => 'language_names' ];
			$vars = [
				'row_id' => 'eng.language_id',
				'language_name' => 'COALESCE(ln2.language_name,eng.language_name)'
			];
			$join_conds = [ 'ln2' => [
				'LEFT JOIN', [
					'eng.language_id = ln2.language_id',
					'ln2.name_language_id' => $langId
				]
			] ];
		}

		return [ $table, $vars, $cond, $join_conds ];
	}

}

/**
 * @param bool $purge purge cache
 * @return array of language names for the user's language preference
 * @todo for deprecation, use OwDatabaseAPI::getOwLanguageNames instead
 */
function getOwLanguageNames( $purge = false ) {
	require_once 'OmegaWikiDatabaseAPI.php';
	return OwDatabaseAPI::getOwLanguageNames( $purge );
}

/* @return Return an array containing all language names translated into the language
 *	indicated by $code, with fallbacks in English where the language names
 *	aren't present in that language.
 * @todo for deprecation, use OwDatabaseAPI::getOwLanguageNames instead
 */
function getLangNames( $code ) {
	require_once 'OmegaWikiDatabaseAPI.php';
	return OwDatabaseAPI::getOwLanguageNames( null, $code );
}

function getLanguageIdForCode( $code ) {
	static $languages = null;
	if ( $languages === null ) {
		$dbr = wfGetDB( DB_REPLICA );
		$id_res = $dbr->select(
			'language',
			[ 'language_id', 'wikimedia_key' ],
			'', __METHOD__
		);
		foreach ( $id_res as $id_row ) {
			$languages[$id_row->wikimedia_key] = $id_row->language_id;
		}
	}
	if ( is_array( $languages ) && array_key_exists( $code, $languages ) ) {
		return $languages[$code];
	}
	return null;
}

/**
 * returns the language_id corresponding to the
 * iso639_3 $code
 *
 * @return int
 */
function getLanguageIdForIso639_3( $code ) {
	static $languages = null;

	if ( $languages === null ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			'language',
			[ 'language_id', 'iso639_3' ],
			'', __METHOD__
		);
		foreach ( $result as $row ) {
			$languages[$row->iso639_3] = $row->language_id;
		}
	}

	if ( is_array( $languages ) && array_key_exists( $code, $languages ) ) {
		return $languages[$code];
	}
	return null;
}

/**
 * returns the iso639_3 code corresponding to the
 * language_id $id
 */
function getLanguageIso639_3ForId( $id ) {
	static $languages = null;

	if ( $languages === null ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			'language',
			[ 'language_id', 'iso639_3' ],
			'', __METHOD__
		);
		foreach ( $result as $row ) {
			$languages['id' . $row->language_id] = $row->iso639_3;
		}
	}

	if ( is_array( $languages ) && array_key_exists( 'id' . $id, $languages ) ) {
		return $languages['id' . $id];
	}
	return null;
}

/**
 * returns the DM_id corresponding to the
 * iso639_3 $code, according to the collection $wgIso639_3CollectionId
 * null if not found
 */
function getDMIdForIso639_3( $code ) {
	global $wgIso639_3CollectionId;
	// should we use the static approach, as for the other functions?
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );

	$langdm = $dbr->selectField(
		"{$dc}_collection_contents",
		'member_mid',
		[
			'collection_id' => $wgIso639_3CollectionId,
			'internal_member_id' => $code,
			'remove_transaction_id' => null
		], __METHOD__
	);

	// langdm is false if not found
	if ( $langdm ) {
		return $langdm;
	}
	return null;
}

/* @return Return an array containing all language names translated into the language
 * Returns a SQL query string for fetching language names in a given language.
 * @param string $lang_code the language in which to retrieve the language names
 * @param int[] $lang_subset an array in the form ( 85, 89, ...) that restricts the language_id that are returned
 * this array can be generated with ViewInformation->getFilterLanguageList() according to user preferences
 *
 * @todo for deprecation, use OwDatabaseAPI::getSQLForLanguageNames instead
 */
function getSQLForLanguageNames( $lang_code, $lang_subset = [] ) {
	require_once 'OmegaWikiDatabaseAPI.php';
	return OwDatabaseAPI::getSQLForLanguageNames( $lang_code, $lang_subset );
}

function getLanguageIdLanguageNameFromIds( $languageId, $nameLanguageId ) {
	$dbr = wfGetDB( DB_REPLICA );

	$languageId = $dbr->selectField(
		'language_names',
		'language_name',
		[
			'language_id' => $languageId,
			'name_language_id' => $nameLanguageId
		], __METHOD__
	);

	if ( $languageId ) {
		return $languageId;
	}
	return null;
}

// Returns true or false
function LanguageIdExist( $languageId ) {
	$dbr = wfGetDB( DB_REPLICA );

	$languageId = $dbr->selectField(
		'language',
		'language_id',
		[
			'language_id' => $languageId
		], __METHOD__
	);

	if ( $languageId ) {
		return true;
	}
	return false;
}
