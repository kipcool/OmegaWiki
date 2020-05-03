<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

class SpecialSuggest extends SpecialPage {

	private $dbr;
	private $dc;
	private $o;
	private $userLangId;

	private $table;
	private $vars;
	private $conds;
	private $options;
	private $join_conds;

	function __construct() {
		parent::__construct( 'Suggest', 'UnlistedSpecialPage' );
	}

	function execute( $par ) {
		global $wgOut;
		require_once "Attribute.php";
		require_once "WikiDataBootstrappedMeanings.php";
		require_once "RecordSet.php";
		// This made my local copy useless, I do not know why. ~he
		// require_once( "Editor.php" );
		require_once "HTMLtable.php";
		require_once "Transaction.php";
		require_once "OmegaWikiEditors.php";
		require_once "Utilities.php";
		require_once "Wikidata.php";
		require_once "WikiDataTables.php";
		require_once "WikiDataGlobals.php";
		require_once 'OmegaWikiDatabaseAPI.php';

		$this->o = OmegaWikiAttributes::getInstance();
		$this->dc = wdGetDataSetContext();
		$this->dbr = wfGetDB( DB_REPLICA );
		$wgOut->disable();

		$request = $this->getRequest();
		$search = ltrim( $request->getVal( 'search-text' ) );
		$prefix = $request->getVal( 'prefix' );
		$query = $request->getVal( 'query' );
		$definedMeaningId = $request->getVal( 'definedMeaningId' );
		$offset = $request->getVal( 'offset' );
		$attributesLevel = $request->getVal( 'attributesLevel' );
		$annotationAttributeId = $request->getVal( 'annotationAttributeId' );
		$syntransId = $request->getVal( 'syntransId' );

		// retrieve languageCode from user global else from lang global
		$langCode = OwDatabaseAPI::getUserLanguage();
		$this->userLangId = getLanguageIdForCode( $langCode );

		if ( !$this->userLangId ) {
			// English default
			$this->userLangId = WLD_ENGLISH_LANG_ID;
		}

		$this->table = null;
		$this->vars = null;
		$this->conds = null;
		$this->options = null;
		$this->join_conds = null;

		$rowText = 'spelling';
		switch ( $query ) {
			case 'relation-type':
				$sqlActual = $this->getSQLForCollectionOfType( 'RELT' );
				$sqlFallback = $this->getSQLForCollectionOfType( 'RELT', WLD_ENGLISH_LANG_ID );
				$sql = $this->constructSQLWithFallback( $sqlActual, $sqlFallback, [ "member_mid", "spelling", "collection_mid" ] );
				$this->table = [ 'coalesced' => "({$this->sql})" ];
				$this->vars = '*';
				break;
			case 'class':
				$this->getParametersForClasses();
				break;
			case WLD_RELATIONS: // 'rel'
				if ( $attributesLevel == "DefinedMeaning" ) {
					$this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'DM' );
				} elseif ( $attributesLevel == "SynTrans" ) {
					$this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'SYNT' );
				}
				break;
			case 'text-attribute':
				$this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'TEXT' );
				break;
			case 'translated-text-attribute':
				$this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'TRNS' );
				break;
			case WLD_LINK_ATTRIBUTE:
				$this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'URL' );
				break;
			case WLD_OPTION_ATTRIBUTE:
				$this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'OPTN' );
				break;
			case 'language':
				require_once 'OmegaWikiDatabaseAPI.php';
				list(
					$this->table,
					$this->vars,
					$this->conds,
					$this->join_conds
				) = OwDatabaseAPI::getParametersForLanguageNames( $langCode, $this->o->getViewInformation()->getFilterLanguageList() );
				$rowText = 'language_name';
				break;
			case WLD_DEFINED_MEANING:
				$this->getParametersForDMs();
				break;
			case WLD_SYNONYMS_TRANSLATIONS:
				$this->getParametersForSyntranses();
				break;
			case 'class-attributes-level':
				$this->getParametersForLevels();
				break;
			case 'collection':
				$this->getParametersForCollection();
				break;
			case 'transaction':
				$this->table = [ "{$this->dc}_transactions" ];
				// @todo check vars compatibility with SQLite
				$this->vars = [
					'transaction_id', 'user_id', 'user_ip',
					'time' => " CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2), ' '," .
					" SUBSTRING(timestamp, 9, 2), ':', SUBSTRING(timestamp, 11, 2), ':', SUBSTRING(timestamp, 13, 2))", 'comment'
				];
				$this->conds = [ '1' ];

				$rowText = "CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2), ' '," .
						" SUBSTRING(timestamp, 9, 2), ':', SUBSTRING(timestamp, 11, 2), ':', SUBSTRING(timestamp, 13, 2))";
				break;
		}

		if ( $search != '' ) {
			if ( $query == 'transaction' ) {
				$this->conds[] = $rowText . $this->dbr->buildLike( $this->dbr->anyString(), $search, $this->dbr->anyString() );
			} elseif ( $query == 'class' ) {
				$this->conds[] = $rowText . $this->dbr->buildLike( $search, $this->dbr->anyString() );
			} elseif ( $query == WLD_RELATIONS or
				$query == WLD_LINK_ATTRIBUTE or
				$query == WLD_OPTION_ATTRIBUTE or
				$query == 'translated-text-attribute' or
				$query == 'text-attribute' ) {
				$this->options['HAVING'] = $rowText . $this->dbr->buildLike( $search, $this->dbr->anyString() );
			} elseif ( $query == 'language' ) {
				$this->options['HAVING'] = $rowText . $this->dbr->buildLike( $this->dbr->anyString(), $search, $this->dbr->anyString() );
			} elseif ( $query == 'relation-type' ) { // not sure in which case 'relation-type' happens...
				$this->conds[] = $rowText . $this->dbr->buildLike( $search, $this->dbr->anyString() );
			} else {
				$this->conds[] = $rowText . $this->dbr->buildLike( $search, $this->dbr->anyString() );
			}
		}

		if ( $query == 'transaction' ) {
			$orderBy = 'transaction_id DESC';
		} else {
			$orderBy = $rowText;
		}

		$this->options['ORDER BY'] = $orderBy;

		if ( $offset > 0 ) {
			$this->options['OFFSET'] = $offset;
		}

		// print only 10 results
		$this->options['LIMIT'] = 10;

		// remove duplicates
		if ( $query == 'relation-type' ) {
			$this->options[] = 'DISTINCT';
		}

		# == Actual query here
		// wfdebug("]]]".$sql."\n");
		$queryResult = $this->dbr->select(
			$this->table, $this->vars, $this->conds, __METHOD__,
			$this->options, $this->join_conds
		);

		# == Process query
		switch ( $query ) {
			case 'relation-type':
				list( $recordSet, $editor ) = $this->getRelationTypeAsRecordSet( $queryResult );
				break;
			case 'class':
				list( $recordSet, $editor ) = $this->getClassAsRecordSet( $queryResult );
				break;
			case WLD_RELATIONS:
				list( $recordSet, $editor ) = $this->getDefinedMeaningAttributeAsRecordSet( $queryResult );
				break;
			case 'text-attribute':
				list( $recordSet, $editor ) = $this->getTextAttributeAsRecordSet( $queryResult );
				break;
			case 'translated-text-attribute':
				list( $recordSet, $editor ) = $this->getTranslatedTextAttributeAsRecordSet( $queryResult );
				break;
			case WLD_LINK_ATTRIBUTE:
				list( $recordSet, $editor ) = $this->getLinkAttributeAsRecordSet( $queryResult );
				break;
			case WLD_OPTION_ATTRIBUTE:
				list( $recordSet, $editor ) = $this->getOptionAttributeAsRecordSet( $queryResult );
				break;
			case WLD_DEFINED_MEANING:
				list( $recordSet, $editor ) = $this->getDefinedMeaningAsRecordSet( $queryResult );
				break;
			case WLD_SYNONYMS_TRANSLATIONS:
				list( $recordSet, $editor ) = $this->getSyntransAsRecordSet( $queryResult );
				break;
			case 'class-attributes-level':
				list( $recordSet, $editor ) = $this->getClassAttributeLevelAsRecordSet( $queryResult );
				break;
			case 'collection':
				list( $recordSet, $editor ) = $this->getCollectionAsRecordSet( $queryResult );
				break;
			case 'language':
				list( $recordSet, $editor ) = $this->getLanguageAsRecordSet( $queryResult );
				break;
			case 'transaction':
				list( $recordSet, $editor ) = $this->getTransactionAsRecordSet( $queryResult );
				break;
		}

		$this->dbr->freeResult( $queryResult );
		$output = $editor->view( new IdStack( $prefix . 'table' ), $recordSet );

		echo $output;
	}

	/** Constructs a new SQL query from 2 other queries such that if a field exists
	 * in the fallback query, but not in the actual query, the field from the
	 * fallback query will be returned. Fields not in the fallback are ignored.
	 * You will need to state which fields in your query need to be returned.
	 * As a (minor) hack, the 0th element of $fields is assumed to be the key field.
	 */
	private function constructSQLWithFallback( $actual_query, $fallback_query, $fields ) {
		# if ($actual_query==$fallback_query)
		# return $actual_query;

		foreach ( $fields as $field ) {
			$vars[] = "COALESCE(actual.{$field}, fallback.{$field}) as {$field}";
		}

		$table['fallback'] = "({$fallback_query})";
		$table['actual'] = "({$actual_query})";

		$field0 = $fields[0]; # slightly presumptuous
		$join_conds = [ 'fallback' => [
			'LEFT JOIN', "actual.{$field0} = fallback.{$field0}"
		] ];

		$this->sql = $this->dbr->selectSQLText(
			$table,
			$vars,
			null, __METHOD__,
			null, $join_conds
		);

		// The sql produced above has errors, this is a quick fix ~he
		$this->sql = str_replace( "`.`", '.', $this->sql );
		$this->sql = str_replace( "``", '`', $this->sql );
		$this->sql = str_replace( "`(", '(', $this->sql );
		$this->sql = str_replace( ")`", ')', $this->sql );
	}

	/**
	 * Returns the list of attributes of a given $attributesType (DM, TEXT, TRNS, URL, OPTN)
	 * in the user language or in English
	 */
	private function getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, $attributesType ) {
		global $wgDefaultClassMids, $wgIso639_3CollectionId;

		$classMids = $wgDefaultClassMids;

		if ( ( $syntransId !== null ) && ( $wgIso639_3CollectionId !== null ) ) {
			// find the language of the syntrans and add attributes of that language
			// by adding the language DM to the list of default classes
			// this first query returns the language_id
			$language_id = OwDatabaseAPI::getLanguageId( [ 'sid' => $syntransId ] );

			// this second query finds the DM number for a given language_id
			$language_dm_id = $this->dbr->selectField(
				[ 'colcont' => $this->dc . '_collection_contents', 'lng' => 'language' ],
				'member_mid',
				[
					'lng.language_id' => $language_id,
					'colcont.collection_id' => $wgIso639_3CollectionId,
					'lng.iso639_3 = colcont.internal_member_id',
					'colcont.remove_transaction_id' => null
				], __METHOD__
			);

			if ( !$language_dm_id ) {
				// this language does not have an associated dm
				$classMids = $wgDefaultClassMids;
			} else {
				$classMids = array_merge( $wgDefaultClassMids, [ $language_dm_id ] );
			}
		}

		$this->getFilteredAttributesRestriction( $annotationAttributeId );

		// fallback is English, and second fallback is the DM id
			$this->vars = [
				'object_id',
				'attribute_mid'
			];
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$this->vars['spelling'] = 'COALESCE( exp_lng.spelling, exp_en.spelling, attribute_mid )';
		} else {
			$this->vars['spelling'] = 'COALESCE( exp_en.spelling, attribute_mid )';
		}
		$table = [
			'bdm' => "{$this->dc}_bootstrapped_defined_meanings",
			'clatt' => "{$this->dc}_class_attributes"
		];
		$tables = $this->getTableSQL( $table );
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$table = null;
			$join_conds = null;
			$table['synt_lng'] = "{$this->dc}_syntrans";
			$table['exp_lng'] = "{$this->dc}_expression";
			$join_conds['synt_lng'] = [
				'LEFT JOIN', [
					'clatt.attribute_mid = synt_lng.defined_meaning_id',
					'exp_lng.expression_id = synt_lng.expression_id',
					"exp_lng.language_id = {$this->userLangId}"
				]
			];
			$addJoinTable = $this->getComplexTableJoin( $table, $join_conds );
			$tables .= " $addJoinTable";
		}
		$table = null;
		$join_conds = null;
		$table['synt_en'] = "{$this->dc}_syntrans";
		$table['exp_en'] = "{$this->dc}_expression";
		$join_conds['synt_en'] = [
			'LEFT JOIN', [
				'clatt.attribute_mid = synt_en.defined_meaning_id',
				'exp_en.expression_id = synt_en.expression_id',
				'exp_en.language_id = ' . WLD_ENGLISH_LANG_ID
			]
		];
		$addJoinTable = $this->getComplexTableJoin( $table, $join_conds );
		$tables .= " $addJoinTable";
		$this->table = $tables;

		$this->conds = [
			'bdm.name' => $attributesLevel,
			'bdm.defined_meaning_id = clatt.level_mid',
			'clatt.attribute_type' => $attributesType // lacks $filteredAttributesRestriction
		];

		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$this->conds['synt_lng.remove_transaction_id'] = null;
		}
		$this->conds['synt_en.remove_transaction_id'] = null;
		$this->conds['clatt.remove_transaction_id'] = null;

		$iniConds = 'clatt.class_mid IN ( ';
		$insertSQL = $this->dbr->selectSQLText(
			[
				'clmem' => "{$this->dc}_class_membership"
			], 'class_mid',
			[
				"clmem.class_member_mid = {$definedMeaningId}",
				'clmem.remove_transaction_id' => null
			], __METHOD__
		);
		$insertSQL = preg_replace( '/,$/', '', $insertSQL );

		if ( count( $classMids ) > 0 ) {
			$finalConds = "OR clatt.class_mid IN (" . implode( ", ", $classMids ) . ")";
		}
		$this->conds[] = "{$iniConds}{$insertSQL} ) {$finalConds}";

		// group by to obtain unicity
		$this->options['GROUP BY'] = 'object_id';
	}

	private function getTableSQL( $table ) {
		$queryResult = $this->dbr->selectSQLText(
			$table, 'temp', null, __METHOD__
		);
		$queryResult = str_replace( '`', '', $queryResult );
		$queryResult = preg_replace( '/' . "SELECT .+ FROM " . '/', '', $queryResult );
		return $queryResult;
	}

	private function getComplexTableJoin( $table, $join_conds ) {
		// get join key
		foreach ( $join_conds as $key => $value ) {
			$joinTableKey = $key;
			$joinTypeKey = $value[0];
		}
		foreach ( $table as $key => $value ) {
			$tempTable['key'] = "`$value` `$key`";
			preg_match( '/' . $joinTableKey . '/', $tempTable['key'], $match );
			if ( $match ) {
				$replaceThis = $tempTable['key'];
			} else {
				$remove_this = $tempTable['key'];
			}
		}
		$queryResult = $this->dbr->selectSQLText(
			$table, 'temp', null, __METHOD__, null, $join_conds
		);
		$queryResult = preg_replace( '/' . $remove_this . ',/', '', $queryResult );
		$queryResult = preg_replace( '/' . "$remove_this $joinTypeKey" . '/', $joinTypeKey, $queryResult );
		$queryResult = preg_replace( '/' . "$replaceThis" . '/', "( $replaceThis, $remove_this )", $queryResult );
		$queryResult = preg_replace( '/' . "SELECT .+ FROM " . '/', '', $queryResult );
		$queryResult = str_replace( '`', '', $queryResult );
		return $queryResult;
	}

	private function getPropertyToColumnFilterForAttribute( $annotationAttributeId ) {
		global $wgPropertyToColumnFilters;

		$i = 0;
		$result = null;

		while ( $result == null && $i < count( $wgPropertyToColumnFilters ) ) {
			if ( $wgPropertyToColumnFilters[$i]->getAttribute()->id == $annotationAttributeId ) {
				$result = $wgPropertyToColumnFilters[$i];
			} else {
				$i++;
			}
		}
		return $result;
	}

	private function getFilteredAttributes( $annotationAttributeId ) {
		$propertyToColumnFilter = $this->getPropertyToColumnFilterForAttribute( $annotationAttributeId );

		if ( $propertyToColumnFilter != null ) {
			return $propertyToColumnFilter->attributeIDs;
		} else {
			return [];
		}
	}

	private function getAllFilteredAttributes() {
		global $wgPropertyToColumnFilters;

		$result = [];

		foreach ( $wgPropertyToColumnFilters as $propertyToColumnFilter ) {
			$result = array_merge( $result, $propertyToColumnFilter->attributeIDs );
		}

		return $result;
	}

	private function getFilteredAttributesRestriction( $annotationAttributeId ) {
		$propertyToColumnFilter = $this->getPropertyToColumnFilterForAttribute( $annotationAttributeId );

		if ( $propertyToColumnFilter != null ) {
			$filteredAttributes = $propertyToColumnFilter->attributeIDs;

			if ( count( $filteredAttributes ) > 0 ) {
				$this->conds[] = "clatt.attribute_mid IN (" . implode( ", ", $filteredAttributes ) . ")";
			} else {
				$this->conds[] = '0';
			}
		} else {
			$allFilteredAttributes = $this->getAllFilteredAttributes();

			if ( count( $allFilteredAttributes ) > 0 ) {
				$this->conds[] = "clatt.attribute_mid NOT IN (" . implode( ", ", $allFilteredAttributes ) . ")";
			}
		}
	}

	/**
	 * @return sql parameters for query needed to select a DM in a DM-DM relation
	 */
	private function getParametersForDMs() {
		$this->table = [ // tables
			'exp' => "{$this->dc}_expression",
			'synt' => "{$this->dc}_syntrans"
		];
		$this->vars = [ // fields
			'defined_meaning_id' => 'synt.defined_meaning_id',
			'spelling' => 'exp.spelling',
			'language_id' => 'exp.language_id'
		];
		$this->conds = [ // where
			'exp.remove_transaction_id' => null
		];
		$this->options = [ 'STRAIGHT_JOIN' ]; // options
		$this->join_conds = [ 'synt' => [ 'JOIN', [
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null
		] ] ];
	}

	/**
	 * @return sql parameters for query used to select a syntrans in a syntrans-syntrans relation
	 */
	private function getParametersForSyntranses() {
		$this->table = [ // tables
			'exp' => "{$this->dc}_expression",
			'synt' => "{$this->dc}_syntrans"
		];
		$this->vars = [ // fields
			'syntrans_sid' => 'synt.syntrans_sid',
			'defined_meaning_id' => 'synt.defined_meaning_id',
			'spelling' => 'exp.spelling',
			'language_id' => 'exp.language_id'
		];
		$this->conds = [ // where
			'exp.remove_transaction_id' => null
		];
		$this->options = [ 'STRAIGHT_JOIN' ]; // options
		$this->join_conds = [ 'synt' => [ 'JOIN', [
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null
		] ] ];
	}

	/**
	 * @return parameters needed to produce the name of all classes and their spelling
	 *	in the user language or in English
	 */
	private function getParametersForClasses() {
		$this->table = [
			'col_contents' => "{$this->dc}_collection_contents",
			'col' => "{$this->dc}_collection",
			'synt' => "{$this->dc}_syntrans",
			'exp' => "{$this->dc}_expression",
			'dm' => "{$this->dc}_defined_meaning"
		];
		$this->vars = [ 'member_mid', 'spelling' ];
		$this->conds = [
			"col.collection_type='CLAS'",
			"col_contents.collection_id = col.collection_id",
			"synt.defined_meaning_id = col_contents.member_mid",
			// "synt.identical_meaning" => 1,
			"exp.expression_id = synt.expression_id",
			"dm.defined_meaning_id = synt.defined_meaning_id"
		];

		// fallback is English
		$iniCond = "exp.language_id = {$this->userLangId} ";
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$notExistsQuery = $this->dbr->selectSQLText(
				[
					'synt2' => "{$this->dc}_syntrans",
					'exp2' => "{$this->dc}_expression"
				],
				'*',
				[
					'synt2.defined_meaning_id = synt.defined_meaning_id',
					'exp2.expression_id = synt2.expression_id',
					'exp2.language_id' => $this->userLangId,
					'synt2.remove_transaction_id' => null
				], __METHOD__,
				[ 'LIMIT' => 1 ]
			);
			$iniCond .= " OR ( language_id = " . WLD_ENGLISH_LANG_ID .
				" AND NOT EXISTS ( {$notExistsQuery} ) ) ";
		}
		$this->conds[] = $iniCond;

		$this->conds['col.remove_transaction_id'] = null;
		$this->conds['col_contents.remove_transaction_id'] = null;
		$this->conds['synt.remove_transaction_id'] = null;
		$this->conds['exp.remove_transaction_id'] = null;
		$this->conds['dm.remove_transaction_id'] = null;
	}

	private function getSQLForCollectionOfType( $collectionType, $language = "<ANY>" ) {
		$cond = [
			'colcont.collection_id = col.collection_id',
			'col.collection_type' => $collectionType,
			'synt.defined_meaning_id = colcont.member_mid',
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'col.remove_transaction_id' => null,
			'colcont.remove_transaction_id' => null
		];

		if ( $language != "<ANY>" ) {
			$cond['language_id'] = $language;
		}

		$sql = $this->dbr->selectSQLText(
			[
				'colcont' => $this->dc . '_collection_contents',
				'col' => $this->dc . '_collection',
				'synt' => $this->dc . '_syntrans',
				'exp' => $this->dc . '_expression'
			],
			[ 'member_mid', 'spelling', 'collection_mid' ],
			$cond,
			__METHOD__
		);

		return $sql;
	}

	private function getParametersForCollection() {
		$this->table = [
			'exp' => "{$this->dc}_expression",
			'col' => "{$this->dc}_collection",
			'synt' => "{$this->dc}_syntrans",
			'dm' => "{$this->dc}_defined_meaning"
		];
		$this->vars = [ 'collection_id', 'spelling' ];
		$this->conds = [
			'exp.expression_id=synt.expression_id',
			'synt.defined_meaning_id=col.collection_mid',
			'dm.defined_meaning_id = synt.defined_meaning_id',
			'synt.identical_meaning=1'
		];

		// fallback is English
		$iniCond = "exp.language_id = {$this->userLangId} ";
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$notExistsQuery = $this->dbr->selectSQLText(
				[
					'synt2' => "{$this->dc}_syntrans",
					'exp2' => "{$this->dc}_expression"
				], 'exp2.expression_id', // whatever
				[
					'synt2.defined_meaning_id = synt.defined_meaning_id',
					'exp2.expression_id = synt2.expression_id',
					'exp2.language_id' => $this->userLangId,
					'synt2.remove_transaction_id' => null
				], __METHOD__,
				[ 'LIMIT' => 1 ]
			);
			$iniCond .= " OR ( language_id = " . WLD_ENGLISH_LANG_ID .
				" AND NOT EXISTS ( {$notExistsQuery} ) ) ";
		}
		$this->conds[] = $iniCond;

		$this->conds['col.remove_transaction_id'] = null;
		$this->conds['synt.remove_transaction_id'] = null;
		$this->conds['exp.remove_transaction_id'] = null;
		$this->conds['dm.remove_transaction_id'] = null;
	}

	private function getParametersForLevels() {
		global $wgWldClassAttributeLevels;

		// TO DO: Add support for multiple languages here
		$this->table = [
			'dm' => "{$this->dc}_defined_meaning",
			'exp' => "{$this->dc}_expression",
			'bsdm' => "{$this->dc}_bootstrapped_defined_meanings"
		];
		$this->vars = [
			'bsdm.defined_meaning_id',
			'exp.spelling'
		];
		$this->conds = [
			'name IN (' . implodeFixed( $wgWldClassAttributeLevels ) . ')',
			'dm.defined_meaning_id = bsdm.defined_meaning_id',
			'dm.expression_id = exp.expression_id'
		];
	}

	private function getRelationTypeAsRecordSet( $queryResult ) {
		$relationTypeAttribute = new Attribute( "relation-type", wfMessage( 'ow_RelationType' )->text(), "short-text" );
		$collectionAttribute = new Attribute( "collection", wfMessage( 'ow_Collection' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $relationTypeAttribute, $collectionAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->member_mid, $row->spelling, OwDatabaseAPI::getDefinedMeaningExpression( $row->collection_mid ) ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $relationTypeAttribute ) );
		$editor->addEditor( createShortTextViewer( $collectionAttribute ) );

		return [ $recordSet, $editor ];
	}

	/**
	 * Writes an html table from a sql table corresponding to the list of classes, as shown by
	 * http://www.omegawiki.org/index.php?title=Special:Suggest&query=class
	 *
	 * @param stdClass[] $queryResult the result of a SQL query to be made into an html table
	 */
	function getClassAsRecordSet( $queryResult ) {
		// Setting the two column, with titles
		$classAttribute = new Attribute( "class", wfMessage( 'ow_Class' )->text(), "short-text" );
		$definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $classAttribute, $definitionAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->member_mid, $row->spelling, getDefinedMeaningDefinition( $row->member_mid ) ] );
		}

		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $classAttribute ) );
		$editor->addEditor( createShortTextViewer( $definitionAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getDefinedMeaningAttributeAsRecordSet( $queryResult ) {
		$definedMeaningAttributeAttribute = new Attribute( WLD_DM_ATTRIBUTES, wfMessage( 'ow_Relations' )->plain(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $definedMeaningAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->attribute_mid, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $definedMeaningAttributeAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getTextAttributeAsRecordSet( $queryResult ) {
		$textAttributeAttribute = new Attribute( "text-attribute", wfMessage( 'ow_TextAttributeHeader' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $textAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->attribute_mid, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $textAttributeAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getLinkAttributeAsRecordSet( $queryResult ) {
		$linkAttributeAttribute = new Attribute( WLD_LINK_ATTRIBUTE, wfMessage( 'ow_LinkAttributeHeader' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $linkAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->attribute_mid, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $linkAttributeAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getTranslatedTextAttributeAsRecordSet( $queryResult ) {
		$translatedTextAttributeAttribute = new Attribute( "translated-text-attribute", "Translated text attribute", "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $translatedTextAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->attribute_mid, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $translatedTextAttributeAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getOptionAttributeAsRecordSet( $queryResult ) {
		$optionAttributeAttribute = new Attribute( WLD_OPTION_ATTRIBUTE, wfMessage( 'ow_OptionAttributeHeader' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $optionAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->object_id, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $optionAttributeAttribute ) );

		return [ $recordSet, $editor ];
	}

	/**
	 * returns a table with three columns for selecting a DM:
	 * spelling / language / definition
	 * The three together represent a specific (unique) defined_meaning_id
	 */
	private function getDefinedMeaningAsRecordSet( $queryResult ) {
		$definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "definition" );

		$spellingLangDefStructure = new Structure( $this->o->id, $this->o->spelling, $this->o->language, $definitionAttribute );
		$recordSet = new ArrayRecordSet( $spellingLangDefStructure, new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$definition = getDefinedMeaningDefinition( $row->defined_meaning_id );

			$recordSet->addRecord( [ $row->defined_meaning_id, $row->spelling, $row->language_id, $definition ] );
		}

		$definitionEditor = new TextEditor( $definitionAttribute, new SimplePermissionController( false ), false, true, 75 );

		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $this->o->spelling ) );
		$editor->addEditor( createLanguageViewer( $this->o->language ) );
		$editor->addEditor( $definitionEditor );

		return [ $recordSet, $editor ];
	}

	/**
	 * returns a table with three columns for selecting a Syntrans:
	 * spelling / language / definition
	 * The three together represent a specific (unique) syntrans_sid
	 */
	private function getSyntransAsRecordSet( $queryResult ) {
		$definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "definition" );

		$spellingLangDefStructure = new Structure( $this->o->id, $this->o->spelling, $this->o->language, $definitionAttribute );
		$recordSet = new ArrayRecordSet( $spellingLangDefStructure, new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$definition = getDefinedMeaningDefinition( $row->defined_meaning_id );

			$recordSet->addRecord( [ $row->syntrans_sid, $row->spelling, $row->language_id, $definition ] );
		}

		$definitionEditor = new TextEditor( $definitionAttribute, new SimplePermissionController( false ), false, true, 75 );

		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $this->o->spelling ) );
		$editor->addEditor( createLanguageViewer( $this->o->language ) );
		$editor->addEditor( $definitionEditor );

		return [ $recordSet, $editor ];
	}

	private function getClassAttributeLevelAsRecordSet( $queryResult ) {
		$classAttributeLevelAttribute = new Attribute( "class-attribute-level", wfMessage( 'ow_ClassAttributeLevel' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet( new Structure( $this->o->id, $classAttributeLevelAttribute ), new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->defined_meaning_id, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $classAttributeLevelAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getCollectionAsRecordSet( $queryResult ) {
		$collectionAttribute = new Attribute( "collection", wfMessage( 'ow_Collection' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet( new Structure( $this->o->id, $collectionAttribute ), new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->collection_id, $row->spelling ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $collectionAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getLanguageAsRecordSet( $queryResult ) {
		$languageAttribute = new Attribute( "language", wfMessage( 'ow_Language' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet( new Structure( $this->o->id, $languageAttribute ), new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->row_id, $row->language_name ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $languageAttribute ) );

		return [ $recordSet, $editor ];
	}

	private function getTransactionAsRecordSet( $queryResult ) {
		$userAttribute = new Attribute( "user", wfMessage( 'ow_User' )->text(), "short-text" );
		$timestampAttribute = new Attribute( "timestamp", wfMessage( 'ow_Time' )->text(), "timestamp" );
		$summaryAttribute = new Attribute( "summary", wfMessage( 'ow_transaction_summary' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $userAttribute, $timestampAttribute, $summaryAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( [ $row->transaction_id, getUserLabel( $row->user_id, $row->user_ip ), $row->time, $row->comment ] );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $timestampAttribute ) );
		$editor->addEditor( createShortTextViewer( $this->o->id ) );
		$editor->addEditor( createShortTextViewer( $userAttribute ) );
		$editor->addEditor( createShortTextViewer( $summaryAttribute ) );

		return [ $recordSet, $editor ];
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
