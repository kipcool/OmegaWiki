<?php

require_once 'forms.php';
require_once 'converter.php';
require_once 'Attribute.php';
require_once 'Record.php';

abstract class RecordSet {
	abstract public function getStructure();

	abstract public function getKey();

	abstract public function getRecordCount();

	abstract public function getRecord( $index );

	protected $records;
	# public function save(); # <- we first need to implement, then uncomment

/**
	* @return carriage return separated list of values
	*/
	public function __tostring() {
		return $this->tostring_indent();
	}

	public function tostring_indent( $depth = 0, $key = "", $myname = "RecordSet" ) {
		$rv = "\n" . str_pad( "", $depth * 8 );
		$str = $this->getStructure();
		$type = $str->getStructureType();
		$rv .= "$key:$myname(... $type) {";
		$rv2 = $rv;
		foreach ( $this->records as $value ) {
			$rv = $rv2;
			$methods = get_class_methods( get_class( $value ) );
			if ( $methods !== null ) {
				if ( in_array( "tostring_indent", $methods ) ) {
					$value = $value->tostring_indent( $depth + 1 );
				}
			}
			$rv .= "$value";

			$rv2 = $rv;
			$rv2 .= ", ";
		}
		$rv .= "}";

		return $rv;
	}

}

class ArrayRecordSet extends RecordSet {
	protected $structure;
	protected $key;

	/**
	 * an ArrayRecordSet basically contains $records, an array of Record
	 */
	protected $records = [];

	/**
	 * extraCaption stores an array of caption that can be added above a record
	 * such as a "noun", "verb" (if sorted by pos) or an "etymology 1" (if sorted by etym)
	 */
	protected $extraHierarchyCaption = [];

	public function __construct( Structure $structure, $key ) {
		$this->structure = $structure;
		$this->key = $key;
	}

	public function add( $record ) {
		$this->records[] = $record;
	}

	public function remove( $index ) {
		array_splice( $this->records, $index, 1 );
	}

	public function addRecord( $values ) {
		$record = new ArrayRecord( $this->structure );
		$record->setAttributeValuesByOrder( $values );

		$this->records[] = $record;
	}

	public function getStructure() {
		return $this->structure;
	}

	public function getKey() {
		return $this->key;
	}

	/**
	 * returns the number of records contained by the ArrayRecordSet
	 * (size of array $records)
	 */
	public function getRecordCount() {
		return count( $this->records );
	}

	public function getRecord( $index ) {
		return $this->records[$index];
	}

	// returns the value of extraHierarchyCaption[$index] if it exists
	// null if not
	// Note: The value of extraHierarchyCaption[$index] might
	// exist, but be null.
	public function getExtraHierarchyCaption( $index ) {
		if ( array_key_exists( $index, $this->extraHierarchyCaption ) ) {
			return $this->extraHierarchyCaption[$index];
		}
		// else
		return null;
	}

	/**
	 * sort the ArrayRecordSet using php array_multisort
	 * the given $arrayForSorting is sorted like a normal array
	 * and the ArrayRecordSet is sorted similarly to $arrayForSorting
	 */
	public function sortRecord( $arrayForSorting ) {
		if ( empty( $arrayForSorting ) ) {
			return;
		}
		array_multisort( $arrayForSorting, SORT_LOCALE_STRING | SORT_FLAG_CASE, $this->records );

		// if 'zzz' is the first value, it is also all values. Do nothing.
		if ( $arrayForSorting[0] == 'zzz' ) {
			return;
		}
		$lastValue = '';

		// now read arrayForSorting and fill in extraHierarchyCaption accordingly
		foreach ( $arrayForSorting as $i => $sortValue ) {
			// default value
			$this->extraHierarchyCaption[$i] = null;

			// records having the same sort value are under the same hierarchycaption
			if ( $sortValue != $lastValue ) {
				$lastValue = $sortValue;
				if ( $arrayForSorting[$i] == 'zzz' ) {
					// 'zzz' is only to be sorted last. Put something more meaningful instead.
					$this->extraHierarchyCaption[$i] = '??';
				} else {
					$this->extraHierarchyCaption[$i] = $sortValue;
				}
			}
		}
	}

