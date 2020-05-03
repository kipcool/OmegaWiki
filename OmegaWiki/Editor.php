<?php

require_once "WikiDataGlobals.php";
require_once 'IdStack.php';
require_once "HTMLtable.php";
require_once "Controller.php";
require_once "type.php";
require_once "Wikidata.php";
require_once "ContextFetcher.php";
require_once "OmegaWikiDatabaseAPI.php";

# End of line string for readable HTML, set to "\n" for testing
define( 'EOL', "\n" ); # Makes human (and vim :-p) readable output (somewhat...)
# define('EOL',""); # Output only readable by browsers

// added the "allow add controller" to be able to control the usage of the add field in different circumstances
// instances of this class are used instead of the boolean "allowAdd" in the editors
class AllowAddController {
	protected $value;

	public function __construct( $value ) {
		$this->value = $value;
	}

	public function check( $idPath ) {
		return $this->value;
	}
}

class ShowEditFieldChecker {
	protected $value;

	public function __construct( $value ) {
		$this->value = $value;
	}

	public function check( IdStack $idPath ) {
		return $this->value;
	}
}

class ShowEditFieldForClassesChecker extends ShowEditFieldChecker {
	protected $objectIdAttributeLevel;
	protected $objectIdAttribute;

	public function __construct( $objectIdAttributeLevel, Attribute $objectIdAttribute ) {
		$this->objectIdAttributeLevel = $objectIdAttributeLevel;
		$this->objectIdAttribute = $objectIdAttribute;
	}

	public function check( IdStack $idPath ) {
		$peek = $idPath->getKeyStack()->peek( $this->objectIdAttributeLevel );
		$objectId = $peek->getAttributeValue( $this->objectIdAttribute );
		return isClass( $objectId );
	}
}

interface Editor {
	public function getAttribute();

	public function getUpdateAttribute();

	public function getAddAttribute();

	public function showsData( $value );

	public function view( IdStack $idPath, $value );

	public function showEditField( IdStack $idPath );

	public function edit( IdStack $idPath, $value );

	public function add( IdStack $idPath );

	public function save( IdStack $idPath, $value );

	public function getUpdateValue( IdStack $idPath );

	public function getAddValues( IdStack $idPath );

	public function getEditors();

	public function getAttributeEditorMap();
}

class AttributeEditorMap {
	protected $attributeEditorMap = [];

	public function addEditor( $editor ) {
		$attributeId = $editor->getAttribute()->id;
		$this->attributeEditorMap[$attributeId] = $editor;
	}

	public function getEditorForAttributeId( $attributeId ) {
		if ( isset( $this->attributeEditorMap[$attributeId] ) ) {
			return $this->attributeEditorMap[$attributeId];
		}
		return null;
	}

	public function getEditorForAttribute( Attribute $attribute ) {
		return $this->getEditorForAttributeId( $attribute->id );
	}
}

/**
 * Basic Editor class.
 */
abstract class DefaultEditor implements Editor {
	protected $editors;
	protected $attributeEditorMap;
	protected $attribute;
	protected $isCollapsible;
	protected $displayHeader;

	public function __construct( Attribute $attribute = null ) {
		$this->attribute = $attribute;
		$this->editors = [];
		$this->attributeEditorMap = new AttributeEditorMap();
		$this->isCollapsible = true;
		// show header by default
		$this->displayHeader = true;
	}

	public function addEditor( Editor $editor ) {
		$this->editors[] = $editor;
		$this->attributeEditorMap->addEditor( $editor );
	}

	public function getAttribute() {
		return $this->attribute;
	}

	public function getEditors() {
		return $this->editors;
	}

	public function getAttributeEditorMap() {
		return $this->attributeEditorMap;
	}

	/**
	 * returns true if the editor is collapsible
	 * @return bool
	 */
	public function getCollapsible() {
		return $this->isCollapsible;
	}

	/**
	 * set the editor as collapsible or not collapsible
	 * @param bool $value
	 */
	public function setCollapsible( $value ) {
		$this->isCollapsible = $value;
	}

	public function setDisplayHeader( $value ) {
		$this->displayHeader = $value;
	}

	public function getDisplayHeader() {
		return $this->displayHeader;
	}

	public function addCollapsablePrefixToClass( $class ) {
		return "collapsable-$class";
	}

	/**
	 * returns two spans elements, each containing an arrow.
	 * One arrow (down) is shown when the editor ($this) is open
	 * The other arrow (left or right) is shown when the editor is closed
	 */
	public function getExpansionPrefix( $class, $elementId ) {
		if ( !$this->isCollapsible ) {
			return '';
		}

		// if it is collapsible, continue
		global $wgLang;
		$arrow = ( $wgLang->getDir() == 'ltr' ) ? "►" : "◄";
		$prefix = Html::element( 'span', [
			'class' => "prefix collapse-$class"
			], $arrow );
		$prefix .= Html::element( 'span', [
			'class' => "prefix expand-$class"
			], "▼" );

		return $prefix;
	}
}

abstract class Viewer extends DefaultEditor {
	public function getUpdateAttribute() {
		return null;
	}

	public function getAddAttribute() {
		return null;
	}

	public function edit( IdStack $idPath, $value ) {
		return $this->view( $idPath, $value );
	}

	public function add( IdStack $idPath ) {
		return "";
	}

	public function save( IdStack $idPath, $value ) {
	}

	public function getUpdateValue( IdStack $idPath ) {
		return null;
	}

	public function getAddValues( IdStack $idPath ) {
		return null;
	}

	public function showEditField( IdStack $idPath ) {
		return true;
	}
}

abstract class RecordSetEditor extends DefaultEditor {
	protected $permissionController;
	protected $showEditFieldChecker;
	protected $allowAddController;
	protected $allowRemove;
	protected $isAddField;
	protected $controller;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, ShowEditFieldChecker $showEditFieldChecker, AllowAddController $allowAddController, $allowRemove, $isAddField, UpdateController $controller = null ) {
		parent::__construct( $attribute );

		$this->permissionController = $permissionController;
		$this->showEditFieldChecker = $showEditFieldChecker;
		$this->allowAddController = $allowAddController;
		$this->allowRemove = $allowRemove;
		$this->isAddField = $isAddField;
		$this->controller = $controller;
	}

	public function getAddValues( IdStack $idPath ) {
		$addStructure = $this->getAddStructure();

		if ( count( $addStructure->getAttributes() ) > 0 ) {
			$relations = [];

			$value_array_array = [ [] ];

			foreach ( $this->getEditors() as $editor ) {
				if ( $attribute = $editor->getAddAttribute() ) {
					$idPath->pushAttribute( $attribute );

					$addValues = $editor->getAddValues( $idPath );
					$i = 0;
					foreach ( $addValues as $value ) {
						$value_array_array[$i][] = $value;
						$i++;
					}

					$idPath->popAttribute();
				}
			}

			foreach ( $value_array_array as $value_array ) {
				$relation = new ArrayRecordSet( $addStructure, $addStructure );  // TODO Determine real key
				$relation->addRecord( $value_array );
				$relations[] = $relation;
			}

			return $relations;
		}
		return null;
	}

	protected function saveRecord( IdStack $idPath, Record $record ) {
		foreach ( $this->getEditors() as $editor ) {
			$attribute = $editor->getAttribute();
			$value = $record->getAttributeValue( $attribute );
			$idPath->pushAttribute( $attribute );
			$editor->save( $idPath, $value );
			$idPath->popAttribute();
		}
	}

	protected function updateRecord( IdStack $idPath, Record $record, Structure $structure, $editors ) {
		if ( count( $editors ) > 0 ) {
			$updateRecord = $this->getUpdateRecord( $idPath, $structure, $editors );

			// only update if it has been modified (for example an modified definition)
			if ( !equalRecords( $structure, $record, $updateRecord ) ) {
				$this->controller->update( $idPath->getKeyStack(), $updateRecord );
			}
		}
	}

	protected function removeRecord( IdStack $idPath ) {
		global $wgRequest;

		if ( $wgRequest->getCheck( 'remove-' . $idPath->getId() ) ) {
			$this->controller->remove( $idPath->getKeyStack() );
			return true;
		}
		return false;
	}

	public function getStructure() {
		$attributes = [];

		foreach ( $this->getEditors() as $editor ) {
			$attributes[] = $editor->getAttribute();
		}
		return new Structure( $attributes );
	}

	public function getUpdateValue( IdStack $idPath ) {
		return null;
	}

	protected function getUpdateStructure() {
		$attributes = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $updateAttribute = $editor->getUpdateAttribute() ) {
				$attributes[] = $updateAttribute;
			}
		}
		return new Structure( $attributes );
	}

	protected function getAddStructure() {
		$attributes = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $addAttribute = $editor->getAddAttribute() ) {
				$attributes[] = $addAttribute;
			}
		}
		return new Structure( $attributes );
	}

	protected function getUpdateEditors() {
		$updateEditors = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $editor->getUpdateAttribute() ) {
				$updateEditors[] = $editor;
			}
		}
		return $updateEditors;
	}

	protected function getAddEditors() {
		$addEditors = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $editor->getAddAttribute() ) {
				$addEditors[] = $editor;
			}
		}
		return $addEditors;
	}

	public function getAddRecord( IdStack $idPath, Structure $structure, $editors ) {
		$results = [];

		foreach ( $editors as $editor ) {
			if ( $attribute = $editor->getAddAttribute() ) {
				$idPath->pushAttribute( $attribute );
				$addValues = $editor->getAddValues( $idPath );
				$i = 0;
				foreach ( $addValues as $value ) {
					if ( !array_key_exists( $i, $results ) ) {
						$results[$i] = new ArrayRecord( $structure );
					}
					$results[$i]->setAttributeValue( $attribute, $value );
					$i++;
				}
				$idPath->popAttribute();
			}
		}
		return $results;
	}

	public function getUpdateRecord( IdStack $idPath, Structure $structure, $editors ) {
		$result = new ArrayRecord( $structure );

		foreach ( $editors as $editor ) {
			if ( $attribute = $editor->getUpdateAttribute() ) {
				$idPath->pushAttribute( $attribute );
				$result->setAttributeValue( $attribute, $editor->getUpdateValue( $idPath ) );
				$idPath->popAttribute();
			}
		}
		return $result;
	}

	public function save( IdStack $idPath, $value ) {
		// save the new field (definition, translation, ...)
		if ( $this->allowAddController->check( $idPath ) && $this->controller != null ) {
			$addStructure = $this->getAddStructure();

			if ( count( $addStructure->getAttributes() ) > 0 ) {
				$addEditors = $this->getAddEditors(); // array of editors

				$records = [];
				$records = $this->getAddRecord( $idPath, $addStructure, $addEditors );
				foreach ( $records as $record ) {
					$this->controller->add( $idPath, $record );
				}
			}
		}

		if ( !$value ) {
			// RecordSetEditor has no value, can happen with new edit mode
			return;
		}
		// update the existing and modified fields (definition, translation, ...)
		$recordCount = $value->getRecordCount();
		$key = $value->getKey();
		$updateStructure = $this->getUpdateStructure();
		$updateEditors = $this->getUpdateEditors();

		for ( $i = 0; $i < $recordCount; $i++ ) {
			$record = $value->getRecord( $i );
			$idPath->pushKey( project( $record, $key ) );

			if ( !$this->allowRemove || !$this->removeRecord( $idPath ) ) {
				$this->saveRecord( $idPath, $record );
				$this->updateRecord( $idPath, $record, $updateStructure, $updateEditors );
			}

			$idPath->popKey();
		}
	}

	public function getUpdateAttribute() {
		return null;
	}

	public function getAddAttribute() {
		$result = null;

		if ( $this->isAddField ) {
			$addStructure = $this->getAddStructure();

			if ( count( $addStructure->getAttributes() ) > 0 ) {
				$result = new Attribute( $this->attribute->id, $this->attribute->name, $addStructure );
			}
		}
		return $result;
	}

	public function showsData( $value ) {
		return $value->getRecordCount() > 0;
	}

	public function showEditField( IdStack $idPath ) {
		return $this->showEditFieldChecker->check( $idPath );
	}
} // class RecordSetEditor

