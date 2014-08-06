<?php
/** Hooks used by BounceHandler
*/

class BounceHandlerHooks {
	/**
	 * This function generates the VERP address on UserMailer::send()
	 * @param array $recip recipients array
	 * @param string  returnPath return-path address
	 * @return bool true
	 */
	public static function onVERPAddressGenerate( $recip, &$returnPath ) {
		$user = User::newFromName( $recip[0]->name );
		if ( !$user ) {
			return true;
		}
		$email = $recip[0]->address;
		if ( $user->getEmail() === $email && $user->isEmailConfirmed() ) {
			$uid = $user->getId();
		} else {
			return true;
		}
		$returnPath = self::generateVERP( $uid );
		return true;
	}

	/**
	 * Generate VERP address of the form
	 *
	 * @param string recipient email
	 * @return string ReturnPath address
	 */
	protected static function generateVERP( $uid ) {
		global $wgVERPalgorithm, $wgVERPsecret, $wgServer, $wgSMTP;
		// Get the time in Unix timestamp to compare with seconds
		$timeNow = wfTimestamp();
		if(  is_array( $wgSMTP ) && isset( $wgSMTP['IDHost'] ) && $wgSMTP['IDHost'] ) {
			$email_domain = $wgSMTP['IDHost'];
		} else {
			$url = wfParseUrl( $wgServer );
			$email_domain = $url['host'];
		}
		// Creating the VERP address prefix as wikiId-base36( $UserID )-base36( $Timestamp )
		// and the generated VERP return path is of the form :
		// wikiId-base36( $UserID )-base36( $Timestamp )-hash( $algorithm, $key, $prefix )@$email_domain
		// We dont want repeating '-' in our WikiId
		$wikiId = str_replace( '-', '.', wfWikiID() );
		$email_prefix = $wikiId. '-'. base_convert( $uid, 10, 36). '-'. base_convert( $timeNow, 10, 36);
		$verp_hash = hash_hmac( $wgVERPalgorithm, $email_prefix, $wgVERPsecret );
		$returnPath = $email_prefix. '-' .$verp_hash. '@' .$email_domain;
		return $returnPath;
	}


	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @param array &$files
	 *
	 * @return boolean
	 */
	public static function registerUnitTests( array &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = array();
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );
		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 *
	 * Add tables to Database
	 */
	public static function addBounceRecordsTable( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'bounce_records',
			__DIR__. '/sql/bounce_records.sql', true
			);
		return true;
	}
}