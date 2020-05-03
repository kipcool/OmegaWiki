<?php

require_once 'WikiDataGlobals.php';
require_once 'WikiDataAPI.php';

class Expression {
	public $id;
	public $spelling;
	public $languageId;
	public $meaningIds = [];
	public $dataset;

	function __construct( $id, $spelling, $languageId, $dc = null ) {
		$this->id = $id;
		$this->spelling = $spelling;
		$this->languageId = $languageId;
		if ( $dc === null ) {
			$this->dataset = wdGetDataSetContext();
		} else {
			$this->dataset = $dc;
		}
	}

	function createNewInDatabase() {
		$wikipage = $this->createExpressionPage();
		createInitialRevisionForPage( $wikipage, 'Created by adding expression' );
	}

	function createExpressionPage() {
		return createPage( NS_EXPRESSION, getPageTitle( $this->spelling ) );
	}

	function isBoundToDefinedMeaning( $definedMeaningId ) {
		return expressionIsBoundToDefinedMeaning( $definedMeaningId, $this->id );
	}

	function bindToDefinedMeaning( $definedMeaningId, $identicalMeaning = "true" ) {
		createSynonymOrTranslation( $definedMeaningId, $this->id, $identicalMeaning );
	}

	function assureIsBoundToDefinedMeaning( $definedMeaningId, $identicalMeaning ) {
		if ( !$this->isBoundToDefinedMeaning( $definedMeaningId ) ) {
			$this->bindToDefinedMeaning( $definedMeaningId, $identicalMeaning );
		}
	}
}

class Expressions {

	public function __construct() {
	}

	/** @brief creates a new Expression entry.
	 *
	 * @param string $spelling req'd
	 * @param int $languageId req'd
	 * @param array $options opt'l
	 *
	 * 	options:
	 * 		updateId int Inserts a transaction id instead of the updated one.
	 * 		dc       str The data set
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::createExpressionId instead.
	 */
	public static function createId( $spelling, $languageId, $options = [] ) {
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		} else {
			$dc = wdGetDataSetContext();
		}
		$dbw = wfGetDB( DB_MASTER );

		$expressionId = newObjectId( "{$dc}_expression" );
		if ( isset( $options['updateId'] ) ) {
			if ( $options['updateId'] === -1 ) {
				$updateId = getUpdateTransactionId();
			} else {
				$updateId = $options['updateId'];
			}
		} else {
			$updateId = getUpdateTransactionId();
		}

