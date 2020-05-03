<?php
/**
 * Create a list of OmegaWiki Developer data which can be used to generate
 * Dictionaries.
 *
 * TODO
 * Monolingual:
 * Final download file would be compressed as owl_fra_csv.zip
 */

global $wgWldOwScriptPath, $wgWldIncludesScriptPath;
require_once $wgWldOwScriptPath . 'Attribute.php';
require_once $wgWldOwScriptPath . 'DefinedMeaning.php';
require_once $wgWldOwScriptPath . 'Expression.php';
require_once $wgWldIncludesScriptPath . 'formatCSV.php';

class CreateOwdListJob extends Job {

	public function __construct( $title, $params ) {
		parent::__construct( 'CreateOwdList', $title, $params );
	}

	/**
	 * Execute the job
	 * @return bool
	 */
	public function run() {
		// Load data from $this->params and $this->title
		$this->version = '1.1';
		if ( isset( $this->params['langcode'] ) ) {
			$languageId = $this->params['langcode'];
		}

		if ( isset( $this->params['type'] ) ) {
			$type = $this->params['type'];
		}

		if ( isset( $this->params['format'] ) ) {
			$format = $this->params['format'];
		}

		if ( isset( $this->params['start'] ) ) {
			$start = $this->params['start'];
		}

		// Perform your updates

		if ( $type && $languageId && $format && $start ) {
			$this->createList( $type, $languageId, $format, $start );
			return true;
		}
		return false;
	}

	protected function createList( $type, $code, $format, $start ) {
		global $wgWldDownloadScriptPath;
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );
		$csv = new WldFormatCSV();

		// the greater the value of $sqlLimit the faster the download file is
		// finished but the slower each web page loads while the job is being
		// processed.
		$sqlLimit = 50;

		$options['OFFSET'] = $start;

		$options['LIMIT'] = $sqlLimit;

		// Why order by defined_meaning_id? To avoid duplication of words
		// and skipping of some. When a language is constantly edited,
		// order by spelling would not accurately get all unique expressions
		// when job queued.
		$options['ORDER BY'] = 'defined_meaning_id';

		// language specifics
		$languageId = getLanguageIdForIso639_3( $code );
		if ( !$languageId ) {
			return false;
		}
		$this->DefinedMeanings = new DefinedMeanings;
		$languageDefinedMeaningIds = DefinedMeanings::getLanguageIdDefinedMeaningId( $languageId, $options );

		// create File name
		$zippedAs = $type . "_$code" . ".$format";
		$synZippedAs = $type . '_syn_' . $code . ".$format";
		$attZippedAs = $type . '_att_' . $code . ".$format";
		$iniZippedAs = $type . "_$code" . '.ini';
		$miaZippedAs = $type . "_$code" . '.mia';

		$zipName = $wgWldDownloadScriptPath . $type . "_$code" . "_$format" . ".zip";

		// When someone updates the file while someone is
		// downloading the file, the file may ( in my mind ),
		// be corrupted. So process it first as a temporary file,
		// delete the original file, and rename the temporary file ~he
		$tempFileName = $wgWldDownloadScriptPath;
		$tempFileName .= $zippedAs;
		$tempSynFileName = $wgWldDownloadScriptPath;
		$tempSynFileName .= $synZippedAs;
		$tempAttFileName = $wgWldDownloadScriptPath;
		$tempAttFileName .= $attZippedAs;
		$tempIniFileName = $wgWldDownloadScriptPath;
		$tempIniFileName .= $iniZippedAs;
		$tempMiaFileName = $wgWldDownloadScriptPath;
		$tempMiaFileName .= $miaZippedAs;

