<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

require_once "Wikidata.php";
require_once "WikiDataGlobals.php";

/** @file
 * @brief Creates a list of option_ids and spellings separated by a
 * semicolon via parameters options attribute id and attribute object id.
 */
class SpecialSelect extends SpecialPage {
	function SpecialSelect() {
		parent::__construct( 'Select', 'UnlistedSpecialPage' );
	}

	/** Execute the Special Page Select
	 */
	function execute( $par ) {
		require_once "OmegaWikiDatabaseAPI.php";
		require_once 'Transaction.php';
		global $wgOut, $wgRequest;

		$wgOut->disable();

		$optionAttribute = $wgRequest->getVal( WLD_OPTION_ATTRIBUTE );
		$attributeObject = $wgRequest->getVal( 'attribute-object', 0 );

		$objectLanguage = 0;
		if ( $attributeObject != 0 ) {
			$objectLanguage = OwDatabaseAPI::getLanguageIdForSid( $attributeObject );
			// language is not always defined, for example for a DM Option Attribute
			if ( !$objectLanguage ) {
				$objectLanguage = 0;
			}
		}

		$optionRes = OwDatabaseAPI::getOptionAttributeOptionsOptionIdForAttributeId( $optionAttribute, [ $objectLanguage, 0 ], 'multiple' );

		$optionsString = '';
		$optionsArray = [];
		foreach ( $optionRes as $optionsRow ) {
			$spellingRow = null;
			// find the user's expression spelling, if none found, use English.
			$spellingRow = OwDatabaseAPI::getDefinedMeaningSpellingForUserLanguage( $optionsRow->option_mid );

			$optionsArray[$optionsRow->option_id] = $spellingRow;
		}

		asort( $optionsArray );
		foreach ( $optionsArray as $option_id => $spelling ) {
			if ( $optionsString != '' ) {
				$optionsString .= "\n";
			}
			$optionsString .= $option_id . ';' . $spelling;
		}

	echo $optionsString;
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
