<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}
/**
 * A Special Page extension to copy defined meanings between datasets.
 *
 * Copied over from SpecialConceptMapping.
 * User Interface temporarily retained (but currently flawed)
 * Web API will be implemented
 * Minimal documentation is available by calling with &action=help, as a parameter
 *
 * @file
 * @ingroup Extensions
 *
 * @author Erik Moeller <Eloquence@gmail.com>	(Possibly some remaining code)
 * @author Kim Bruning <kim@bruning.xs4all.nl>
 * @author Alan Smithee <Alan.Smithee@brown.paper.bag> (if code quality improves, may yet claim)
 * @license GPL-2.0-or-later
 */
require_once "WikiDataAPI.php";
require_once "Utilities.php";
require_once "Copy.php";

class SpecialCopy extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'Copy' );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest;

		# $wgOut->setPageTitle("Special:Copy");

		if ( !$this->getUser()->isAllowed( 'wikidata-copy' ) ) {
			$wgOut->addHTML( wfMessage( "ow_Permission_denied" )->text() );
			return false;
		}

		$action = $wgRequest->getText( 'action' );
		if ( !$action ) {
			$this->ui();
		} elseif ( $action == "copy" ) {
			$this->copy_by_param();
		} elseif ( $action == "list" ) {
			$this->list_sets();
		} elseif ( $action == "help" ) {
			$this->help();
		} else {
			$wgOut->addWikiMsg( "ow_no_action_specified", $action );
			$wgOut->addWikiMsg( "ow_copy_help" );
		}
	}

	/** reserved for ui elements */
	protected function ui() {
		global $wgOut;
		$wgOut->addWikiMsg( "ow_no_action_specified" );
	}

	/** display a helpful help message.
	 * (if desired)
	 */
	protected function help() {
		global $wgOut;
		$wgOut->addWikiTextAsInterface( "<h2>Help</h2>" );
		$wgOut->addWikiMsg( "ow_copy_help" );
	}

	/** read in and partially validate parameters,
	 * then call _doCopy()
	 */
	protected function copy_by_param() {
		global
			$wgRequest, $wgOut;

		$dmid_dirty = $wgRequest->getText( "dmid" );
		$dc1_dirty = $wgRequest->getText( "dc1" );
		$dc2_dirty = $wgRequest->getText( "dc2" );

		$abort = false; 	# check all input before aborting

		if ( $dmid_dirty === null ) {
			$wgOut->addWikiMsg( "ow_please_provide_dmid" );
			$abort = true;
		}
		if ( $dc1_dirty === null ) {
			$wgOut->addWikiMsg( "ow_please_provide_dc1" );
			$abort = true;
		}
		if ( $dc2_dirty === null ) {
			$wgOut->addWikiMsg( "ow_please_provide_dc2" );
			$abort = true;
		}

		if ( $abort ) {
			return;
		}

		# seems ok so far, let's try and copy.
		$success = $this->_doCopy( $dmid_dirty, $dc1_dirty, $dc2_dirty );
		if ( $success ) {
			$this->autoredir();
		} else {
			$wgOut->addWikiMsg( "ow_copy_unsuccessful" );
		}
	}

	/** automatically redirects to another page.
	 * make sure you haven't used $wgOut before calling this!
	 */
	protected function autoredir() {
		global $wgOut, $wgRequest;

		$dmid_dirty = $wgRequest->getText( "dmid" );
		$dc1_dirty = $wgRequest->getText( "dc1" );
		$dc2_dirty = $wgRequest->getText( "dc2" );

		# Where should we redirect to?
		$meanings = getDefinedMeaningDataAssociatedByConcept( $dmid_dirty, $dc1_dirty );
		$targetdmm = $meanings[$dc2_dirty];
		$title = $targetdmm->getTitleObject();
		$url = $title->getLocalURL( "dataset=$dc2_dirty&action=edit" );

		# do the redirect
		$wgOut->disable();
		header( 'Location: ' . $url );
		# $wgOut->addHTML("<a href=\"$url\">$url</a>");
	}

	/* Using Copy.php; perform a copy of a defined meaning from one dataset to another,
	   provided the user has permission to do so,*/
	protected function _doCopy( $dmid_dirty, $dc1_dirty, $dc2_dirty ) {
		global $wgOut, $wgCommunity_dc;

		# escape parameters
		$dmid = mysql_real_escape_string( $dmid_dirty );
		$dc1 = mysql_real_escape_string( $dc1_dirty );
		$dc2 = mysql_real_escape_string( $dc2_dirty );

		# check permission
		if ( !( $this->getUser()->isAllowed( 'wikidata-copy' ) ) or $dc2 != $wgCommunity_dc ) {
			$wgOut->addHTML( wfMessage( "ow_Permission_denied" )->text() );
			return false; # we didn't perform the copy.
		}

		# copy
		CopyTools::newCopyTransaction( $dc1, $dc2 );
		$dmc = new DefinedMeaningCopier( $dmid, $dc1, $dc2 );
		$dmc->dup();

		# For purposes of current "edit copy",
		# having the dm be already_there() is ok.
		# (hence commented out)
		# if ($dmc->already_there() ) {
		# $wgOut->addHTML(wfMessage("ow_already_there")->text());
		# return false;
		# }

		return true; # seems everything went ok.
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