class RecordSetTableEditor extends RecordSetEditor {
	protected $rowHTMLAttributes = [];
	protected $repeatInput = false;
	protected $hideEmptyColumns = true;

	protected function getRowAttributesArray() {
		return $this->rowHTMLAttributes;
	}

	public function setRowHTMLAttributes( $rowHTMLAttributes ) {
		$this->rowHTMLAttributes = $rowHTMLAttributes;
	}

	/**
	 * Determines if there is at least one non-empty cell in the column
	 * and returns true in that case.
	 * This can be used so that columns that are empty are not displayed.
	 */
	protected function columnShowsData( Editor $columnEditor, RecordSet $value, $attributePath ) {
		$result = false;
		$recordCount = $value->getRecordCount();
		$i = 0;

		while ( !$result && $i < $recordCount ) {
			$recordOrScalar = $value->getRecord( $i );

			foreach ( $attributePath as $attribute ) {
				$recordOrScalar = $recordOrScalar->getAttributeValue( $attribute );
			}
			$result = $columnEditor->showsData( $recordOrScalar );
			$i++;
		}

		return $result;
	}

	public function getTableStructure( Editor $editor ) {
		$attributes = [];

		foreach ( $editor->getEditors() as $childEditor ) {
			$childAttribute = $childEditor->getAttribute();

			if ( $childEditor instanceof RecordTableCellEditor ) {
				$type = $this->getTableStructure( $childEditor );
			} else {
				$type = 'short-text';
			}

			$attributes[] = new Attribute( $childAttribute->id, $childAttribute->name, $type );
		}

		return new Structure( $attributes );
	}

	protected function getTableStructureShowingData( $viewOrEdit, Editor $editor, IdStack $idPath, RecordSet $value, $attributePath = [] ) {
		$attributes = [];

		foreach ( $editor->getEditors() as $childEditor ) {
			$childAttribute = $childEditor->getAttribute();
			array_push( $attributePath, $childAttribute );

			if ( $childEditor instanceof RecordTableCellEditor ) {
				$type = $this->getTableStructureShowingData( $viewOrEdit, $childEditor, $idPath, $value, $attributePath );

				if ( count( $type->getAttributes() ) > 0 ) {
					$attributes[] = new Attribute( $childAttribute->id, $childAttribute->name, $type );
				}
			} elseif ( $viewOrEdit == "view" ) {
				if ( $this->columnShowsData( $childEditor, $value, $attributePath ) ) {
					$attributes[] = new Attribute( $childAttribute->id, $childAttribute->name, 'short-text' );
				}
			} elseif ( $viewOrEdit == "edit" ) {
				if ( $childEditor->showEditField( $idPath ) ) {
					$attributes[] = new Attribute( $childAttribute->id, $childAttribute->name, 'short-text' );
				}
			}

			array_pop( $attributePath );
		}

		return new Structure( $attributes );
	}

	public function viewHeader( IdStack $idPath, Structure $visibleStructure ) {
		$attribs = [ 'id' => $idPath->getId(), 'class' => 'wiki-data-table' ];
		$result = Html::openElement( 'table', $attribs );

		foreach ( getStructureAsTableHeaderRows( $visibleStructure, 0, $idPath ) as $headerRow ) {
			$result .= Html::rawElement( 'tr', [], $headerRow );
		}

		return $result;
	}

	public function viewRows( IdStack $idPath, RecordSet $value, Structure $visibleStructure ) {
		$result = "";
		$rowAttributes = $this->getRowAttributesArray();
		$key = $value->getKey();
		$recordCount = $value->getRecordCount();

		for ( $i = 0; $i < $recordCount; $i++ ) {
			$record = $value->getRecord( $i );
			$idPath->pushKey( project( $record, $key ) );
			$trattr = [ 'id' => $idPath->getId() ];
			$trattr += $rowAttributes;
			$trcontent = getRecordAsTableCells( $idPath, $this, $visibleStructure, $record );
			$result .= Html::rawElement( 'tr', $trattr, $trcontent );

			$idPath->popKey();
		}

		return $result;
	}

	public function viewFooter( IdStack $idPath, Structure $visibleStructure ) {
		return Html::closeElement( 'table' );
	}

	public function getTableStructureForView( IdStack $idPath, RecordSet $value ) {
		if ( $this->hideEmptyColumns ) {
			return $this->getTableStructureShowingData( "view", $this, $idPath, $value );
		} else {
			return $this->getTableStructure( $this );
		}
	}

	public function getTableStructureForEdit( IdStack $idPath, RecordSet $value ) {
		return $this->getTableStructureShowingData( "edit", $this, $idPath, $value );
	}

	public function view( IdStack $idPath, $value ) {
		$visibleStructure = $this->getTableStructureForView( $idPath, $value );

		$result =
			$this->viewHeader( $idPath, $visibleStructure ) .
			$this->viewRows( $idPath, $value, $visibleStructure ) .
			$this->viewFooter( $idPath, $visibleStructure );

		return $result;
	}

	public function edit( IdStack $idPath, $value ) {
		$tableattr = [ 'id' => $idPath->getId(), 'class' => 'wiki-data-table' ];
		$result = Html::openElement( 'table', $tableattr );
		$key = $value->getKey();
		$rowAttributes = $this->getRowAttributesArray();
		$visibleStructure = $this->getTableStructureForEdit( $idPath, $value );

		$columnOffset = $this->allowRemove ? 1 : 0;
		$headerRows = getStructureAsTableHeaderRows( $visibleStructure, $columnOffset, $idPath );

		$result .= Html::openElement( 'thead' );

		if ( $this->allowRemove ) {
			$thattr = [ 'class' => 'wld-remove-header', 'rowspan' => count( $headerRows ), 'title' => wfMessage( "ow_RemoveHint" )->text() ];
			$headerRows[0] = Html::element( 'th', $thattr ) . $headerRows[0];
		}

		if ( $this->repeatInput ) {
			$thattr = [ 'class' => 'add', 'rowspan' => count( $headerRows ) ];
			$headerRows[0] .= Html::element( 'th', $thattr, 'Input rows' );
		}
		foreach ( $headerRows as $headerRow ) {
			$trattr = [ 'id' => $idPath->getId() ];
			$trattr += $rowAttributes;
			$result .= Html::rawElement( 'tr', $trattr, $headerRow );
		}

		$result .= Html::closeElement( 'thead' );
		$result .= Html::openElement( 'tbody' );

		$recordCount = $value->getRecordCount();

		for ( $i = 0; $i < $recordCount; $i++ ) {
			$result .= Html::openElement( 'tr' );
			$record = $value->getRecord( $i );
			$idPath->pushKey( project( $record, $key ) );

			if ( $this->allowRemove ) {
				$result .= Html::openElement( 'td', [ 'class' => 'remove' ] );

				if ( $this->permissionController->allowRemovalOfValue( $idPath, $record ) ) {
					$result .= getRemoveCheckBox( 'remove-' . $idPath->getId() );
				}
				$result .= Html::closeElement( 'td' );
			}

			if ( $this->permissionController->allowUpdateOfValue( $idPath, $record ) ) {
				$result .= getRecordAsEditTableCells( $idPath, $this, $visibleStructure, $record );
			} else {
				$result .= getRecordAsTableCells( $idPath, $this, $visibleStructure, $record );
			}
			$idPath->popKey();

			if ( $this->repeatInput ) {
				$result .= Html::element( 'td' );
			}
			$result .= Html::closeElement( 'tr' );
		}

		$result .= Html::closeElement( 'tbody' );

		// the part in the "tfoot" does not get sorted by jquery.tablesorter
		$result .= Html::openElement( 'tfoot' );

		if ( $this->allowAddController->check( $idPath ) ) {
			$result .= $this->getAddRowAsHTML( $idPath, $this->repeatInput, $this->allowRemove );
		}
		$result .= Html::closeElement( 'tfoot' );
		$result .= Html::closeElement( 'table' );

		return $result;
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			$tableattr = [ 'id' => $idPath->getId(), 'class' => 'wiki-data-table' ];
			$result = Html::openElement( 'table', $tableattr );
			$headerRows = getStructureAsTableHeaderRows( $this->getAddStructure(), 0, $idPath );

			foreach ( $headerRows as $headerRow ) {
				$result .= Html::openElement( 'tr' );
				$result .= $headerRow;
				$result .= Html::closeElement( 'tr' );
			}

			$result .= $this->getAddRowAsHTML( $idPath, false, false );
			$result .= Html::closeElement( 'table' );

			return $result;
		}
		return "";
	}

	function getStructureAsAddCells( IdStack $idPath, Editor $editor, &$startColumn = 0 ) {
		$result = '';

		foreach ( $editor->getEditors() as $childEditor ) {
			$attribute = $childEditor->getAttribute();
			$type = $attribute->type;
			$idPath->pushAttribute( $attribute );

			if ( $childEditor instanceof RecordTableCellEditor ) {
				$result .= $this->getStructureAsAddCells( $idPath, $childEditor, $startColumn );
			} else {
				if ( $childEditor->showEditField( $idPath ) ) {
					$tdclass = getHTMLClassForType( $type, $attribute ) . ' column-' . parityClass( $startColumn );
					$result .= Html::openElement( 'td', [ 'class' => $tdclass ] );
					$result .= $childEditor->add( $idPath );
					$result .= Html::closeElement( 'td' );
				}
				$startColumn++;
			}

			$idPath->popAttribute();
		}

		return $result;
	}

	function getAddRowAsHTML( IdStack $idPath, $repeatInput, $allowRemove ) {
		global $wgScriptPath;

		$attrid = 'add-' . $idPath->getId();
		$attr = [ 'id' => $attrid ];
		if ( $repeatInput ) {
			$attr['class'] = 'repeat';
		}
		$result = Html::openElement( 'tr', $attr );

		# + is add new Fo o(but grep this file for Add.png for more)
		if ( $allowRemove ) {
			$imgsrc = $wgScriptPath . '/extensions/WikiLexicalData/Images/Add.png';
			$imgtitle = wfMessage( "ow_AddHint" )->text();
			$imgattr = [ 'src' => $imgsrc, 'title' => $imgtitle, 'alt' => 'Add' ];

			$result .= Html::openElement( 'td', [ 'class' => 'add addemptyrow' ] );
			$result .= Html::element( 'img', $imgattr );
			$result .= Html::closeElement( 'td' );
		}

		$result .= $this->getStructureAsAddCells( $idPath, $this );

		if ( $repeatInput ) {
			$result .= Html::element( 'td', [ 'class' => 'input-rows' ] );
		}
		$result .= Html::closeElement( 'tr' );

		return $result;
	}

	public function setHideEmptyColumns( $hideEmptyColumns ) {
		$this->hideEmptyColumns = $hideEmptyColumns;
	}
}

