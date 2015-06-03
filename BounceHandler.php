<?php
/**
 * BounceHandler Extension to handle email bounces in MediaWiki
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
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
	'license-name' => 'GPL-2.0+',
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
$wgAutoloadClasses['ProcessBounceWithRegex'] = $dir. '/includes/ProcessBounceWithRegex.php';
$wgAutoloadClasses['VerpAddressGenerator'] = $dir. '/includes/VerpAddressGenerator.php';
$wgAutoloadClasses['PruneOldBounceRecords'] = $dir. '/includes/PruneOldBounceRecords.php';

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
 * $wgGenerateVERP -  Toggle VERP generation
 * wgVERPprefix - The prefix of the VERP address.
 * wgVERPdomainPart - The domain part of the VERP email address, defaults to $wgServerName
 * wgVERPalgorithm - Algorithm to hash the return path address.Possible algorithms are
 * md2. md4, md5, sha1, sha224, sha256, sha384, ripemd128, ripemd160, whirlpool and more.
 * wgVERPsecret - The secret key to hash the return path address
 * wgBounceHandlerUnconfirmUsers - Toggle the user un-subscribe action
 */
$wgGenerateVERP = true;
$wgVERPprefix = 'wiki';
$wgVERPalgorithm = 'md5';
$wgVERPsecret = 'MediawikiVERP';
$wgBounceHandlerUnconfirmUsers = false;
$wgUnrecognizedBounceNotify = null;
$wgVERPdomainPart = null;  // set this only if you want the domain part of your email different from your wgServerName
$wgVERPAcceptTime = 259200; //3 days time
$wgBounceRecordPeriod = 604800; // 60 * 60 * 24 * 7 - 7 days bounce activity are considered before un-subscribing
$wgBounceRecordLimit = 10; // If there are more than 10 bounces in the $wgBounceRecordPeriod, the user is un-subscribed

/* Allow only internal IP range to do the POST request */
$wgBounceHandlerInternalIPs = array( '127.0.0.1', '::1' );

/* Admin email address which should be notified in the case of an unprocessed valid bounce */
$wgExtensionFunctions[] = function() {
	global $wgNoReplyAddress, $wgServerName, $wgUnrecognizedBounceNotify, $wgVERPdomainPart;
	$wgUnrecognizedBounceNotify = $wgUnrecognizedBounceNotify ? : array( $wgNoReplyAddress );
	$wgVERPdomainPart = $wgVERPdomainPart ? : $wgServerName;
};

# Alternative DB cluster to use for the bounce tables
$wgBounceHandlerCluster = false;

# Central DB name to use if the bounce table is to be shared
$wgBounceHandlerSharedDB = false;

# Maximum time in seconds until which a bounce record should be stored in the table
$wgBounceRecordMaxAge = 5184000; //60 * 24 * 60 *60  ( 60 Days time in seconds )