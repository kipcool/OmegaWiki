<?php

// currently shares globals with @ "OmegaWiki/WikiDataGlobals.php".
// @note if we need to separate Omegawiki from WikiLexicalData, we need
// to transfer all OmegaWiki hooks here.

class OmegaWikiHooks {

	/**
	 * Use the OmegaWiki page renderer for OmegaWiki namespaces.
	 */
	public static function onArticleFromTitle( &$title, &$article ) {
		if ( $title->inNamespaces( NS_EXPRESSION, NS_DEFINEDMEANING ) ) {
			$article = new WikidataArticle( $title );
			// no return value expected. It is enough that $article is not empty.
		}
		return true;
	}

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


	/**
	 * Adds canonical namespaces.
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_DEFINEDMEANING] = 'DefinedMeaning';
		$list[NS_DEFINEDMEANING + 1] = 'DefinedMeaning_talk';
		$list[NS_EXPRESSION] = 'Expression';
		$list[NS_EXPRESSION + 1] = 'Expression_talk';
		return true;
	}

	/**
	 * Use the OmegaWiki page editor for OmegaWiki namespaces.
	 */
	public static function onCustomEditor( $article, $user ) {
		$title = $article->getTitle();
		if ( $title->inNamespaces( NS_EXPRESSION, NS_DEFINEDMEANING ) ) {
			$editor = new WikidataEditPage( $article );
			$editor->edit();
			return false;
		}
		return true;
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


	/** @brief links to DefinedMeaning pages re modified to use cononical page titles
	 *
	 * Link having target pages having canonical titles are left untouched.
	 * Target pages with valid DefinedMeaning IDs in links are replaced by their canonical titles.
	 * Target pages having invalid DefinedMeaning IDs are replaced by a link to (invalid) DefinedMeaning ID 0.
	 */
	public static function onInternalParseBeforeLinks( Parser $parser, &$text ) {
		global $wgExtraNamespaces;
		// FIXME: skip if not action=submit
		// FIXME: skip if not page text
		if ( true ) {
			$nspace = 'DefinedMeaning';	// FIXME: compute the standard (english) name, do not use a constant.
			$namspce = $wgExtraNamespaces[NS_DEFINEDMEANING];
			if ( $nspace !== $namspce ) {
				$nspace .= '|';
				$nspace .= $namspce;
			}
			// case insensitivly find all internal links going to DefinedMeaning pages
			$pattern = '/\\[\\[(\\s*(' . $nspace . ')\\s*' .
				':(([^]\\|]*)\((\\d+)\\)[^]\\|]*))(\\|[^]]*)?\\]\\]/i';
			preg_match_all( $pattern, $text, $match );
			if ( $match[0] ) {
				// collect all DefinedMeaning IDs, all links to any of them, point to their array position
				foreach ( $match[5] as $index => $dmNumber ) {
					$dmIds[0 + $dmNumber][$match[0][$index]] = $index;
				}
				foreach ( $dmIds as $dmId => $links ) {
					if ( OwDatabaseAPI::verifyDefinedMeaningId( $dmId ) ) {
						$title = OwDatabaseAPI::definingExpression( $dmId ) . '_(' . $dmId . ')';
					} else {
						$title = '_(0)';
					}
					foreach ( $links as $link => $index ) {
						if ( trim( $match[3][$index] ) != $title ) {
							// alter only if it would change
							switch ( strlen( trim( $match[6][$index] ) ) ) {
							  case 0:	// there was no "|" in the link
								$replace = '|' . $match[1][$index];
								break;
							  case 1:	// there was an "|" not followed by text
								$replace = '|' . $match[3][$index];
								break;
							  default:	// there was an "|" followed by text
								$replace = $match[6][$index];
							}
							$replace = '[[' . $namspce . ':' . $title . $replace . ']]';
							$text = str_replace( $link, $replace, $text );
						}
					}
				}
			}
		}
		return true;
	}

	/**
	 * Use the OmegaWiki editor for History pages of the OmegaWiki namespaces
	 */
	public static function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki ) {
		$action = $request->getVal( 'action' );
		$isWikidataNs = $title->inNamespaces( NS_EXPRESSION, NS_DEFINEDMEANING );
		if ( $action === 'history' && $isWikidataNs ) {
			$history = new WikidataPageHistory( $article );
			$history->onView();
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
		$isWikidataNs = $skin->getTitle()->inNamespaces( NS_EXPRESSION, NS_DEFINEDMEANING );
		if ( !$isWikidataNs ) {
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


	/** @brief basic lexical statistic data for Special:Statistics
	 */
	public static function onSpecialStatsAddExtra( &$extraStats ) {
		$extra = new SpecialOWStatistics;
		$extraStats = $extra->getOverview( true );
		return true;
	}

} // Class OmegaWikiHooks
