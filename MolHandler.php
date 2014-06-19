<?php
/**
 * MolHandler extension
 *
 * @file
 * @ingroup Extensions
 *
 * Stores the setup instructions for MolHandler.
 *
 * Usage: Include the following line in your LocalSettings.php
 * require_once( "$IP/extensions/MolHandler/MolHandler.php" );
 * The indigo-depict tool must be present.
 *
 * @author Rainer Rillke <[lastname]@wikipedia.de>
 * @license GPL.v2 or later
 * @version 0.1
 */

# Not a valid entry point, skip unless MEDIAWIKI is defined
if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'MolHandler is a MediaWiki extension.';
	exit( 1 );
}

// Credits
$wgExtensionCredits['media'][] = array(
	'path' => __FILE__,
	'name' => 'Mol Handler',
	'author' => array(
		'Rainer Rillke',
	),
	'version' => '0.1.0',
	'descriptionmsg' => 'mwe-mh-credits-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:MolHandler',
	'license-name' => 'GPL-2.0+',
);

/* Configuration */
$wgMolHandlerDir = __DIR__ . '/';
$wgMessagesDirs['MolHandler'] = $wgMolHandlerDir . 'i18n';

# Converter. Note that babel is not yet able to convert reactions
$wgMolConverterPath = '/usr/bin';
$wgMolConvertCommands = array(
	'babel' => array(
		'command' => '$path/babel -i$format $input $output',
		'supportedFormats' => array( 'mol' )
	),
	'indigo' => array(
		'command' => '$path/indigo-depict $input $output',
		'supportedFormats' => array( 'mol', 'rxn' )
	),
);
$wgMolConverter = 'indigo';

$wgMediaHandlers['chemical/x-mdl-molfile'] = 'MolMediaHandler';
$wgMediaHandlers['chemical/x-mdl-rxnfile'] = 'RxnMediaHandler';

# Require modules
$wgAutoloadClasses['MolHandler']      = $wgMolHandlerDir . 'MolHandler_body.php';
$wgAutoloadClasses['MolMediaHandler'] = $wgMolHandlerDir . 'MolMediaHandler.php';
$wgAutoloadClasses['RxnMediaHandler'] = $wgMolHandlerDir . 'RxnMediaHandler.php';
