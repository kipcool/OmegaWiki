<?php
/** @file
 *
 * @brief Contains attribute related classes
 */

/** @brief Default Attribute Class
 */
class Attribute {
	public $id = null;
	public $name = "";
	public $type = "";

	/**
	 * @param string|null $id (string) or null if type is Structure
	 * @param string $name
	 * @param string|Structure $type
	 *  If string, can be "language", "spelling", "boolean",
	 *  "defined-meaning", "defining-expression", "relation-type", "attribute",
	 *  "collection", "short-text", "text"
	 *
	 *  If Structure, see below.
	 */
	public function __construct( $id, $name, $type ) {
		$this->id = $id;
		$this->name = $name;
		$this->setAttributeType( $type );
	}

	public function setAttributeType( $type ) {
		# Copy the structure since we might modify it
		if ( $type instanceof Structure ) {
			$this->type = clone $type;
		} else {
			$this->type = $type;
		}

		// Since the attribute is a structure and unnamed, we use
		// the default label associated with it.
		if ( $this->id === null && ( $this->type instanceof Structure ) ) {
			$this->id = $this->type->getStructureType();
		// Override structure label with a more specific one
		} elseif ( $this->id !== null && ( $this->type instanceof Structure ) ) {
			$this->type->setStructureType( $this->id );
		}
	}

	public function getId() {
		return $this->id;
	}

	public function __tostring() {
		$id = $this->id;
		$name = $this->name;
		$type = $this->type;
		return "Attribute($id, $name, $type)";
	}
}

class Structure {
	private $attributes;
	private $type;

	public function getAttributes() {
		return $this->attributes;
	}

	public function addAttribute( Attribute $attribute ) {
		$this->attributes[] = $attribute;
	}

	public function getStructureType() {
		return $this->type;
	}

	public function setStructureType( $type ) {
		$this->type = $type;
	}

	/**
	 * Construct named Structure which contains Attribute objects
	 *
	 * @param string|Attribute[]|Attribute $argumentList
	 *  Can be an identifying string that describes the structure.
	 *                        Optional; if not specified, will be considered
	 *                        'anonymous-structure' unless there is only a
	 *                        a single Attribute object, in which case the structure
	 *                        will inherit its ID. Do not pass null.
	 *  Can be an array or parameter list of one or more Attribute objects.
	 */
	public function __construct( $argumentList ) {
		# We're trying to be clever.
		$args = func_get_args();
		$this->attributes = null;

		if ( $args[0] instanceof Attribute ) {
			$this->attributes = $args;
		} elseif ( is_array( $args[0] ) ) {
			$this->attributes = $args[0];
		}

		if ( is_array( $this->attributes ) ) {
			# We don't know what to call an unnamed
			# structure with multiple attributes.
			if ( count( $this->attributes ) > 1 ) {
				$this->type = 'anonymous-structure';
			# Meh, just one Attribute. Let's eat it.
			} elseif ( count( $this->attributes ) == 1 ) {
				$this->type = $this->attributes[0]->id;
			} else {
				$this->type = 'empty-structure';
			}

		# First parameter is the structure's name.
		} elseif ( is_string( $args[0] ) && !empty( $args[0] ) ) {
			$this->type = $args[0];
			if ( is_array( $args[1] ) ) {
				$this->attributes = $args[1];
			} else {
				array_shift( $args );
				$this->attributes = $args;
			}
		} else {
			# WTF?
			throw new Exception( "Invalid structure constructor: " . print_r( $args, true ) );
		}
	}

	public function supportsAttributeId( $attributeId ) {
		// $result = false;
		// $i = 0;
		//
		// while (!$result && $i < count($this->attributes)) {
		// $result = $this->attributes[$i]->id == $attributeId;
		// $i++;
		// }
		//
		// return $result;
		return true;
	}

	public function supportsAttribute( Attribute $attribute ) {
		return $this->supportsAttributeId( $attribute->id );
	}

	public function __tostring() {
		$result = "{";

		if ( count( $this->attributes ) > 0 ) {
			$result .= $this->attributes[0]->id;

			for ( $i = 1; $i < count( $this->attributes ); $i++ ) {
				$result .= ", " . $this->attributes[$i]->id;
			}
		}

		$result .= "}";

		return $result;
	}
}

