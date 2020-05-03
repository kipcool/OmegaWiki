<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

/**
 * A Special Page extension to add languages, runnable by users with the 'addlanguage' right.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Erik Moeller <Eloquence@gmail.com>
 * @license public domain
 */

$wgAvailableRights[] = 'addlanguage';
$wgGroupPermissions['bureaucrat']['addlanguage'] = true;

$wgExtensionCredits['specialpage'][] = [
	'name' => 'Language manager',
	'author' => 'Erik Moeller',
	'descmsg' => 'langman-desc',
];

$wgSpecialPages['Languages'] = 'SpecialLanguages';

class SpecialLanguages extends SpecialPage {
	function __construct() {
		parent::__construct( 'Languages' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $par ) {
		// added $wgDBprefix for wld and mw prefix compatibility
		global $wgOut, $wgRequest, $wgDBprefix;
		$wgOut->setPageTitle( wfMessage( 'langman_title' )->text() );
		if ( !$this->getUser()->isAllowed( 'addlanguage' ) ) {
			$wgOut->addHTML( wfMessage( 'langman_not_allowed' )->text() );
			return false;
		}
		$action = $wgRequest->getText( 'action' );
		if ( !$action ) {
			$wgOut->addWikiMsg( 'langman_header' );
		} else {
			$dbr = wfGetDB( DB_MASTER );
			$langname = $wgRequest->getText( 'langname' );
			$langiso6393 = $wgRequest->getText( 'langiso6393' );
			$langiso6392 = $wgRequest->getText( 'langiso6392' );
			$langwmf = $wgRequest->getText( 'langwmf' );
			if ( !$langname || !$langiso6393 ) {
				$wgOut->addHTML( "<strong>" . wfMessage( 'langman_req_fields' )->text() . "</strong>" );
			} else {
				$wgOut->addHTML( "<strong>" . wfMessage( 'langman_adding', $langname, $langiso6393 )->text() . "</strong>" );
				$sql = 'INSERT INTO ' . $wgDBprefix . 'language(iso639_2,iso639_3,wikimedia_key) values(' . $dbr->addQuotes( $langiso6392 ) . ',' . $dbr->addQuotes( $langiso6393 ) . ',' . $dbr->addQuotes( $langwmf ) . ')';

				$dbr->query( $sql );
				$id = $dbr->insertId();
				$sql = 'INSERT INTO ' . $wgDBprefix . 'language_names(language_id,name_language_id,language_name) values (' . $id . ',85,' . $dbr->addQuotes( $langname ) . ')';
				$dbr->query( $sql );

			}

		}
		$this->showForm();

		# $wgRequest->getText( 'page' );
	}

	function showForm() {
		global $wgOut;
		$action = htmlspecialchars( $this->getPageTitle()->getLocalURL( 'action=submit' ) );
		$wgOut->addHTML(
<<<END
<form name="addlanguage" method="post" action="$action">
<table border="0">
<tr>
<td>
END
. wfMessage( 'langman_langname' )->text() .
<<<END
</td>
<td>
<input type="text" size="40" name="langname">
</td>
</tr>
<tr>
<td>
END
. wfMessage( 'langman_iso639-3' )->text() .
<<<END
</td>
<td>
<input type="text" size="8" name="langiso6393">
</td>
</tr>
<tr>
<td>
END
. wfMessage( 'langman_iso639-2' )->text() .
<<<END
</td>
<td>
<input type="text" size="8" name="langiso6392">
END
. wfMessage( 'langman_field_optional' )->text() .
<<<END
</td>
</tr>
<tr>
<td>
END
. wfMessage( 'langman_wikimedia' )->text() .
<<<END
</td>
<td>
<input type="text" size="4" name="langwmf">
END
. wfMessage( 'langman_field_optional' )->text() .
<<<END
</td>
</tr>
<tr><td>
<input type="submit" value="
END
. wfMessage( 'langman_addlang' )->text() .
<<<END
">
</td></tr>
</table>
</form>
END
);
		return true;
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
