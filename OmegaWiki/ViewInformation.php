<?php

require_once 'PropertyToColumnFilter.php';

/**
 * ViewInformation is used to capture various settings that influence the way a page will be viewed
 * depending on different use case scenarios.
 *
 * A ViewInformation can be constructed based on various conditions. The language filtering for instance
 * could be an application wide setting, or a setting that can be controlled by the user. Functions that
 * use ViewInformation do not care about this. They are supposed to respect the settings provided wherever
 * possible.
 */
class ViewInformation {
	/**
	 * array containing a list of languages that the user wants to display
	 * so that other languages are hidden.
	 * If the array is empty, all languages are displayed.
	 */
	public $filterLanguageList;

	/**
	 * The language of the expression to display, according to the url &explang=...
	 */
	public $expressionLanguageId;

	public $queryTransactionInformation;
	public $showRecordLifeSpan;
	public $viewOrEdit;                         ///< either "view" or "edit"

	protected $propertyToColumnFilters;

	public function __construct() {
		global $wgRequest, $wgUser;

		// filterLanguageList allows to only display languages that are in that list.
		// The list can be set up in the user preferences.
		$this->filterLanguageList = [];
		$this->expressionLanguageId = $wgRequest->getVal( 'explang', 0 );
		$this->queryTransactionInformation = null;
		$this->showRecordLifeSpan = false;
		$this->propertyToColumnFilters = [];
		$this->viewOrEdit = "view";

		// check if language filtering is changed from the url
		// and modify the user options accordingly. Cf. onSkinTemplateNavigation hook
		$langFilterRequest = $wgRequest->getVal( 'langfilter' );
		if ( $langFilterRequest == "on" ) {
			$wgUser->setOption( 'ow_language_filter', true );
			$wgUser->saveSettings();
		} elseif ( $langFilterRequest == "off" ) {
			$wgUser->setOption( 'ow_language_filter', false );
			$wgUser->saveSettings();
		}

		// set filterLanguageList according to the user preferences
		if ( $wgUser->getOption( 'ow_language_filter' ) ) {
			// language filtering is activated (checkbox selected in preferences)
			$owLanguageNames = getOwLanguageNames();
			foreach ( $owLanguageNames as $language_id => $language_name ) {
				if ( $wgUser->getOption( 'ow_language_filter_list' . $language_id ) ) {
					// language $language_id/$language_name is selected by the user
					$this->filterLanguageList[] = $language_id;
				}
			}
		}
	}

	public function hasMetaDataAttributes() {
		return $this->showRecordLifeSpan;
	}

	/**
	 * returns an array containing the language_id that the user wants to display
	 * if the array is empty, all languages should be displayed.
	 */
	public function getFilterLanguageList() {
		return $this->filterLanguageList;
	}

	public function setPropertyToColumnFilters( array $propertyToColumnFilters ) {
		$this->propertyToColumnFilters = $propertyToColumnFilters;
	}

	public function getPropertyToColumnFilters() {
		return $this->propertyToColumnFilters;
	}

	public function getLeftOverAttributeFilter() {
		$allFilteredAttributeIds = [];

		foreach ( $this->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
			$allFilteredAttributeIds = array_merge( $allFilteredAttributeIds, $propertyToColumnFilter->attributeIDs );
		}

		return new ExcludeAttributeIDsFilter( $allFilteredAttributeIds );
	}

	/* make an attempt at a hashCode function.
	 * note that this function is imperfect..., I've left out
	 * some attributes because I am lazy.
	 * please check and recheck when creating new viewinformation
	 * when using such viewinformation together with OmegaWikiAttributes.
	 */
	public function hashCode() {
		return $this->showRecordLifeSpan . "," .
			$this->viewOrEdit;
	}

	public function __tostring() {
		return "viewinformation object>";
	}
}
