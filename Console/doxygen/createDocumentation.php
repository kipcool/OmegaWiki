<?php
/** \file createDocumentation.php
 *  \brief Maintenance script to create a WikiLexicalData/OmegaWiki documentation
 */

$baseDir = __DIR__ . '/../../../..';
require_once $baseDir . '/maintenance/Maintenance.php';
require_once $baseDir . '/extensions/WikiLexicalData/OmegaWiki/WikiDataGlobals.php';
require_once $baseDir . '/extensions/WikiLexicalData/OmegaWiki/Transaction.php';

echo "start\n";

/** \class CreateDocumentation
 * \brief WikiLexicalData's Document generator
 */
class CreateDocumentation extends Maintenance {

	/** \brief public function constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Generates the WikiLexicalData Document\n"
			. 'Example usage: php createDocumentation.php --config=docTemplate.cfg ' );
		$this->addOption( 'config', 'A doxygen config file used to creating the documentation. e.g. --config=docTemplate.cfg' );
	}

	/** \brief public function execute
	 */
	public function execute() {
		global $wgWldDownloadScriptPath;

		// checking that the needed parameters are given
		// else use the default config file
		if ( !$this->hasOption( 'config' ) ) {
			$config = 'docTemplate.cfg';
		} else {
			$config = $this->getOption( 'config' );
		}

		$configFinal = $wgWldDownloadScriptPath . 'doc.cfg';
		$dhtml = $wgWldDownloadScriptPath . 'docH.html ';
		$dhtml .= $wgWldDownloadScriptPath . 'docF.html ';
		$dhtml .= $wgWldDownloadScriptPath . 'doc.css';
		chdir( '../..' );
		echo getcwd() . "\n";
		$this->readTemplateConfigFile( $config, $configFinal );

		// create a command to execute.
		$this->output( "updating...\ndoxygen -u $configFinal\n\n" );
		exec( 'doxygen -s -u ' . $configFinal );
		$this->output( "updating...\ndoxygen -w html $dhtml\n\n" );
		exec( 'doxygen -w html ' . $dhtml );
		$this->output( "executing...\ndoxygen $configFinal\n\n" );
		exec( 'doxygen ' . $configFinal );

		$this->output( "fin.\n" );
	}

	/** \brief Read, interpret and create the configuration to use
	 *
	 * @param string $filename The template config file.
	 * @param string $configFinal The config file to produce.
	 */
	protected function readTemplateConfigFile( $filename, $configFinal ) {
		global $wgWldDownloadScriptPath;

		if ( file_exists( 'Console/doxygen/' . $filename ) ) {
			$this->output( "Preparing file..\n\n" );
			$this->output( "copy Console/doxygen/" . $filename . " $configFinal\n\n" );
			copy( 'Console/doxygen/' . $filename, $configFinal );
		} else {
			die( "template file not found..\n\n" );
		}

		$this->output( "\nParsing config file $configFinal...\n\n" );

		$str = file_get_contents( $configFinal );
		$wldBase = __DIR__ . '/../';
		$str = trim( str_replace( '/*$wgWLDdir*/', $wldBase, $str ) );
		$str = trim( str_replace( '/*$wgWLDdownload*/', $wgWldDownloadScriptPath, $str ) );
		$fp = fopen( $configFinal, 'w' );
		if ( false === $fp ) {
			return "Could not open \"{$filename}\".\n";
		}
		echo "Configuring {$configFinal}\n\n";
		fwrite( $fp, $str );

		fclose( $fp );
		return true;
	}

}

$maintClass = 'CreateDocumentation';
require_once RUN_MAINTENANCE_IF_MAIN;
