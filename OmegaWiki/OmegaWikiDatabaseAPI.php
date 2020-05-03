<?php
/** @file
 *
 * @brief Contains class OwDatabaseAPI.
 *
 * Future access point for the OmegaWiki PHP API.
 *
 * @todo In the future, WikidataAPI.php functions must be slowly
 * transferred to their respective classes and then accessed here.
 */

/** @class OwDatabaseAPI
 *
 * @brief This is the unified PHP API class to access the
 * WikiLexical OmegaWiki database.
 *
 * @see OwDBAPILanguage.php for Language functions
 */
class OwDatabaseAPI {

	/** @var Attributes|null */
	private $Attributes;
	/** @var DefinedMeanings|null */
	private $DefinedMeaning;
	/** @var Expressions|null */
	private $Expression;
	/** @var WLDLanguage|null */
	private $Language;
	/** @var Syntrans|null */
	private $Syntrans;
	/** @var Transactions|null */
	private $Transaction;
	/** @var OmegaWikiDataBase|null */
	private $OmegaWiki;

	public function __construct() {
	}

	/** @addtogroup OwDbAPIcomFn OwDatabaseAPI's Common database functions
	 *	 @{
	 */

	/**
	 * @param string $table table name
	 * @param string $column column name
	 * @param mixed $value
	 * @param int $isDc if has DataSet Context
	 * @return mixed|null value of column, or null if not found
	 */
	public static function verifyColumn( $table, $column, $value, $isDc ) {
		$api = new OwDatabaseAPI;
		$dc = null;
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		}
		$api->settings( 'omegawiki', $dc );
		return $api->OmegaWiki->verifyColumn( $table, $column, $value, $isDc );
	}

	/*! @} group OwDbAPIcomFn ends here.*/

	/** @addtogroup OwDbAPIeFn OwDatabaseAPI's Expression functions
	 *	 @{
	 */

	/** @brief creates a new Expression entry.
	 *
	 * @param string $spelling req'd
	 * @param int $languageId req'd
	 * @param array $options opt'l
	 *
	 * 	options:
	 * 		updateId int Inserts a transaction id instead of the updated one.
	 * 		dc       str The data set
	 *
	 * @see Expressions::createId.
	 */
	public static function createExpressionId( $spelling, $languageId, $options = [] ) {
		$api = new OwDatabaseAPI;
		$dc = null;
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		}
		$api->settings( 'expression', $dc );
		return $api->Expression->createId( $spelling, $languageId, $options );
	}

	/** @brief Returns the expressionId corresponding to $spelling and $languageId
	 *
	 * @return string The expressionId
	 * @return null if not exist
	 * @see OwDatabaseAPI::getTheExpressionId
	 */
	public static function getExpressionId( $spelling, $languageId, $options = [] ) {
		return self::getTheExpressionId( $spelling, $languageId, $options );
	}

	/** @brief Returns the expression->expression_id corresponding to a $spelling and
	 *  also returns the corresponding expression->languageId (the first found in the DB)
	 *
	 * @return array array( expessionId, languageId )
	 * @return null  if not exist
	 * @see OwDatabaseAPI::getTheExpressionId
	 */
	public static function getExpressionIdAnyLanguage( $spelling, $options = [] ) {
		return self::getTheExpressionId( $spelling, null, $options );
	}

	/** @brief returns a list of DefinedMeaning ids
	 *
	 * @return array list of defined meaning ids.
	 * @return array if empty, an empty array.
	 * @see OwDatabaseAPI::getExpressionMeaningIds
	 */
	public static function getExpressionMeaningIds( $spelling, $options = [] ) {
		return self::getTheExpressionMeaningIds( $spelling, null, $options );
	}

	/** @brief returns a list of DefinedMeaning ids for languages contained in the array languageIds
	 *
	 * @return array list of defined meaning ids.
	 * @return array if empty, an empty array.
	 * @see OwDatabaseAPI::getExpressionMeaningIds
	 */
	public static function getExpressionMeaningIdsForLanguages( $spelling, $languageIds, $options = [] ) {
		return self::getTheExpressionMeaningIds( $spelling, $languageIds, $options );
	}

	/** @brief the core getExpression function
	 *
	 * @param string $spelling req'd
	 * @param int|null $languageId opt'l
	 * @param array $options opt'l
	 *
	 * @return string expression id for the languageId indicated.
	 * @return array The first expressionId/languageId [array( expessionId, languageId )] when languageId is skipped.
	 * 	options:
	 * 		dc           str The data set
	 *
	 * @see Expressions::getId.
	 */
	public static function getTheExpressionId( $spelling, $languageId = null, $options = [] ) {
		$api = new OwDatabaseAPI;
		$dc = null;
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		}
		$api->settings( 'expression', $dc );
		return $api->Expression->getId( $spelling, $languageId, $options );
	}

	/** @brief the core getMeaningIds function
	 *
	 * @param string $spelling req'd
	 * @param array $languageIds opt'l
	 * @param array $options opt'l
	 *
	 * @return array list of defined meaning ids.
	 * @return array if not exists, an empty array.
	 * 	options:
	 * 		dc           str The data set
	 *
	 * @see Expressions::getMeaningIds.
	 */
	public static function getTheExpressionMeaningIds( $spelling, $languageIds, $options ) {
		$api = new OwDatabaseAPI;
		$dc = null;
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		}
		$api->settings( 'expression', $dc );
		return $api->Expression->getMeaningIds( $spelling, $languageIds, $options );
	}

	/*! @} group OwDbAPIeFn ends here.*/

	/** @addtogroup OwDbAPIdmFn OwDatabaseAPI's Defined Meaning functions
	 *	 @{
	 */

	/** @brief Returns the spelling of an expression used as
	 * the definedMeaning namespace of a given DM
	 *
	 * @param int $definedMeaningId
	 * @param string|null $dc
	 *
	 * @return string expression
	 * @return if not exists, null
	 *
	 * @see DefinedMeanings::definingExpression
	 */
	public static function definingExpression( $definedMeaningId, $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->definingExpression( $definedMeaningId, $api->dc );
	}

	/**
	 * @brief Returns one spelling of an expression corresponding to a given DM
	 * 	- in a given language if it exists
	 * 	- or else in English
	 * 	- or else in any language
	 *
	 * @param int $definedMeaningId
	 * @return string expression
	 *
	 * @see DefinedMeanings::getExpression
	 */
	public static function getDefinedMeaningExpression( $definedMeaningId, $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getExpression( $definedMeaningId, $api->dc );
	}

	/** @brief Returns one spelling of an expression corresponding to a given DM in a given language
	 *
	 * @param int $definedMeaningId
	 * @param int $languageId
	 * @return string spelling
	 * @return if not exists, ""
	 *
	 * @see DefinedMeanings::getExpressionForLanguage
	 */
	public static function getDefinedMeaningExpressionForLanguage( $definedMeaningId, $languageId, $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getExpressionForLanguage( $definedMeaningId, $languageId, $api->dc );
	}

	/** @brief Returns one spelling of an expression corresponding to a given DM in any language
	 *
	 * @param int $definedMeaningId
	 * @return string spelling
	 * @return if not exists, ""
	 *
	 * @see DefinedMeanings::getExpressionForAnyLanguage instead.
	 */
	public static function getDefinedMeaningExpressionForAnyLanguage( $definedMeaningId, $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getExpressionForLanguage( $definedMeaningId, $api->dc );
	}

	/** @brief spelling via the defined meaning and/or language id
	 * @return spelling empty string if not exists
	 * @see uses DefinedMeanings::getSpelling
	 */
	public static function getDefinedMeaningSpelling( $definedMeaningId, $languageId = null, $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getSpelling( $definedMeaningId, $languageId, $api->dc );
	}

	/** @brief returns a spelling that is one of the possible translations of a given DM
	 * in any language
	 */
	public static function getDefinedMeaningSpellingForAnyLanguage( $definedMeaningId, $dc = null ) {
		return self::getDefinedMeaningSpelling( $definedMeaningId, $dc );
	}

	/** @brief a spelling that is one of the possible translations of a given DM
	 * in a given language
	 */
	public static function getDefinedMeaningSpellingForLanguage( $definedMeaningId, $language, $dc = null ) {
		return self::getDefinedMeaningSpelling( $definedMeaningId, $language );
	}

	/** @brief a spelling that is one of the possible translations of a given DM
	 * in a given language
	 */
	public static function getDefinedMeaningSpellingForUserLanguage( $definedMeaningId, $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getSpellingForUserLanguage( $definedMeaningId, $api->dc );
	}

	/**
	 * @brief Returns the defined_meaning table's DefinedMeaning id via translatedContentId
	 *
	 * @param int $translatedContentId req'd The object id
	 * @param array $options Optional parameters
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return array( int meaning1_id, int relationtype_mid, int meaning2_mid )
	 * @return if not exists, array()
	 *
	 * @see DefinedMeanings::getTranslatedContentIdDefinedMeaningId, for a list of options.
	 */
	public static function getTranslatedContentIdDefinedMeaningId( $translatedContentId, $options = [], $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getTranslatedContentIdDefinedMeaningId( $translatedContentId, $options, $api->dc );
	}

	public static function verifyDefinedMeaningId( $definedMeaningId ) {
		return self::verifyColumn( 'defined_meaning', 'defined_meaning_id', $definedMeaningId, 1 );
	}

	/*! @} group OwDbAPIdmFn ends here.*/

	/** @addtogroup OwDbAPIlangFn OwDatabaseAPI's language functions
	 *	 @{
	 */

	/**
	 * Returns a SQL query string for fetching language names in a given language.
	 * @param string $lang_code the language in which to retrieve the language names
	 * @param int[] $lang_subset an array in the form ( 85, 89, ...) that restricts the language_id that are returned
	 * this array can be generated with ViewInformation->getFilterLanguageList() according to user preferences
	 * @return array
	 *
	 * @see WLDLanguage::getParametersForNames
	 */
	public static function getParametersForLanguageNames( $lang_code, $lang_subset = [] ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'language' );
		return $api->Language->getParametersForNames( $lang_code, $lang_subset );
	}

	/** @brief returns the languageId
	 * @param array $options req'd
	 * 	- options:
	 * 		- sid   str return the language Id using the syntrans id
	 * 		- wmkey str return the language Id for the wikimedia key
	 * 		- dc    str the dataset prefix
	 * @see WLDLanguage::getId instead
	 */
	static function getLanguageId( $options ) {
		$api = new OwDatabaseAPI;
		$dc = null;
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		}
		$api->settings( 'language', $dc );
		$options['dc'] = $api->dc;
		return $api->Language->getId( $options );
	}

	/** @brief returns the language Id from syntrans Id
	 * @note uses OwDatabaseAPI::getLanguageId
	 */
	static function getLanguageIdForSid( $syntransId, $dc = null ) {
		return self::getLanguageId( [ 'sid' => $syntransId, 'dc' => $dc ] );
	}

	/**
	 * @brief returns the User Language Id
	 *
	 * @return language id
	 * @return if not exist, null
	 */
	public static function getUserLanguageId() {
		global $wgLang;
		$userLanguageId = getLanguageIdForCode( $wgLang->getCode() );
		if ( !$userLanguageId ) {
			$code = self::getLanguageCodeForIso639_3( $wgLang->getCode() );
			$userLanguageId = getLanguageIdForCode( $code );
		}
		return $userLanguageId;
	}

	/**
	 * @brief returns the User Language Code
	 *
	 * @return language (wikimedia) code
	 * @return if not exist, null
	 *
	 * @todo refactor this in the future with getUserLanguageCode, as suggested
	 * 	by Kip. ~he
	 */
	public static function getUserLanguage() {
		global $wgLang;
		if ( !getLanguageIdForCode( $wgLang->getCode() ) ) {
			$userLanguage = self::getLanguageCodeForIso639_3( $wgLang->getCode() );
		} else {
			$userLanguage = $wgLang->getCode();
		}
		return $userLanguage;
	}

	/**
	 * @param bool $purge purge cache
	 * @param string|null $code the language code
	 *
	 * @return an array containing all language names translated into the language
	 * 	indicated by $code ( if it exists ), with a fallback in English where the language
	 * 	names aren't present in that language.
	 * @return In case $code is not given, an array of language names for the
	 * 	user's language preference is given, with a fallback in English where the language
	 * 	names aren't present in that language.
	 *
	 * @see WLDLanguage::getNames
	 * @todo Should we change the name to getLanguageNames instead? ~he
	 */
	static function getOwLanguageNames( $purge = false, $code = null ) {
		static $owLanguageNames = null;
		if ( $owLanguageNames === null && !$purge ) {
			// if code is not given, get user Language.
			if ( !$code ) {
				$code = self::getUserLanguage();
			}
			$api = new OwDatabaseAPI;
			$api->settings( 'language' );
			return $api->Language->getNames( $code );
		}
		return $owLanguageNames;
	}

	/**
	 * @param int $iso639_3 OmegaWiki's improvised iso
	 * @return the wikimedia code corresponding to the iso639_3 $code
	 * @see OwDatabaseAPI::getCodeForIso639_3
	 */
	static function getLanguageCodeForIso639_3( $iso639_3 ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'language' );
		return $api->Language->getCodeForIso639_3( $iso639_3 );
	}

	/*! @} group OwDbAPIlangFn ends here.*/

	/** @addtogroup OwDbAPIrelAttFn OwDatabaseAPI's relations Attribute functions
	 *	 @{
	 */

	/**
	 * @brief Returns the meaning_relations table's details via relation_id
	 *
	 * @param int $relationId req'd The object id
	 * @param array $options Optional parameters
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return array( int defined_meaning_id )
	 * @return if not exists, array()
	 *
	 * @see Attributes::getRelationIdRelation, for a list of options.
	 */
	public static function getRelationIdRelationAttribute( $relationId, $options = [], $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'attributes', $dc );
		return $api->Attributes->getRelationIdRelation( $relationId, $options, $api->dc );
	}

	/*! @} group OwDbAPIrelAttFn ends here.*/

	/** @addtogroup OwDbAPIoptAttFn OwDatabaseAPI's options Attribute functions
	 *	 @{
	 */

	/** @brief getOptionsAttributeOption Template
	 * @param int $attributeId req'd
	 * @param int|null $optionMeaningId opt'l
	 * @param int|int[] $languageId req'd
	 * @param string|null $option opt'l
	 * 	- multiple multiple lines
	 * 	- exists   returns boolean, depending whether the queried values exists or not.
	 * @see uses Attributes::getOptionAttributeOptions.
	 */
	public static function getOptionAttributeOptions( $attributeId, $optionMeaningId = null, $languageId, $option = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'attributes' );
		return $api->Attributes->getOptionAttributeOptions( $attributeId, $optionMeaningId, $languageId, $option );
	}

	/** @return option id. If not exists, returns null.
	 */
	public static function getOptionAttributeOptionsOptionId( $attributeId, $optionMeaningId, $languageId ) {
		return self::getOptionAttributeOptions( $attributeId, $optionMeaningId, $languageId );
	}

	/** @return an array of option_id and option_mid via the attribute id. If not exists, returns null.
	 */
	public static function getOptionAttributeOptionsOptionIdForAttributeId( $attributeId, $languageId, $options ) {
		return self::getOptionAttributeOptions( $attributeId, null, $languageId, $options );
	}

	/** @return bool
	 */
	public static function optionAttributeOptionExists( $attributeId, $optionMeaningId, $languageId ) {
		return self::getOptionAttributeOptions( $attributeId, $optionMeaningId, $languageId, 'exists' );
	}

	/*! @} group OwDbAPIoptAttFn ends here.*/

	/** @addtogroup OwDbAPIsyntFn OwDatabaseAPI's Syntrans functions
	 *	 @{
	 */

	/** @brief adds Syntrans
	 * @param string $spelling req'd The expression
	 * @param int $languageId req'd The language Id
	 * @param int $definedMeaningId req'd The defined Meaning Id of the concept
	 * @param string $identicalMeaning req'd If the word has an identical meaning or not
	 * 	to the concept. 'true' or 'false' only.
	 * @param array $options opt'l
	 * @see Expressions::createId for options.
	 *
	 * @see Syntrans::add
	 */
	public static function addSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning, $options = [] ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'syntrans' );
		return $api->Syntrans->add( $spelling, $languageId, $definedMeaningId, $identicalMeaning, $options );

		$expression = findOrCreateExpression( $spelling, $languageId, $options );
		$expression->assureIsBoundToDefinedMeaning( $definedMeaningId, $identicalMeaning );
	}

	/**
	 * @param int $syntransId req'd The syntrans id
	 * @param array $options Optional parameters
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return array( str spelling, int defined_meaning_id )
	 * @return if not exists, array()
	 *
	 * @see Syntrans::getSpellingWithDM, for a list of options.
	 */
	public static function getSyntransSpellingWithDM( $syntransId, $options = [], $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'syntrans', $dc );
		return $api->Syntrans->getSpellingWithDM( $syntransId, $options, $api->dc );
	}

	/**
	 * @param int $definedMeaningId req'd The defined meaning id
	 * @param int $languageId req'd language id
	 * @param string $spelling req'd The Expression
	 *
	 * @return array( str spelling, int language_id, int identical_meaning )
	 * @return if not exists, null
	 *
	 * @see Syntrans::getAllSynonymAndTranslation
	 */
	public static function getSynonyms( $definedMeaningId, $languageId, $spelling ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'syntrans' );
		$options = [
			'language_id' => $languageId,
			'scope' => 'syn',
			'spelling' => $spelling
		];
		return $api->Syntrans->getAllSynonymAndTranslation( $definedMeaningId, $options );
	}

	/*! @} group OwDbAPIsyntFn ends here.*/

	/** @addtogroup OwDbAPItransactFn OwDatabaseAPI's Transactions functions
	 *	 @{
	 */

	/**
	 * @param int $transactionId req'd The transaction id
	 * @param array $options Optional parameters
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return array( int user_id, str user_ip, str timestamp, str comment )
	 * @return if not exists, array()
	 *
	 * @see Transactions::getIdDetails, for a list of options.
	 */
	public static function getTransactionIdDetails( $transactionId, $options = [], $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'transaction', $dc );
		return $api->Transaction->getIdDetails( $transactionId, $options, $api->dc );
	}

	/**
	 * @param int $languageId req'd The language id
	 * @param array $options Optional parameters
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return array( int user_id, str user_ip, str timestamp, str comment )
	 * @return if not exists, array()
	 *
	 * @see Transactions::getLanguageIdLatestTransactionId, for a list of options.
	 */
	public static function getLanguageIdLatestTransactionId( $languageId, $options = [], $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'transaction', $dc );
		return $api->Transaction->getLanguageIdLatestTransactionId( $languageId, $options, $api->dc );
	}

	/*! @} group OwDbAPItransactFn ends here.*/

	/**
	 * @brief sets the initial settings for static functions
	 *
	 * @param string $class req'd The database class to access
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 */
	protected function settings( $class, $dc = null ) {
		$this->getDc( $dc );

		switch ( $class ) {
			case 'attributes':
				require_once 'Attribute.php';
				$this->Attributes = new Attributes;
				break;
			case 'definedMeaning':
				require_once 'DefinedMeaning.php';
				$this->DefinedMeaning = new DefinedMeanings;
				break;
			case 'expression':
				require_once 'Expression.php';
				$this->Expression = new Expressions;
				break;
			case 'language':
				require_once 'languages.php';
				$this->Language = new WLDLanguage;
				break;
			case 'syntrans':
				require_once 'WikiDataAPI.php';
				$this->Syntrans = new Syntrans;
				break;
			case 'transaction':
				require_once 'Transaction.php';
				$this->Transaction = new Transactions;
				break;
			case 'omegawiki':
				$this->OmegaWiki = new OmegaWikiDataBase;
				break;
		}
	}

	/**
	 * @brief sets the dc.
	 * @return string|null $dc
	 */
	protected function getDc( $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$this->dc = $dc;
	}

}

