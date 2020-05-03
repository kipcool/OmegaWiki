<?php

require_once 'languages.php';

function getTextBox( $name, $value = "", $onChangeHandler = "", $disabled = false ) {
	if ( $onChangeHandler != "" ) {
		$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
	} else {
		$onChangeAttribute = '';
	}

	$disableText = $disabled ? 'disabled="disabled" ' : '';
	$inputHTML = '<input ' . $disableText . 'type="text" id="' . $name . '" name="' . $name .
		'" value="' . htmlspecialchars( $value ) . '"' . $onChangeAttribute .
		' style="width: 100%; padding: 0px; margin: 0px;"/>';

	return $inputHTML;
}

function getTextArea( $name, $text = "", $rows = 5, $columns = 80, $disabled = false ) {
	if ( $disabled ) {
		// READONLY alone is not enough: apparently, some browsers ignore it
		return '<textarea disabled="disabled" name="' . $name . '" rows="' . $rows . '" cols="' . $columns . '" READONLY>' . htmlspecialchars( $text ) . '</textarea>';
	} else {
		return '<textarea name="' . $name . '" rows="' . $rows . '" cols="' . $columns . '">' . htmlspecialchars( $text ) . '</textarea>';
	}
}

function checkBoxCheckAttribute( $isChecked ) {
	if ( $isChecked ) {
		return ' checked="checked"';
	}
	return '';
}

function getCheckBox( $name, $isChecked, $disabled = false ) {
	// a disabled checkbox returns no value, as if unchecked
	// therefore the value of a disabled, but checked, checkbox must be sent with a hidden input
	if ( $disabled ) {
		if ( $isChecked ) {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '/><input type="hidden" name="' . $name . '" value="1"/>';
		} else {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '/>';
		}
	} else {
		return '<input type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '/>';
	}
}

function getCheckBoxWithClass( $name, $isChecked, $class, $disabled = false ) {
	if ( $disabled ) {
		if ( $isChecked ) {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '"/><input type="hidden" name="' . $name . '" value="1"/>';
		} else {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '"/>';
		}
	} else {
		return '<input type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . ' class="' . $class . '"/>';
	}
}

function getRemoveCheckBox( $name ) {
	global $wgUser;
	$dc = wdGetDataSetContext();
	if ( ( $dc == "uw" ) and ( !$wgUser->isAllowed( 'deletewikidata-uw' ) ) ) {
		// do not print the checkbox
		return '';
	} else {
		return getCheckBoxWithClass( $name, false, "remove-checkbox" );
	}
}

/** @todo for deprecration use Class OmegaWikiForms's getSelect function instead.
 */
function getSelect( $name, $options, $selectedValue = "", $onChangeHandler = "" ) {
	$form = new OmegaWikiForms;
	return $form->getSelect( $name, $options, $selectedValue, $onChangeHandler );
}

function getFileField( $name, $onChangeHandler = "" ) {
	if ( $onChangeHandler != "" ) {
		$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
	} else {
		$onChangeAttribute = '';
	}

	return '<input type="file" id="' . $name . '" name="' . $name . '"' . $onChangeAttribute . ' style="width: 100%; padding: 0px; margin: 0px;"/>';
}

/**
 * Returns HTML for an autocompleted form field.
 *
 * @param string $name unique identifier for this form field
 * @param string $query type of query to run
 * @param array $parameters
 * @param int $value Default value
 * @param string $label How default value will be shown
 * @param string[] $displayLabelColumns Override column titles
 * @param DataSet|null $dc Override standard dataset
 * @return string HTML
 */
function getSuggest( $name, $query, $parameters = [], $value = 0, $label = '', $displayLabelColumns = [ 0 ], DataSet $dc = null ) {
	if ( $dc === null ) {
		$dc = wdGetDataSetContext();
	}
	if ( $label == "" ) {
		$label = '&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;';
	}

	$result = Html::openElement( 'span', [ 'class' => 'suggest' ] );

	// the input that will contain the value selected with suggest.js
	$inputOptions = [
		'id' => $name,
		'name' => $name,
		'value' => $value,
		'type' => 'hidden'
	];
	$result .= Html::element( 'input', $inputOptions );

	$spanOptions = [
		'id' => $name . '-suggest-link',
		'name' => $name . '-suggest-link',
		'class' => 'suggest-link',
		'title' => wfMessage( "ow_SuggestHint" )->text(),
		'query' => $query,
		'offset' => 0,
		'label-columns' => implode( ', ', $displayLabelColumns ),
		'dataset' => $dc
	];

	foreach ( $parameters as $parameter => $parameterValue ) {
		// parameters like level, definedMeaningId, annotationAttributeId, syntransId
		$spanOptions[$parameter] = $parameterValue;
	}

	$result .= Html::rawElement( 'span', $spanOptions, $label );

	$result .= Html::closeElement( 'span' );

	// The table that then allows to select from a dropdown list
	// is generated with javascript (cf. suggest.js)

	return $result;
}

