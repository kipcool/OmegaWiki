<?php

// global variables should be named $wgWldVariable
// where Wld stands for "WikiLexicalData".
// TODO: rename the other variables

define( 'NS_EXPRESSION', 16 );
define( 'NS_DEFINEDMEANING', 24 );
define( 'WLD_ENGLISH_LANG_ID', 85 );
define( 'WLD_ENGLISH_LANG_WMKEY', "en" );

// Achtung: the following defines should match the strings used in
// the Javascript files
define( 'WLD_ALTERNATIVE_DEF', "altDef" );
define( 'WLD_ALTERNATIVE_DEFINITIONS', "altDefs" );
define( 'WLD_CLASS_ATTRIBUTES', "classAtt" );
define( 'WLD_CLASS_MEMBERSHIP', "classMembers" );
define( 'WLD_COLLECTION_MEMBERSHIP', "colMembers" );
define( 'WLD_DEFINED_MEANING', "dm" );
define( 'WLD_DM_ATTRIBUTES', "dmAtt" );
define( 'WLD_DEFINITION', "def" );
define( 'WLD_EXPRESSION', "exp" );
define( 'WLD_EXPRESSION_APPROX_MEANINGS', "approx" );
define( 'WLD_EXPRESSION_EXACT_MEANINGS', "exact" );
define( 'WLD_EXPRESSION_MEANINGS', "meanings" );
define( 'WLD_IDENTICAL_MEANING', "identMeaning" );
define( 'WLD_INCOMING_RELATIONS', "incomingRel" );
define( 'WLD_LINK_ATTRIBUTE', "linkAtt" );
define( 'WLD_LINK_ATTRIBUTE_VALUES', "linkAttVal" );
define( 'WLD_OBJECT_ATTRIBUTES', "objAtt" );
define( 'WLD_OPTION_ATTRIBUTE', "optnAtt" );
define( 'WLD_OPTION_ATTRIBUTE_OPTION', "optnAttOptn" ); // WLD_OPTION_ATTRIBUTE . WLD_OPTION_SUFFIX
define( 'WLD_OPTION_ATTRIBUTE_VALUES', "optnAttVal" ); // WLD_OPTION_ATTRIBUTE . "Val"
define( 'WLD_OPTION_SUFFIX', "Optn" );
define( 'WLD_OTHER_OBJECT', "otherObj" );
define( 'WLD_RELATIONS', "rel" );
define( 'WLD_SYNONYMS_TRANSLATIONS', "syntrans" );
define( 'WLD_SYNT_ATTRIBUTES', "syntAtt" );
define( 'WLD_TEXT_ATTRIBUTES_VALUES', "txtAttVal" );
define( 'WLD_TRANSLATED_TEXT', "transl" );

# Global context override. This is an evil hack to allow saving, basically.
$wdCurrentContext = null;

$wgIso639_3CollectionId = null;

// paths
global $wgWldScriptPath, $wgWldOwScriptPath, $wgWldDownloadScriptPath, $wgWldIncludesScriptPath, $wgWldSpecialsScriptPath, $wgWldAPIScriptPath, $wgWldSetupScriptPath, $wgWldJobsScriptPath, $wgWldDbScripts;
$wgWldScriptPath = __DIR__ . '/';
$wgWldScriptPath = str_replace( '\\', '/', $wgWldScriptPath );
$wgWldScriptPath = str_replace( 'OmegaWiki/', '', $wgWldScriptPath );
if ( !isset( $IP ) ) {
	$IP = $wgWldScriptPath . '../../';
}
$wgWldOwScriptPath     = $wgWldScriptPath . "OmegaWiki/";
$wgWldDownloadScriptPath = $IP . "/downloads/";
$wgWldDownloadScriptPath = str_replace( '//', '/', $wgWldDownloadScriptPath );
$wgWldDownloadScriptPath = str_replace( '\/', '/', $wgWldDownloadScriptPath );
$wgWldIncludesScriptPath = $wgWldScriptPath . "includes/";
$wgWldSpecialsScriptPath = $wgWldIncludesScriptPath . "specials/";
$wgWldAPIScriptPath      = $wgWldIncludesScriptPath . "api/";
$wgWldSetupScriptPath    = $wgWldIncludesScriptPath . "setup/";
$wgWldJobsScriptPath     = $wgWldIncludesScriptPath . "jobs/";
$wgWldDbScripts          = $wgWldIncludesScriptPath . 'updateScripts/';

/**
 * the DM of an annotation to use for sorting expression
 * such as the DM representing the concept "part of speech"
 */
$wgWldSortingAnnotationDM = null;

