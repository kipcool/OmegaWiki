<?php

require_once "Attribute.php";
require_once "Record.php";
require_once "RecordSet.php";

/* Functions to create a hierarchical table header
 * using rowspan and colspan for <th> elements
 */

class TableHeaderNode {
	public $attribute = null;
	public $width = 0;
	public $height = 0;
	public $column = 0;
	public $childNodes = [];
}

function getTableHeaderNode( Structure $structure, &$currentColumn = 0 ) {
	$tableHeaderNode = new TableHeaderNode();

	foreach ( $structure->getAttributes() as $attribute ) {
		$type = $attribute->type;

		if ( $type instanceof Structure ) {
			$atts = $type->getAttributes();
			$childNode = getTableHeaderNode( new Structure( $atts ), $currentColumn );
		} else {
			$childNode = new TableHeaderNode();
			$childNode->width = 1;
			$childNode->height = 1;
			$childNode->column = $currentColumn++;
		}

		$tableHeaderNode->height = max( $tableHeaderNode->height, $childNode->height );
		$tableHeaderNode->width += $childNode->width;
		$tableHeaderNode->childNodes[] = $childNode;
		$childNode->attribute = $attribute;
	}

	$tableHeaderNode->height++;

	return $tableHeaderNode;
}

function addChildNodesToRows( TableHeaderNode $headerNode, &$rows, $currentDepth, $columnOffset, IdStack $idPath, $leftmost = true ) {
	$height = $headerNode->height;
	foreach ( $headerNode->childNodes as $childNode ) {
		$attribute = $childNode->attribute;
		$idPath->pushAttribute( $attribute );
		$type = $attribute->type;

		if ( !$type instanceof Structure ) {
			$class = $type . ' ' . $attribute->id;
		} else {
			$class = $attribute->id;
		}
		$id = $idPath->getId() . '-h';
		$rowSpan = $height - $childNode->height;
		$colSpan = $childNode->width;
		$attr = [ 'id' => $id, 'class' => $class, 'rowspan' => $rowSpan, 'colspan' => $colSpan ];
		$rows[$currentDepth] .= Html::element( 'th', $attr, $attribute->name );

		addChildNodesToRows( $childNode, $rows, $currentDepth + $rowSpan, $columnOffset, $idPath, $leftmost );
		$idPath->popAttribute();
	}
}

function getStructureAsTableHeaderRows( Structure $structure, $columnOffset, IdStack $idPath ) {
	$rootNode = getTableHeaderNode( $structure );
	$result = [];

	for ( $i = 0; $i < $rootNode->height - 1; $i++ ) {
		$result[$i] = "";
	}
	addChildNodesToRows( $rootNode, $result, 0, $columnOffset, $idPath );

	return $result;
}

function getHTMLClassForType( $type, Attribute $attribute ) {
	if ( $type instanceof Structure ) {
		return $attribute->id;
	} else {
		return $type;
	}
}

function getRecordAsTableCells( IdStack $idPath, Editor $editor, Structure $visibleStructure, Record $record ) {
	$result = '';
	$childEditorMap = $editor->getAttributeEditorMap();

	foreach ( $visibleStructure->getAttributes() as $visibleAttribute ) {
		$childEditor = $childEditorMap->getEditorForAttribute( $visibleAttribute );

		if ( $childEditor != null ) {
			$attribute = $childEditor->getAttribute();
			$type = $attribute->type;
			$value = $record->getAttributeValue( $attribute );
			$idPath->pushAttribute( $attribute );
			$attributeId = $idPath->getId();

			if ( $childEditor instanceof RecordTableCellEditor ) {
				$result .= getRecordAsTableCells( $idPath, $childEditor, $visibleAttribute->type, $value );
			} else {
				$tdclass = getHTMLClassForType( $type, $attribute );
				$tdattribs = [ "class" => $tdclass ];
				$displayValue = $childEditor->showsData( $value ) ? $childEditor->view( $idPath, $value ) : "";

				if ( $childEditor instanceof LanguageEditor ) {
					$tdattribs["langid"] = $value;
				}
				$result .= Html::rawElement( 'td', $tdattribs, $displayValue );
			}

			$idPath->popAttribute();
		} else {
			$result .= Html::element( 'td' );
		}
	}
	return $result;
}

function getRecordAsEditTableCells( IdStack $idPath, Editor $editor, Structure $visibleStructure, Record $record ) {
	$result = '';
	$childEditorMap = $editor->getAttributeEditorMap();

	foreach ( $visibleStructure->getAttributes() as $visibleAttribute ) {
		$childEditor = $childEditorMap->getEditorForAttribute( $visibleAttribute );

		if ( $childEditor != null ) {
			$attribute = $childEditor->getAttribute();
			$type = $attribute->type;
			$value = $record->getAttributeValue( $attribute );
			$idPath->pushAttribute( $attribute );

			if ( $childEditor instanceof RecordTableCellEditor ) {
				$result .= getRecordAsEditTableCells( $idPath, $childEditor, $visibleAttribute->type, $value );
			} else {
				$tdclass = getHTMLClassForType( $type, $attribute ) . ' edit';
				$tdattribs = [ "class" => $tdclass ];
				$displayValue = $childEditor->showEditField( $idPath ) ? $childEditor->edit( $idPath, $value ) : "";

				if ( $childEditor instanceof LanguageEditor ) {
					$tdattribs["langid"] = $value;
				}
				$result .= Html::rawElement( 'td', $tdattribs, $displayValue );
			}

			$idPath->popAttribute();
		} else {
			$result .= Html::element( 'td' );
		}
	}
	return $result;
}
