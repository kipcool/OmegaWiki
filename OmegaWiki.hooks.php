<?php

// currently shares globals with @ "OmegaWiki/WikiDataGlobals.php".
// @note if we need to separate Omegawiki from WikiLexicalData, we need
// to transfer all OmegaWiki hooks here.

class OmegaWikiHooks extends WikiLexicalDataHooks {

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
	 * Adds canonical namespaces.
	 */
	public static function addCanonicalNamespaces( &$list ) {
		$list[NS_DEFINEDMEANING] = 'DefinedMeaning';
		$list[NS_DEFINEDMEANING + 1] = 'DefinedMeaning_talk';
		$list[NS_EXPRESSION] = 'Expression';
		$list[NS_EXPRESSION + 1] = 'Expression_talk';
		return true;
	}

}