	public function tostring_indent( $depth = 0, $key = "", $myname = "" ) {
		return parent::tostring_indent( $depth, $key, $myname . "_ArrayRecordSet" );
	}

}

class ConvertingRecordSet extends RecordSet {
	protected $relation;
	protected $converters;
	protected $structure;

	public function __construct( $relation, $converters ) {
		$this->relation = $relation;
		$this->converters = $converters;
		$this->structure = $this->determineStructure();
	}

	public function getStructure() {
		return $this->structure;
	}

	public function getKey() {
		return $this->relation->getKey();
	}

	public function getRecordCount() {
		return $this->relation->getRecordCount();
	}

	public function getRecord( $index ) {
		$record = $this->relation->getRecord( $index );
		$result = new ArrayRecord( $this->structure );

		foreach ( $this->converters as $converter ) {
			$result->setSubRecord( $converter->convert( $record ) );
		}

		return $result;
	}

	protected function determineStructure() {
		$attributes = [];

		foreach ( $this->converters as $converter ) {
			$attributes = array_merge( $attributes, $converter->getStructure()->getAttributes() );
		}

		return new Structure( $attributes );
	}

	public function tostring_indent( $depth = 0, $key = "", $myname = "" ) {
		return parent::tostring_indent( $depth, $key, $myname . "_ConvertingRecordSet" );
	}
}

function getRelationAsHTMLList( $relation ) {
	$structure = $relation->getStructure();

	$result = getStructureAsListStructure( $structure );
	$result .= '<ul class="wiki-data-unordered-list">';

	for ( $i = 0; $i < $relation->getRecordCount(); $i++ ) {
		$record = $relation->getRecord( $i );
		$result .= '<li>';
		$result .= getRecordAsListItem( $structure, $record );
		$result .= '</li>';
	}

	$result .= '</ul>';
	return $result;
}

function getStructureAsListStructure( $structure ) {
	$result = '<h5>';

	foreach ( $structure->getAttributes() as $attribute ) {
		$result .= getAttributeAsText( $attribute );
		$result .= ' - ';
	}

	$result = rtrim( $result, ' - ' ) . '</h5>';
	return $result;
}

function getAttributeAsText( $attribute ) {
	$type = $attribute->type;
	if ( is_a( $type, Structure ) ) {
		$structure = $type->getStructure();
		foreach ( $structure->getAttributes() as $innerAttribute ) {
			$result .= getAttributeAsText( $innerAttribute );
			$result .= ' - ';
		}
		$result = rtrim( $result, ' - ' );
	} else {
		$result = $attribute->name;
	}
	return $result;
}

function getRecordAsListItem( $structure, $record ) {
	$result = '';

	foreach ( $structure->getAttributes() as $attribute ) {
		$type = $attribute->type;
		$value = $record->getAttributeValue( $attribute );

		if ( is_a( $type, Structure ) ) {
			$result .= getRecordAsListItem( $type->getStructure(), $value );
		} else {
			$result .= convertToHTML( $value, $type );
		}
		$result .= ' - ';
	}
	$result = rtrim( $result, ' - ' );

	return $result;
}

function getRecordKeyName( $record, $key ) {
	$ids = [];

	foreach ( $key->attributes as $attribute ) {
		$ids[] = $record->getAttributeValue( $attribute );
	}
	return implode( "-", $ids );
}

function splitRecordSet( $recordSet, $groupAttribute ) {
	$result = [];
	$structure = $recordSet->getStructure();
	$key = $recordSet->getKey();

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$groupAttributeValue = $record->getAttributeValue( $groupAttribute );
		@$groupRecordSet = $result[$groupAttributeValue]; # FIXME - check existence in array

		if ( $groupRecordSet == null ) {
			$groupRecordSet = new ArrayRecordSet( $structure, $key );
			$result[$groupAttributeValue] = $groupRecordSet;
		}

		$groupRecordSet->add( $record );
	}

	return $result;
}
