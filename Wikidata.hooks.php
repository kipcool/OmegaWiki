<?php

require_once "OmegaWiki/WikiDataGlobals.php";

class WikiLexicalDataHooks {

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return true
	 */
	public static function onBeforePageDisplay( $out, $skin ) {
		global $wgContLang;

		$request = $out->getRequest();

		$out->addModules( 'ext.Wikidata.css' );
		$out->addModules( 'ext.Wikidata.ajax' );

		// for editing, but also needed in view mode when dynamically editing annotations
		$out->addModules( 'ext.Wikidata.edit' );
		$out->addModules( 'ext.Wikidata.suggest' );

		// remove Expression: from title. Looks better on Google
		$action = $request->getText( "action", "view" );
		if ( $action == 'view' ) {
			$namespace = $skin->getTitle()->getNamespace();
			if ( $namespace == NS_EXPRESSION ) {
				$namespaceText = $wgContLang->getNsText( $namespace );
				// cut the namespaceText from the title
				$out->setPageTitle( mb_substr( $out->getPageTitle(), mb_strlen( $namespaceText ) + 1 ) );
			}
		}

		// SpecialPage Add from External API
		if (
			$skin->getTitle()->mNamespace === -1 and
			$skin->getTitle()->mTextform === 'Ow addFromExtAPI'
		) {
			$out->addModules( 'ext.OwAddFromExtAPI.js' );
		}
		return true;
	}

	private static function isWikidataNs( $title ) {
		global $wdHandlerClasses;
		return array_key_exists( $title->getNamespace(), $wdHandlerClasses );
	}

	/** @brief OmegaWiki-specific preferences
	 */
	public static function onGetPreferences( $user, &$preferences ) {
/*
		// preference to select between several available datasets
		$datasets = wdGetDatasets();
		foreach ( $datasets as $datasetid => $dataset ) {
			$datasetarray[$dataset->fetchName()] = $datasetid;
		}
		$preferences['ow_uipref_datasets'] = array(
			'type' => 'multiselect',
			'options' => $datasetarray,
			'section' => 'omegawiki',
			'label' => wfMessage( 'ow_shown_datasets' )->text(),
		);
*/
		// allow the user to select the languages to display
		$preferences['ow_alt_layout'] = [
			'type' => 'check',
			'label' => 'Alternative layout',
			'section' => 'omegawiki',
		];
		$preferences['ow_language_filter'] = [
			'type' => 'check',
			'label' => wfMessage( 'ow_pref_lang_switch' )->text(),
			'section' => 'omegawiki/ow-lang',
		];
		$preferences['ow_language_filter_list'] = [
			'type' => 'multiselect',
			'label' => wfMessage( 'ow_pref_lang_select' )->text(),
			'options' => [], // to be filled later
			'section' => 'omegawiki/ow-lang',
		];

		$owLanguageNames = getOwLanguageNames();
		// There are PHP that does not have the Collator class. ~he
		if ( class_exists( 'Collator', false ) ) {
			$col = new Collator( 'en_US.utf8' );
			$col->asort( $owLanguageNames );
		}
		foreach ( $owLanguageNames as $language_id => $language_name ) {
			$preferences['ow_language_filter_list']['options'][$language_name] = $language_id;
		}

		return true;
	}

	public static function onArticleFromTitle( &$title, &$article ) {
		if ( self::isWikidataNs( $title ) ) {
			$article = new WikidataArticle( $title );
		}
		return true;
	}

	public static function onCustomEditor( $article, $user ) {
		if ( self::isWikidataNs( $article->getTitle() ) ) {
			$editor = new WikidataEditPage( $article );
			$editor->edit();
			return false;
		}
		return true;
	}

