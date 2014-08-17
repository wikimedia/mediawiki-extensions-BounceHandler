<?php
/**
 * Hooks used by BounceHandler
 */

class BounceHandlerHooks {
	/**
	 * This function generates the VERP address on UserMailer::send()
	 *
	 * @param MailAddress|MailAddress[] $recip Recipient's email (or an array of them)
	 * @param string $returnPath return-path address
	 * @return bool
	 * @throws InvalidArgumentException
	 */
	public static function onVERPAddressGenerate( $recip, &$returnPath ) {
		if ( is_object( $recip ) ) {
			self::generateVerp( $recip, $returnPath );
		} else if ( is_array( $recip ) ){
			// Generating VERP address for a batch of send emails is complex. This feature is hence disabled
			return true;
		} else {
			throw new InvalidArgumentException( "Expected MailAddress object or an array of MailAddress, got $recip" );
		}
		return true;
	}

	/**
	 * Process a given $to address and return its VERP return path
	 *
	 * @param MailAddress $to
	 * @param string $returnPath return-path address
	 * @return bool true
	 */
	public static function generateVerp( MailAddress $to, &$returnPath ) {
		global $wgVERPprefix, $wgVERPalgorithm, $wgVERPsecret, $wgServer, $wgSMTP;
		$user = User::newFromName( $to->name );
		if ( !$user ) {
			return true;
		}
		$email = $to->address;
		if ( $user->getEmail() === $email && $user->isEmailConfirmed() ) {
			$uid = $user->getId();
		} else {
			return true;
		}
		$verpAddress = new VerpAddressGenerator( $wgVERPprefix, $wgVERPalgorithm, $wgVERPsecret, $wgServer, $wgSMTP );
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