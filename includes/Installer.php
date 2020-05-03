<?php
/** @file
 */

/** @brief a different take on creating tables and index for update.php.
 */
class ExtensionDatabaseUpdater {

	/** @brief constructor
	 */
	public function __construct() {
	}

	/** @brief creates table and index
	 * @return bool whether any queries were processed.
	 */
	function addExtensionSCHEMA( $pattern, $prefix, $path, $freshInstall = false ) {
		$this->pattern = $pattern;
		$this->prefix = $prefix;
		$this->path = $path;
		$this->freshInstall = $freshInstall;
		$this->processed = false;

		$this->dbw = wfGetDB( DB_MASTER );
		$this->dbr = wfGetDB( DB_MASTER );

		$this->setInternalParameters();

		$this->getFileHandler();
		$this->readSQLFile();

		return $this->processed;
	}

	/** @brief Checks if SQL file exists
	 */
	protected function checkSQLExists() {
		if ( !file_exists( $this->path ) ) {
			$this->output( $this->path . ' does not exists.' );
			die;
		}
	}

	/** @brief get File handler
	 */
	protected function getFileHandler() {
		$this->checkSQLExists();
		$this->fh = fopen( $this->path, 'r' );
	}

	/** @brief reads the SQL file to process
	 */
	protected function readSQLFile() {
		while ( !feof( $this->fh ) ) {
			$line = trim( fgets( $this->fh, 1024 ) );
			$sl = strlen( $line ) - 1;

			if ( $sl < 0 ) {
				continue;
			}
			if ( '-' == $line [ 0 ] && '-' == $line [ 1 ] ) {
				continue;
			}

			if ( ';' == $line [ $sl ] && ( $sl < 2 || ';' != $line [ $sl - 1 ] ) ) {
				$this->done = true;
				$line = substr( $line, 0, $sl );
			}

			if ( '' != $this->cmd ) {
				$this->cmd .= ' ';
			}
			$this->cmd .= "$line\n";

			if ( $this->done ) {
				if ( preg_match( '/CREATE TABLE/', $this->cmd ) ) {
					$this->processTable();
				}

				if ( preg_match( '/CREATE INDEX/', $this->cmd ) ) {
					$this->processIndex();
				}

				$this->resetInternalParameters();
			}
		}
	}

	/** @brief process a table
	 */
	protected function processTable() {
		global $wgSitename;
		$this->replacePatternWithPrefix();
		$this->extractTableName();
		// process table if not exists
		$found = $this->dbr->tableExists( $this->table );
		if ( $found ) {
			$this->output( '...' . $wgSitename . "'s " . $this->table . ' table already exists.' );
		} else {
			$this->processQuery();
			$this->output( '...' . $wgSitename . "'s " . $this->table . ' table added.' );
		}
	}

	/** @brief process an index
	 */
	protected function processIndex() {
		global $wgSitename;
		$this->replacePatternWithPrefix();
		$this->extractIndexTableName();
		// process table if not exists
		$found = $this->dbr->indexExists( $this->table, $this->index );
		if ( $found ) {
			$this->output( '...' . $wgSitename . "'s " . $this->index . ' index already exists.' );
		} else {
			if ( $this->dbr->indexUnique( $this->table, $this->index ) ) {
				$this->output( '...' . $wgSitename . "'s " . $this->index . ' index not unique.' );
				die;
			}
			$this->processQuery();
			$this->output( '...' . $wgSitename . "'s " . $this->index . ' index added.' );
		}
	}

	/** @brief Additional processing when a table or index does not exist.
	 */
	protected function processQuery() {
		global $wgDBtype;
		if ( $wgDBtype == 'sqlite' ) {
			$this->sqliteLineReplace();
		}

		$this->preCommitAdjustments();
		$this->commit();
	}