/** @class Syntrans
 *
 * @brief PHP API class for Syntrans
 */
class Syntrans {

	public function __construct() {
		require_once 'OmegaWikiDatabaseAPI.php';
	}

	/** @brief adds Syntrans
	 * @param string $spelling req'd The expression
	 * @param int $languageId req'd The language Id
	 * @param int $definedMeaningId req'd The defined Meaning Id of the concept
	 * @param string $identicalMeaning req'd If the word has an identical meaning or not
	 * 	to the concept. 'true' or 'false' only.
	 * @param array $options opt'l
	 * @see Expressions::createId for options.
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::addSynonymOrTranslation instead.
	 */
	public static function add( $spelling, $languageId, $definedMeaningId, $identicalMeaning, $options = [] ) {
		$expression = findOrCreateExpression( $spelling, $languageId, $options );
		$expression->assureIsBoundToDefinedMeaning( $definedMeaningId, $identicalMeaning );
	}

	/** @brief added checks before adding Syntrans. Useful for APIs.
	 *
	 * @return an array of result or warning.
	 * @todo Future implemetation. Use this function in connection with
	 * 	MediaWiki/WikiLexicalData API's owAddSyntrans.php
	 */
	public static function addWithNotes( $spelling, $languageId, $definedMeaningId, $identicalMeaning, $options = [] ) {
		global $wgUser;
		$dc = wdGetDataSetContext();

		// set test status
		if ( !isset( $options['test'] ) ) {
			$options['test'] = false;
		}
		// check that the language_id exists
		if ( !verifyLanguageId( $languageId ) ) {
			return [
				'WARNING' => 'Non existent language id (' . $languageId . ').'
			];
		}

		// check that defined_meaning_id exists
		if ( !verifyDefinedMeaningId( $definedMeaningId ) ) {
			return [
				'WARNING' => 'Non existent dm id (' . $definedMeaningId . ').'
			];
		}

		if ( $identicalMeaning === true ) {
			$identicalMeaning = 1;
		}
		if ( $identicalMeaning === false ) {
			$identicalMeaning = 0;
		}
		if ( $identicalMeaning === 1 ) {
			$identicalMeaningStr = "true";
			if ( $options['ver'] == '1' ) {
				$identicalMeaning = "true";
			}
		} else {
			$identicalMeaningStr = "false";
			$identicalMeaning = 0;
			if ( $options['ver'] == '1' ) {
				$identicalMeaning = "false";
			}
		}

		if ( !isset( $options['addedBy'] ) ) { // assumed used by API
			$addedBy = 'API function add_syntrans';
		} else {
			$addedBy = $options['addedBy'];
		}

		// first check if it exists, then create the transaction and put it in db
		$expression = findExpression( $spelling, $languageId, $options );
		$concept = getDefinedMeaningSpellingForLanguage( $definedMeaningId, WLD_ENGLISH_LANG_ID );
		if ( $expression ) {
			// the expression exists, check if it has this syntrans
			$bound = expressionIsBoundToDefinedMeaning( $definedMeaningId, $expression->id );
			if ( $bound == true ) {
				$synonymId = getSynonymId( $definedMeaningId, $expression->id );
				$note = [
					'status' => 'exists',
					'in' => $concept . ' DM(' . $definedMeaningId . ')',
					'sid' => $synonymId,
					'e' => $spelling,
					'langid' => $languageId,
					'dm' => $definedMeaningId,
					'im' => $identicalMeaning
				];
				if ( $options['test'] ) {
					$note['note'] = 'test run only';
				}

				return $note;
			}
		}

		// adding the expression
		$expressionId = getExpressionId( $spelling, $languageId );
		$synonymId = getSynonymId( $definedMeaningId, $expressionId );
		$note = [
			'status' => 'added',
			'to' => $concept . ' DM(' . $definedMeaningId . ')',
			'sid' => $synonymId,
			'e' => $spelling,
			'langid' => $languageId,
			'dm' => $definedMeaningId,
			'im' => $identicalMeaning
		];

		// safety net.
		if ( !isset( $options['transacted'] ) ) {
			$options['transacted'] = false;
		}
		if ( !isset( $options['updateId'] ) ) {
			$options['updateId'] = -1;
		}
		if ( !isset( $options['tid'] ) ) {
			$options['tid'] = -1;
		}
		if ( !isset( $options['test'] ) ) {
			$options['test'] = false;
		}

		// add note['tid'] from $options['tid'] (transaction id), if null, get value
		// from $this->options['updateId'].
		if ( $options['ver'] == '1.1' ) {
			if ( $options['tid'] ) {
				$note['tid'] = $options['tid'];
			} else {
				$note['tid'] = $options['updateId'];
			}
		}

		if ( !$options['test'] ) {
			if ( !$options['transacted'] ) {
				$note['transacted'] = true; // @todo when used in owAddSyntrans.php, kindly use this to switch $this->transacted to true.
				$tid = startNewTransaction( $wgUser->getID(), "0.0.0.0", 'Added using ' . $addedBy, $dc );
				if ( $options['ver'] == '1.1' ) {
					$note['tid'] = $tid;
				}
			}
			OwDatabaseAPI::addSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaningStr, $options );
			$synonymId = getSynonymId( $definedMeaningId, $expressionId );
			$note['sidd'] = $synonymId;
		} else {
			$note['note'] = 'test run only';
		}

