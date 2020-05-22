<?php
/** \file App.php
 * \brief This is where the WikiLexicalData extension extends OmegaWiki to MediaWiki.
 *
 * This sets some initial settings
 * other settings are in the file extension.json
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Invalid entry point.' );
}
global $wgWldScriptPath, $wgWldAPIScriptPath, $wgWldSetupScriptPath;
global $wgWldProcessExternalAPIClasses, $wgWldExtenalResourceLanguages;

$dir = __DIR__ . '/';
$dir = str_replace( '\\', '/', $dir );


require_once $dir . 'OmegaWiki/WikiDataGlobals.php';
require_once $dir . 'OmegaWiki/Wikidata.php';
require_once $wgWldScriptPath . '/SpecialLanguages.php';

// API
require_once $wgWldAPIScriptPath . '/OmegaWikiExt.php';

// WikiLexicalData Configuration.

# The term dataset prefix identifies the Wikidata instance that will
# be used as a resource for obtaining language-independent strings
# in various places of the code. If the term db prefix is empty,
# these code segments will fall back to (usually English) strings.
# If you are setting up a new Wikidata instance, you may want to
# set this to ''.
$wdTermDBDataSet = 'uw';

# This is the dataset that should be shown to all users by default.
# It _must_ exist for the Wikidata application to be executed
# successfully.
$wdDefaultViewDataSet = 'uw';

$wdShowCopyPanel = false;
$wdShowEditCopy = true;

$wdGroupDefaultView = [];
$wdGroupDefaultView['wikidata-omega'] = 'uw';

$wgCommunity_dc = 'uw';
$wgCommunityEditPermission = 'editwikidata-uw';

# what is this?
$wdCopyAltDefinitions = false;

# what is this?
$wdCopyDryRunOnly = false;

# The site prefix allows us to have multiple sets of customized
# messages (for different, typically site-specific UIs)
# in a single database.
# is it still used?
if ( !isset( $wdSiteContext ) ) {
	$wdSiteContext = "uw";
}

/**
 * Uncomment to enable Wordnik extension
 */
/*
$wgWldProcessExternalAPIClasses = [];
$wgWldExtenalResourceLanguages = [];

if ( file_exists( $wgWldScriptPath . '/external/wordnik/wordnik/Swagger.php' ) ) {
	$wgAutoloadClasses['WordnikExtension' ] = $wgWldSpecialsScriptPath . 'ExternalWordnik.php';
	$wgAutoloadClasses['WordnikWiktionaryExtension' ] = $wgWldSpecialsScriptPath . 'ExternalWordnik.php';
	$wgAutoloadClasses['WordnikWordnetExtension' ] = $wgWldSpecialsScriptPath . 'ExternalWordnik.php';
	$wgWldProcessExternalAPIClasses['WordnikExtension'] = 'Wordnik';
	$wgWldProcessExternalAPIClasses['WordnikWiktionaryExtension'] = 'Wordnik Wiktionary';
	$wgWldProcessExternalAPIClasses['WordnikWordnetExtension'] = 'Wordnik Wordnet';
	$wgWldExtenalResourceLanguages[WLD_ENGLISH_LANG_ID] = 'English';
	require_once $wgWldScriptPath . '/external/wordnikConfig.php';
}

if ( $wgWldProcessExternalAPIClasses ) {
	$wgResourceModules['ext.OwAddFromExtAPI.js'] = $resourcePathArray + [
		'scripts' => 'omegawiki-addExtAPI.js'
	];
}
*/

