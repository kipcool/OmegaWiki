<?php

require_once "Record.php";

/**
 * Class IdStack is used to keep track of context during the rendering of
 * a hierarchical structure of Records and RecordSets. The name IdStack might
 * not be accurate anymore and might be renamed to something else like RenderContext.
 */
class IdStack {
	protected $keyStack;
	protected $idStack = [];
	protected $currentId;
	protected $classStack = [];
	protected $currentClass;
	protected $definedMeaningIdStack = []; 	// Used to keep track of which defined meaning is being rendered
	protected $annotationAttributeStack = [];	// Used to keep track of which annotation attribute currently is being rendered
	protected $classAttributesStack = [];		// Used to keep track of the class attributes that are currently in effect

	public function __construct( $prefix ) {
		$this->keyStack = new RecordStack();
		$this->currentId = $prefix;
		$this->currentClass = $prefix;
	}

	protected function getKeyIds( Record $record ) {
		$ids = [];

		foreach ( $record->getStructure()->getAttributes() as $attribute ) {
			$ids[] = $record->getAttributeValue( $attribute );
		}

		return $ids;
	}

	protected function pushId( $id ) {
		$this->idStack[] = $this->currentId;
		$this->currentId .= '-' . $id;
	}

	protected function popId() {
		$this->currentId = array_pop( $this->idStack );
	}

	protected function pushClass( $class ) {
		$this->classStack[] = $this->currentClass;
		$this->currentClass = $class;
	}

	protected function popClass() {
		$this->currentClass = array_pop( $this->classStack );
	}

	public function pushKey( Record $record ) {
		$this->keyStack->push( $record );
		$this->pushId( implode( "-", $this->getKeyIds( $record ) ) );
	}

	public function pushAttribute( Attribute $attribute ) {
		# FIXME: check attribute id existence
		@$id = $attribute->id;
		$this->pushId( $id );
		$this->pushClass( $id );
	}

	public function popKey() {
		$this->popId();
		return $this->keyStack->pop();
	}

	public function popAttribute() {
		$this->popId();
		$this->popClass();
	}

	public function getId() {
		return $this->currentId;
	}

	public function getClass() {
		return $this->currentClass;
	}

	public function getKeyStack() {
		return $this->keyStack;
	}

	public function pushDefinedMeaningId( $definedMeaningId ) {
		$this->definedMeaningIdStack[] = $definedMeaningId;
	}

	public function popDefinedMeaningId() {
		return array_pop( $this->definedMeaningIdStack );
	}

	public function getDefinedMeaningId() {
		$stackSize = count( $this->definedMeaningIdStack );

		if ( $stackSize > 0 ) {
			return $this->definedMeaningIdStack[$stackSize - 1];
		} else {
			throw new Exception( "There is no defined meaning defined in the current context" );
		}
	}

	public function pushAnnotationAttribute( Attribute $annotationAttribute ) {
		$this->annotationAttributeStack[] = $annotationAttribute;
	}

	public function popAnnotationAttribute() {
		return array_pop( $this->annotationAttributeStack );
	}

	public function getAnnotationAttribute() {
		$stackSize = count( $this->annotationAttributeStack );

		if ( $stackSize > 0 ) {
			return $this->annotationAttributeStack[$stackSize - 1];
		} else {
			throw new Exception( "There is no annotation attribute in the current context" );
		}
	}

	public function pushClassAttributes( ClassAttributes $classAttributes ) {
		$this->classAttributesStack[] = $classAttributes;
	}

	public function popClassAttributes() {
		return array_pop( $this->classAttributesStack );
	}

	public function getClassAttributes() {
		$stackSize = count( $this->classAttributesStack );

		if ( $stackSize > 0 ) {
			return $this->classAttributesStack[$stackSize - 1];
		} else {
			throw new Exception( "There are no class attributes in the current context" );
		}
	}

	public function __tostring() {
		return "IdStack(" . $this->getId() . ")\n";
	}
}

class RecordStack {
	protected $stack = [];

	public function push( Record $record ) {
		$this->stack[] = $record;
	}

	public function pop() {
		return array_pop( $this->stack );
	}

	public function peek( $level ) {
		return $this->stack[count( $this->stack ) - $level - 1];
	}
}