/**
 * RecordEditor
 */
abstract class RecordEditor extends DefaultEditor {
	protected function getUpdateStructure() {
		$attributes = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $updateAttribute = $editor->getUpdateAttribute() ) {
				$attributes[] = $updateAttribute;
			}
		}
		return new Structure( $attributes );
	}

	protected function getAddStructure() {
		$attributes = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $addAttribute = $editor->getAddAttribute() ) {
				$attributes[] = $addAttribute;
			}
		}
		return new Structure( $attributes );
	}

	public function getUpdateValue( IdStack $idPath ) {
		$result = new ArrayRecord( $this->getUpdateStructure() );

		foreach ( $this->getEditors() as $editor ) {
			if ( $attribute = $editor->getUpdateAttribute() ) {
				$idPath->pushAttribute( $attribute );
				$result->setAttributeValue( $attribute, $editor->getUpdateValue( $idPath ) );
				$idPath->popAttribute();
			}
		}
		return $result;
	}

	public function getAddValues( IdStack $idPath ) {
		$results = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $attribute = $editor->getAddAttribute() ) {
				$idPath->pushAttribute( $attribute );
				$addValues = [];
				$addValues = $editor->getAddValues( $idPath );
				$i = 0;
				foreach ( $addValues as $value ) {
					if ( !array_key_exists( $i, $results ) ) {
						$results[$i] = new ArrayRecord( $this->getAddStructure() );
					}
					$results[$i]->setAttributeValue( $attribute, $value );
					$i++;
				}
				$idPath->popAttribute();
			}
		}
		return $results;
	}

	public function getUpdateAttribute() {
		$updateStructure = $this->getUpdateStructure();

		if ( count( $updateStructure->getAttributes() ) > 0 ) {
			return new Attribute( $this->attribute->id, $this->attribute->name, $updateStructure );
		}
		return null;
	}

	public function getAddAttribute() {
		$addStructure = $this->getAddStructure();

		if ( count( $addStructure->getAttributes() ) > 0 ) {
			return new Attribute( $this->attribute->id, $this->attribute->name, $addStructure );
		}
		return null;
	}

	public function save( IdStack $idPath, $value ) {
		if ( !$value ) {
			return;
		}
		foreach ( $this->getEditors() as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );
			$editor->save( $idPath, $value->getAttributeValue( $attribute ) );
			$idPath->popAttribute();
		}
	}

	public function showsData( $value ) {
		$result = true;
		$i = 0;
		$childEditors = $this->getEditors();

		while ( $result && $i < count( $childEditors ) ) {
			$editor = $childEditors[$i];
			$result = $editor->showsData( $value->getAttributeValue( $editor->getAttribute() ) );
			$i++;
		}

		return $result;
	}

	public function showEditField( IdStack $idPath ) {
		return true;
	}
}

class RecordTableCellEditor extends RecordEditor {
	public function view( IdStack $idPath, $value ) {
	}

	public function edit( IdStack $idPath, $value ) {
	}

	public function add( IdStack $idPath ) {
	}

	public function save( IdStack $idPath, $value ) {
	}
}

/**
 * ScalarEditor is an editor that shows one field
 * such as a cell in a table.
 */
abstract class ScalarEditor extends DefaultEditor {
	protected $permissionController;
	protected $isAddField;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, $isAddField ) {
		parent::__construct( $attribute );

		$this->permissionController = $permissionController;
		$this->isAddField = $isAddField;
	}

	protected function addId( $id ) {
		return "add-" . $id;
	}

	protected function updateId( $id ) {
		return "update-" . $id;
	}

	public function save( IdStack $idPath, $value ) {
	}

	public function getUpdateAttribute() {
		if ( $this->permissionController->allowUpdateOfAttribute( $this->attribute ) ) {
			return $this->attribute;
		}
		return null;
	}

	public function getAddAttribute() {
		if ( $this->isAddField ) {
			return $this->attribute;
		}
		return null;
	}

	abstract public function getViewHTML( IdStack $idPath, $value );

	abstract public function getEditHTML( IdStack $idPath, $value );

	abstract public function getInputValue( $id );

	public function getUpdateValue( IdStack $idPath ) {
		return $this->getInputValue( "update-" . $idPath->getId() );
	}

	// tries to get multiple "add" values e.g. adding multiple translations at once
	// the "X-" corresponds to what is in omegawiki-ajax.js, function recursiveChangeId
	public function getAddValues( IdStack $idPath ) {
		$addValues = [];
		$prefix = "add-";

		while ( ( $value = $this->getInputValue( $prefix . $idPath->getId() ) ) != '' ) {
			$addValues[] = $value;
			$prefix = $prefix . "X-";
		}

		return $addValues;
	}

	public function view( IdStack $idPath, $value ) {
		return $this->getViewHTML( $idPath, $value );
	}

	public function edit( IdStack $idPath, $value ) {
		if ( $this->permissionController->allowUpdateOfValue( $idPath, $value ) ) {
			return $this->getEditHTML( $idPath, $value );
		}
		return $this->getViewHTML( $idPath, $value );
	}

	public function showsData( $value ) {
		return ( $value != null ) && ( trim( $value ) != "" );
	}

	public function showEditField( IdStack $idPath ) {
		return true;
	}
}

/**
 * LanguageEditor manages the languages that are editable at omegawiki
 * it displays the language name in the user language
 * and in edit mode, it give a combobox to select the language
 */
class LanguageEditor extends ScalarEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		return languageIdAsText( $value );
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		return getSuggest( $this->updateId( $idPath->getId() ), "language" );
	}

	public function add( IdStack $idPath ) {
		return getSuggest( $this->addId( $idPath->getId() ), "language" );
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return $wgRequest->getInt( $id );
	}

	public function showsData( $value ) {
		return ( $value != null ) && ( $value != 0 );
	}
}

/**
 * Shows one language at a time,
 * and adds tabs showing other available languages
 * (for a given expression as defined in IdStack)
 * $value is the currently displayed language
 */
