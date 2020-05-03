<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}
/**
 * A Special Page extension to create concept-mappings
 * also provides a web-api. Minimal documentation is available by calling with &action=help, as a parameter
 *
 * @file
 * @ingroup Extensions
 *
 * @author Erik Moeller <Eloquence@gmail.com>
 * @author Kim Bruning <kim@bruning.xs4all.nl>
 * @license GPL-2.0-or-later
 */
require_once "WikiDataAPI.php";
require_once "Utilities.php";
require_once "WikiDataGlobals.php";

class SpecialConceptMapping extends SpecialPage {

	function __construct() {
		parent::__construct( 'ConceptMapping' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgUser, $wdTermDBDataSet;
		$wgOut->setPageTitle( wfMessage( 'ow_conceptmapping_title' )->text() );

		if ( !$wgUser->isAllowed( 'editwikidata-' . $wdTermDBDataSet ) ) {
			$wgOut->addHTML( wfMessage( "ow_Permission_denied" )->text() );
			return false;
		}
		$sets = wdGetDataSets();
		if ( count( $set ) < 2 ) {
			$wgOut->addHTML( wfMessage( "ow-conceptmapping-too-few" )->text() );
			return false;
		}
		$action = $wgRequest->getText( 'action' );
		if ( !$action ) {
			$this->ui();
		} elseif ( $action == "insert" ) {
			$this->insert();
		} elseif ( $action == "get" ) {
			$this->get();
		} elseif ( $action == "list_sets" ) {
			$this->list_sets();
		} elseif ( $action == "help" ) {
			$this->help();
		} elseif ( $action == "get_associated" ) {
			$this->get_associated();
		} else {
			$wgOut->addWikiMsg( "ow_conceptmapping_no_action_specified", $action );
			$wgOut->addWikiMsg( "ow_conceptmapping_help" );
		}
	}

	protected function ui() {
		global $wgOut, $wgRequest, $wgLang;
		$lang = $wgLang->getCode();
		require_once "forms.php";
		$wgOut->addHTML( wfMessage( "ow_conceptmapping_uitext" )->text() );
		$sets = wdGetDataSets();
		$options = [];
		$html = "";
		$mappings = [];
		$rq = [];

		foreach ( $sets as $key => $setObject ) {
			$set = $setObject->getPrefix();
			$rq[$set] = $wgRequest->getText( "set_" . $set );
			$rq[$set] = trim( $rq[$set] );
			$rq[$set] = (int)$rq[$set];
			if ( $rq[$set] ) {
				$dmModel = new DefinedMeaningModel( $rq[$set], [ "dataset" => $setObject ] );
				$defaultSel = $dmModel->getSyntransByLanguageCode( $lang );
				$options[$setObject->fetchName()] = getSuggest( "set_$set", WLD_DEFINED_MEANING, [], $rq[$set], $defaultSel, [ 0 ], $setObject );
			} else {
				$options[$setObject->fetchName()] = getSuggest( "set_$set", WLD_DEFINED_MEANING, [], null, null, [ 0 ], $setObject );
			}

		}
		$wgOut->addHTML( getOptionPanel( $options ) );
		$noerror = $wgRequest->getText( "suppressWarnings" );

		foreach ( $sets as $key => $setObject ) {
			$set = $setObject->getPrefix();
			if ( !$rq[$set] ) {
				$wgOut->addHTML( ' <span style="color:yellow">[' . wfMessage( "ow_dm_not_present" )->text() . ']</span>' );
			} else {
				$dmModel = new DefinedMeaningModel( $rq[$set], [ "dataset" => $setObject ] );
				$dmModel->checkExistence();
				if ( $dmModel->exists() ) {
					$id = $dmModel->getId();
					$title = $dmModel->getTitleText();
				} else {
					$id = null;
					$title = null;
				}
				if ( !$noerror ) {
					$wgOut->addHTML( "$key: " . $rq[$set] . " ($title)" );
				}
				if ( $id != null ) {
					$mappings[$key] = $id;
					if ( !$noerror ) {
						$wgOut->addHTML( ' <span style="color:green">[' . wfMessage( "ow_dm_OK" )->text() . ']</span>' );
					}
				} else {
					if ( !$noerror ) {
						$wgOut->addHTML( ' <span style="color:red">[' . wfMessage( "ow_dm_not_found" )->text() . ']</span>' );
					}
				}
			}
			$wgOut->addHTML( "<br />\n" );
		}
		if ( count( $mappings ) > 1 ) {
			createConceptMapping( $mappings );
			$wgOut->addHTML( wfMessage( "ow_mapping_successful" )->text() );
		} else {
			$wgOut->addHTML( wfMessage( "ow_mapping_unsuccessful" )->text() );
		}
	}

	protected function getDm( $dataset ) {
		global $wgRequest;
		$setname = "set_" . $dataset->getPrefix();
		$rq = $wgRequest->getText( $setname );
		$html = getTextBox( $setname, $rq );
		return $html;
	}

	protected function help() {
		global $wgOut;
		$wgOut->addWikiTextAsInterface( "<h2>Help</h2>" );
		$wgOut->addWikiMsg( "ow_conceptmapping_help" );
	}

	protected function insert() {
		global
			$wgRequest, $wgOut;

		# $wgRequest->getText( 'page' );
		$sets = wdGetDataSets();
		# $requests=$wgRequest->getValues();
		$wgOut->addWikiTextAsInterface( "<h2>" . wfMessage( "ow_will_insert" )->plain() . "</h2>" );
		$map = [];
		foreach ( $sets as $key => $set ) {
			$dc = $set->getPrefix();
			$dm_id = $wgRequest->getText( $dc );
			$name = $set->fetchName();

			$dm_id_ui = $dm_id; # Only for teh purdy
			if ( $dm_id_ui == null ) {
				$dm_id_ui = "unset";
			}
			$wgOut->addWikiTextAsInterface( "$name ->$dm_id_ui" );
			$map[$dc] = $dm_id;
		# $dbr=&wfGetDB(DB_MASTER);
		}
		createConceptMapping( $map );
	}

	protected function get() {
		global
			$wgOut, $wgRequest;
		$concept_id = $wgRequest->getText( "concept" );
		$wgOut->addWikiTextAsInterface( "<h2>" . wfMessage( "ow_contents_of_mapping" )->plain() . "</h2>" );
		$map = readConceptMapping( $concept_id );
		# $sets=wdGetDataSets();

		foreach ( $map as $dc => $dm_id ) {
			$wgOut->addWikiTextAsInterface( "$dc -> $dm_id" );
		}
	}

	protected function list_sets() {
		global $wgOut;
		$wgOut->addWikiTextAsInterface( "<h2>" . wfMessage( "ow_available_contexts" )->plain() . "</h2>" );
		$sets = wdGetDataSets();
		foreach ( $sets as $key => $set ) {
			$name = $set->fetchName();
			$wgOut->addWikiTextAsInterface( "$key => $name" );
		}
	}

	protected function get_associated() {
		global $wgOut, $wgRequest;
		$dm_id = $wgRequest->getText( "dm" );
		$dc = $wgRequest->getText( "dc" );
		$map = getAssociatedByConcept( $dm_id, $dc );
		foreach ( $map as $dc => $dm_id ) {
			$wgOut->addWikiTextAsInterface( "$dc -> $dm_id" );
		}
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
