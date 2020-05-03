<?php

require_once "Editor.php";

/**
 * an editor that wraps around another unique editor
 * such as popup editor
 */
class WrappingEditor implements Editor {
	protected $wrappedEditor;

	public function __construct( Editor $wrappedEditor ) {
		$this->wrappedEditor = $wrappedEditor;
	}

	public function getAttribute() {
		return $this->wrappedEditor->getAttribute();
	}

	public function getUpdateAttribute() {
		return $this->wrappedEditor->getUpdateAttribute();
	}

	public function getAddAttribute() {
		return $this->wrappedEditor->getAddAttribute();
	}

	public function showsData( $value ) {
		return $this->wrappedEditor->showsData( $value );
	}

	public function showEditField( IdStack $idPath ) {
		return $this->wrappedEditor->showEditField( $idPath );
	}

	public function view( IdStack $idPath, $value ) {
		return $this->wrappedEditor->view( $idPath, $value );
	}

	public function edit( IdStack $idPath, $value ) {
		return $this->wrappedEditor->edit( $idPath, $value );
	}

	public function add( IdStack $idPath ) {
		return $this->wrappedEditor->add( $idPath );
	}

	public function save( IdStack $idPath, $value ) {
		$this->wrappedEditor->save( $idPath, $value );
	}

	public function getUpdateValue( IdStack $idPath ) {
		return $this->wrappedEditor->getUpdateValue( $idPath );
	}

	public function getAddValues( IdStack $idPath ) {
		return $this->wrappedEditor->getAddValues( $idPath );
	}

	public function getEditors() {
		return $this->wrappedEditor->getEditors();
	}

	public function getAttributeEditorMap() {
		return $this->wrappedEditor->getAttributeEditorMap();
	}

	public function getDisplayHeader() {
		// only used for DefaultEditor so far.
		// here returns only default true.
		return true;
	}
}

/**
 * Editor to edit object attributes, i.e. annotations
 * it is a wrappingEditor for RecordUnorderedListEditor
 */
class ObjectAttributeValuesEditor extends WrappingEditor {
	protected $recordSetTableEditor;
	protected $propertyAttribute;
	protected $valueAttribute;
	protected $attributeIDFilter;
	protected $levelName;
	protected $showPropertyColumn;

	public function __construct( Attribute $attribute, $propertyCaption, $valueCaption, ViewInformation $viewInformation, $levelName, AttributeIDFilter $attributeIDFilter ) {
		$this->wrappedEditor = new RecordUnorderedListEditor( $attribute, 5 );

		$this->levelName = $levelName;
		$this->attributeIDFilter = $attributeIDFilter;
		$this->showPropertyColumn = !$attributeIDFilter->leavesOnlyOneOption();

		$this->recordSetTableEditor = new RecordSetTableEditor(
			$attribute,
			new SimplePermissionController( false ),
			new ShowEditFieldChecker( true ),
			new AllowAddController( false ),
			false,
			false,
			null
		);

		$this->propertyAttribute = new Attribute( "property", $propertyCaption, "short-text" );
		$this->valueAttribute = new Attribute( "value", $valueCaption, "short-text" );

		foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
			$this->recordSetTableEditor->addEditor( new DummyViewer( $propertyToColumnFilter->getAttribute() ) );
		}

		$o = OmegaWikiAttributes::getInstance();

		$this->recordSetTableEditor->addEditor( new DummyViewer( $o->objectAttributes ) );

