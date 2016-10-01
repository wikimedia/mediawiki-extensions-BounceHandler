<?php
/**
 * Hooks used by BounceHandler
 *
 * @file
 * @ingroup Hooks
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class BounceHandlerHooks {
	/**
	 * Function run on startup in $wgExtensionFunctions
	 */
	public static function extensionFunction() {
		global $wgNoReplyAddress, $wgServerName, $wgUnrecognizedBounceNotify, $wgVERPdomainPart;

		$wgUnrecognizedBounceNotify = $wgUnrecognizedBounceNotify ? : [ $wgNoReplyAddress ];
		$wgVERPdomainPart = $wgVERPdomainPart ? : $wgServerName;
	}

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
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function LoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'bounce_records', __DIR__ . '/sql/bounce_records.sql' );
		$updater->modifyExtensionField(
			'bounce_records', 'br_user', __DIR__ . '/sql/alter_user_column.sql'
		);
		$updater->addExtensionIndex(
			'bounce_records', 'br_mail_timestamp', __DIR__ .'/sql/create_index_mail_timestamp.sql'
		);
		$updater->addExtensionIndex(
			'bounce_records', 'br_timestamp', __DIR__ .'/sql/create_index_timestamp.sql'
		);

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
			'presentation-model' => 'EchoBounceHandlerPresentationModel',
			'primary-link' => [
				'message' => 'notification-link-text-change-email',
				'destination' => 'change-email'
			],
			'formatter-class' => 'EchoBounceHandlerFormatter',
			'category' => 'system',
			'section' => 'alert',
			// We cannot have additional Echo emails being sent after a user is un-subscribed
			'notify-type-availability' => [ 'email' => false ],

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
