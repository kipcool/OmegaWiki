<?php
/**
 * Special download page. Currently regenerated files are formatted
 * using standard csv with headers and commas as separator and quotation
 * marks, when appropriate as text delimiter (but actually, I guess the
 * all non numeric fields are text delimited, to be remedied in future
 * revisions).
 *
 * QUESTION: Is there a way for the job to know that it is processing a
 * job while in the Special Dowload Page?
 * If so, is there a way to refresh the page to show the updated file with date
 * and at the same time not run another job? ~he
 */
global $wgWldJobsScriptPath;
require_once $wgWldJobsScriptPath . 'OWExpressionListJob.php';
require_once $wgWldJobsScriptPath . 'WldJobs.php';

class SpecialOWDownloads extends SpecialPage {

	function __construct() {
		parent::__construct( 'ow_downloads' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $par ) {
		global $wgWldDownloadScriptPath, $wgWldOwScriptPath;

		require_once $wgWldOwScriptPath . 'Expression.php';
		require_once $wgWldOwScriptPath . 'DefinedMeaning.php';

		$this->Expressions = new Expressions;
		$this->DefinedMeanings = new DefinedMeanings;
		$this->Attributes = new Attributes;
		$this->Transactions = new Transactions;
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		# Get requests
		$fileName = $request->getText( 'update' );

		// This should change if non csv files will be produced.
		$filePrefix = [
			'def',
			'exp',
			'owd',
			'lang'
		];

		$this->checkMode = false;
		$this->checkLanguage = null;
		$this->checkOwdFilename = null;

		$htmlContents = [];
		$this->definitions = [];
		$this->expressions = [];
		$this->translations = [];
		$this->development = [];
		$this->downloadIniExist = false;

		// scan directory for filenames
		if ( !is_dir( $wgWldDownloadScriptPath ) || !is_readable( $wgWldDownloadScriptPath ) ) {
			$output->addHTML( wfMessage( 'ow-downloads-directory-missing' )
				->params( htmlentities( $wgWldDownloadScriptPath ) )->plain() );
			$downloadDir = [];
			$this->updateCheckChecked = true;
		} else {
			$downloadDir = scandir( $wgWldDownloadScriptPath );
			$this->updateCheckChecked = false;
		}

		// Segregate by groups
		$this->segregateGroups( $downloadDir );

		$this->updateCheck = null;
		$this->updateCheckList = [];
		$this->processingOwd = false;

		// This should change if non csv files will be produced.
		foreach ( $filePrefix as $row ) {
			// check if "create-" . $prefix in url, translate it to fileName
			if ( $request->getInt( 'create-' . $row ) ) {
				$langId = $request->getInt( 'create-' . $row );
				$langCode = getLanguageIso639_3ForId( $langId );
				$fileName = $row . '_' . $langCode . '.csv';
			}
			// check if "check-" . $prefix . "status" in url
			if ( $request->getInt( 'check_' . $row . '_status' ) ) {
				$langId = $request->getInt( 'check_' . $row . '_status' );
				$langCode = getLanguageIso639_3ForId( $langId );
				$fileName = $wgWldDownloadScriptPath . $row . '_' . $langCode . '.csv';
				if ( $row == 'owd' ) {
					$wldJobs = new WldJobs();
					$this->checkMode = true;
					$this->checkLanguage = $langId;
					$this->checkOwdFilename = 'owd_' . $langCode . '.csv';

					$jobName = 'JobQuery/' . $this->checkOwdFilename;
					$jobExist = $wldJobs->downloadJobExist( $jobName );
					if ( $jobExist ) {
						$this->processingOwd = true;
					} else {
						// Get Update Check variables
						$this->getUpdateChecks();
						$this->updateCheckChecked = false;
						$this->updateOwdStatus( $fileName );
					}
				}
			}
		}

		if ( !$this->updateCheckChecked ) {
			// Get Update Check variables
			$this->getUpdateChecks();
		}

		$this->update_definition_notice = null;
		$this->update_expression_notice = null;
		$this->update_owd_notice = null;
		if ( $fileName ) {
			$wldJobs = new WldJobs();
			// update Expression
			if ( preg_match( '/^exp_/', $fileName ) ) {
				preg_match( '/_(.+)\.(.+)/', $fileName, $match );
				// check if language exist
				$languageId = getLanguageIdForIso639_3( $match[1] );
				if ( !$languageId ) {
					$this->update_expression_notice = "\n$fileName does not have a valid language<br/>\n";
				} else {
					$this->update_expression_notice = "\n$fileName has been requested<br/>\n";
					$jobParams = [ 'type' => 'exp', 'langcode' => $match[1], 'format' => $match[2] ];
					$title = Title::newFromText( 'User:JobQuery/exp_' . $match[1] . '.' . $match[2] );
					$job = new CreateExpressionListJob( $title, $jobParams );
					JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
				}
			}
			// update Definition
			if ( preg_match( '/^def_/', $fileName ) ) {
				preg_match( '/_(.+)\.(.+)/', $fileName, $match );
				// check if language exist
				$languageId = getLanguageIdForIso639_3( $match[1] );
				if ( !$languageId ) {
					$this->update_definition_notice = "\n$fileName does not have a valid language<br/>\n";
				} else {
					$this->update_definition_notice = "\n$fileName has been requested<br/>\n";
					$jobName = 'JobQuery/' . $fileName;
					$jobParams = [ 'type' => 'def', 'langcode' => $match[1], 'format' => $match[2], 'start' => '1' ];
					$jobExist = $wldJobs->downloadJobExist( $jobName );
					if ( !$jobExist ) {
						$title = Title::newFromText( 'User:' . $jobName );
						$job = new CreateDefinedExpressionListJob( $title, $jobParams );
						JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
					}
				}
			}

			// update Owd
			if ( preg_match( '/^owd_/', $fileName ) ) {
				preg_match( '/_(.+)\.(.+)/', $fileName, $match );
				// check if language exist
				$languageId = getLanguageIdForIso639_3( $match[1] );
				if ( !$languageId ) {
					$this->update_owd_notice = "<br/>\n$fileName does not have a valid language<br/>\n";
				} else {
					$this->update_owd_notice = "<br/>\n$fileName has been requested<br/>\n";
					$jobName = 'JobQuery/' . $fileName;
					$jobParams = [ 'type' => 'owd', 'langcode' => $match[1], 'format' => $match[2], 'start' => '1' ];
					$jobExist = $wldJobs->downloadJobExist( $jobName );
					if ( !$jobExist ) {
						// Check if zip file exist, if not create an empty archive.
						$zipName = $wgWldDownloadScriptPath . 'owd_' . $match[1] . '_csv.zip';
						if ( !file_exists( $zipName ) ) {
							$zip = new ZipArchive();
							$zip->open( $zipName, ZipArchive::CREATE );
							$zip->addEmptyDir( '.' );
							$zip->close();
						}
						$title = Title::newFromText( 'User:' . $jobName );
						$job = new CreateOwdListJob( $title, $jobParams );
						JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
					}
				}
			}
		}

		// Process Definitions
		if ( $this->definitions ) {
			$htmlContents[] = '<h3>Definitions</h3>' . $this->update_definition_notice;
			$myLine = $this->processText( $this->definitions );
			$htmlContents[] = $myLine . '</table>' . "\n";
		}

		// Process Expressions
		if ( $this->expressions ) {
			$htmlContents[] = '<h3>List of Expressions</h3>' . $this->update_expression_notice;
			$myLine = $this->processText( $this->expressions );
			$htmlContents[] = $myLine . '</table>' . "\n";
		}

		// Process Translations
		if ( $this->translations ) {
			$htmlContents[] = '<h3>Translations</h3>';
			$myLine = $this->processText( $this->translations );
			$htmlContents[] = $myLine . '</table>' . "\n";
		}

		// Process Development
		if ( $this->development ) {
			$htmlContents[] = '<h3>Development</h3>';
			$htmlContents[] = 'These files are still in alpha development.  Column numbers may change.' . $this->update_owd_notice;
			$myLine = $this->processText( $this->development );
			$htmlContents[] = $myLine . '</table>' . "\n";
		}

		// output html
		foreach ( $htmlContents as $htmlLine ) {
			$output->addHTML( $htmlLine );
		}

		// DfM Downloads
		$wikiText = "=== Dictionary For Mids ===\n";
		$wikiText .= "{{DictionaryForMIDs}}\n";

		// see also
		$wikiText .= "==Help==\n";
		$wikiText .= "*[[Help:Downloading_the_data#CSV_Files|About the OmegaWiki Special Downloads Page]]\n";
		$wikiText .= "*[[Help:OmegaWiki's Development CSVs|OmegaWiki's Development CSVs]]\n";
		$output->addWikiTextAsInterface( $wikiText );
	}

	protected function processText( $text ) {
		global $wgServer, $wgScriptPath, $wgWldDownloadScriptPath;

		$presetMyLine = '<table class="wikitable">' . "\n" .
			'<tr>' . "\n" .
			'<th> Language </th>' . "\n" .
			'<th> Date </th>' . "\n" .
			'<th> Size (bytes) </th>' . "\n" .
			'<th> Status </th>'	. "\n" .
			'<th> Action </th></tr>' . "\n";
		$myLine = $presetMyLine;

		foreach ( $text as $line ) {
			if ( preg_match( '/^(exp)_(.+)\./', $line, $match ) ) {
				$this->type = $match[1];
				$language = $match[2];
			}
			if ( preg_match( '/^(def)_(.+)\./', $line, $match ) ) {
				$this->type = $match[1];
				$language = $match[2];
			}
			if ( preg_match( '/^(owd)_(.+)_csv\./', $line, $match ) ) {
				$this->type = $match[1];
				$language = $match[2];
			}
			$languageId = getLanguageIdForIso639_3( $language );
			// TODO: How to internationalize $nameLanguageId?
			$nameLanguageId = WLD_ENGLISH_LANG_ID;

			$languageName = getLanguageIdLanguageNameFromIds( $languageId, $nameLanguageId );

			$theRow = '';
			if ( $myLine != $presetMyLine ) {
				$theRow = '<tr>' . "\n";
			}
			$myLink = '<a href="' . $wgServer . $wgScriptPath . '/downloads/' . $line . '">' . $languageName . '</a>';
			$myLine .= $theRow . '<td>' . $myLink . '</td>' . "\n";
			$filestats = stat( $wgWldDownloadScriptPath . $line );

			$date = new DateTime();
			$date->setTimestamp( $filestats['mtime'] );
			$dateTime = $date->format( 'Y-m-d H:i:s' );

			$myLine .= '<td>' . $dateTime . '</td>' . "\n";
			$myLine .= '<td align="right">' . $filestats['size'] . '</td>' . "\n";

			if ( preg_match( '/^(owd_.+)_csv\./', $line, $match ) ) {
				$line = $match[1] . '.csv';
			}

			$jobName = 'JobQuery/' . $line;
			$wldJobs = new WldJobs();
			$jobExist = $wldJobs->downloadJobExist( $jobName );
			if ( !$jobExist ) {
			// $action = '<a href="' . "$wgServer$wgScript/Special:Ow_downloads?create-" . $this->type . '=' . $languageId . '">Regenerate</a>' . "\n";
				$action = $this->postAction( $this->type, $languageId );
				$status = $this->getStatus( $languageId, $line );
			} else {
				$action = ' ';
				$status = "updating";
			}
			// ability to update the file
			$myLine .= '<td>' . $status . '</td><td>' . $action . '</td>' . "\n" . '</tr>' . "\n";
		}
		if ( preg_match( '/^(owd|exp|def)_.+\./', $line, $match ) ) {
			$myLine .= '<tr>' . "\n" . '<td colspan="5" >' . "\n" . $this->createNewForm( $match[1] ) . "\n" . '</td></tr>' . "\n";
		}
		return $myLine;
	}

	protected function getStatus( $languageId, $line ) {
		// Create a check if latest if not make status 'outdated'
		if ( preg_match( '/^owd_(.+)\./', $line, $match ) ) {
			global $wgServer, $wgScript;
			$languageCode = $match[1];

			$owdFile = 'owd_' . $languageCode . '.csv';

			if ( $this->checkMode == true && $this->checkLanguage == $languageId && $this->checkOwdFilename == $owdFile ) {
				if ( $this->processingOwd ) {
					return 'updating';
				} else {
					// get ow transaction
					$owTransactionId = Transactions::getLanguageIdLatestTransactionId( $languageId );
					$owTransactionRecord = getTransactionRecord( $owTransactionId );

					if ( $owTransactionId == -2 ) {
						return 'unavailable';
					} else {
						// get owd transaction
						$owdTransactionId = $this->updateCheck;
						$owdTransactionRecord = getTransactionRecord( $owdTransactionId );

						$old = false;
						if ( $owTransactionId > $owdTransactionId ) {
							$old = true;
						}

						if ( $old == true ) {
							return 'outdated(' . timestampAsText( $owdTransactionRecord->timestamp ) . ');new(' . timestampAsText( $owTransactionRecord->timestamp ) . ')';
							return 'outdated( ' . timestampAsText( $owdTransactionRecord->timestamp ) . ' )';
						}
					// return 'up-to-date(' . timestampAsText( $owdTransactionRecord->timestamp ) . ');new(' . timestampAsText( $owTransactionRecord->timestamp ) . ')';
						return 'up-to-date( ' . timestampAsText( $owdTransactionRecord->timestamp ) . ' )';
					}
				}
			}
			return '<a href="' . "$wgServer$wgScript/Special:Ow_downloads" . '?' . 'check_owd_status=' . $languageId . '">check status</a>';
			return 'check status';
		}
		// temporarily output this:
		return 'latest';
	}

// $action = '<a href="' . "$wgServer$wgScript/Special:Ow_downloads?create-" . $this->type . '=' . $languageId . '">Regenerate</a>' . "\n";
// $action = postAction( $this->type, $languageId );

	protected function postAction( $prefix, $languageId ) {
		global $wgServer, $wgScript;

		$form = '';
		$formOptions = [
			'method' => 'POST',
			'action' => "$wgServer$wgScript/Special:Ow_downloads"
		];
		$form .= Html::openElement( 'form', $formOptions );

		$form .= Html::element(
			'input',
			[
				'type' => 'hidden',
				'name' => 'create-' . $prefix,
				'value' => $languageId
			]
		);

		// submit button
		$form .= Html::element(
			'input',
			[
				'type' => 'submit',
				'value' => 'Regenerate'
			]
		);

		$form .= Html::closeElement( 'form' );
		return $form;
	}

	protected function createNewForm( $prefix ) {
		global $wgServer, $wgScript;

		$form = '';
		$formOptions = [
			'method' => 'GET',
			'action' => "$wgServer$wgScript/Special:Ow_downloads"
		];
		$form .= Html::openElement( 'form', $formOptions );

		// suggest combobox
		$form .= getSuggest(
			'create' . "-$prefix", // name, parameter transmitted in url
			'language', // query
			[] // html parameters
		);

		// submit button
		$form .= Html::element( 'input', [
			'type' => 'submit',
			// 'name' => $prefix . '-submit',
			'value' => 'Generate'
		] );

		$form .= Html::closeElement( 'form' );
		return $form;
	}

	function updateOwdStatus( $fileName ) {
		global $wgWldDownloadScriptPath;
		$downloadIni = $wgWldDownloadScriptPath . 'downloads.ini';

		$zipName = preg_replace( '/\.csv/', '_csv.zip', $fileName );
		if ( preg_match( '/(owd\_.+\.csv)/', $fileName, $match ) ) {
			$fnPattern = $match[1];
		}
		$iniZippedAs = preg_replace( '/\.csv/', '.ini', $fileName );
		if ( preg_match( '/(owd\_.+\.ini)/', $iniZippedAs, $match ) ) {
			$iniZippedAs = $match[1];
		}

		$rh = zip_open( $zipName );
		while ( $zipEntry = zip_read( $rh ) ) {
			if ( zip_entry_name( $zipEntry ) == $iniZippedAs ) {
				while ( $file = zip_entry_read( $zipEntry ) ) {
					$lines = explode( "\n", $file );
					foreach ( $lines as $line ) {
						if ( preg_match( '/^version\:(.+)$/', $line, $match ) ) {
							$owdVersionId = $match[1];
						}
						// version 1.0
						if ( preg_match( '/^transaction_id\: (.+)$/', $line, $match ) ) {
							$owdTransactionId = $match[1];
						}
					}
				}
			}
			$fhz = zip_entry_open( $rh, $zipEntry );
		}
		zip_close( $rh );

		$matchFound = false;
		$latest = true;
		$reconstructedLine = [];

		foreach ( $this->updateCheckList as $row ) {
			if ( $row[0] == $fnPattern ) {
				$matchFound = true;

				if ( $row[1] != $owdTransactionId ) {
					$newLine = $fnPattern . "	" . $owdTransactionId . "	" . $owdVersionId . "\n";
					$latest = false;
				} else {
					$newLine = $row[0] . "	" . $row[1] . "	" . $row[2] . "\n";
				}
			} else {
				$newLine = $row[0] . "	" . $row[1] . "	" . $row[2] . "\n";
			}
			$reconstructedLine[] = $newLine;
		}

		if ( !$matchFound ) {
			$fh = fopen( $downloadIni, 'a' );
			fwrite( $fh, $fnPattern . "	" . $owdTransactionId . "	" . $owdVersionId . "\n" );
			fclose( $fh );
		}

		if ( $matchFound && !$latest ) {
			$fh = fopen( $downloadIni, 'w' );
			foreach ( $reconstructedLine as $row ) {
				fwrite( $fh, $row );
			}
			fclose( $fh );
		}
		$reconstructedLine = [];
	}

	protected function segregateGroups( $directory ) {
		foreach ( $directory as $files ) {
			// definitions
			if ( preg_match( '/^def_/', $files ) ) {
				$this->definitions[] = "$files";
			}
			// definitions
			if ( preg_match( '/^exp_/', $files ) ) {
				$this->expressions[] = "$files";
			}
			// translations
			if ( preg_match( '/^trans_/', $files ) ) {
				$this->translations[] = "$files";
			}
			// OmegaWiki Development
			if ( preg_match( '/^owd_.+zip$/', $files ) ) {
				$this->development[] = "$files";
			}
			// check if 'downloads.ini' exists
			if ( 'downloads.ini' == $files ) {
				$this->downloadIniExist = true;
			}
		}
	}

	protected function getUpdateChecks() {
		// if 'downloads.ini' exists and check status is needed
		// get updateCheck's transaction number else create blank file
		global $wgWldDownloadScriptPath;
		$downloadIni = $wgWldDownloadScriptPath . 'downloads.ini';

		$updateCheck = [];
		if ( $this->downloadIniExist == true ) {
			$contents = file_get_contents( $downloadIni );
			$lines = explode( "\n", $contents );
			foreach ( $lines as $content ) {
				$line = explode( "	", $content );
				if ( $line[0] == $this->checkOwdFilename && isset( $line[1] ) ) {
					$this->updateCheck = $line[1];
				}
				if ( isset( $line[1] ) ) {
					$this->updateCheckList[] = [
						$line[0],
						$line[1],
						$line[2]
					];
				}
			}
		} else {
			$fh = fopen( $downloadIni, 'w' );
			fclose( $fh );
		}
		$contents = null;
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