		if ( $viewInformation->showRecordLifeSpan ) {
			$this->recordSetTableEditor->addEditor( createTableLifeSpanEditor( $o->recordLifeSpan ) );
		}
	}

	public function getAttributeIDFilter() {
		return $this->attributeIDFilter;
	}

	public function getLevelName() {
		return $this->levelName;
	}

	protected function attributeInStructure( Attribute $attribute, Structure $structure ) {
		$result = false;
		$attributes = $structure->getAttributes();
		$i = 0;

		while ( !$result && $i < count( $attributes ) ) {
			$result = $attribute->id == $attributes[$i]->id;
			$i++;
		}

		return $result;
	}

	protected function attributeInStructures( Attribute $attribute, array &$structures ) {
		$result = false;
		$i = 0;

		while ( !$result && $i < count( $structures ) ) {
			$result = $this->attributeInStructure( $attribute, $structures[$i] );
			$i++;
		}

		return $result;
	}

	protected function getSubStructureForAttribute( Structure $structure, Attribute $attribute ) {
		$attributes = $structure->getAttributes();
		$result = null;
		$i = 0;

		while ( $result == null && $i < count( $attributes ) ) {
			if ( $attribute->id == $attributes[$i]->id ) {
				$result = $attributes[$i]->type;
			} else {
				$i++;
			}
		}

		return $result;
	}

	protected function filterStructuresOnAttribute( array &$structures, Attribute $attribute ) {
		$result = [];

		foreach ( $structures as $structure ) {
			$subStructure = $this->getSubStructureForAttribute( $structure, $attribute );

			if ( $subStructure != null ) {
				$result[] = $subStructure;
			}
		}

		return $result;
	}

	protected function filterAttributesByStructures( array &$attributes, array &$structures ) {
		$result = [];

		foreach ( $attributes as $attribute ) {
			if ( $attribute->type instanceof Structure ) {
				// recursively run filterAttributesByStructures on subAttributes
				$subAttributes = $attribute->type->getAttributes();
				$filteredStructures = $this->filterStructuresOnAttribute( $structures, $attribute );
				$filteredAttributes = $this->filterAttributesByStructures( $subAttributes, $filteredStructures );

				if ( count( $filteredAttributes ) > 0 ) {
					$result[] = new Attribute( $attribute->id, $attribute->name, new Structure( $filteredAttributes ) );
				}
			} elseif ( $this->attributeInStructures( $attribute, $structures ) ) {
				$result[] = $attribute;
			}
		}

		return $result;
	}

	public function determineVisibleSuffixAttributes( IdStack $idPath, $value ) {
		$visibleStructures = [];

		foreach ( $this->getEditors() as $editor ) {
			$visibleStructure = $editor->getTableStructureForView( $idPath, $value->getAttributeValue( $editor->getAttribute() ) );

			if ( count( $visibleStructure->getAttributes() ) > 0 ) {
				$visibleStructures[] = $visibleStructure;
			}
		}

		$tableStructure = $this->recordSetTableEditor->getTableStructure( $this->recordSetTableEditor );
		$attributes = $tableStructure->getAttributes();
		$result = $this->filterAttributesByStructures( $attributes, $visibleStructures );
		return $result;
	}

	public function addEditor( Editor $editor ) {
		$this->wrappedEditor->addEditor( $editor );
	}

	protected function getVisibleStructureForEditor( Editor $editor, $showPropertyColumn, array &$suffixAttributes ) {
		$leadingAttributes = [];
		$childEditors = $editor->getEditors();

		for ( $i = $showPropertyColumn ? 0 : 1; $i < 2; $i++ ) {
			$leadingAttributes[] = $childEditors[$i]->getAttribute();
		}

		return new Structure( array_merge( $leadingAttributes, $suffixAttributes ) );
	}

	public function view( IdStack $idPath, $value ) {
		$visibleAttributes = [];

		if ( $this->showPropertyColumn ) {
			$visibleAttributes[] = $this->propertyAttribute;
		}

		$visibleAttributes[] = $this->valueAttribute;

		$idPath->pushAnnotationAttribute( $this->getAttribute() );
		$visibleSuffixAttributes = $this->determineVisibleSuffixAttributes( $idPath, $value );

		$visibleStructure = new Structure( array_merge( $visibleAttributes, $visibleSuffixAttributes ) );

		$result = $this->recordSetTableEditor->viewHeader( $idPath, $visibleStructure );

		foreach ( $this->getEditors() as $editor ) {
			$attribute = $editor->getAttribute();
			$idPath->pushAttribute( $attribute );
			$result .= $editor->viewRows(
				$idPath,
				$value->getAttributeValue( $attribute ),
				$this->getVisibleStructureForEditor( $editor, $this->showPropertyColumn, $visibleSuffixAttributes )
			);
			$idPath->popAttribute();
		}

		$result .= $this->recordSetTableEditor->viewFooter( $idPath, $visibleStructure );

		$idPath->popAnnotationAttribute();

		return $result;
	}

	public function edit( IdStack $idPath, $value ) {
		$idPath->pushAnnotationAttribute( $this->getAttribute() );
		$result = $this->wrappedEditor->edit( $idPath, $value );
		$idPath->popAnnotationAttribute();

		return $result;
	}

	public function add( IdStack $idPath ) {
		$idPath->pushAnnotationAttribute( $this->getAttribute() );
		$result = $this->wrappedEditor->add( $idPath );
		$idPath->popAnnotationAttribute();

		return $result;
	}

	public function save( IdStack $idPath, $value ) {
		$idPath->pushAnnotationAttribute( $this->getAttribute() );
		$this->wrappedEditor->save( $idPath, $value );
		$idPath->popAnnotationAttribute();
	}

	protected function getAttributeOptionCount( IdStack $idPath ) {
		$classAttributes = $idPath->getClassAttributes()->filterClassAttributesOnLevel( $this->getLevelName() );
		$classAttributes = $this->getAttributeIDFilter()->filter( $classAttributes );

		return count( $classAttributes );
	}

	// displays the field only if there is at least one attribute of that type
	public function showEditField( IdStack $idPath ) {
		return $this->getAttributeOptionCount( $idPath ) > 0;
	}
}

