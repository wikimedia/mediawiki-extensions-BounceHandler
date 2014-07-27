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

	public function __construct( $wikiId, $bounceRecordPeriod, $bounceRecordLimit ) {
		$this->wikiId = $wikiId;
		$this->bounceRecordPeriod = $bounceRecordPeriod;
		$this->bounceRecordLimit = $bounceRecordLimit;
	}

	/**
	 * Perform actions on users who failed to receive emails in a given period
	 *
	 * @param string $originalEmail The email-id of the failing recipient
	 * @param string $bounceTimestamp The bounce mail timestamp
	 * @return bool
	 */
	public function handleFailingRecipient( $originalEmail, $bounceTimestamp ) {
		$unixTime = wfTimestamp();
		$bounceValidPeriod = wfTimestamp( TS_MW, $unixTime - $this->bounceRecordPeriod );
		$dbr = wfGetDB( DB_SLAVE, array(), $this->wikiId );
		$res = $dbr->selectRow( 'bounce_records',
			array( 'COUNT(*) as total_count' ),
			array( 'br_user'=> $originalEmail ),
			__METHOD__
		);
		if( $res !== false && ( $res->total_count > $this->bounceRecordLimit ) ) {
			$this->unSubscribeUser( $originalEmail );
		} else {
			wfDebugLog( 'BounceHandler',"Error fetching the count of past bounces for user $originalEmail" );
		}

		return true;
	}

	/**
	 * Function to Un-subscribe a failing recipient
	 *
	 * @param string $originalEmail
	 */
	private function unSubscribeUser( $originalEmail ) {
		//Un-subscribe the user
		$dbw = wfGetDB( DB_MASTER, array(), $this->wikiId );
		$res = $dbw->update( 'user',
			array(
				'user_email_authenticated' => null,
				'user_email_token' => null,
				'user_email_token_expires' => null
			),
			array( 'user_email' => $originalEmail ),
			__METHOD__
		);
		if ( $res ) {
			wfDebugLog( 'BounceHandler', "Un-subscribed user $originalEmail for exceeding Bounce
							Limit $this->bounceRecordLimit" );
		} else {
			wfDebugLog( 'BounceHandler', "Failed to un-subscribe the failing recipient $originalEmail" );
		}
	}

}