	/** @brief commits the query. Either reports a query error or set the
	 * $this->processed flag to true.
	 */
	protected function commit() {
		$result = $this->dbw->query( $this->cmd, __METHOD__, true );
		if ( $result === false ) {
			$this->output( "\n\nERROR:\n\n" . 'Query "' . $this->cmd . '" failed with error code.' . "\n" );
			die;
		}
		$this->processed = true;
	}

	/** @brief Last line of checks before commit
	 */
	protected function preCommitAdjustments() {
		$this->cmd = str_replace( ';;', ";", $this->cmd );
	}

	/** @brief SQLite compatibility
	 */
	protected function sqliteLineReplace() {
		$this->cmd = preg_replace( '/ int(eger|) /i', ' INTEGER ', $this->cmd );
		$this->cmd = preg_replace( '/ int(eger|)\(/i', " INTEGER(", $this->cmd );
		$this->cmd = str_replace( ' auto_increment', " AUTO_INCREMENT", $this->cmd );

		$this->cmd = str_replace( 'CREATE INDEX', "CREATE INDEX IF NOT EXISTS", $this->cmd );

		// Index name in SQLite cannot be the same as a table name.
		if ( preg_match( '/CREATE INDEX /', $this->cmd ) ) {
			$indexName = $this->extractIndexName();
			$indexNameExistsAsTable = $this->dbr->tableExists( $indexName );
			if ( $indexNameExistsAsTable ) {
				$this->cmd = str_replace(
					'CREATE INDEX ' . $indexName . ' ON',
					'CREATE INDEX i_' . $indexName . ' ON',
					$this->cmd
				);
			}
			$this->cmd = preg_replace( '/(\(\d+\))/', ' ', $this->cmd );
		}
		$this->cmd = str_replace( ' unsigned', '', $this->cmd );
		$this->cmd = preg_replace( '/INTEGER\(\d+\)/', 'INTEGER', $this->cmd );
		$this->cmd = str_replace( 'AUTO_INCREMENT', "AUTOINCREMENT", $this->cmd );
		$this->cmd = str_replace( 'collate utf8_bin', "collate binary", $this->cmd );
		$this->cmd = str_replace( ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci', ")", $this->cmd );
	}

	/** @brief replace patterns with prefix
	 */
	protected function replacePatternWithPrefix() {
		$ctr = 0;
		foreach ( $this->pattern as $pattern ) {
			$this->cmd = trim( str_replace( $pattern, $this->prefix[$ctr], $this->cmd ) );
			$ctr++;
		}
	}

	/** @brief set the table name
	 */
	protected function extractTableName() {
		$temp = str_replace( 'IF NOT EXISTS ', '', $this->cmd );
		preg_match( '/CREATE TABLE (.+) \(/', $temp, $match );
		$this->table = trim( $match[1] );
	}

	/** @brief set the index name
	 */
	protected function extractIndexName() {
		$temp = str_replace( 'IF NOT EXISTS ', '', $this->cmd );
		preg_match( '/CREATE INDEX (.+) ON/', $temp, $match );
		$this->index = trim( $match[1] );
	}

	/** @brief set both table and index name
	 */
	protected function extractIndexTableName() {
		$temp = str_replace( 'IF NOT EXISTS ', '', $this->cmd );
		preg_match( '/CREATE INDEX .+ ON (.+)( |)\(/', $temp, $match );
		$this->table = trim( $match[1] );
		while ( preg_match( '/(\()/', $this->table ) ) {
			preg_match( '/^(.+) \(/', $this->table, $match );
			$this->table = trim( $match[1] );
		}
		$this->extractIndexName();
	}

	/** @brief output formatting
	 */
	protected function output( $echo ) {
		echo $echo . "\n";
	}

	/** @brief reset internal parameters
	 */
	protected function resetInternalParameters() {
		$this->cmd = null;
		$this->done = false;
		$this->table = null;
		$this->index = null;
	}

	/** @brief set internal parameters
	 */
	protected function setInternalParameters() {
		$this->resetInternalParameters();
	}

}
