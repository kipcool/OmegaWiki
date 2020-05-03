<?php
/**
 * Tags Setup
 */

 # Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'To install my extension, put the following line in LocalSettings.php:' .
	"\n" .
	'require_once( "' . $wgWldScriptPath . 'OWTags.php" );
	';
	exit( 1 );
}

# Location of the tags classes (Tell MediaWiki to load this file)
// $wgAutoloadClasses['OmegaWikiTags'] = $wgWldIncludesScriptPath . 'OmegaWikiTags.php';
require_once $wgWldIncludesScriptPath . 'OmegaWikiTags.php';

# Tell MediaWiki about the jobs and its class name
$wgHooks['ParserFirstCallInit'][] = 'omegaWikiTags';

// Return true so that MediaWiki continues to load extensions.
return true;