class TabLanguageEditor extends ScalarEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		global $wgRequest;
		$dc = wdGetDataSetContext();
		$output = "";

		// We must find the spelling and the list of possible languages from $idPath
		$expressionId = $idPath->getKeyStack()->peek( 0 )->expressionId;
		$spelling = getExpression( $expressionId, $dc )->spelling;
		$title = Title::makeTitle( NS_EXPRESSION, $spelling );
		$expressionsArray = getExpressions( $spelling, $dc );

		$languageIdList = [];
		foreach ( $expressionsArray as $expression ) {
			if ( $expression->languageId != $value ) {
				// only add languages that are not the current language
				$languageIdList[] = $expression->languageId;
			}
		}

		if ( !empty( $languageIdList ) ) {
			// there might be duplicates
			$languageIdList = array_unique( $languageIdList );

			// Now the names
			$languageNameList = [];
			foreach ( $languageIdList as $languageId ) {
				$languageNameList[$languageId] = languageIdAsText( $languageId );
			}
			asort( $languageNameList );

			$output .= Html::openElement( 'span', [ 'class' => 'wd-tablist' ] );
			$output .= wfMessage( 'ow_OtherLanguages' )->text();

			// now the <li> definining the menu
			// display: none is also in the .css, but defined here to prevent the list to show
			// when the .css is not yet loaded.
			foreach ( $languageNameList as $languageId => $languageName ) {
				$output .= Html::openElement( 'span', [ 'class' => 'wd-tabitem' ] );

				// create links to other available languages
				$urlOptions = [ 'explang' => $languageId ];
				if ( $wgRequest->getVal( "action" ) == "edit" ) {
					$urlOptions['action'] = "edit";
				}
				$aHref = $title->getLocalURL( $urlOptions );
				$output .= Html::rawElement( 'a', [ 'href' => $aHref ], $languageName );

				$output .= Html::closeElement( 'span' ); // wd-tabitem
			}
			$output .= Html::closeElement( 'span' ); // wd-tablist
			$output .= Html::rawElement( 'br' );
			$output .= Html::rawElement( 'br' );
		} // if not empty other languages

		// Add the "Language: German" part
		$output .= Html::openElement( 'span', [ 'class' => 'wd-languagecurrent' ] );
		$output .= wfMessage( 'ow_Language' )->text() . ": " . languageIdAsText( $value );
		$output .= Html::closeElement( 'span' );

		return $output;
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		// is this used?
		return getSuggest( $this->updateId( $idPath->getId() ), "language" );
	}

	public function add( IdStack $idPath ) {
		$output = Html::openElement( 'div', [ 'class' => 'wd-languageadd' ] );
		$output .= wfMessage( 'ow_Language' )->text() . ': ';
		$output .= getSuggest( $this->addId( $idPath->getId() ), "language" );
		$output .= Html::closeElement( 'div' );

		return $output;
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return $wgRequest->getInt( $id );
	}

	public function showsData( $value ) {
		return ( $value != null ) && ( $value != 0 );
	}
}

class SpellingEditor extends ScalarEditor {

	public function getViewHTML( IdStack $idPath, $value ) {
		return spellingAsLink( $value );
	}

	public function getEditHTML( IdStack $idPath, $value ) {
			return getTextBox( $this->updateId( $idPath->getId() ) );
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getTextBox( $this->addId( $idPath->getId() ) );
		}
		return "";
	}

	// retrieves the new added translation when saving, according to "name" property (not id)
	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}
}

class DefinedMeaningEditor extends ScalarEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		return definedMeaningAsLink( $value );
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		return "";
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getTextBox( $this->addId( $idPath->getId() ) );
		}
		return "";
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}
}

class DefinedMeaningHeaderEditor extends ScalarEditor {

	/** Integer type
	 * indicates where the definition should be truncated. 0 for no truncation
	 */
	protected $truncateAt;
	protected $addText = "";

	public function __construct( $attribute, $truncateAt = 0 ) {
		parent::__construct( $attribute, new SimplePermissionController( false ), false );

		$this->truncateAt = $truncateAt;
	}

	public function getViewHTML( IdStack $idPath, $definedMeaningId ) {
		global $wgOut, $wgUser;

		/**
		 * the first definition will be used as a meta descriptor for search engines
		 * then isMetaDescSet is set to one, to indicate that the meta descriptor is already set
		 */
		static $isMetaDescSet = 0;

		$output = "";

		$userLanguageId = OwDatabaseAPI::getUserLanguageId();
		$definition = getDefinedMeaningDefinition( $definedMeaningId );
		$definingExpression = OwDatabaseAPI::definingExpression( $definedMeaningId );

		// word being currently viewed (typically title of page "Expression:word")
		// the "peek(1)" part is a bit of a mystery
		$expressionId = $idPath->getKeyStack()->peek( 1 )->expressionId;
		$expression = getExpression( $expressionId );

		// getting the truncated definition
		if ( ( $this->truncateAt > 0 ) && ( strlen( $definition ) > $this->truncateAt ) ) {
			$escapedDefinition = htmlspecialchars( $definition );
			$shortdef = htmlspecialchars( mb_substr( $definition, 0, $this->truncateAt ) ) . wfMessage( 'ellipsis' )->text();

			$htmlDefinition = Html::element( 'span', [ 'class' => 'defheader', 'title' => $escapedDefinition ], $shortdef );
		} else {
			// normal situation, no truncation
			$htmlDefinition = Html::element( 'span', [ 'class' => 'defheader' ], $definition );
		}
		// setting the definition as meta description for the page
		if ( $isMetaDescSet == 0 ) {
			$wgOut->addMeta( 'Description', $definition );
			$isMetaDescSet = 1;
		}

		// creating the link to edit the DM directly, that will be displayed on the right
		$DMPageName = $definingExpression . " (" . $definedMeaningId . ")";
		$DMTitle = Title::makeTitle( NS_DEFINEDMEANING, $DMPageName );
		$editURL = $DMTitle->getLocalURL( 'action=edit' );
		$editLinkContent = '[' . createLink( $editURL, wfMessage( 'edit' )->text() ) . ']';
		$editLink = Html::rawElement( 'span', [ 'class' => 'dm_edit_link' ], $editLinkContent );

		if ( $wgUser->getOption( 'ow_alt_layout' ) ) {
			// EXPERIMENTAL LAYOUT:
			// DMlink (expression of page) : translation \n definition
			$translation = "";
			$definedMeaningAsLink = definedMeaningReferenceAsLink( $definedMeaningId, $definingExpression, $expression->spelling );

			if ( ( $userLanguageId != $expression->languageId ) && ( $userLanguageId > 0 ) ) {
				// find a translation in the user language if exists
				// returns "" if not found
				$translation = OwDatabaseAPI::getDefinedMeaningExpressionForLanguage( $definedMeaningId, $userLanguageId );
			}
			$output = $editLink;
			$output .= $definedMeaningAsLink;
			if ( $translation != "" ) {
				$output .= " : " . $translation;
			}
			$output .= Html::element( 'br' ) . $htmlDefinition;

		} else {
			// STANDARD CLASSIC LAYOUT:
			// DMlink (translated if possible) : definition
			if ( $userLanguageId == $expression->languageId ) {
				// no translation needed
				$definedMeaningAsLink = definedMeaningReferenceAsLink( $definedMeaningId, $definingExpression, $expression->spelling );
			} else {
				// try to get a translation
				$definedMeaningAsLink = definedMeaningAsLink( $definedMeaningId );
			}
			$output = $definedMeaningAsLink . $editLink . " : " . $htmlDefinition;
		}

		return $output;
	}

	public function getEditHTML( IdStack $idPath, $definedMeaningId ) {
		return "";
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getTextArea( $this->addId( $idPath->getId() ), "", 3 );
		} else {
			return $this->addText;
		}
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}

	public function setAddText( $addText ) {
		$this->addText = $addText;
	}
}

class TextEditor extends ScalarEditor {
	protected $truncate;
	protected $truncateAt;
	protected $addText = "";
	protected $controller;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, $isAddField, $truncate = false, $truncateAt = 0, UpdateAttributeController $controller = null ) {
		parent::__construct( $attribute, $permissionController, $isAddField );

		$this->truncate = $truncate;
		$this->truncateAt = $truncateAt;
		$this->controller = $controller;
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$escapedValue = htmlspecialchars( $value );

		if ( !$this->truncate || strlen( $value ) <= $this->truncateAt ) {
			return $escapedValue;// $parserOutput->getText();
		} else {
			$spancontent = htmlspecialchars( substr( $value, 0, $this->truncateAt ) ) . wfMessage( 'ellipsis' )->text();
			return Html::element( 'span', [ 'title' => $escapedValue ], $spancontent );
		}
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		global $wgUser;
		$dc = wdGetDataSetContext();
		if ( ( $dc == "uw" ) and ( !$wgUser->isAllowed( 'deletewikidata-uw' ) ) ) {
		// disable
			return getTextArea( $this->updateId( $idPath->getId() ), $value, 3, 80, true );
		} else {
			return getTextArea( $this->updateId( $idPath->getId() ), $value, 3 );
		}
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getTextArea( $this->addId( $idPath->getId() ), "", 3 );
		} else {
			return $this->addText;
		}
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}

	public function setAddText( $addText ) {
		$this->addText = $addText;
	}

	public function save( IdStack $idPath, $value ) {
		if ( $this->controller != null ) {
			$inputValue = $this->getInputValue( $this->updateId( $idPath->getId() ) );

			if ( $inputValue != $value ) {
				$this->controller->update( $idPath->getKeyStack(), $inputValue );
			}
		}
	}
}

class ShortTextEditor extends ScalarEditor {
	protected $onChangeHandler;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, $isAddField, $onChangeHandler = "" ) {
		parent::__construct( $attribute, $permissionController, $isAddField );

		$this->onChangeHandler = $onChangeHandler;
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		return htmlspecialchars( $value );
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		global $wgUser;
		$dc = wdGetDataSetContext();
		if ( ( $dc == "uw" ) and ( !$wgUser->isAllowed( 'deletewikidata-uw' ) ) ) {
			// disable
			return getTextBox( $this->updateId( $idPath->getId() ), $value, $this->onChangeHandler, true );
		} else {
			return getTextBox( $this->updateId( $idPath->getId() ), $value, $this->onChangeHandler );
		}
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getTextBox( $this->addId( $idPath->getId() ), "", $this->onChangeHandler );
		} else {
			return "";
		}
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}
}

class ShortTextNoEscapeEditor extends ShortTextEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		return $value;
	}
}

class LinkEditor extends ShortTextEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		$label = htmlspecialchars( $value->linkLabel );
		$url = htmlspecialchars( $value->linkURL );

		if ( $label == "" ) {
			$label = $url;
		}
		$output = Html::element( 'a', [ 'href' => $url ], $label );
		return $output;
	}
}

class BooleanEditor extends ScalarEditor {
	protected $defaultValue;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, $isAddField, $defaultValue ) {
		parent::__construct( $attribute, $permissionController, $isAddField );

		$this->defaultValue = $defaultValue;
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		return booleanAsHTML( $value );
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		global $wgUser;
		$dc = wdGetDataSetContext();
		if ( ( $dc == "uw" ) and ( !$wgUser->isAllowed( 'deletewikidata-uw' ) ) ) {
			return getCheckBox( $this->updateId( $idPath->getId() ), $value, true );
		} else {
			return getCheckBox( $this->updateId( $idPath->getId() ), $value );
		}
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getCheckBox( $this->addId( $idPath->getId() ), $this->defaultValue );
		}
		return "";
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return $wgRequest->getCheck( $id );
	}
}