/**
 * A WrappingEditor that adds a "show/hide" button
 * to display or hide the wrapped editor in a floating div
 */
class PopUpEditor extends WrappingEditor {
	protected $linkCaption;
	protected $viewInformation;

	public function __construct( Editor $wrappedEditor, $linkCaption ) {
		parent::__construct( $wrappedEditor );

		$this->linkCaption = $linkCaption;
	}

	public function view( IdStack $idPath, $value ) {
		return $this->startToggleCode( $idPath->getId() ) .
			$this->wrappedEditor->view( $idPath, $value ) .
			$this->endToggleCode( $idPath->getId() );
	}

	/**
	 * $value is an ArrayRecord
	 */
	public function edit( IdStack $idPath, $value ) {
		return $this->startToggleCode( $idPath->getId() ) .
			$this->wrappedEditor->edit( $idPath, $value ) .
			$this->endToggleCode( $idPath->getId() );
	}

	protected function startToggleCode( $attributeId ) {
		$id = 'popup-' . $attributeId . '-link';
		$result = Html::openElement( 'a', [ 'class' => "togglePopup", 'id' => $id ] );

		$popupShow = Html::element( 'span', [
			'class' => "popupshow"
			], wfMessage( 'showtoc' )->plain() . " ▼" );
		$popupHide = Html::element( 'span', [
			'class' => "popuphide",
			'style' => "display:none;"
			], wfMessage( 'hidetoc' )->plain() . " ▲" );

		$result .= $popupShow . $popupHide;
		$result .= Html::closeElement( 'a' );

		$id = 'popup-' . $attributeId . '-toggleable';
		$result .= Html::openElement( 'div', [ 'class' => "popupToggleable", 'id' => $id ] );

		return $result;
	}

	protected function endToggleCode( $attributeId ) {
		return Html::closeElement( 'div' );
	}
}

/**
 * editor used for editing the syntrans attributes
 * in the translation table (rightmost column)
 */
class SyntransPopupEditor extends PopUpEditor {

	public function __construct( Editor $wrappedEditor, $linkCaption, $viewInformation ) {
		$this->wrappedEditor = $wrappedEditor;
		$this->linkCaption = $linkCaption;
		$this->viewInformation = $viewInformation;
	}

	/**
	 * in the SyntransPopupEditor, view has an empty $value
	 * the value is loaded dynamically when requested with SpecialPopUpEditor
	 */
	public function view( IdStack $idPath, $value ) {
		return $this->getOpenHideLink( $idPath );
	}

	/**
	 * in the SyntransPopupEditor, edit has an empty $value
	 * the value is loaded dynamically when requested with SpecialPopUpEditor
	 */
	public function edit( IdStack $idPath, $value ) {
		return $this->getOpenHideLink( $idPath );
	}

	/**
	 * creates the html code for the link that toggles (show or hide)
	 * the annotation pannel when clicked
	 */
	public function getOpenHideLink( IdStack $idPath ) {
		$idPathFlat = $idPath->getId();
		$syntransId = $idPath->getKeyStack()->peek( 0 )->syntransId;
		$definedMeaningId = $idPath->getKeyStack()->peek( 1 )->definedMeaningId;

		$id = 'popup-' . $idPathFlat . '-link';

		$htmlOptions = [
			'class' => 'togglePopup',
			'id' => $id,
			'syntransid' => $syntransId,
			'dmid' => $definedMeaningId,
			'idpathflat' => $idPathFlat
		];
		if ( $this->viewInformation->showRecordLifeSpan ) {
			$htmlOptions['action'] = 'history';
		}
		$result = Html::openElement( 'a', $htmlOptions );

		$popupShow = Html::element( 'span', [
			'class' => 'popupshow'
			], wfMessage( 'showtoc' )->plain() . " ▼" );
		$popupHide = Html::element( 'span', [
			'class' => 'popuphide',
			'style' => 'display:none;'
			], wfMessage( 'hidetoc' )->plain() . " ▲" );

		$result .= $popupShow . $popupHide;
		$result .= Html::closeElement( 'a' );

		return $result;
	}

