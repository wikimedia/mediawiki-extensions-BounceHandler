<?php
/**
 * Hooks used by BounceHandler
 */

class BounceHandlerHooks {
	/**
	 * This function generates the VERP address on UserMailer::send()
	 * Generating VERP address for a batch of send emails is complex. This feature is hence disabled
	 *
	 * @param MailAddress[] $recip Recipient's email array
	 * @param string $returnPath return-path address
	 * @return bool
	 * @throws InvalidArgumentException
	 */
	public static function onVERPAddressGenerate( array $recip, &$returnPath ) {
		global $wgGenerateVERP;
		if ( $wgGenerateVERP && count( $recip ) === 1 ) {
			self::generateVerp( $recip[0], $returnPath );
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
	protected static function generateVerp( MailAddress $to, &$returnPath ) {
		global $wgVERPprefix, $wgVERPalgorithm, $wgVERPsecret, $wgVERPdomainPart;
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
		$verpAddress = new VerpAddressGenerator( $wgVERPprefix,
			$wgVERPalgorithm, $wgVERPsecret, $wgVERPdomainPart );
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
	public static function LoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'bounce_records', __DIR__ . '/sql/bounce_records.sql', true );
		$updater->modifyExtensionField( 'bounce_records', 'br_user', __DIR__ . '/sql/alter_user_column.sql' );
		$updater->addExtensionIndex( 'bounce_records', 'br_mail_timestamp', __DIR__ .'/sql/create_index.sql' );

		return true;
	}
}
