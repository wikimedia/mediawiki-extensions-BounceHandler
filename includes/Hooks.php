<?php
namespace MediaWiki\Extension\BounceHandler;

use DatabaseUpdater;
use EchoEvent;
use InvalidArgumentException;
use MailAddress;
use User;

/**
 * Hooks used by BounceHandler
 *
 * @file
 * @ingroup Hooks
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class Hooks {
	/**
	 * This function generates the VERP address on UserMailer::send()
	 * Generating VERP address for a batch of send emails is complex. This feature is hence disabled
	 *
	 * @param MailAddress[] $recip Recipient's email array
	 * @param string &$returnPath return-path address
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
	 * @param string &$returnPath return-path address
	 * @return bool true
	 */
	protected static function generateVerp( MailAddress $to, &$returnPath ) {
		global $wgVERPprefix, $wgVERPalgorithm, $wgVERPsecret, $wgVERPdomainPart, $wgServerName;
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
		$domainPart = $wgVERPdomainPart ?? $wgServerName;
		$verpAddress = new VerpAddressGenerator( $wgVERPprefix,
			$wgVERPalgorithm, $wgVERPsecret, $domainPart );
		$returnPath = $verpAddress->generateVERP( $uid );

		return true;
	}

	/**
	 * Add tables to the database
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();
		$path = dirname( __DIR__ ) . '/sql';

		$updater->addExtensionTable( 'bounce_records', "$path/$type/tables-generated.sql" );

		if ( $type !== 'sqlite' ) {
			// 1.38
			$updater->modifyExtensionField(
				'bounce_records', 'br_timestamp', "$path/$type/patch-bounce_records-br_timestamp.sql"
			);
		}
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
	 * @param EchoEvent $event
	 * @param User[] &$users
	 * @return bool
	 */
	public static function onEchoGetDefaultNotifiedUsers( EchoEvent $event, array &$users ) {
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