/** @brief PHP API class for Attributes
 */
class Attributes {

	public function __construct() {
		require_once 'OmegaWikiDatabaseAPI.php';
	}

	/**
	 * @param int $objectId req'd the object id
	 * @param array $options opt'l
	 * @param string|null $dc opt'l the dataset to use
	 *
	 * @return array(
	 * 	'text' => $string,
	 * 	'attribute_name' => $string,
	 * 	'attribute_id' => $integer
	 * )
	 * @return if not exists, array()
	 *
	 * Note: $options can be used to introduce new variables
	 */
	public static function getTextAttributes( $objectId, $options = [], $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$cond = [];
		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY'] = $options['ORDER BY'];
		} else {
			$cond['ORDER BY'] = 'text';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT'] = $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET'] = $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$languageId = null;
		if ( isset( $options['languageId'] ) ) {
			$languageId = $options['languageId'];
		}

		$queryResult = $dbr->select(
			"{$dc}_text_attribute_values",
			[
				'text',
				'attribute_mid',
			],
			[
				'object_id' => $objectId,
				'remove_transaction_id' => null
			],
			__METHOD__,
			$cond
		);

		$textAttributes = [];
		$attributes = new Attributes;
		foreach ( $queryResult as $ta ) {
			$textAttributes[] = [
				'text' => $ta->text,
				'attribute_name' => $attributes->getAttributeName( $ta->attribute_mid, $languageId ),
				'attribute_id' => $ta->attribute_mid
			];
		}

