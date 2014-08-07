<?php
/**
 * Hooks used by BounceHandler
 */

class BounceHandlerHooks {
	/**
	 * This function generates the VERP address on UserMailer::send()
	 *
	 * @param MailAddress $recip recipients array
	 * @param string  returnPath return-path address
	 * @return bool true
	 */
	public static function onVERPAddressGenerate( $recip, &$returnPath ) {
		global $wgVERPalgorithm, $wgVERPsecret, $wgServer, $wgSMTP;
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
		$verpAddress = new VerpAddressGenerator( $wgVERPalgorithm, $wgVERPsecret, $wgServer, $wgSMTP );
		$returnPath = $verpAddress->generateVERP( $uid );

		return true;
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
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function addBounceRecordsTable( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'bounce_records',
			__DIR__. '/sql/bounce_records.sql', true
			);
		return true;
	}
}