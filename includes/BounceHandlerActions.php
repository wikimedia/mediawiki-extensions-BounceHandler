<?php

namespace MediaWiki\Extension\BounceHandler;

use ExtensionRegistry;
use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * Class BounceHandlerActions
 *
 * Actions to be done on finding out a failing recipient
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class BounceHandlerActions {

	/**
	 * @var string
	 */
	protected $wikiId;

	/**
	 * @var int
	 */
	protected $bounceRecordPeriod;

	/**
	 * @var int
	 */
	protected $bounceRecordLimit;

	/**
	 * @var bool
	 */
	protected $bounceHandlerUnconfirmUsers;

	/**
	 * @var string
	 */
	protected $emailRaw;

	/**
	 * @param string $wikiId The database id of the failing recipient
	 * @param int $bounceRecordPeriod Time period for which bounce activities are considered
	 *  before un-subscribing
	 * @param int $bounceRecordLimit The number of bounce allowed in the bounceRecordPeriod.
	 * @param bool $bounceHandlerUnconfirmUsers Enable/Disable user un-subscribe action
	 * @param string $emailRaw The raw bounce Email
	 */
	public function __construct(
		$wikiId, $bounceRecordPeriod, $bounceRecordLimit, $bounceHandlerUnconfirmUsers, $emailRaw
	) {
		if ( $wikiId !== WikiMap::getCurrentWikiId() ) {
			// We want to use the User class methods, which make no sense on the wrong wiki
			throw new InvalidArgumentException( "BounceHandlerActions constructed for a foreign wiki." );
		}

		$this->wikiId = $wikiId;
		$this->bounceRecordPeriod = $bounceRecordPeriod;
		$this->bounceRecordLimit = $bounceRecordLimit;
		$this->bounceHandlerUnconfirmUsers = $bounceHandlerUnconfirmUsers;
		$this->emailRaw = $emailRaw;
	}

	/**
	 * Perform actions on users who failed to receive emails in a given period
	 *
	 * @param array $failedUser The details of the failing user
	 * @param array $emailHeaders Email headers
	 * @return bool
	 */
	public function handleFailingRecipient( array $failedUser, $emailHeaders ) {
		if ( $this->bounceHandlerUnconfirmUsers ) {
			$originalEmail = $failedUser['rawEmail'];
			$bounceValidPeriod = time() - $this->bounceRecordPeriod; // Unix

			$dbr = ProcessBounceEmails::getBounceRecordDB( DB_REPLICA, $this->wikiId );

			$totalBounces = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'bounce_records' )
				->where( [
					'br_user_email' => $originalEmail,
					$dbr->expr( 'br_timestamp', '>=', $dbr->timestamp( $bounceValidPeriod ) )
				] )
				->limit( $this->bounceRecordLimit )
				->caller( __METHOD__ )->fetchRowCount();

			if ( $totalBounces >= $this->bounceRecordLimit ) {
				$this->unSubscribeUser( $failedUser, $emailHeaders );
			}
		}

		return true;
	}

	/**
	 * Function to trigger Echo notifications
	 *
	 * @param int $userId ID of user to be notified
	 * @param string $email un-subscribed email address used in notification
	 */
	public function createEchoNotification( $userId, $email ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			Event::create( [
				'type' => 'unsubscribe-bouncehandler',
				'extra' => [
					'failed-user-id' => $userId,
					'failed-email' => $email,
				],
			] );
		}
	}

	/**
	 * Function to inject Echo notification to the last source of bounce for an
	 * unsubscribed Global user
	 *
	 * @param int $bounceUserId
	 * @param string $originalEmail
	 */
	public function notifyGlobalUser( $bounceUserId, $originalEmail ) {
		$params = [
			'failed-user-id' => $bounceUserId,
			'failed-email' => $originalEmail,
			'wikiId' => $this->wikiId,
			'bounceRecordPeriod' => $this->bounceRecordPeriod,
			'bounceRecordLimit' => $this->bounceRecordLimit,
			'bounceHandlerUnconfirmUsers' => $this->bounceHandlerUnconfirmUsers,
			'emailRaw' => $this->emailRaw,
		];
		$title = Title::newFromText( 'BounceHandler Global user notification Job' );
		$job = new BounceHandlerNotificationJob( $title, $params );
		MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup( $this->wikiId )->push( $job );
	}

	/**
	 * Function to Un-subscribe a failing recipient
	 *
	 * @param array $failedUser The details of the failing user
	 * @param array $emailHeaders Email headers
	 */
	public function unSubscribeUser( array $failedUser, $emailHeaders ) {
		// Un-subscribe the user
		$originalEmail = $failedUser['rawEmail'];
		$bounceUserId = $failedUser['rawUserId'];

		$user = User::newFromId( $bounceUserId );
		$stats = \MediaWiki\MediaWikiServices::getInstance()->getStatsdDataFactory();
		// Handle the central account email status (if applicable)
		$unsubscribeLocalUser = true;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			$caUser = CentralAuthUser::getPrimaryInstance( $user );
			if ( $caUser->isAttached() ) {
				$unsubscribeLocalUser = false;
				$caUser->setEmailAuthenticationTimestamp( null );
				$caUser->saveSettings();
				$this->notifyGlobalUser( $bounceUserId, $originalEmail );
				wfDebugLog( 'BounceHandler',
					"Un-subscribed global user {$caUser->getName()} <$originalEmail> for " .
						"exceeding Bounce Limit $this->bounceRecordLimit.\nProcessed Headers:\n" .
						$this->formatHeaders( $emailHeaders ) . "\nBounced Email: \n$this->emailRaw"
				);
				$stats->increment( 'bouncehandler.unsub.global' );
			}
		}
		if ( $unsubscribeLocalUser ) {
			// Invalidate the email-id of a local user
			$user->setEmailAuthenticationTimestamp( null );
			$user->saveSettings();
			$this->createEchoNotification( $bounceUserId, $originalEmail );
			wfDebugLog( 'BounceHandler',
				"Un-subscribed {$user->getName()} <$originalEmail> for exceeding Bounce limit " .
					"$this->bounceRecordLimit.\nProcessed Headers:\n" .
					$this->formatHeaders( $emailHeaders ) . "\nBounced Email: \n$this->emailRaw"
			);
			$stats->increment( 'bouncehandler.unsub.local' );
		}
	}

	/**
	 * Turns a keyed array into "Key: Value" newline split string
	 *
	 * @param array $emailHeaders
	 * @return string
	 */
	private function formatHeaders( $emailHeaders ) {
		return implode(
			"\n",
			array_map(
				static function ( $v, $k ) {
					return "$k: $v";
				},
				$emailHeaders,
				array_keys( $emailHeaders )
			)
		);
	}

}
