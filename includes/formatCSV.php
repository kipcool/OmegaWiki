<?php
/**
 * Some CSV functions for WLD
 */

/**
 * Simple formatting
 */
class WldFormatCSV {
	function formatCSVcolumn( $column ) {
		if ( is_numeric( $column ) ) {
			return $column;
		}
		if ( preg_match( '/\"/', $column ) ) {
			$column = str_replace( '"', '""', $column );
		}
		$column = '"' . $column . '"';
		if ( $column == '""' ) {
			return '';
		}
		$column = preg_replace( '/\t/', '\\t', $column );
		$column = preg_replace( '/\r/', '', $column );
		return preg_replace( '/\n/', '\\n', $column );
	}
}