/**
 * IdenticalMeaningEditor
 * in view mode, shows either = or ≈
 * in edit mode, shows a combobox to choose.
 * for html we use strings "true" and "false" instead of "0" and "1"
 * to be sure that an undefined value will not be considered as a "0".
 */
class IdenticalMeaningEditor extends ScalarEditor {
	protected $defaultValue;
	// textValues is an array of "value" => "how the value is displayed"
	// e.g. array( "true" => "=", "false" => "≈" );
	protected $textValuesView;
	protected $textValuesEdit;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, $isAddField ) {
		parent::__construct( $attribute, $permissionController, $isAddField );

		$this->defaultValue = "true";
		$this->textValuesView = [ "true" => "&nbsp;", "false" => "≈" ];
		$this->textValuesEdit = [ "true" => "=", "false" => "≈" ];
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		// $value is what is returned from the database, i.e. an integer, 0 or 1
		if ( $value == 0 ) {
			return $this->textValuesView["false"];
		}
		if ( $value == 1 ) {
			return $this->textValuesView["true"];
		}
		return "undefined"; // should not happen
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		global $wgUser;
		if ( !$wgUser->isAllowed( 'deletewikidata-uw' ) ) {
			return $this->getViewHTML( $idPath, $value );
		}

		// $value is what is returned from the database, i.e. an integer, 0 or 1
		if ( $value == 0 ) {
			return getSelect( $this->updateId( $idPath->getId() ), $this->textValuesEdit, "false" );
		}
		if ( $value == 1 ) {
			return getSelect( $this->updateId( $idPath->getId() ), $this->textValuesEdit, "true" );
		}

		// if no $value is not 0 and not 1, should not happen
		return "undefined";
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getSelect( $this->addId( $idPath->getId() ), $this->textValuesEdit, $this->defaultValue );
		}
		return "";
	}

	public function getInputValue( $id ) {
		global $wgRequest;
		$inputvalue = trim( $wgRequest->getText( $id ) );
		return $inputvalue;
	}
}

abstract class SuggestEditor extends ScalarEditor {
	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getSuggest( $this->addId( $idPath->getId() ), $this->suggestType() );
		}
		return "";
	}

	abstract protected function suggestType();

	public function getEditHTML( IdStack $idPath, $value ) {
		return getSuggest( $this->updateId( $idPath->getId() ), $this->suggestType() );
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}
}

class DefinedMeaningReferenceEditor extends SuggestEditor {
	protected function suggestType() {
		return WLD_DEFINED_MEANING;
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$definedMeaningId = $value->definedMeaningId;
		$definedMeaningLabel = $value->definedMeaningLabel;
		$definedMeaningDefiningExpression = $value->definedMeaningDefiningExpression;

		return definedMeaningReferenceAsLink( $definedMeaningId, $definedMeaningDefiningExpression, $definedMeaningLabel );
	}
}

class SyntransReferenceEditor extends SuggestEditor {
	// very similar to a DefinedMeaningReferenceEditor
	protected function suggestType() {
		return WLD_SYNONYMS_TRANSLATIONS;
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$syntransId = $value->syntransId;
		$spelling = $value->spelling;

		return syntransAsLink( $syntransId, $spelling );
	}
}

class ClassAttributesLevelDefinedMeaningEditor extends SuggestEditor {
	protected function suggestType() {
		return "class-attributes-level";
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$definedMeaningId = $value->definedMeaningId;
		$definedMeaningLabel = $value->definedMeaningLabel;
		$definedMeaningDefiningExpression = $value->definedMeaningDefiningExpression;

		return definedMeaningReferenceAsLink( $definedMeaningId, $definedMeaningDefiningExpression, $definedMeaningLabel );
	}
}

abstract class SelectEditor extends ScalarEditor {
	abstract protected function getOptions();

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			return getSelect( $this->addId( $idPath->getId() ), $this->getOptions() );
		}
		return "";
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$options = $this->getOptions();
		return $options[$value];
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		return getSelect( $this->addId( $idPath->getId() ), $this->getOptions() );
	}

	public function getInputValue( $id ) {
		global $wgRequest;

		return trim( $wgRequest->getText( $id ) );
	}
}

class ClassAttributesTypeEditor extends SelectEditor {
	protected function getOptions() {
/*
	the translated version
		'DM' => wfMessage( 'ow_class_attr_type_dm' )->text(),
		'TRNS' => wfMessage( 'ow_class_attr_type_xlate' )->text(),
		'SYNT' => "SynTrans",
		'TEXT' => wfMessage( 'ow_class_attr_type_plain' )->text(),
		'URL' => wfMessage( 'ow_class_attr_type_link' )->text(),
		'OPTN' => wfMessage( 'ow_class_attr_type_option' )->text()
*/
	// more descriptive titles, but without translation for the moment
	// this is only seen by the users who have access to adding new annotations
		return [
			'DM' => "Relation to DefinedMeaning",
			'TRNS' => "Text, translatable",
			'SYNT' => "Relation to SynTrans",
			'TEXT' => "Text, plain",
			'URL' => "Link to URL",
			'OPTN' => "List of options",
		];
	}
}

class OptionSelectEditor extends SelectEditor {
	protected function getOptions() {
		return [];
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$definedMeaningId = $value->definedMeaningId;
		$definedMeaningLabel = $value->definedMeaningLabel;
		$definedMeaningDefiningExpression = $value->definedMeaningDefiningExpression;

		return definedMeaningReferenceAsLink( $definedMeaningId, $definedMeaningDefiningExpression, $definedMeaningLabel );
	}
}

class RelationTypeReferenceEditor extends DefinedMeaningReferenceEditor {
	protected function suggestType() {
		return "relation-type";
	}
}

class ClassReferenceEditor extends DefinedMeaningReferenceEditor {
	protected function suggestType() {
		return "class";
	}
}

class CollectionReferenceEditor extends DefinedMeaningReferenceEditor {
	protected function suggestType() {
		return "collection";
	}
}

class AttributeEditor extends DefinedMeaningReferenceEditor {
	protected $attributesLevelName;
	protected $attributeIDFilter;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, $isAddField, AttributeIDFilter $attributeIDFilter, $attributesLevelName ) {
		parent::__construct( $attribute, $permissionController, $isAddField );

		$this->attributeIDFilter = $attributeIDFilter;
		$this->attributesLevelName = $attributesLevelName;
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			$parameters = [
				"level" => $this->attributesLevelName,
				"definedMeaningId" => $idPath->getDefinedMeaningId(),
				"annotationAttributeId" => $idPath->getAnnotationAttribute()->getId()
			];

			if ( $this->attributesLevelName == WLD_SYNTRANS_MEANING_NAME ) {
				// find and add syntransId as a parameter
				$syntransId = $idPath->getKeyStack()->peek( 0 )->syntransId;
				if ( $syntransId == "" ) {
					// second tentative, sometimes it is called objectId
					$syntransId = $idPath->getKeyStack()->peek( 0 )->objectId;
				}
				if ( $syntransId != "" ) {
					$parameters["syntransId"] = $syntransId;
				}
			}

			return getSuggest( $this->addId( $idPath->getId() ), $this->suggestType(), $parameters );
		}
		return "";
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		$parameters = [ "level" => $this->attributesLevelName ];
		return getSuggest( $this->updateId( $idPath->getId() ), $this->suggestType(), $parameters );
	}

	public function showEditField( IdStack $idPath ) {
		return !$this->attributeIDFilter->leavesOnlyOneOption();
	}
}

class RelationTypeEditor extends AttributeEditor {
	protected function suggestType() {
		return WLD_RELATIONS;
	}
}

class TextAttributeEditor extends AttributeEditor {
	protected function suggestType() {
		return "text-attribute";
	}
}

class TranslatedTextAttributeEditor extends AttributeEditor {
	protected function suggestType() {
		return "translated-text-attribute";
	}
}

class LinkAttributeEditor extends AttributeEditor {
	protected function suggestType() {
		return WLD_LINK_ATTRIBUTE;
	}
}

class OptionAttributeEditor extends AttributeEditor {
	protected function suggestType() {
		return WLD_OPTION_ATTRIBUTE;
	}

	public function add( IdStack $idPath ) {
		if ( $this->isAddField ) {
			$parameters = [
				"level" => $this->attributesLevelName,
				"definedMeaningId" => $idPath->getDefinedMeaningId(),
				"annotationAttributeId" => $idPath->getAnnotationAttribute()->getId()
			];

			if ( $this->attributesLevelName == WLD_SYNTRANS_MEANING_NAME ) {
				// find and add syntransId as a parameter
				$syntransId = $idPath->getKeyStack()->peek( 0 )->syntransId;
				if ( $syntransId == "" ) {
					// second tentative, sometimes it is called objectId
					$syntransId = $idPath->getKeyStack()->peek( 0 )->objectId;
				}
				if ( $syntransId != "" ) {
					$parameters["syntransId"] = $syntransId;
				}
			}

			return getSuggest( $this->addId( $idPath->getId() ), $this->suggestType(), $parameters );
		}
		return '';
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		$parameters = [
			"level" => $this->attributesLevelName
		];

		return getSuggest( $this->updateId( $idPath->getId() ), $this->suggestType(), $parameters );
	}
}

class RecordListEditor extends RecordEditor {
	protected $headerLevel = 1;
	protected $htmlTag;

	public function __construct( Attribute $attribute = null, $headerLevel, $htmlTag ) {
		parent::__construct( $attribute );

		$this->htmlTag = $htmlTag;
		$this->headerLevel = $headerLevel;
	}

