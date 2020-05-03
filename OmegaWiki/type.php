<?php

require_once 'languages.php';
require_once 'forms.php';
require_once 'Attribute.php';
require_once 'Record.php';
require_once 'Transaction.php';
require_once 'WikiDataAPI.php';
require_once 'Wikidata.php';
require_once 'WikiDataGlobals.php';

function booleanAsText( $boolValue, $textValues = [ "true" => "Yes", "false" => "No" ] ) {
	if ( $boolValue ) {
		return $textValues["true"];
	} else {
		return $textValues["false"];
	}
}

function booleanAsHTML( $value ) {
	if ( $value ) {
		return '<input type="checkbox" checked="checked" disabled="disabled"/>';
	} else {
		return '<input type="checkbox" disabled="disabled"/>';
	}
}

/**
 * adds a parameter to a url, with "?" or "&" depending on
 * what is already in the url
 * $parameter is for example "dataset=$dc"
 */
function addParameterToURL( &$url, $parameter ) {
	if ( strpos( $url, "?" ) ) {
		$url .= "&" . $parameter;
	} else {
		$url .= "?" . $parameter;
	}
}

function pageAsURL( $nameSpace, $title, $usedc = true ) {
	global $wgArticlePath, $wdDefaultViewDataSet;

	$myTitle = str_replace( "&", urlencode( "&" ), $title );
	$myTitle = str_replace( "?", urlencode( "?" ), $title );
	$url = str_replace( "$1", $nameSpace . ':' . $myTitle, $wgArticlePath );

	if ( $usedc ) {
		$dc = wdGetDataSetContext();
		if ( $dc == $wdDefaultViewDataSet ) {
			return $url;
		}
		addParameterToURL( $url, "dataset=$dc" );
	}
	return $url;
}

function spellingAsURL( $spelling, $lang = 0 ) {
	global $wdDefaultViewDataSet;

	$title = Title::makeTitle( NS_EXPRESSION, $spelling );
	$query = [];

	$dc = wdGetDataSetContext();
	if ( $dc != $wdDefaultViewDataSet ) {
		$query['dataset'] = $dc;
	}
	if ( $lang != 0 ) {
		$query['explang'] = $lang;
	}

	return $title->getLocalURL( $query );
}

function definedMeaningReferenceAsURL( $definedMeaningId, $definingExpression ) {
	return pageAsURL( "DefinedMeaning", "$definingExpression ($definedMeaningId)" );
}

function definedMeaningIdAsURL( $definedMeaningId ) {
	global $wgWldOwScriptPath;
	require_once $wgWldOwScriptPath . 'OmegaWikiDatabaseAPI.php';
	return definedMeaningReferenceAsURL( $definedMeaningId, OwDatabaseAPI::definingExpression( $definedMeaningId ) );
}

function createLink( $url, $text ) {
	return '<a href="' . htmlspecialchars( $url ) . '">' . htmlspecialchars( $text ) . '</a>';
}

function spellingAsLink( $spelling, $lang = 0 ) {
	return createLink( spellingAsURL( $spelling, $lang ), $spelling );
}

function syntransAsLink( $syntransId, $spelling ) {
	$url = spellingAsURL( $spelling );
	addParameterToURL( $url, "syntrans=$syntransId" );
	return createLink( $url, $spelling );
}

function definedMeaningReferenceAsLink( $definedMeaningId, $definingExpression, $label ) {
	return createLink( definedMeaningReferenceAsURL( $definedMeaningId, $definingExpression ), $label );
}

function languageIdAsText( $languageId ) {
	$owLanguageNames = getOwLanguageNames();
	if ( array_key_exists( $languageId, $owLanguageNames ) ) {
		return $owLanguageNames[$languageId];
	} else {
		return null;
	}
}

function collectionIdAsText( $collectionId ) {
	if ( $collectionId > 0 ) {
		require_once 'OmegaWikiDatabaseAPI.php';
		return OwDatabaseAPI::getDefinedMeaningExpression( getCollectionMeaningId( $collectionId ) );
	} else {
		return "";
	}
}

function timestampAsText( $timestamp ) {
	return substr( $timestamp, 0, 4 ) . '-' . substr( $timestamp, 4, 2 ) . '-' . substr( $timestamp, 6, 2 ) . ' ' .
		substr( $timestamp, 8, 2 ) . ':' . substr( $timestamp, 10, 2 ) . ':' . substr( $timestamp, 12, 2 );
}

function definingExpressionAsLink( $definedMeaningId ) {
	global $wgWldOwScriptPath;
	require_once $wgWldOwScriptPath . 'OmegaWikiDatabaseAPI.php';
	return spellingAsLink( OwDatabaseAPI::definingExpression( $definedMeaningId ) );
}

function definedMeaningAsLink( $definedMeaningId ) {
	if ( $definedMeaningId > 0 ) {
		require_once 'OmegaWikiDatabaseAPI.php';
		return createLink( definedMeaningIdAsURL( $definedMeaningId ), OwDatabaseAPI::getDefinedMeaningExpression( $definedMeaningId ) );
	} else {
		return "";
	}
}

function collectionAsLink( $collectionId ) {
	return definedMeaningAsLink( getCollectionMeaningId( $collectionId ) );
}