// Defined meaning editor
$wdDefinedMeaningAttributesOrder = [
	WLD_SYNT_ATTRIBUTES,
	WLD_DEFINITION,
	// Kip: alternative definitions disabled until we find a use for that field
	// WLD_ALTERNATIVE_DEFINITIONS,
	WLD_SYNONYMS_TRANSLATIONS,
	WLD_DM_ATTRIBUTES,
	WLD_CLASS_MEMBERSHIP,
	WLD_CLASS_ATTRIBUTES,
	WLD_COLLECTION_MEMBERSHIP,
	WLD_INCOMING_RELATIONS
];

// Page titles
$wgWldUseExpressionPageTitlePrefix = true;	# malafaya: Use the expression prefix "Multiple meanings:" from message ow_Multiple_meanings
$wgWldShowClassicPageTitles = false;
$wgWldExpressionPageTitlePrefix = 'Multiple meanings';
/** @note both wgWldUseExpressionPageTitlePrefix and wgWldExpressionPageTitles
 *	seems to be unused based on ...
 *		git grep -e wgWldUseExpressionPageTitlePrefix
 *		and
 *		git grep -e wgWldExpressionPageTitlePrefix
 *	If not going to be used. Should ow_Multiple_meanings @ i18n/lexicaldata/en.json be removed also? ~he
 */

// Search page
$wgWldSearchExternalIDDefault = false;
$wgWldSearchWordsDefault = true;

// KIP: disabled for now, it's only confusing
/** @todo replace with a radiobox */
$wgWldSearchExternalIDOption = false;
$wgWldSearchWordsOption = false;

/**
 * $wgPropertyToColumnFilters is an array of property to column filters
 *
 * Example:
 *   $wgPropertyToColumnFilters = array(
 *     new PropertyToColumnFilter("references", "References", array(1000, 2000, 3000)) // Defined meaning ids are the attribute ids to filter
 *   )
 */
$wgPropertyToColumnFilters = [];

/**
 * A Wikidata application can manage multiple data sets.
 * The current "context" is dependent on multiple factors:
 * - the URL can have a dataset parameter
 * - there is a global default
 * - there can be defaults for different user groups
 * @param int|null $dc optional, for convenience.
 * 		if the dataset context is already set, will
 * 		return that value, else will find the relevant value
 * @return string prefix (without underscore)
 */
function wdGetDataSetContext( $dc = null ) {
	global $wgRequest, $wdDefaultViewDataSet, $wdGroupDefaultView, $wgUser,
		$wdCurrentContext;

	# overrides
	if ( $dc !== null ) {
		return $dc; # local override
	}
	if ( $wdCurrentContext !== null ) {
		return $wdCurrentContext; # global override
	}

	$datasets = wdGetDataSets();
	$groups = $wgUser->getGroups();
	$dbs = wfGetDB( DB_REPLICA );
	$pref = $wgUser->getOption( 'ow_uipref_datasets' );

	$trydefault = '';
	foreach ( $groups as $group ) {
		if ( isset( $wdGroupDefaultView[$group] ) ) {
			# We don't know yet if this prefix is valid.
			$trydefault = $wdGroupDefaultView[$group];
		}
	}

	# URL parameter takes precedence over all else
	if ( ( $ds = $wgRequest->getText( 'dataset' ) ) && array_key_exists( $ds, $datasets ) && $dbs->tableExists( $ds . "_transactions" ) ) {
		return $datasets[$ds];
	# User preference
	} elseif ( !empty( $pref ) && array_key_exists( $pref, $datasets ) ) {
		return $datasets[$pref];
	}
	# Group preference
 elseif ( !empty( $trydefault ) && array_key_exists( $trydefault, $datasets ) ) {
		return $datasets[$trydefault];
	} else {
		return $datasets[$wdDefaultViewDataSet];
	}
}

/**
 * Load dataset definitions from the database if necessary.
 *
 * @return an array of all available datasets
 *
 * @note 2014-03-19 Added $wgDBprefix for wld~mw prefix compatibility ~he
 */
function &wdGetDataSets() {
	global $wgDBprefix;

	static $datasets, $wgGroupPermissions;
	if ( empty( $datasets ) ) {
		// Load defs from the DB
		$dbs = wfGetDB( DB_REPLICA );
		$res = $dbs->select( 'wikidata_sets', [ 'set_prefix' ] );

		while ( $row = $dbs->fetchObject( $res ) ) {
			$dc = new DataSet();
			$dc->setPrefix( $row->set_prefix );
			$dc->setDBprefix( $wgDBprefix );
			if ( $dc->isValidPrefix() ) {
				$datasets[$row->set_prefix] = $dc;
				wfDebug( "Imported data set: " . $dc->fetchName() . "\n" );
			} else {
				wfDebug( $row->set_prefix . " does not appear to be a valid dataset!\n" );
			}
		}
	}
	return $datasets;
}