	/**
	 * hierarchical showsData, returns true
	 * if at least on of its childEditors (and their childEditors...)
	 * has at least one value to show
	 */
	public function showsData( $value ) {
		if ( !$value ) {
			return false;
		}
		$i = 0;
		$result = false;
		$childEditors = $this->getEditors();

		while ( !$result && $i < count( $childEditors ) ) {
			$editor = $childEditors[$i];
			$attribute = $editor->getAttribute();
			$attributeValue = $value->getAttributeValue( $attribute );
			$result = $editor->showsData( $attributeValue );
			$i++;
		}

		return $result;
	}

	protected function shouldCompressOnView( $idPath, $value, $editors ) {
		$visibleEditorCount = 0;

		foreach ( $editors as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );

			if ( $editor->showsData( $value->getAttributeValue( $attribute ) ) ) {
				$visibleEditorCount++;
			}
			$idPath->popAttribute();
		}

		return $visibleEditorCount <= 1;
	}

	protected function viewEditors( IdStack $idPath, $value, $editors, $htmlTag, $compress ) {
		$result = '';

		foreach ( $editors as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );
			$class = $idPath->getClass();
			$attributeId = $idPath->getId();
			$attributeValue = $value->getAttributeValue( $attribute );

			if ( $editor->showsData( $attributeValue ) ) {
				if ( !$compress ) {
					$result .= Html::openElement( $htmlTag, [ 'class' => $class ] );

					if ( $editor->getDisplayHeader() ) {
						$result .= $this->childHeader( $editor, $attribute, $class, $attributeId );
					}
				}
				$result .= $this->viewChild( $editor, $idPath, $value, $attribute, $class, $attributeId );

				if ( !$compress ) {
					$result .= Html::closeElement( $htmlTag );
				}
			}

			$idPath->popAttribute();
		} // foreach editors

		return $result;
	}

	public function view( IdStack $idPath, $value ) {
		$editors = $this->getEditors();
		return $this->viewEditors( $idPath, $value, $editors, $this->htmlTag, $this->shouldCompressOnView( $idPath, $value, $editors ) );
	}

	public function showEditField( IdStack $idPath ) {
		return true;
	}

	protected function shouldCompressOnEdit( $idPath, $value, $editors ) {
		$visibleEditorCount = 0;

		foreach ( $editors as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );

			if ( $editor->showEditField( $idPath ) ) {
				$visibleEditorCount++;
			}
			$idPath->popAttribute();
		}

		return $visibleEditorCount <= 1;
	}

	protected function editEditors( IdStack $idPath, $value, $editors, $htmlTag, $compress ) {
		$result = '';

		foreach ( $editors as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );

			if ( $editor->showEditField( $idPath ) ) {
				$class = $idPath->getClass();
				$attributeId = $idPath->getId();

				if ( !$compress ) {
					$result .= Html::openElement( $htmlTag, [ 'class' => $class ] );

					if ( $editor->getDisplayHeader() ) {
						$result .= $this->childHeader( $editor, $attribute, $class, $attributeId );
					}
				}

				$result .= $this->editChild( $editor, $idPath, $value,  $attribute, $class, $attributeId );

				if ( !$compress ) {
					$result .= Html::closeElement( $htmlTag );
				}
			}

			$idPath->popAttribute();
		}

		return $result;
	}

	public function edit( IdStack $idPath, $value ) {
		$editors = $this->getEditors();
		return $this->editEditors( $idPath, $value, $editors, $this->htmlTag, $this->shouldCompressOnEdit( $idPath, $value, $editors ) );
	}

	protected function addEditors( IdStack $idPath, $editors, $htmlTag ) {
		$result = '';

		foreach ( $editors as $editor ) {
			if ( $attribute = $editor->getAddAttribute() ) {
				$idPath->pushAttribute( $attribute );
				$class = $idPath->getClass() . '-add';
				$attributeId = $idPath->getId();

				$result .= Html::openElement( $htmlTag );
				if ( $editor->getDisplayHeader() ) {
					$result .= $this->childHeader( $editor, $attribute, $class, $attributeId );
				}
				$result .= $this->addChild( $editor, $idPath, $attribute, $class, $attributeId );
				$result .= Html::closeElement( $htmlTag );

				$editor->add( $idPath );
				$idPath->popAttribute();
			}
		}

		return $result;
	}

	public function add( IdStack $idPath ) {
		return $this->addEditors( $idPath, $this->getEditors(), $this->htmlTag );
	}

	protected function childHeader( Editor $editor, Attribute $attribute, $class, $attributeId ) {
		$expansionPrefix = $this->getExpansionPrefix( $class, $attributeId );

		$divclass = 'level' . $this->headerLevel;
		$childHeaderHtml = Html::openElement( 'div', [ 'class' => $divclass ] );

		$spanclass = $class;
		if ( $this->isCollapsible ) {
			$spanclass = 'toggle ' . $this->addCollapsablePrefixToClass( $class );
		}
		$spanattribs = [ 'class' => $spanclass ];
		$spantext = $expansionPrefix . '&#160;' . $attribute->name;

		$childHeaderHtml .= Html::rawElement( 'span', $spanattribs, $spantext );
		$childHeaderHtml .= Html::closeElement( 'div' );

		return $childHeaderHtml;
	}

	protected function viewChild( Editor $editor, IdStack $idPath, $value, Attribute $attribute, $class, $attributeId ) {
		$divid = 'collapsable-' . $attributeId;
		$divclass = 'expand-' . $class;
		$divcontent = $editor->view( $idPath, $value->getAttributeValue( $attribute ) );
		return Html::rawElement( 'div', [ 'id' => $divid, 'class' => $divclass ], $divcontent );
	}

	protected function editChild( Editor $editor, IdStack $idPath, $value, Attribute $attribute, $class, $attributeId ) {
		if ( $this->isCollapsible ) {
			$divid = 'collapsable-' . $attributeId;
			$divclass = 'expand-' . $class;
		} else {
			$divid = $attributeId;
			$divclass = $class;
		}
		$divcontent = $editor->edit( $idPath, $value->getAttributeValue( $attribute ) );
		return Html::rawElement( 'div', [ 'id' => $divid, 'class' => $divclass ], $divcontent );
	}

	protected function addChild( Editor $editor, IdStack $idPath, Attribute $attribute, $class, $attributeId ) {
		$attrid = 'collapsable-' . $attributeId;
		$attrclass = 'expand-' . $class;
		$attr = [ 'id' => $attrid, 'class' => $attrclass ];
		$divcontent = $editor->add( $idPath );
		return Html::rawElement( 'div', $attr, $divcontent );
	}
}

class RecordUnorderedListEditor extends RecordListEditor {
	public function __construct( Attribute $attribute = null, $headerLevel ) {
		parent::__construct( $attribute, $headerLevel, "li" );
	}

	protected function wrapInList( $listItems ) {
		if ( $listItems != "" ) {
			return Html::rawElement( 'ul', [ 'class' => 'collapsable-items' ], $listItems );
		}
		return "";
	}

	public function view( IdStack $idPath, $value ) {
		$editors = $this->getEditors();

		// $compress = $this->shouldCompressOnView( $idPath, $value, $editors );
		$compress = false;
		$result = $this->viewEditors( $idPath, $value, $editors, $this->htmlTag, $compress );

		if ( !$compress ) {
			return $this->wrapInList( $result );
		} else {
			return $result;
		}
	}

	public function edit( IdStack $idPath, $value ) {
		$editors = $this->getEditors();
		$compress = $this->shouldCompressOnEdit( $idPath, $value, $editors );
		$result = $this->editEditors( $idPath, $value, $editors, $this->htmlTag, $compress );

		if ( !$compress ) {
			return $this->wrapInList( $result );
		} else {
			return $result;
		}
	}

	public function add( IdStack $idPath ) {
		return $this->wrapInList( parent::add( $idPath ) );
	}
}

class RecordDivListEditor extends RecordListEditor {
	public function __construct( Attribute $attribute = null ) {
		parent::__construct( $attribute, 0, "div" );
	}

	protected function wrapInDiv( $listItems ) {
		return Html::rawElement( 'div', [], $listItems );
	}

	public function view( IdStack $idPath, $value ) {
		return $this->wrapInDiv( parent::view( $idPath, $value ) );
	}

	public function edit( IdStack $idPath, $value ) {
		return $this->wrapInDiv( parent::edit( $idPath, $value ) );
	}

	public function add( IdStack $idPath ) {
		return $this->wrapInDiv( parent::add( $idPath ) );
	}

	protected function childHeader( Editor $editor, Attribute $attribute, $class, $attributeId ) {
		return "";
	}
}

class RecordSetListEditor extends RecordSetEditor {
	protected $headerLevel;
	protected $captionEditor;
	protected $valueEditor;

	public function __construct( Attribute $attribute = null, PermissionController $permissionController, ShowEditFieldChecker $showEditFieldChecker, AllowAddController $allowAddController, $allowRemove, $isAddField, UpdateController $controller = null, $headerLevel ) {
		parent::__construct( $attribute, $permissionController, $showEditFieldChecker, $allowAddController, $allowRemove, $isAddField, $controller );

		$this->headerLevel = $headerLevel;
	}

	public function setCaptionEditor( Editor $editor ) {
		$this->captionEditor = $editor;
		$this->editors[0] = $editor;
	}

	public function setValueEditor( Editor $editor ) {
		$this->valueEditor = $editor;
		$this->editors[1] = $editor;
	}

