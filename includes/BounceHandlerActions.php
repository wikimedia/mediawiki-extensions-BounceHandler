<?php

namespace MediaWiki\Extension\BounceHandler;

use ExtensionRegistry;
use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * Actions to perform after we receive an email bounce
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
		MediaWikiServices::getInstance()->getJobQueueGroupFactory()
			->makeJobQueueGroup( $this->wikiId )
			->push( $job );
	}

	/**
	 * Un-subscribe a failing recipient
	 *
	 * @param array $failedUser The details of the failing user
	 * @param array $emailHeaders Email headers
	 */
	public function unSubscribeUser( array $failedUser, $emailHeaders ) {
		$originalEmail = $failedUser['rawEmail'];
		$bounceUserId = $failedUser['rawUserId'];

		$user = User::newFromId( $bounceUserId );
		$caUser = null;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			$instance = CentralAuthUser::getPrimaryInstance( $user );
			if ( $instance->isAttached() ) {
				$caUser = $instance;
			}
		}

		if ( $caUser ) {
			// Invalidate gu_email_authenticated in the CentralAuth database
			$caUser->setEmailAuthenticationTimestamp( null );
			$caUser->saveSettings();
			$this->notifyGlobalUser( $bounceUserId, $originalEmail );
		} else {
			// Invalidate user_email_authenticated in the local user table
			$user->setEmailAuthenticationTimestamp( null );
			$user->saveSettings();
			$this->createEchoNotification( $bounceUserId, $originalEmail );
		}

		$logger = LoggerFactory::getInstance( 'BounceHandler' );
		$logger->info( 'Un-subscribed {name} <{email}> for exceeding bounce limit {limit}', [
			'name' => $caUser ? $caUser->getName() : $user->getName(),
			'email' => $originalEmail,
			'limit' => $this->bounceRecordLimit,
			'emailHeaders' => $this->formatHeaders( $emailHeaders ),
			'emailRaw' => $this->emailRaw,
		] );

		$from = $caUser ? 'global' : 'local';
		MediaWikiServices::getInstance()->getStatsFactory()
			->getCounter( 'BounceHandler_unsubscribed_total' )
			->setLabel( 'from', $from )
			->copyToStatsdAt( "bouncehandler.unsub.$from" )
			->increment();
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
