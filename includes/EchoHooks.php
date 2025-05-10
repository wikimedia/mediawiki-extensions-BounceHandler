<?php
namespace MediaWiki\Extension\BounceHandler;

use MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook;
use MediaWiki\Extension\Notifications\Hooks\EchoGetDefaultNotifiedUsersHook;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\User\User;

/**
 * Hooks used by BounceHandler
 *
 * @file
 * @ingroup Hooks
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */

class EchoHooks implements
	BeforeCreateEchoEventHook,
	EchoGetDefaultNotifiedUsersHook
{
	/**
	 * Add BounceHandler events to Echo
	 *
	 * @param array &$notifications Echo notifications
	 * @param array &$notificationCategories To expand $wgEchoNotificationCategories
	 * @param array &$notificationIcons To expand $wgEchoNotificationIcons
	 * @return bool
	 */
	public function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$notificationIcons
	) {
		$notifications['unsubscribe-bouncehandler'] = [
			'presentation-model' => EchoBounceHandlerPresentationModel::class,
			'primary-link' => [
				'message' => 'notification-link-text-change-email',
				'destination' => 'change-email'
			],
			// We cannot have additional Echo emails being sent after a user is unsubscribed
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
	public function onEchoGetDefaultNotifiedUsers( Event $event, array &$users ) {
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