	public function view( IdStack $idPath, $arrayRecordSet ) {
		$recordCount = $arrayRecordSet->getRecordCount();

		if ( $recordCount > 0 ) {
			$result = Html::openElement( 'ul', [ 'class' => 'collapsable-items' ] );
			$key = $arrayRecordSet->getKey();
			$captionAttribute = $this->captionEditor->getAttribute();
			$valueAttribute = $this->valueEditor->getAttribute();
			$extraLevelUlOpen = false;

			for ( $i = 0; $i < $recordCount; $i++ ) {
				// check if we have an extraHierarchyCaption to add
				// this can happen for example if we sort by part of speeches and want to display that.
				$extraLevelName = $arrayRecordSet->getExtraHierarchyCaption( $i );
				if ( $extraLevelName !== null ) {
					// close the previous extraHierarchy if needed
					if ( $extraLevelUlOpen ) {
						$result .= Html::closeElement( 'ul' );
					}
					$extraLevelClass = $idPath->getClass() . "-sortcaption";
					$result .= Html::openElement( 'li', [ 'class' => $extraLevelClass ] );
					$result .= Html::rawElement( 'span', [], $extraLevelName );
					$result .= Html::closeElement( 'li' );
					$result .= Html::openElement( 'ul', [ 'class' => 'collapsable-items' ] );
					$extraLevelUlOpen = true;
				}

				$record = $arrayRecordSet->getRecord( $i );
				$idPath->pushKey( project( $record, $key ) );
				$recordId = $idPath->getId();
				$captionClass = $idPath->getClass() . "-record";
				$captionExpansionPrefix = $this->getExpansionPrefix( $captionClass, $recordId );
				$valueClass = $idPath->getClass() . "-record";

				$idPath->pushAttribute( $captionAttribute );
				$result .= Html::openElement( 'li' );
				$class = 'level' . $this->headerLevel;
				$result .= Html::openElement( 'div', [ 'class' => $class ] );

				$text = $captionExpansionPrefix . '&#160;'
					. $this->captionEditor->view( $idPath, $record->getAttributeValue( $captionAttribute ) );

				$attribs = []; // default if not collapsible
				if ( $this->isCollapsible ) {
					// collapsible element
					$class = 'toggle ' . $this->addCollapsablePrefixToClass( $captionClass );
					$attribs = [ 'class' => $class ];
				}
				$result .= Html::rawElement( 'span', $attribs, $text );
				$result .= Html::closeElement( 'div' );

				$idPath->popAttribute();
				$idPath->pushAttribute( $valueAttribute );

				$text = $this->valueEditor->view( $idPath, $record->getAttributeValue( $valueAttribute ) );
				$class = 'expand-' . $valueClass;
				$id = 'collapsable-' . $recordId;
				$result .= Html::rawElement( 'div', [ 'class' => $class , 'id' => $id ], $text );
				$result .= Html::closeElement( 'li' );
				$idPath->popAttribute();
				$idPath->popKey();
			}

			// close the extraHierarchy if needed
			if ( $extraLevelUlOpen ) {
				$result .= Html::closeElement( 'ul' );
			}

			$result .= Html::closeElement( 'ul' );
			return $result;
		}
		return "";
	}

	public function edit( IdStack $idPath, $arrayRecordSet ) {
		$recordCount = $arrayRecordSet->getRecordCount();

		if ( $recordCount > 0 || $this->allowAddController->check( $idPath ) ) {
			$result = Html::openElement( 'ul', [ 'class' => "collapsable-items" ] );
			$key = $arrayRecordSet->getKey();
			$captionAttribute = $this->captionEditor->getAttribute();
			$valueAttribute = $this->valueEditor->getAttribute();

			for ( $i = 0; $i < $recordCount; $i++ ) {
				$record = $arrayRecordSet->getRecord( $i );
				$idPath->pushKey( project( $record, $key ) );

				$recordId = $idPath->getId();
				$captionClass = $idPath->getClass();
				$captionExpansionPrefix = $this->getExpansionPrefix( $captionClass, $recordId );
				$valueClass = $idPath->getClass();

				$idPath->pushAttribute( $captionAttribute );

				$result .= Html::openElement( 'li' );
				$divclass = 'level' . $this->headerLevel;
				$result .= Html::openElement( 'div', [ 'class' => $divclass ] );

				$spantext = $captionExpansionPrefix . '&#160;'
					. $this->captionEditor->edit( $idPath, $record->getAttributeValue( $captionAttribute ) );
				$spanattribs = []; // default if not collapsible
				if ( $this->isCollapsible ) {
					// add toggle as a class
					$spanclass = 'toggle ' . $this->addCollapsablePrefixToClass( $captionClass );
					$spanattribs = [ 'class' => $spanclass ];
				}
				$result .= Html::rawElement( 'span', $spanattribs, $spantext );
				$result .= Html::closeElement( 'div' );

				$idPath->popAttribute();
				$idPath->pushAttribute( $valueAttribute );

				if ( $this->isCollapsible ) {
					$divid = 'collapsable-' . $recordId;
					$divclass = 'expand-' . $valueClass;
				} else {
					$divid = $recordId;
					$divclass = $valueClass;
				}
				$divattribs = [ 'id' => $divid, 'class' => $divclass ];
				$divtext = $this->valueEditor->edit( $idPath, $record->getAttributeValue( $valueAttribute ) );
				$result .= Html::rawElement( 'div', $divattribs, $divtext );

				$result .= Html::closeElement( 'li' );
				$idPath->popAttribute();

				$idPath->popKey();
			}

			if ( $this->allowAddController->check( $idPath ) ) {
				$recordId = 'add-' . $idPath->getId();
				$idPath->pushAttribute( $captionAttribute );
				$class = $idPath->getClass();

				$result .= Html::openElement( 'li' );
				$divclass = 'level' . $this->headerLevel;
				$result .= Html::openElement( 'div', [ 'class' => $divclass ] );

				$spantext = $this->getExpansionPrefix( $idPath->getClass(), $idPath->getId() ) . $this->captionEditor->add( $idPath );
				$spanattribs = []; // default if not collapsible
				if ( $this->isCollapsible ) {
					// add toggle as a class
					$spanclass = 'toggle ' . $this->addCollapsablePrefixToClass( $class );
					$spanattribs = [ 'class' => $spanclass ];
				}
				$result .= Html::rawElement( 'span', $spanattribs, $spantext );
				$result .= Html::closeElement( 'div' );

				$idPath->popAttribute();
				$idPath->pushAttribute( $valueAttribute );#

				$divid = 'collapsable-' . $recordId;
				$divclass = 'expand-' . $class;
				$divattribs = [ 'id' => $divid, 'class' => $divclass ];
				$divtext = $this->valueEditor->add( $idPath );

				$result .= Html::rawElement( 'div', $divattribs, $divtext );
				$result .= Html::closeElement( 'li' );

				$idPath->popAttribute();
			}

			$result .= Html::closeElement( 'ul' );

			return $result;
		}
		return "";
	}

	public function add( IdStack $idPath ) {
		$result = Html::openElement( 'ul', [ 'class' => 'collapsable-items' ] );
		$captionAttribute = $this->captionEditor->getAttribute();
		$valueAttribute = $this->valueEditor->getAttribute();

		$recordId = 'add-' . $idPath->getId();

		$idPath->pushAttribute( $captionAttribute );
		$class = $idPath->getClass();

		$result .= Html::openElement( 'li' );
		$divclass = 'level' . $this->headerLevel;
		$result .= Html::openElement( 'div', [ 'class' => $divclass ] );

		$spanattr = [];
		if ( $this->isCollapsible ) {
			$spanclass = 'toggle ' . $this->addCollapsablePrefixToClass( $class );
			$spanattr['class'] = $spanclass;
		}
		$result .= Html::openElement( 'span', $spanattr );
		$result .= $this->getExpansionPrefix( $idPath->getClass(), $idPath->getId() );
		$result .= '&#160;';
		$result .= $this->captionEditor->add( $idPath );
		$result .= Html::closeElement( 'span' );
		$result .= Html::closeElement( 'div' );

		$idPath->popAttribute();

		$idPath->pushAttribute( $valueAttribute );
		$divid = 'collapsable-' . $recordId;
		$divclass = 'expand-' . $class;
		$result .= Html::openElement( 'div', [ 'id' => $divid, 'class' => $divclass ] );
		$result .= $this->valueEditor->add( $idPath );
		$result .= Html::closeElement( 'div' );
		$result .= Html::closeElement( 'li' );

		$idPath->popAttribute();

		$result .= Html::closeElement( 'ul' );

		return $result;
	}
}

class AttributeLabelViewer extends Viewer {
	public function view( IdStack $idPath, $value ) {
		return $this->attribute->name;
	}

	public function add( IdStack $idPath ) {
		return "New " . strtolower( $this->attribute->name );
	}

	public function showsData( $value ) {
		return true;
	}

	public function showEditField( IdStack $idPath ) {
		return true;
	}
}

class RecordSpanEditor extends RecordEditor {
	protected $attributeSeparator;
	protected $valueSeparator;
	protected $showAttributeNames;

	public function __construct( Attribute $attribute = null, $valueSeparator, $attributeSeparator, $showAttributeNames = true ) {
		parent::__construct( $attribute );

		$this->attributeSeparator = $attributeSeparator;
		$this->valueSeparator = $valueSeparator;
		$this->showAttributeNames = $showAttributeNames;
	}

	public function view( IdStack $idPath, $value ) {
		$fields = [];

		foreach ( $this->getEditors() as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );
			$attributeValue = $editor->view( $idPath, $value->getAttributeValue( $attribute ) );

			if ( $this->showAttributeNames ) {
				$field = $attribute->name . $this->valueSeparator . $attributeValue;
			} else {
				$field = $attributeValue;
			}
			if ( $field != "" ) {
				$fields[] = $field;
			}
			$idPath->popAttribute();
		}

		return implode( $this->attributeSeparator, $fields );
	}

	public function add( IdStack $idPath ) {
		$fields = [];

		foreach ( $this->getEditors() as $editor ) {
			if ( $attribute = $editor->getAddAttribute() ) {
				$attribute = $editor->getAttribute();
				$idPath->pushAttribute( $attribute );
				$attributeId = $idPath->getId();
				if ( $this->showAttributeNames ) {
					$fields[] = $attribute->name . $this->valueSeparator . $editor->add( $idPath );
				} else {
					$fields[] = $editor->add( $idPath );
				}
				$editor->add( $idPath );
				$idPath->popAttribute();
			}
		}

		return implode( $this->attributeSeparator, $fields );
	}

	public function edit( IdStack $idPath, $value ) {
		$fields = [];

		foreach ( $this->getEditors() as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );
			if ( $this->showAttributeNames ) {
				$fields[] = $attribute->name . $this->valueSeparator . $editor->view( $idPath, $value->getAttributeValue( $attribute ) );
			} else {
				$fields[] = $editor->view( $idPath, $value->getAttributeValue( $attribute ) );
			}
			$idPath->popAttribute();
		}

		return implode( $this->attributeSeparator, $fields );
	}
}