		$dbw->insert(
			"{$dc}_expression",
			[ 'expression_id' => $expressionId,
				'spelling' => $spelling,
				'language_id' => $languageId,
				'add_transaction_id' => $updateId
			], __METHOD__
		);
		return $expressionId;
	}

	/** @ core getId function
	 *
	 * @param string $spelling req'd
	 * @param int|null $languageId opt'l
	 * @param array $options opt'l
	 *
	 * @return string expression id for the languageId indicated.
	 * @return array The first expressionId/languageId [array( expessionId, languageId )] when languageId is skipped.
	 * 	options:
	 * 		dc           str The data set
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getTheExpressionId instead.
	 */
	public static function getId( $spelling, $languageId = null, $options = [] ) {
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		} else {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$opt = [
			'spelling' => $spelling,
			'remove_transaction_id' => null
		];

		// assumes that languageId does not exists
		$var = [ 'expression_id', 'language_id' ];
		// then checks if the languageId does exists
		if ( $languageId ) {
			$opt['language_id'] = $languageId;
			$var = 'expression_id';
			// selectField returns only one field. false if not exists.
			$expression = $dbr->selectField(
				$dc . '_expression', $var, $opt, __METHOD__
			);
		} else {
			// selectRow returns only one array. false if not exists.
			$expression = $dbr->selectRow(
				$dc . '_expression', $var, $opt, __METHOD__
			);
		}

		// if expression exists, returns either an array or a string
		if ( $expression ) {
			return $expression;
		}
		return null;
	}

	/** @ core getMeaningIds function
	 *
	 * @param string $spelling req'd
	 * @param array $languageIds opt'l
	 * @param array $options opt'l
	 *
	 * @return array list of defined meaning ids.
	 * @return array if empty, an empty array.
	 * 	options:
	 * 		dc           str The data set
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getTheExpressionMeaningIds instead.
	 */
	public static function getMeaningIds( $spelling, $languageIds = [], $options = [] ) {
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		} else {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$opt = [
			'spelling' => $spelling,
			'exp.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'exp.expression_id = synt.expression_id'
		];

		// adjust variables based on languageId existence.
		if ( $languageIds ) {
			// Sanity check: in case languageId is string, convert to array.
			if ( is_string( $languageIds ) ) {
				$temp = [ $languageIds ]; 	// if there is a better way to convert
				$languageIds = $temp;			// this, kindly refactor. thanks!
			}
			$var = [ 'defined_meaning_id', 'language_id' ];
		} else {
			$var = 'defined_meaning_id';
		}

		$queryResult = $dbr->select(
			[
				'exp' => "{$dc}_expression",
				'synt' => "{$dc}_syntrans"
			],
			$var,
			[
				'spelling' => $spelling,
				'exp.remove_transaction_id' => null,
				'synt.remove_transaction_id' => null,
				'exp.expression_id = synt.expression_id'
			], __METHOD__,
			'DISTINCT'
		);

		$dmlist = [];

		foreach ( $queryResult as $synonymRecord ) {
			if ( $languageIds ) {
				if ( in_array( $synonymRecord->language_id, $languageIds ) ) {
					$dmlist[] = $synonymRecord->defined_meaning_id;
				}
			} else {
				$dmlist[] = $synonymRecord->defined_meaning_id;
			}
		}

		return $dmlist;
	}

	/**
	 * returns the total number of "Expressions"
	 *
	 * else returns null
	 */
	public static function getNumberOfExpressions( $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$cond[] = null;

		$queryResult = $dbr->select(
			"{$dc}_expression",
			[
				'total' => 'count(expression_id)',
			],
			[
				'remove_transaction_id' => null
			],
			__METHOD__,
			$cond
		);

		$total = null;
		foreach ( $queryResult as $tot ) {
			$total = $tot->total;
		}

		if ( $total ) {
			return $total;
		}
		return null;
	}

	/**
	 * returns an array of "Expression" objects
	 * for a language
	 *
	 * else returns null
	 */
	public static function getLanguageIdExpressions( $languageId, $options = [], $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY'] = $options['ORDER BY'];
		} else {
			$cond['ORDER BY'] = 'spelling';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT'] = $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET'] = $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$queryResult = $dbr->select(
			"{$dc}_expression",
			'spelling',
			[
				'language_id' => $languageId,
				'remove_transaction_id' => null
			],
			__METHOD__,
			$cond
		);

		$expression = [];
		foreach ( $queryResult as $exp ) {
			$expression[] = $exp;
		}

		if ( $expression ) {
			return $expression;
		}
		return null;
	}

	/**
	 * returns an array of "Expression" objects
	 * for a defined meaning id for a language
	 *
	 * else returns null
	 */
	public static function getDefinedMeaningIdAndLanguageIdExpressions( $languageId, $definedMeaningId, $options = [], $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY'] = $options['ORDER BY'];
		} else {
			$cond['ORDER BY'] = 'spelling';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT'] = $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET'] = $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$queryResult = $dbr->select(
			[
				'synt' => "{$dc}_syntrans",
				'exp' => "{$dc}_expression"
			],
			'spelling',
			[
				'synt.expression_id = exp.expression_id',
				'language_id' => $languageId,
				'defined_meaning_id' => $definedMeaningId,
				'synt.remove_transaction_id' => null,
				'exp.remove_transaction_id' => null
			],
			__METHOD__,
			$cond
		);

		$expression = [];
		foreach ( $queryResult as $exp ) {
			$expression[] = $exp->spelling;
		}

		if ( $expression ) {
			return $expression;
		}
		return null;
	}

}
