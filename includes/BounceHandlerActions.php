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
		$originalEmail = $failedUser['rawEmail'];
		$currentTime = wfTimestamp();
		$bounceValidPeriod = wfTimestamp( $currentTime - $this->bounceRecordPeriod );
		$dbr = wfGetDB( DB_SLAVE, array(), $this->wikiId );
		$res = $dbr->selectRow( 'bounce_records',
			array( 'COUNT(*) as total_count' ),
			array(
				'br_user'=> $originalEmail,
				'br_timestamp' >= $bounceValidPeriod
			),
			__METHOD__
		);
		if( $res !== false && ( $res->total_count > $this->bounceRecordLimit ) && $this->bounceHandlerUnconfirmUsers ) {
			$this->unSubscribeUser( $failedUser );
		} else {
			wfDebugLog( 'BounceHandler',"Error fetching the count of past bounces for user $originalEmail" );
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
		if ( class_exists( 'CentralAuthUser') ) {
			$caUser = CentralAuthUser::getInstance( $user );
			if ( $caUser->isAttached( $this->wikiId ) ) {
				$caUser->setEmailAuthenticationTimestamp( null );
				$caUser->saveSettings();
				wfDebugLog( 'BounceHandler', " Un-subscribed global user $originalEmail for exceeding Bounce
				Limit $this->bounceRecordLimit" );
			} else {
				wfDebugLog( 'BounceHandler', " $originalEmail not found attached to $this->wikiId database in the CentralAuth " );
			}
		} else {
			$res = $user->invalidateEmail();
			if ( $res ) {
				$user->saveSettings();
				wfDebugLog( 'BounceHandler', "Un-subscribed user $originalEmail for exceeding Bounce
				Limit $this->bounceRecordLimit" );
			} else {
				wfDebugLog( 'BounceHandler', "Failed to un-subscribe the failing recipient $originalEmail" );
			}
		}
	}

}