		if ( $textAttributes ) {
			return $textAttributes;
		}
		return [];
	}

	/**
	 * @param int $objectId req'd the object id
	 * @param array $options opt'l
	 * @param string|null $dc opt'l the dataset to use
	 *
	 * @return array(
	 * 	'attribute_name' => $string,
	 * 	'attribute_option_name' => $string,
	 * 	'attribute_id' => $integer
	 * )
	 * @return if not exists array()
	 *
	 * @note $objectId can be either syntransId or definedMeaningId
	 *
	 * Note: $options can be used to introduce new variables
	 */
	public static function getOptionAttributes( $objectId, $options = [], $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$vars = [
			'oav.object_id' => $objectId,
			'oav.option_id = oao.option_id',
			'ca.object_id = oao.attribute_id',
			'oav.remove_transaction_id' => null,
			'oao.remove_transaction_id' => null,
			'ca.remove_transaction_id' => null
		];

		$cond = [];
		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT'] = $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET'] = $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$languageId = null;
		if ( isset( $options['languageId'] ) ) {
			$languageId = $options['languageId'];
		}

		$queryResult = $dbr->select(
			[
				'oav' => "{$dc}_option_attribute_values",
				'oao' => "{$dc}_option_attribute_options",
				'ca' => "{$dc}_class_attributes"
			],
			[
				'attribute_mid',
				'option_mid'
			],
			$vars,
			__METHOD__,
			$cond
		);

		$optionAttributes = [];
		$attributes = new Attributes;
		foreach ( $queryResult as $oa ) {
			$optionAttributes[] = [
				'attribute_name' => $attributes->getAttributeName( $oa->attribute_mid, $languageId ),
				'attribute_option_name' => $attributes->getAttributeName( $oa->option_mid, $languageId ),
				'attribute_id' => $oa->attribute_mid,
				'option_id' => $oa->option_mid
			];
		}

		if ( $optionAttributes ) {
			return $optionAttributes;
		}
		return [];
	}

	/** @brief getOptionsAttributeOption Template
	 * @param int $attributeId req'd
	 * @param int|null $optionMeaningId opt'l
	 * @param int|int[] $languageId req'd
	 * @param string|null $option opt'l
	 * 	- multiple multiple lines
	 * 	- exists   returns boolean, depending whether the queried values exists or not.
	 * @see use OwDatabaseAPI::getOptionAttributeOptions instead.
	 */
	public static function getOptionAttributeOptions( $attributeId, $optionMeaningId = null, $languageId, $option = null ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_REPLICA );

		$conds = [
			'attribute_id' => $attributeId,
			'language_id' => $languageId,
			'remove_transaction_id' => null
		];

		$vars = 'option_id';
		if ( $optionMeaningId ) {
			$conds['option_mid'] = $optionMeaningId;
		} else {
			$vars = [ $vars, 'option_mid' ];
		}

		if ( is_array( $vars ) ) {
			if ( $option == 'multiple' ) {
				$optionId = $dbr->select(
					"{$dc}_option_attribute_options",
					$vars,
					$conds, __METHOD__
				);
			} else {
				$optionId = $dbr->selectRow(
					"{$dc}_option_attribute_options",
					$vars,
					$conds, __METHOD__
				);
			}
		} else {
			$optionId = $dbr->selectField(
				"{$dc}_option_attribute_options",
				$vars,
				$conds, __METHOD__
			);
		}

		if ( $option == 'exists' ) {
			$returnTrue = true;
			$returnFalse = false;
		} else {
			$returnTrue = $optionId;
			$returnFalse = null;
		}

		if ( $optionId ) {
			return $returnTrue;
		}
		return $returnFalse;
	}

	/**
	 * @param int $attributeId req'd the attribute id
	 * @param int|null $languageId opt'l
	 * @param string|null $dc opt'l the dataset to use
	 *
	 * @return string The Attribute Name
	 * @return if not exist, null
	 */
	public static function getAttributeName( $attributeId, $languageId = null, $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$vars = [
			'synt.defined_meaning_id' => $attributeId,
			'synt.expression_id = exp.expression_id',
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		];

		if ( $languageId ) {
			$vars['exp.language_id'] = $languageId;
		}

		$attributeName = $dbr->selectField(
			[
				'exp' => "{$dc}_expression",
				'synt' => "{$dc}_syntrans",
			],
			'spelling',
			$vars,
			__METHOD__
		);

		if ( $attributeName ) {
			return $attributeName;
		}
		return null;
	}

	/**
	 * @brief Returns the Attribute Id of an Expression and/or a language id
	 *
	 * @param int $attributeExpression req'd The expression
	 * @param int|null $languageId opt'l The language id
	 * @param string|null $dc opt'l The dataset to use
	 *
	 * @return int the Option Attribute Id
	 * @return if not exist, null
	 */
	public static function getClassAttributeId( $attributeExpression, $languageId = null, $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$vars = [
			'synt.expression_id = exp.expression_id',
			'ca.attribute_mid = synt.defined_meaning_id',
			'spelling' => $attributeExpression,
			'language_id' => $languageId,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'ca.remove_transaction_id' => null
		];

		if ( $languageId ) {
			$vars['exp.language_id'] = $languageId;
		}

		$cond[] = 'DISTINCT';

		$attributeId = $dbr->selectField(
			[
				'exp' => "{$dc}_expression",
				'synt' => "{$dc}_syntrans",
				'ca' => "{$dc}_class_attributes",
			],
			'attribute_mid',
			$vars,
			__METHOD__,
			$cond
		);

		if ( $attributeId ) {
			return $attributeId;
		}
		return null;
	}

	/**
	 * @brief Returns the meaning_relations table's details via relation_id
	 *
	 * @param int $objectId req'd The object id
	 * @param array $options Optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param string|null $dc opt'l The WikiLexicalData dataset
	 *
	 * @return if exist, array( meaning1_id, relationtype_mid, meaning2_mid)
	 * @return if not, array()
	 *
	 * @note options parameter can be used to extend this function.
	 * Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getRelationIdRelationAttribute instead.
	 * Also note that this function currently includes all data, even removed ones.
	 */
	public static function getRelationIdRelation( $objectId, $options, $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_REPLICA );

		$test = false;
		if ( isset( $options['test'] ) ) {
			$test = true;
		}

		$relation = $dbr->selectRow(
			"{$dc}_meaning_relations",
			[
				'meaning1_mid',
				'relationtype_mid',
				'meaning2_mid'
			],
			[
				'relation_id' => $objectId
			], __METHOD__
		);

		if ( $relation ) {
			if ( $test ) {
				var_dump( $relation );
				die;
			}
			return $relation;
		}
		if ( $test ) {
			echo 'array()';
			die;
		}
		return [];
	}

}