		if ( $start == 1 ) {
			$translations = [];
			$fh = fopen( $tempFileName, 'w' );
			$fhsyn = fopen( $tempSynFileName, 'w' );
			$fhatt = fopen( $tempAttFileName, 'w' );
			$fhini = fopen( $tempIniFileName, 'w' );
				fwrite( $fhini, $this->getIniFile() );
			$fhmia = fopen( $tempMiaFileName, 'w' );
		} else {
			$fh = fopen( $tempFileName, 'a' );
			$fhsyn = fopen( $tempSynFileName, 'a' );
			$fhatt = fopen( $tempAttFileName, 'a' );
			$fhini = fopen( $tempIniFileName, 'a' );
			$fhmia = fopen( $tempMiaFileName, 'a' );
		}
		// Add data
		$ctr = 0;
		if ( $start != 0 ) {
			$this->Attributes = new Attributes;
			$this->Expressions = new Expressions;
			$this->Transactions = new Transactions;
			$this->ClassAttribute = new WLD_Class;
			$this->Collections = new Collections;
			$enId = [
				'IPA' => Attributes::getClassAttributeId( 'International Phonetic Alphabet', WLD_ENGLISH_LANG_ID ),
				'pinyin' => Attributes::getClassAttributeId( 'pinyin', WLD_ENGLISH_LANG_ID ),
				'hyphenation' => Attributes::getClassAttributeId( 'hyphenation', WLD_ENGLISH_LANG_ID ),
				'example' => Attributes::getClassAttributeId( 'example sentence', WLD_ENGLISH_LANG_ID ),
				'usage' => Attributes::getClassAttributeId( 'usage', WLD_ENGLISH_LANG_ID ),
				'POS' => Attributes::getClassAttributeId( 'part of speech', WLD_ENGLISH_LANG_ID ),
				'area' => Attributes::getClassAttributeId( 'area', WLD_ENGLISH_LANG_ID ),
				'GP' => Attributes::getClassAttributeId( 'grammatical property', WLD_ENGLISH_LANG_ID ),
				'number' => Attributes::getClassAttributeId( 'number', WLD_ENGLISH_LANG_ID ),
				'gender' => Attributes::getClassAttributeId( 'gender', WLD_ENGLISH_LANG_ID )
			];

			if ( $code == 'fra' ) {
				$fraId['gender'] = Attributes::getClassAttributeId( 'genre', $languageId );
				$fraId['gender2'] = Attributes::getClassAttributeId( 'sexe', $languageId );
			}

			$totalDefinedMeaning = count( $languageDefinedMeaningIds );
			foreach ( $languageDefinedMeaningIds as $definedMeaningId ) {
				$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
				$text = preg_replace( '/\n/', '\\n', $text );
				$text = $csv->formatCSVcolumn( $text );

				$expressions = Expressions::getDefinedMeaningIdAndLanguageIdExpressions( $languageId, $definedMeaningId );

				foreach ( $expressions as $spelling ) {
					$IPA = null;
					$pinyin = null;
					$hyphenation = null;
					$example = null;
					$usage = null;

					$expressionId = getExpressionId( $spelling, $languageId );
					$syntransId = getSynonymId( $definedMeaningId, $expressionId );

					$textAttributes = Attributes::getTextAttributes( $syntransId, [ 'languageId' => $languageId ] );
					foreach ( $textAttributes as $row ) {
						$row['text'] = preg_replace( '/\n/', '\\n', $row['text'] );
						// Convert tabs to space
						$row['text'] = preg_replace( '/\t/', ' ', $row['text'] );

						if ( !$row['attribute_name'] ) {
							$attribute_name = '<' . $row['attribute_id'] . '/>';
						} else {
							$attribute_name = preg_replace( '/\n/', '\\n', $row['attribute_name'] );
						}
						$attribute_value = preg_replace( '/\n/', '\\n', $row['text'] );
						fwrite( $fhatt,
							$syntransId .
							',' . $row['attribute_id'] .
							',0' .
							',' . $csv->formatCSVcolumn( 'TEXT' ) .
							',' . $csv->formatCSVcolumn( $attribute_name ) .
							',' . $csv->formatCSVcolumn( $attribute_value ) .
							"\n"
						);

						if ( $row['attribute_id'] == $enId['IPA'] ) {
							$IPA .= $row['text'] . ';';
							$row = [ 'text' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['pinyin'] ) {
							$pinyin .= $row['text'] . ';';
							$row = [ 'text' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['hyphenation'] ) {
							$hyphenation .= $row['text'] . ';';
							$row = [ 'text' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['example'] ) {
							$example .= $row['text'] . ';';
							$row = [ 'text' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['usage'] ) {
							$usage .= $row['text'] . ';';
							$row = [ 'text' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['text'] != null ) {
							fwrite(
								$fhmia,
								"SYNTRANS TEXT: " . $row['attribute_name'] .
								" (" . $row['attribute_id'] . ") => " .
								$row['text'] . "\n"
							);
						}

					}
					$textAttributes = [];
					$IPA = $this->removeAllEndSemiColon( $IPA );
					$pinyin = $this->removeAllEndSemiColon( $pinyin );
					$hyphenation = $this->removeAllEndSemiColon( $hyphenation );
					$example = $this->removeAllEndSemiColon( $example );
					$usage = $this->removeAllEndSemiColon( $usage );

					$POS = null;
					$oUsage = null;
					$area = null;
					$grammaticalProperty = null;
					$number = null;
					$gender = null;

					$optionAttributes = Attributes::getOptionAttributes( $syntransId, [ 'languageId' => $languageId ] );
					foreach ( $optionAttributes as $row ) {
						$row['attribute_option_name'] = preg_replace( '/\n/', '\\n', $row['attribute_option_name'] );
						// convert tabs to space
						$row['attribute_option_name'] = preg_replace( '/\t/', ' ', $row['attribute_option_name'] );

						if ( !$row['attribute_name'] ) {
							$attribute_name = '<' . $row['attribute_id'] . '/>';
						} else {
							$attribute_name = preg_replace( '/\n/', '\\n', $row['attribute_name'] );
						}
						if ( !$row['attribute_option_name'] ) {
							$attribute_value = '<' . $row['option_id'] . '/>';
						} else {
							$attribute_value = preg_replace( '/\n/', '\\n', $row['attribute_option_name'] );
						}
						fwrite( $fhatt,
							$syntransId .
							',' . $row['attribute_id'] .
							',' . $row['option_id'] .
							',' . $csv->formatCSVcolumn( 'OPTN' ) .
							',' . $csv->formatCSVcolumn( $attribute_name ) .
							',' . $csv->formatCSVcolumn( $attribute_value ) .
							"\n"
						);

						if ( $row['attribute_id'] == $enId['POS'] ) {
							$POS .= $row['attribute_option_name'] . ';';
							$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['usage'] ) {
							$oUsage .= $row['attribute_option_name'] . ';';
							$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['area'] ) {
							$area .= $row['attribute_option_name'] . ';';
							$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['GP'] ) {
							$grammaticalProperty .= $row['attribute_option_name'] . ';';
							$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['number'] ) {
							$number .= $row['attribute_option_name'] . ';';
							$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $row['attribute_id'] == $enId['gender'] ) {
							$gender .= $row['attribute_option_name'] . ';';
							$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
						}
						if ( $code == 'fra' ) {
							if (
								$row['attribute_id'] == $fraId['gender'] or
								$row['attribute_id'] == $fraId['gender2']
							) {
								$gender .= $row['attribute_option_name'] . ';';
								$row = [ 'attribute_option_name' => null, 'attribute_name' => null, 'attribute_id' => null ];
							}
						}
						if ( $row['attribute_name'] != null ) {
							fwrite(
								$fhmia,
								"SYNTRANS OPTN: " . $row['attribute_name'] .
								" (" . $row['attribute_id'] . ") => " .
								$row['attribute_option_name'] . "\n"
							);
						}

					}
					$optionAttributes = [];
					$POS = $this->removeAllEndSemiColon( $POS );
					$oUsage = $this->removeAllEndSemiColon( $oUsage );
					$area = $this->removeAllEndSemiColon( $area );
					$grammaticalProperty = $this->removeAllEndSemiColon( $grammaticalProperty );
					$number = $this->removeAllEndSemiColon( $number );
					$gender = $this->removeAllEndSemiColon( $gender );

					fwrite( $fhsyn,
						$definedMeaningId .
						',' . $languageId .
						',' . $syntransId .
						',' . $csv->formatCSVcolumn( $spelling ) .
						',' . $csv->formatCSVcolumn( $IPA ) .
						',' . $csv->formatCSVcolumn( $hyphenation ) .
						',' . $csv->formatCSVcolumn( $example ) .
						',' . $csv->formatCSVcolumn( $usage ) .
						',' . $csv->formatCSVcolumn( $POS ) .
						',' . $csv->formatCSVcolumn( $oUsage ) .
						',' . $csv->formatCSVcolumn( $area ) .
						',' . $csv->formatCSVcolumn( $grammaticalProperty ) .
						',' . $csv->formatCSVcolumn( $number ) .
						',' . $csv->formatCSVcolumn( $pinyin ) .
						',' . $csv->formatCSVcolumn( $gender ) .
						"\n"
					);

				}

				$optionAttributes = Attributes::getOptionAttributes( $definedMeaningId, [ 'languageId' => $languageId ] );
				$subject = null;

				$attributeExpressionId = getExpressionId( 'topic', WLD_ENGLISH_LANG_ID );
				$levelMeaningId = $dbr->selectField(
					"{$dc}_bootstrapped_defined_meanings",
					'defined_meaning_id',
					[ 'name' => 'DefinedMeaning' ],
					__METHOD__
				);
				$attributeId = getOptionAttributeOptionsAttributeIdFromExpressionId( $attributeExpressionId, WLD_ENGLISH_LANG_ID, $levelMeaningId );
				$attributeMid = $dbr->selectField(
					"{$dc}_class_attributes",
					'attribute_mid',
					[ 'object_id' => $attributeId ],
					__METHOD__
				);
				foreach ( $optionAttributes as $row ) {
					if ( $row['attribute_id'] == $attributeMid ) {
						if ( $row['attribute_option_name'] ) {
							$subject .= $row['attribute_option_name'] . ';';
						} else {
							$subject .= '<' . $row['option_id'] . '/>';
						}
						$row = [ 'attribute_option_name' => null, 'attribute_name' => null ];
					}
					if ( $row['attribute_name'] != null ) {
						fwrite(
							$fhmia,
							"DM OPTN: " . $row['attribute_name'] .
							" (" . $row['attribute_id'] . ") => " .
							$row['attribute_option_name'] . "\n"
						);
					}
				}
				$subject = trim( $this->removeAllEndSemiColon( $subject ) );

				// Get Class Attributes
				$classes = $this->ClassAttribute->getDefinedMeaningIdClassMembershipExpressions( $definedMeaningId, $languageId );
				$classAttribute = null;
				foreach ( $classes as $row ) {
					if ( $row['expression'] ) {
						$classAttribute .= $row['expression'] . ';';
					} else {
						$classAttribute .= '<' . $row['definedMeaningId'] . '/>;';
					}
				}
				$classes = [];
				$classAttribute = trim( $this->removeAllEndSemiColon( $classAttribute ) );

				// Get Collection Attributes
				$collections = $this->Collections->getDefinedMeaningIdCollectionMembershipExpressions( $definedMeaningId, $languageId );
				$collection = null;
				foreach ( $collections as $row ) {
					if ( $row['expression'] ) {
						$collection .= $row['expression'] . ';';
					} else {
						$collection .= '<' . $row['definedMeaningId'] . '/>;';
					}
				}
				$classes = [];
				$collection = trim( $this->removeAllEndSemiColon( $collection ) );

				fwrite( $fh,
					$definedMeaningId .
					',' . $languageId .
					',' . $text .
					',' . $csv->formatCSVcolumn( $subject ) .
					',' . $csv->formatCSVcolumn( $classAttribute ) .
					',' . $csv->formatCSVcolumn( $collection ) .
					"\n"
				);
				$ctr++;

			}

		}
		fclose( $fh );
		fclose( $fhsyn );
		fclose( $fhatt );
		fclose( $fhmia );

		// incomplete job
		if ( $ctr == $sqlLimit ) {
			fclose( $fhini );
			$jobParams = [ 'type' => $type, 'langcode' => $code, 'format' => $format ];
			$jobParams['start'] = $start + $sqlLimit;
			$jobName = 'User:JobQuery/' . $type . '_' . $code . '.' . $format;
			$title = Title::newFromText( $jobName );
			$job = new CreateOwdListJob( $title, $jobParams );
			JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
		} else { // complete job
			// record transaction_ids
			$transactionId = Transactions::getLanguageIdLatestTransactionId( $languageId, [ 'is_the_job' => true ] );
			fwrite( $fhini,
				'transaction_id: ' . $transactionId . "\n"
			);
			fclose( $fhini );

			// insert transactionId to 'downloads.ini'
			$fileName = $type . '_' . $code . '.' . $format;
			$downloadIni = $wgWldDownloadScriptPath . 'downloads.ini';
			$reconstructLine = [];
			$contents = null;

			$contents = file_get_contents( $downloadIni );
			$lines = explode( "\n", $contents );
			foreach ( $lines as $line ) {
				$checkLine = explode( "	", $line );
				if ( preg_match( '/' . $fileName . '/', $checkLine[0] ) and isset( $checkLine[1] ) ) {
					$reconstructLine[] = $fileName . "	" . $transactionId . "	" . $this->version . "\n";
				} else {
					if ( $line != '' ) {
						$reconstructLine[] = $line . "\n";
					}
				}
			}

			$fh = fopen( $downloadIni, 'w' );
			foreach ( $reconstructLine as $row ) {
				fwrite( $fh, $row );
			}
			fclose( $fh );

			// Zip file
			if ( file_exists( $zipName ) ) {
				unlink( $zipName );
			}
			$zip = new ZipArchive();
			$zip->open( $zipName, ZipArchive::CREATE );
			$zip->addfile( $tempFileName, $zippedAs );
			$zip->addfile( $tempSynFileName, $synZippedAs );
			$zip->addfile( $tempAttFileName, $attZippedAs );
			$zip->addfile( $tempIniFileName, $iniZippedAs );
			$zip->addfile( $tempMiaFileName, $miaZippedAs );
			$zip->close();
			unlink( $tempFileName );
			unlink( $tempSynFileName );
			unlink( $tempAttFileName );
			unlink( $tempIniFileName );
			unlink( $tempMiaFileName );
		}
	}

	protected function removeAllEndSemiColon( $line ) {
		while ( preg_match( '/;$/', $line ) ) {
			$line = preg_replace( '/;$/', '', $line );
		}
		return $line;
	}

	protected function getIniFile() {
		return "version:" . $this->version . "\n" .
		"help: Contents:\n" .
		"help: owd_<languageCode>.csv - contains definitions\n" .
		"help: owd_syn_<languageCode>.csv - contains synonyms and translations with annotations\n" .
		"help: owd_att_<languageCode>.csv - contains list of annotations that can be linked to other language files annotations.\n" .
		"help: owd_<languageCode>.mia - contains attributes that were not included in this version\n";
	}

}