function getStaticSuggest( $name, $suggestions, $idColumns = 1, $value = 0, $label = '', $displayLabelColumns = [ 0 ] ) {
	if ( $label == "" ) {
		$label = '&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;';
	}

	$result = Html::openElement( 'span', [ 'class' => 'suggest' ] );

	// the input that will contain the value selected with suggest.js
	$inputOptions = [
		'id' => $name,
		'name' => $name,
		'value' => $value,
		'type' => 'hidden'
	];
	$result .= Html::element( 'input', $inputOptions );
	$spanOptions = [
		'id' => $name . '-suggest-link',
		'name' => $name . '-suggest-link',
		'class' => 'suggest-link',
		'title' => wfMessage( "ow_SuggestHint" )->text(),
		'query' => $query,
		'offset' => 0,
		'label-columns' => implode( ', ', $displayLabelColumns ),
		'dataset' => $dc
	];

	if ( $idColumns > 1 ) {
		$spanOptions['id-columns'] = $idColumns;
	}

	$result .= Html::rawElement( 'span', $spanOptions, $label );

	$result .= Html::closeElement( 'span' );

	return $result;
}

function getLanguageOptions( $languageIdsToExclude = [] ) {
	$userLanguage = OwDatabaseAPI::getUserLanguage();
	$idNameIndex = getLangNames( $userLanguage );

	$result = [];

	foreach ( $idNameIndex as $id => $name ) {
		if ( !in_array( $id, $languageIdsToExclude ) ) {
			$result[$id] = $name;
		}
	}

	return $result;
}

// @note unused	function
function getLanguageSelect( $name, $languageIdsToExclude = [] ) {
	$userLanguageId = OwDatabaseAPI::getUserLanguageId();

	return getSelect( $name, getLanguageOptions( $languageIdsToExclude ), $userLanguageId );
}

function getSubmitButton( $name, $value ) {
	return '<input type="submit" name="' . $name . '" value="' . $value . '"/>';
}

function getOptionPanel( $fields, $action = '', $buttons = [ "show" => null ] ) {
	global
		$wgTitle;

	$result =
		'<div class="option-panel">' .
			'<form method="GET" action="">' .
				'<table cellpadding="0" cellspacing="0">' .
					'<input type="hidden" name="title" value="' . $wgTitle->getNsText() . ':' . htmlspecialchars( $wgTitle->getText() ) . '"/>';

	if ( $action && $action != '' ) {
		$result .= '<input type="hidden" name="action" value="' . $action . '"/>';
	}

	foreach ( $fields as $caption => $field ) {
		$result .= '<tr><th>' . $caption . '</th><td class="option-field">' . $field . '</td></tr>';
	}

	$buttonHTML = "";

	foreach ( $buttons as $name => $caption ) {
		if ( $caption == null ) {
			// Default parameter/value => Show
			$caption = wfMessage( 'ow_show' )->text();
		}
		$buttonHTML .= getSubmitButton( $name, $caption );
	}

	$result .=
					'<tr><th/><td>' . $buttonHTML . '</td></tr>' .
				'</table>' .
			'</form>' .
		'</div>';

	return $result;
}