	public static function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki ) {
		$action = $request->getVal( 'action' );
		if ( $action === 'history' && self::isWikidataNs( $title ) ) {
			$history = new WikidataPageHistory( $article );
			$history->onView();
			return false;
		}
		return true;
	}

	/** @brief the i18n message and function to abort moving pages. Why the move was unsuccessful
	 *
	 *  In case anyone uses the Special page to move from/to the OmegaWiki namespaces,
	 *  This hook, along with the onNamespaceIsMovable hook aborts the move.
	 */
	public static function onAbortMove( $oldtitle, $newtitle, $user, &$error, $reason ) {
		if ( self::isWikidataNs( $oldtitle ) ) {
			$error = wfMessage( 'wikidata-handler-namespace-move-error' )->text();
			return false;
		}
		if ( self::isWikidataNs( $newtitle ) ) {
			$error = wfMessage( 'wikidata-handler-namespace-move-to-error' )->text();
			return false;
		}
		return true;
	}

	/** @brief disables the "move" button for OmegaWiki namespaces
	 *
	 *  Disable the "move" button for the Expression and DefinedMeaning namespaces
	 *  and prevent their pages to be moved like standard wiki pages. They work differently.
	 */
	public static function onNamespaceIsMovable( $index, $result ) {
		if ( ( $index == NS_EXPRESSION ) || ( $index == NS_DEFINEDMEANING ) ) {
			$result = false;
		}
		return true;
	}

	/**
	 * Replaces the proposition to "create new page" by a custom,
	 * allowing to create new expression as well
	 */
	public static function onNoGoMatchHook( &$title ) {
		global $wgOut,$wgDisableTextSearch;
		$wgOut->addWikiMsg( 'search-nonefound' );
		$wgOut->addWikiMsg( 'ow_searchnoresult', wfEscapeWikiText( $title ) );
	// $wgOut->addWikiMsg( 'ow_searchnoresult', $title );

		$wgDisableTextSearch = true;
		return true;
	}

	/**
	 * The Go button should search (first) in the Expression namespace instead of Article namespace
	 */
	public static function onGoClicked( $allSearchTerms, &$title ) {
		$term = $allSearchTerms[0];
		$title = Title::newFromText( $term );
		if ( $title === null ) {
			return true;
		}

		// Replace normal namespace with expression namespace
		if ( $title->getNamespace() == NS_MAIN ) {
			$title = Title::newFromText( $term, NS_EXPRESSION );
		}

		if ( $title->exists() ) {
			return false; // match!
		}
		return true; // no match
	}

	/** @note There is a language code difference between globals $wgLang and $wgUser.
	 * 	I do not know if this issue affects this function. ~he
	 */
	public static function onPageContentLanguage( $title, &$pageLang, $userLang ) {
		if ( $title->inNamespaces( NS_EXPRESSION, NS_DEFINEDMEANING ) ) {
			// in this wiki, we try to deliver content in the user language
			$pageLang = $userLang;
		}
	}

	public static function onSkinTemplateNavigation( &$skin, &$links ) {
		// only for Expression and DefinedMeaning namespaces
		if ( !self::isWikidataNs( $skin->getTitle() ) ) {
			return true;
		}

		// display an icon for enabling/disabling language filtering
		// only available in Vector.
		if ( $skin instanceof SkinVector ) {
			if ( $skin->getUser()->getOption( 'ow_language_filter' ) ) {
				// language filtering is on. The button is for disabling it
				$links['views']['switch_lang_filter'] = [
					'class' => 'wld_lang_filter_on',
					'text' => '', // no text, just an image, see css
					'href' => $skin->getTitle()->getLocalUrl( "langfilter=off" ),
				];
			} else {
				// language filtering is off. The button is for enablingit
				$links['views']['switch_lang_filter'] = [
					'class' => 'wld_lang_filter_off',
					'text' => '', // no text, just an image, see css
					'href' => $skin->getTitle()->getLocalUrl( "langfilter=on" ),
				];
			}
		}

		// removes the 'move' button for OmegaWiki namespaces
		unset( $links['actions']['move'] );

		return true;
	}

	/** @brief load update schema
	 * @note The base installation of WikiLexicalData's schema is performed
	 * 	by update.php's UpdateWikiLexicalData class on WikiLexical's maintenance folder.
	 * 	this will be used in the future for new tables/index etal.
	 *
	 * 	 @see Preferably, use MediaWiki's https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public static function loadSchema() {
		return true;
	}

	/** @brief basic lexical statistic data for Special:Statistics
	 */
	public static function onSpecialStatsAddExtra( &$extraStats ) {
		$extra = new SpecialOWStatistics;
		$extraStats = $extra->getOverview( true );
		return true;
	}

}