		return $note;
	}

	/**
	 * @param int $syntransId req'd The syntrans id
	 * @param array $options Optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return if exist, array( str spelling, int defined_meaning_id )
	 * @return if not, array()
	 *
	 * @note options parameter can be used to extend this function.
	 * Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getSyntransSpellingWithDM instead.
	 * Also note that this function currently includes all data, even removed ones.
	 */
	public static function getSpellingWithDM( $syntransId, $options = [], $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$test = false;
		if ( isset( $options['test'] ) ) {
			$test = true;
		}

		$syntrans = $dbr->selectRow(
			[
				'synt' => "{$dc}_syntrans",
				'exp' => "{$dc}_expression",
			],
			[
				'spelling',
				'defined_meaning_id'
			],
			[
				'syntrans_sid' => $syntransId,
				"synt.expression_id = exp.expression_id"
			], __METHOD__
		);

		if ( $syntrans ) {
			if ( $test ) {
				var_dump( $syntrans );
				die;
			}
			return $syntrans;
		}
		if ( $test ) {
			echo 'array()';
			die;
		}
		return [];
	}

	/** @brief core get syntrans function
	 *
	 * @param int $definedMeaningId req'd
	 * @param int $options opt'l
	 *
	 * @return array( str spelling, int language_id, int identical_meaning )
	 * @return if not exists, null
	 */
	public static function getAllSynonymAndTranslation( $definedMeaningId, $options ) {
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		} else {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$opt = [
			'defined_meaning_id' => $definedMeaningId,
			'st.expression_id = e.expression_id',
			'st.remove_transaction_id' => null
		];

		if ( isset( $options['language_id'] ) ) {
			$languageId = $options['language_id'];
		}

		if ( isset( $options['spelling'] ) ) {
			$spelling = $options['spelling'];
		}

		if ( isset( $options['scope'] ) ) {
			switch ( $options['scope'] ) {
				case 'syn':
				$opt['language_id'] = $languageId;
				$opt[] = 'spelling not in (' . "'" . $spelling . "'" . ')';
				break;
			}
		}

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
			$opt, __METHOD__,
			[
				'ORDER BY' => [
					'identical_meaning DESC',
					'language_id',
					'spelling'
				]
			]
		);

		$syntrans = [];
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

}
