<?php
/**
 * Job Setup
 */

 # Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'To install my extension, put the following line in LocalSettings.php:' .
	"\n" .
	'require_once( "' . $wgWldScriptPath . 'OWJobs.php" );
	';
	exit( 1 );
}

# Location of the job classes (Tell MediaWiki to load this file)
$wgAutoloadClasses['CreateExpressionListJob'] = $wgWldJobsScriptPath . 'OWExpressionListJob.php';
$wgAutoloadClasses['CreateDefinedExpressionListJob'] = $wgWldJobsScriptPath . 'OWDefinedExpressionListJob.php';
$wgAutoloadClasses['CreateOwdListJob'] = $wgWldJobsScriptPath . 'OWOwdListJob.php';
// $wgAutoloadClasses['CreateSynTransListJob'] = $wgWldJobsScriptPath . 'OWSynTransListJob.php';

# Tell MediaWiki about the jobs and its class name
$wgJobClasses['CreateExpressionList'] = 'CreateExpressionListJob';
$wgJobClasses['CreateDefinedExpressionList'] = 'CreateDefinedExpressionListJob';
$wgJobClasses['CreateOwdList'] = 'CreateOwdListJob';
// $wgJobClasses['CreateSynTransList'] = 'CreateSynTransListJob';

// Return true so that MediaWiki continues to load extensions.
return true;
