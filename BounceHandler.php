<?php
/**
 * BounceHandler Extension to handle email bounces in MediaWiki
 */
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'BounceHandler',
	'author' => array(
		'Tony Thomas',
		'Kunal Mehta',
		'Jeff Green',
	),
	'url' => "https://www.mediawiki.org/wiki/Extension:BounceHandler",
	'descriptionmsg' => 'bouncehandler-desc',
	'version'  => '1.0',
	'license-name' => "GPL V2.0",
);

/* Setup*/
$dir = __DIR__ ;

//Hooks files
$wgAutoloadClasses['BounceHandlerHooks'] =  $dir. '/BounceHandlerHooks.php';

//Register and Load BounceHandler API
$wgAutoloadClasses['ApiBounceHandler'] = $dir. '/includes/ApiBounceHandler.php';
$wgAPIModules['bouncehandler'] = 'ApiBounceHandler';

//Register and Load Jobs
$wgAutoloadClasses['BounceHandlerJob'] = $dir. '/includes/BounceHandlerJob.php';
$wgAutoloadClasses['ProcessBounceEmails'] = $dir. '/includes/ProcessBounceEmails.php';
$wgAutoloadClasses['BounceHandlerActions'] = $dir. '/includes/BounceHandlerActions.php';
$wgAutoloadClasses['ProcessUnRecognizedBounces'] = $dir. '/includes/ProcessUnRecognizedBounces.php';
$wgAutoloadClasses['ProcessBounceWithPlancake'] = $dir. '/includes/ProcessBounceWithPlancake.php';
$wgAutoloadClasses['ProcessBounceWithRegex'] = $dir. '/includes/ProcessBounceWithRegex.php';
$wgAutoloadClasses['VerpAddressGenerator'] = $dir. '/includes/VerpAddressGenerator.php';

$wgJobClasses['BounceHandlerJob'] = 'BounceHandlerJob';

//Register Hooks
$wgHooks['UserMailerChangeReturnPath'][] = 'BounceHandlerHooks::onVERPAddressGenerate';
$wgHooks['UnitTestsList'][] = 'BounceHandlerHooks::registerUnitTests';

/*Messages Files */
$wgMessagesDirs['BounceHandler'] = $dir. '/i18n';

# Schema updates for update.php
$wgHooks['LoadExtensionSchemaUpdates'][] = 'BounceHandlerHooks::loadExtensionSchemaUpdates';

/**
 * VERP Configurations
 * wgVERPprefix - The prefix of the VERP address.
 * wgVERPalgorithm - Algorithm to hash the return path address.Possible algorithms are
 * md2. md4, md5, sha1, sha224, sha256, sha384, ripemd128, ripemd160, whirlpool and more.
 * wgVERPsecret - The secret key to hash the return path address
 */
$wgVERPprefix = 'wiki';
$wgVERPdomainPart = 'meta.wikimedia.org';
$wgVERPalgorithm = 'md5';
$wgVERPsecret = 'MediawikiVERP';
$wgBounceHandlerUnconfirmUsers = false; // Toggle the user un-subscribe action
$wgVERPAcceptTime = 259200; //3 days time
$wgBounceRecordPeriod = 604800; // 60 * 60 * 24 * 7 - 7 days bounce activity are considered before un-subscribing
$wgBounceRecordLimit = 10; // If there are more than 10 bounces in the $wgBounceRecordPeriod, the user is un-subscribed

/* Allow only internal IP range to do the POST request */
$wgBounceHandlerInternalIPs = array( '127.0.0.1', '::1' );

/* Admin email address which should be notified in the case of an unprocessed valid bounce */
$wgUnrecognizedBounceNotify = array( 'wiki-admin@wikimedia.org' );

# Alternative DB cluster to use for the bounce tables
$wgBounceHandlerCluster = false;

# Central DB name to use if the bounce table is to be shared
$wgBounceHandlerSharedDB = false;

// Check and include Plancake email parser library
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/vendor/autoload.php' );
}