class UserEditor extends ScalarEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		if ( $value != "" ) {
			return Linker::link( Title::newFromText( "User:" . $value ), $value );
		}
		return "";
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		return $this->getViewHTML( $idPath, $value );
	}

	public function getInputValue( $id ) {
	}

	public function add( IdStack $idPath ) {
	}
}

class TimestampEditor extends ScalarEditor {
	public function getViewHTML( IdStack $idPath, $value ) {
		if ( $value != "" ) {
			return timestampAsText( $value );
		}
		return "";
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		return $this->getViewHTML( $idPath, $value );
	}

	public function getInputValue( $id ) {
	}

	public function add( IdStack $idPath ) {
	}
}

// The roll back editor is tricked. It shows a checkbox when its value is 'true', meaning that the record is the latest
// so it can be rolled back. However, when requesting the input value it returns the value of the roll back check box.
// This can possibly be solved better later on when we choose to let editors fetch the value(s) of the attribute(s) they're
// viewing within their parent. The roll back editor could then inspect the value of the $isLatestAttribute to decide whether
// to show the roll back check box.

// class RollbackEditor extends BooleanEditor {
// public function __construct($attribute)  {
// parent::__construct($attribute, new SimplePermissionController(false), false, false);
// }
//
// public function getViewHTML($idPath, $value) {
// if ($value)
// return $this->getEditHTML($idPath, false);
// else
// return "";
// }
//
// public function shouldRollBack($id, $value) {
// return $value && isset($_POST[$id]);
// }
// }

class RollBackEditor extends ScalarEditor {
	protected $hasValueFields;
	protected $suggestionsEditor;

	public function __construct( Attribute $attribute = null, $hasValueFields ) {
		parent::__construct( $attribute, new SimplePermissionController( false ), false, false );

		$this->hasValueFields = $hasValueFields;
	}

	public function getViewHTML( IdStack $idPath, $value ) {
		$isLatest = $value->isLatest;
		$operation = $value->operation;

		if ( $isLatest ) {
			$options = [ 'do-nothing' => wfMessage( 'ow_transaction_no_action' )->text() ];

			if ( $this->hasValueFields ) {
				$previousVersionLabel = wfMessage( 'ow_transaction_previous_version' )->text();
				$rollBackChangeHandler = 'rollBackOptionChanged(this);';
			} else {
				$previousVersionLabel = wfMessage( 'ow_transaction_restore' )->text();
				$rollBackChangeHandler = '';
			}

			if ( $this->hasValueFields || $operation != 'Added' ) {
				$options['previous-version'] = $previousVersionLabel;
			}
			if ( $operation != 'Removed' ) {
				$options['remove'] = wfMessage( 'ow_transaction_remove' )->text();
			}
			$result = getSelect( $idPath->getId(), $options, 'do-nothing', $rollBackChangeHandler );

			if ( $this->suggestionsEditor != null ) {
				$divid = $idPath->getId() . '-version-selector';
				$divstyle = 'display: none; padding-top: 4px;';
				$divcontent = $this->getSuggestionsHTML( $idPath, $value );
				$result .= Html::rawElement( 'div', [ 'id' => $divid, 'style' => $divstyle ], $divcontent );
			}
			return $result;
		}
		return "";
	}

	public function getEditHTML( IdStack $idPath, $value ) {
		return $this->getViewHTML( $idPath, $value );
	}

	protected function getSuggestionsHTML( IdStack $idPath, $value ) {
		$attribute = $this->suggestionsEditor->getAttribute();
		$idPath->pushAttribute( $attribute );
		$result = $this->suggestionsEditor->view( $idPath, $value->getAttributeValue( $attribute ) );
		$idPath->popAttribute();

		return $result;
	}

	public function getInputValue( $id ) {
		return "";
	}

	public function add( IdStack $idPath ) {
	}

	public function setSuggestionsEditor( Editor $suggestionsEditor ) {
		$this->suggestionsEditor = $suggestionsEditor;
	}
}

class RecordSubRecordEditor extends RecordEditor {
	protected $subRecordEditor;

	public function view( IdStack $idPath, $value ) {
		$attribute = $this->subRecordEditor->getAttribute();
		$idPath->pushAttribute( $attribute );
		$result = $this->subRecordEditor->view( $idPath, $value->getAttributeValue( $attribute ) );
		$idPath->popAttribute();

		return $result;
	}

	public function edit( IdStack $idPath, $value ) {
		$attribute = $this->subRecordEditor->getAttribute();
		$idPath->pushAttribute( $attribute );
		$result = $this->subRecordEditor->edit( $idPath, $value->getAttributeValue( $attribute ) );
		$idPath->popAttribute();

		return $result;
	}

	public function add( IdStack $idPath ) {
		$attribute = $this->subRecordEditor->getAttribute();
		$idPath->pushAttribute( $attribute );
		$result = $this->subRecordEditor->add( $idPath );
		$idPath->popAttribute();

		return $result;
	}

	public function setSubRecordEditor( Editor $subRecordEditor ) {
		$this->subRecordEditor = $subRecordEditor;
		$this->editors[0] = $subRecordEditor;
	}
}

class RecordSetFirstRecordEditor extends RecordSetEditor {
	protected $recordEditor;

	public function view( IdStack $idPath, $value ) {
		if ( $value->getRecordCount() > 0 ) {
			$record = $value->getRecord( 0 );
			$idPath->pushKey( project( $record, $value->getKey() ) );
			$result = $this->recordEditor->view( $idPath, $record );
			$idPath->popKey();

			return $result;
		}
		return "";
	}

	public function edit( IdStack $idPath, $value ) {
		if ( $value->getRecordCount() > 0 ) {
			$record = $value->getRecord( 0 );
			$idPath->pushKey( project( $record, $value->getKey() ) );
			$result = $this->recordEditor->edit( $idPath, $record );
			$idPath->popKey();
		} else {
			$result = $this->recordEditor->add( $idPath );
		}
		return $result;
	}

	public function add( IdStack $idPath ) {
		return "";
	}

	public function save( IdStack $idPath, $value ) {
		if ( $value->getRecordCount() > 0 ) {
			$record = $value->getRecord( 0 );
			$idPath->pushKey( project( $record, $value->getKey() ) );
			$this->recordEditor->save( $idPath, $record );
			$idPath->popKey();
		} else {
			$addValues = [];
			$addValues = $this->recordEditor->getAddValues( $idPath );
			foreach ( $addValues as $addValue ) {
				$this->controller->add( $idPath, $addValue );
			}
		}
	}

	public function setRecordEditor( Editor $recordEditor ) {
		$this->recordEditor = $recordEditor;
		$this->editors[0] = $recordEditor;
	}
}

class ObjectPathEditor extends Viewer {
	public function view( IdStack $idPath, $value ) {
		return $this->resolveObject( $value );
	}

	protected function resolveObject( $objectId ) {
		$dc = wdGetDataSetContext();
		wfDebug( "dc is <$dc>\n" );

		$tableName = getTableNameWithObjectId( $objectId );

		if ( $tableName != "" ) {
			switch ( $tableName ) {
				case "{$dc}_meaning_relations":
					$result = $this->resolveRelation( $objectId );
					break;
				case "{$dc}_text_attribute_values":
				case "{$dc}_url_attribute_values":
				case "{$dc}_translated_text_attribute_values":
				case "{$dc}_option_attribute_values":
					$result = $this->resolveAttribute( $objectId, $tableName );
					break;
				case "{$dc}_translated_content":
					$result = $this->resolveTranslatedContent( $objectId );
					break;
				case "{$dc}_syntrans":
					$result = $this->resolveSyntrans( $objectId );
					break;
				case "{$dc}_defined_meaning":
					$result = $this->resolveDefinedMeaning( $objectId );
					break;
				default:
					$result = $tableName . " - " . $objectId;
			}
		} else {
			$result = "Object $objectId";
		}
		return $result;
	}

	protected function resolveRelation( $objectId ) {
		$relation = OwDatabaseAPI::getRelationIdRelationAttribute( $objectId );

		if ( $relation ) {
			return definedMeaningAsLink( $relation->meaning1_mid ) . " - " .
				definedMeaningAsLink( $relation->relationtype_mid ) . " - " .
				definedMeaningAsLink( $relation->meaning2_mid );
		} else {
			return "Relation " . $objectId;
		}
	}

	protected function resolveAttribute( $objectId, $tableName ) {
		$dbr = wfGetDB( DB_REPLICA );

		// @todo This query probably needs to be placed in the db API, but where? ~he
		$attribute = $dbr->selectRow(
			$tableName,
			[
				'object_id',
				'attribute_mid'
			],
			[
				'value_id' => $objectId
			], __METHOD__
		);

		if ( $attribute ) {
			return $this->resolveObject( $attribute->object_id ) . " > " .
				definedMeaningAsLink( $attribute->attribute_mid );
		} else {
			return "Attribute " . $objectId;
		}
	}

	protected function resolveTranslatedContent( $objectId ) {
		$definedMeaning = OwDatabaseAPI::getTranslatedContentIdDefinedMeaningId( $objectId );

		if ( $definedMeaning ) {
			return definedMeaningAsLink( $definedMeaning->defined_meaning_id ) . " > Definition ";
		} else {
			return "Translated content " . $objectId;
		}
	}

	protected function resolveSyntrans( $objectId ) {
		$syntrans = OwDatabaseAPI::getSyntransSpellingWithDM( $objectId );

		if ( $syntrans ) {
			return definedMeaningAsLink( $syntrans->defined_meaning_id ) . " > " . spellingAsLink( $syntrans->spelling );
		} else {
			return "Syntrans " . $objectId;
		}
	}

	protected function resolveDefinedMeaning( $definedMeaningId ) {
		return definedMeaningAsLink( $definedMeaningId );
	}

	public function showsData( $value ) {
		return true;
	}
}
