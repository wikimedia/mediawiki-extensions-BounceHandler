<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'BounceHandler' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['BounceHandler'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for BounceHandler extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the BounceHandler extension requires MediaWiki 1.25+' );
}