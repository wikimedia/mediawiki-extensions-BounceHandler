<?php
namespace MediaWiki\Extension\BounceHandler;

use InvalidArgumentException;
use MailAddress;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Hook\UserMailerChangeReturnPathHook;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;

/**
 * Hooks used by BounceHandler
 *
 * @file
 * @ingroup Hooks
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class Hooks implements UserMailerChangeReturnPathHook {
	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
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

	/**
	 * Add BounceHandler events to Echo
	 *
	 * @param array &$notifications Echo notifications
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent( array &$notifications ) {
		$notifications['unsubscribe-bouncehandler'] = [
			'presentation-model' => EchoBounceHandlerPresentationModel::class,
			'primary-link' => [
				'message' => 'notification-link-text-change-email',
				'destination' => 'change-email'
			],
			// We cannot have additional Echo emails being sent after a user is un-subscribed
			'category' => 'system-noemail',
			'section' => 'alert',

			'title-message' => 'notification-bouncehandler',
			'title-params' => [ 'user' ],
			'flyout-message' => 'notification-bouncehandler-flyout',
			'flyout-params' => [ 'failed-email', 'user' ],
		];

		return true;
	}

	/**
	 * Add user to be notified on echo event
	 *
	 * @param Event $event
	 * @param User[] &$users
	 * @return bool
	 */
	public static function onEchoGetDefaultNotifiedUsers( Event $event, array &$users ) {
		if ( $event->getExtraParam( 'failed-user-id' ) === null ) {
			return true;
		}
		$extra = $event->getExtra();
		$eventType = $event->getType();
		if ( $eventType === 'unsubscribe-bouncehandler' ) {
			$recipientId = $extra['failed-user-id'];
			$recipient = User::newFromId( $recipientId );
			$users[$recipientId] = $recipient;
		}

		return true;
	}

}