function getOptionPanelForFileUpload( $fields, $action = '', $buttons = [ "upload" => null ] ) {
	global
		$wgTitle;

	$result =
		'<div class="option-panel">' .
			'<form method="POST" enctype="multipart/form-data" action="">' .
				'<table cellpadding="0" cellspacing="0">' .
					'<input type="hidden" name="title" value="' . $wgTitle->getNsText() . ':' . htmlspecialchars( $wgTitle->getText() ) . '"/>';

	if ( $action && $action != '' ) {
		$result .= '<input type="hidden" name="action" value="' . $action . '"/>';
	}

	foreach ( $fields as $caption => $field ) {
		$result .= '<tr><th>' . $caption . '</th><td class="option-field">' . $field . '</td></tr>';
	}

	$buttonHTML = "";

	foreach ( $buttons as $name => $caption ) {
		if ( $caption == null ) {
			// Default parameter/value => Upload
			$caption = wfMessage( 'ow_upload' )->text();
		}
		$buttonHTML .= getSubmitButton( $name, $caption );
	}

	$result .=
					'<tr><th/><td>' . $buttonHTML . '</td></tr>' .
				'</table>' .
			'</form>' .
		'</div>';

	return $result;
}

/** @brief Generic Forms
 */
class GenericForms {

	public function __construct() {
		$this->labelTemplate = '&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;';
	}

	/**
	 * @param string $name req'd unique identifier for this form field
	 * @param array $options req'd list of options, [value => text] pairs
	 * @param string $selectedValue opt'l in case a value is present
	 * @param string $onChangeHandler js
	 * @return string HTML
	 */
	function getSelect( $name, $options, $selectedValue = "", $onChangeHandler = "" ) {
		if ( $onChangeHandler != "" ) {
			$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
		} else {
			$onChangeAttribute = '';
		}

		$result = '<select id="' . $name . '" name="' . $name . '"' . $onChangeAttribute . '>';

		asort( $options );

		foreach ( $options as $value => $text ) {
			if ( $value == $selectedValue ) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}

			$result .= '<option value="' . $value . '"' . $selected . '>' . htmlspecialchars( $text ) . '</option>';
		}

		return $result . '</select>';
	}

	/**
	 * @param string $name req'd unique identifier for this form field
	 * @param string $value opt'l input value
	 * @param string $onChangeHandler opt'l js
	 * @param bool $disabled opt'l to disable editing of the field
	 * @return string HTML
	 */
	public function getTextBox( $name, $value = "", $onChangeHandler = "", $disabled = false ) {
		if ( $onChangeHandler != "" ) {
			$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
		} else {
			$onChangeAttribute = '';
		}

		$disableText = $disabled ? 'disabled="disabled" ' : '';
		$inputHTML = '<input ' . $disableText . 'type="text" id="' . $name . '" name="' . $name .
			'" value="' . htmlspecialchars( $value ) . '"' . $onChangeAttribute .
			' style="width: 100%; padding: 0px; margin: 0px;"/>';

		return $inputHTML;
	}

}

/** @brief OmegaWiki extension to the generic forms
 */
class OmegaWikiForms extends GenericForms {

	/**
	 * @param string $name unique identifier for this form field
	 * @param string $query type of query to run
	 * @param array $parameters span options (parameters and values )
	 * @param int $value Default value
	 * @param string $label How default value will be shown
	 * @param array $displayLabelColumns Override column titles
	 * @param DataSet|null $dc Override standard dataset
	 * @return string HTML for an autocompleted form field.
	 */
	function getSuggest( $name, $query, $parameters = [], $value = 0, $label = '', $displayLabelColumns = [ 0 ], DataSet $dc = null ) {
		if ( $dc === null ) {
			$dc = wdGetDataSetContext();
		}
		if ( $label == "" ) {
			$label = $this->labelTemplate;
		}

		$result = Html::openElement( 'span', [ 'class' => 'suggest' ] );

		// the input that will contain the value selected with suggest.js
		$inputOptions = [
			'id' => $name,
			'name' => $name,
			'value' => $value,
			'type' => 'hidden'
		];
		$result .= Html::element( 'input', $inputOptions );

		$spanOptions = [
			'id' => $name . '-suggest-link',
			'name' => $name . '-suggest-link',
			'class' => 'suggest-link',
			'title' => wfMessage( "ow_SuggestHint" )->text(),
			'query' => $query,
			'offset' => 0,
			'label-columns' => implode( ', ', $displayLabelColumns ),
			'dataset' => $dc
		];

		foreach ( $parameters as $parameter => $parameterValue ) {
			// parameters like level, definedMeaningId, annotationAttributeId, syntransId
			$spanOptions[$parameter] = $parameterValue;
		}

		$result .= Html::rawElement( 'span', $spanOptions, $label );

		$result .= Html::closeElement( 'span' );

		// The table that then allows to select from a dropdown list
		// is generated with javascript (cf. suggest.js)

		return $result;
	}

}
