<?php
/**
 * Actions to be done on finding out a failing recipient
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
	 * @param string $wikiId The database id of the failing recipient
	 * @param int $bounceRecordPeriod Time period for which bounce activities are considered before un-subscribing
	 * @param int $bounceRecordLimit The number of bounce allowed in the bounceRecordPeriod.
	 * @param bool $bounceHandlerUnconfirmUsers Enable/Disable user un-subscribe action
	 */
	public function __construct( $wikiId, $bounceRecordPeriod, $bounceRecordLimit, $bounceHandlerUnconfirmUsers ) {
		if ( $wikiId !== wfWikiID() ) {
			// We want to use the User class methods, which make no sense on the wrong wiki
			throw new Exception( "BounceHandlerActions constructed for a foreign wiki." );
		}

		$this->wikiId = $wikiId;
		$this->bounceRecordPeriod = $bounceRecordPeriod;
		$this->bounceRecordLimit = $bounceRecordLimit;
		$this->bounceHandlerUnconfirmUsers = $bounceHandlerUnconfirmUsers;
	}

	/**
	 * Perform actions on users who failed to receive emails in a given period
	 *
	 * @param array $failedUser The details of the failing user
	 * @return bool
	 */
	public function handleFailingRecipient( array $failedUser ) {
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
				$this->unSubscribeUser( $failedUser );
			}
		}

		return true;
	}

	/**
	 * Function to Un-subscribe a failing recipient
	 *
	 * @param array $failedUser The details of the failing user
	 */
	public function unSubscribeUser( array $failedUser ) {
		//Un-subscribe the user
		$originalEmail = $failedUser['rawEmail'];
		$bounceUserId = $failedUser['rawUserId'];

		$user = User::newFromId( $bounceUserId );
		// Handle the central account email status (if applicable)
		if ( class_exists( 'CentralAuthUser') ) {
			$caUser = CentralAuthUser::getInstance( $user );
			if ( $caUser->isAttached( $this->wikiId ) ) {
				$caUser->setEmailAuthenticationTimestamp( null );
				$caUser->saveSettings();
				wfDebugLog( 'BounceHandler',
					"Un-subscribed global user $originalEmail for exceeding Bounce Limit $this->bounceRecordLimit"
				);
			}
		}
		// Handle the local account email status
		$this->unConfirmUserEmail( $user );
	}

	/**
	 * Perform the un-subscribe email action on a given bounced user
	 *
	 * @param User $user
	 */
	public function unConfirmUserEmail( User $user ) {
		$userEmail = $user->getEmail();
		$res = $user->invalidateEmail();
		$user->saveSettings();
		if ( $res ) {
			wfDebugLog( 'BounceHandler',
				"Un-subscribed $userEmail for exceeding Bounce limit $this->bounceRecordLimit"
			);
		} else {
			wfDebugLog( 'BounceHandler', "Failed to un-subscribe the failing recipient $userEmail" );
		}
	}

}
