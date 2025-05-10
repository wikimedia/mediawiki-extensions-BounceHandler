<?php
namespace MediaWiki\Extension\BounceHandler;

use InvalidArgumentException;
use MailAddress;
use MediaWiki\Config\Config;
use MediaWiki\Hook\UserMailerChangeReturnPathHook;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserFactory;

/**
 * Hooks used by BounceHandler
 *
 * @file
 * @ingroup Hooks
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class Hooks implements
	UserMailerChangeReturnPathHook
{
	private Config $config;
	private UserFactory $userFactory;

	public function __construct(
		Config $config,
		UserFactory $userFactory
	) {
		$this->config = $config;
		$this->userFactory = $userFactory;
	}

	/**
	 * This function generates the VERP address on UserMailer::send()
	 * Generating VERP address for a batch of send emails is complex. This feature is hence disabled
	 *
	 * @param MailAddress[] $recip Recipient's email array
	 * @param string &$returnPath return-path address
	 * @throws InvalidArgumentException
	 */
	public function onUserMailerChangeReturnPath( $recip, &$returnPath ) {
		if ( $this->config->get( 'GenerateVERP' ) && count( $recip ) === 1 ) {
			$this->generateVerp( $recip[0], $returnPath );
		}
	}

	/**
	 * Process a given $to address and return its VERP return path
	 *
	 * @param MailAddress $to
	 * @param string &$returnPath return-path address
	 * @return bool true
	 */
	protected function generateVerp( MailAddress $to, &$returnPath ) {
		$user = $this->userFactory->newFromName( $to->name );
		if ( !$user ) {
			return true;
		}
		$email = $to->address;
		if ( $user->getEmail() === $email && $user->isEmailConfirmed() ) {
			$uid = $user->getId();
		} else {
			return true;
		}
		$domainPart = $this->config->get( 'VERPdomainPart' ) ??
			$this->config->get( MainConfigNames::ServerName );
		$verpAddress = new VerpAddressGenerator(
			$this->config->get( 'VERPprefix' ),
			$this->config->get( 'VERPalgorithm' ),
			$this->config->get( 'VERPsecret' ),
			$domainPart
		);
		$returnPath = $verpAddress->generateVERP( $uid );

		return true;
	}
}