  // temporary save function
	public function save( IdStack $idPath, $value ) {
		$this->wrappedEditor->save( $idPath, $value );
	}

	/**
	 * Syntrans popup editor is not aware of its values, which
	 * are loaded dynamically with javascript
	 * so: always show
	 */
	public function showsData( $value ) {
		return true;
	}
}

class RecordSetRecordSelector extends WrappingEditor {
	public function view( IdStack $idPath, $value ) {
		return getStaticSuggest(
			$idPath->getId(),
			$this->wrappedEditor->view( $idPath, $value ),
			count( $value->getKey()->getAttributes() )
		);
	}
}

class DefinedMeaningContextEditor extends WrappingEditor {
	public function view( IdStack $idPath, $value ) {
		if ( $value === null ) {
			return;
		}
		$definedMeaningId = (int)$value->definedMeaningId;

		$idPath->pushDefinedMeaningId( $definedMeaningId );
		$idPath->pushClassAttributes( new ClassAttributes( $definedMeaningId ) );

		$result = $this->wrappedEditor->view( $idPath, $value );

		$idPath->popClassAttributes();
		$idPath->popDefinedMeaningId();

		return $result;
	}

	public function edit( IdStack $idPath, $value ) {
		if ( $idPath === null ) {
			throw new Exception( "Null provided for idPath while trying to edit()" );
		}

		if ( $value === null ) {
			throw new Exception( "Null provided for value while trying to edit()" );
		}

		$definedMeaningId = (int)$value->definedMeaningId;

		$idPath->pushDefinedMeaningId( $definedMeaningId );
		$idPath->pushClassAttributes( new ClassAttributes( $definedMeaningId ) );

		$result = $this->wrappedEditor->edit( $idPath, $value );

		$idPath->popClassAttributes();
		$idPath->popDefinedMeaningId();

		return $result;
	}

	public function save( IdStack $idPath, $value ) {
		$definedMeaningId = (int)$value->definedMeaningId;

		$idPath->pushDefinedMeaningId( $definedMeaningId );
		$idPath->pushClassAttributes( new ClassAttributes( $definedMeaningId ) );

		$this->wrappedEditor->save( $idPath, $value );

		$idPath->popClassAttributes();
		$idPath->popDefinedMeaningId();
	}
}

class ObjectContextEditor extends WrappingEditor {
	public function view( IdStack $idPath, $value ) {
		if ( $value === null ) {
			return;
		}
		$o = OmegaWikiAttributes::getInstance();

		$objectId = (int)$value->objectId;
		$objectIdRecord = new ArrayRecord( new Structure( "noname", $o->objectId ) );
		$objectIdRecord->setAttributeValue( $o->objectId, $objectId );

		$idPath->pushKey( $objectIdRecord );

		$result = $this->wrappedEditor->view( $idPath, $value );

		$idPath->popKey();

		return $result;
	}

	public function edit( IdStack $idPath, $value ) {
		if ( $idPath === null ) {
			throw new Exception( "SyntransContextEditor: Null provided for idPath while trying to edit()" );
		}

		if ( $value === null ) {
			throw new Exception( "SyntransContextEditor: Null provided for value while trying to edit()" );
		}

		$o = OmegaWikiAttributes::getInstance();
		$objectId = (int)$value->objectId;
		$objectIdRecord = new ArrayRecord( new Structure( "noname", $o->objectId ) );
		$objectIdRecord->setAttributeValue( $o->objectId, $objectId );

		$idPath->pushKey( $objectIdRecord );

		$result = $this->wrappedEditor->edit( $idPath, $value );

		$idPath->popKey();

		return $result;
	}

	public function save( IdStack $idPath, $value ) {
		$o = OmegaWikiAttributes::getInstance();

		$objectId = (int)$value->objectId;
		$objectIdRecord = new ArrayRecord( new Structure( "noname", $o->objectId ) );
		$objectIdRecord->setAttributeValue( $o->objectId, $objectId );

		$idPath->pushKey( $objectIdRecord );

		$this->wrappedEditor->save( $idPath, $value );

		$idPath->popKey();
	}
}
