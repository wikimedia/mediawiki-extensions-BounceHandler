<?php
/**
 * Class BounceHandlerActions
 *
 * Actions to be done on finding out a failing recipient
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
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
	 * @param int $bounceRecordPeriod Time period for which bounce activities are considered before un-subscribing
	 * @param int $bounceRecordLimit The number of bounce allowed in the bounceRecordPeriod.
	 * @param bool $bounceHandlerUnconfirmUsers Enable/Disable user un-subscribe action
	 * @param string $emailRaw The raw bounce Email
	 * @throws Exception
	 */
	public function __construct( $wikiId, $bounceRecordPeriod, $bounceRecordLimit, $bounceHandlerUnconfirmUsers, $emailRaw
	) {
		if ( $wikiId !== wfWikiID() ) {
			// We want to use the User class methods, which make no sense on the wrong wiki
			throw new Exception( "BounceHandlerActions constructed for a foreign wiki." );
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

			$dbr = ProcessBounceEmails::getBounceRecordDB( DB_SLAVE, $this->wikiId );

			$totalBounces = $dbr->selectRowCount( 'bounce_records',
				array( '*' ),
				array(
					'br_user_email' => $originalEmail,
					'br_timestamp >= ' . $dbr->addQuotes( $dbr->timestamp( $bounceValidPeriod ) )
				),
				__METHOD__,
				array( 'LIMIT' => $this->bounceRecordLimit )
			);

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
		if ( class_exists( 'EchoEvent' ) ) {
			EchoEvent::create( array(
				'type' => 'unsubscribe-bouncehandler',
				'extra' => array(
					'failed-user-id' => $userId,
					'failed-email' => $email,
				),
			) );
		}

	}

	/**
	 * Function to inject Echo notification to the last source of bounce for an unsubscribed Global user
	 *
	 * @param $bounceUserId
	 * @param $originalEmail
	 */
	public function notifyGlobalUser( $bounceUserId, $originalEmail ) {
		$params = array(
			'failed-user-id' => $bounceUserId,
			'failed-email' => $originalEmail,
			'wikiId' => $this->wikiId,
			'bounceRecordPeriod' => $this->bounceRecordPeriod,
			'bounceRecordLimit' => $this->bounceRecordLimit,
			'bounceHandlerUnconfirmUsers' => $this->bounceHandlerUnconfirmUsers
		);
		$title = Title::newFromText( 'BounceHandler Global user notification Job' );
		$job = new BounceHandlerNotificationJob( $title, $params );
		JobQueueGroup::singleton( $this->wikiId )->push( $job );
	}

	/**
	 * Function to Un-subscribe a failing recipient
	 *
	 * @param array $failedUser The details of the failing user
	 * @param array $emailHeaders Email headers
	 */
	public function unSubscribeUser( array $failedUser, $emailHeaders ) {
		//Un-subscribe the user
		$originalEmail = $failedUser['rawEmail'];
		$bounceUserId = $failedUser['rawUserId'];

		$user = User::newFromId( $bounceUserId );
		$stats = \MediaWiki\MediaWikiServices::getInstance()->getStatsdDataFactory();
		// Handle the central account email status (if applicable)
		if ( class_exists( 'CentralAuthUser') ) {
			$caUser = CentralAuthUser::getInstance( $user );
			if ( $caUser->isAttached() ) {
				$caUser->setEmailAuthenticationTimestamp( null );
				$caUser->saveSettings();
				$this->notifyGlobalUser( $bounceUserId, $originalEmail );
				wfDebugLog( 'BounceHandler',
					"Un-subscribed global user {$caUser->getName()} <$originalEmail> for exceeding Bounce Limit $this->bounceRecordLimit.\nProcessed Headers:\n" .
						$this->formatHeaders( $emailHeaders ) . "\nBounced Email: \n$this->emailRaw"
				);
				$stats->increment( 'bouncehandler.unsub.global' );
			}
		} else {
			// Invalidate the email-id of a local user
			$user->setEmailAuthenticationTimestamp( null );
			$user->saveSettings();
			$this->createEchoNotification( $bounceUserId, $originalEmail );
			wfDebugLog( 'BounceHandler',
				"Un-subscribed {$user->getName()} <$originalEmail> for exceeding Bounce limit $this->bounceRecordLimit.\nProcessed Headers:\n" .
					$this->formatHeaders( $emailHeaders ). "\nBounced Email: \n$this->emailRaw"
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
				function ( $v, $k ) { return "$k: $v"; },
				$emailHeaders,
				array_keys( $emailHeaders )
			)
		);
	}

}
