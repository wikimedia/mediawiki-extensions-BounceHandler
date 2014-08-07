<?php
abstract class ProcessBounceEmails {
	/**
	 * Recieves an email from the Job queue and process it
	 *
	 * @param string $email
	 */
	abstract public function processEmail( $email );

	/**
	 * Generates BounceProcessor checking existence of external libraries
	 *
	 * @return BounceProcessor
	 */
	public static function getProcessor() {
		if ( !class_exists( 'PlancakeEmailParser' ) ) {
		 	$bounceProcessor = new ProcessBounceWithRegex();
		} else {
			$bounceProcessor = new ProcessBounceWithPlancake();
		}
		return $bounceProcessor;
	}
	/**
	 * Process received bounce emails from Job Queue
	 * @param array $emailHeaders
	 */
	public function processBounceHeaders( $emailHeaders ) {
		global $wgBounceRecordPeriod, $wgBounceRecordLimit, $wgUnrecognizedBounceNotify, $wgPasswordSender;
		$failedUser = array();
		$to = $emailHeaders[ 'to' ];
		$subject = $emailHeaders[ 'subject' ];
		$emailDate = $emailHeaders[ 'date' ];
		$permanentFailure = $emailHeaders[ 'x-failed-recipients' ];

		// Get original failed user email and wiki details
		$failedUser = self::getUserDetails( $to );
		$wikiId = $failedUser[ 'wikiId' ];
		$originalEmail = $failedUser[ 'rawEmail' ];
		$bounceTimestamp = wfTimestamp( TS_MW, $emailDate );
		$dbw = wfGetDB( DB_MASTER, array(), $wikiId );
		if( is_array( $failedUser ) ) {
			$rowData = array(
			'br_user' => $originalEmail,
			'br_timestamp' => $bounceTimestamp,
			'br_reason' => $subject
			);
			$dbw->insert( 'bounce_records', $rowData, __METHOD__ );
		}
		$takeBounceActions = new BounceHandlerActions( $wikiId, $wgBounceRecordPeriod, $wgBounceRecordLimit );
		$takeBounceActions->handleFailingRecipient( $originalEmail, $bounceTimestamp );

	}

	/**
	 * Validate and extract user info from a given VERP address and
	 *
	 * return the failed user details, if hashes match
	 * @param string $hashedEmail The original hashed Email from bounce email
	 * @return array $failedUser The failed user details
	 * */
	public function getUserDetails( $hashedEmail ) {
		global $wgVERPalgorithm, $wgVERPsecret, $wgVERPAcceptTime;
		$currentTime = wfTimestamp();
		$failedUser = array();
		preg_match( '~(.*?)@~', $hashedEmail, $hashedPart );
		$hashedVERPPart = explode( '-', $hashedPart[1] );
		$hashedData = $hashedVERPPart[0]. '-'. $hashedVERPPart[1]. '-'. $hashedVERPPart[2];
		$emailTime = base_convert( $hashedVERPPart[2], 36, 10 );
		if ( hash_hmac( $wgVERPalgorithm, $hashedData, $wgVERPsecret ) === $hashedVERPPart[3] &&
		$currentTime - $emailTime < $wgVERPAcceptTime ) {
			$failedUser[ 'wikiId' ] = str_replace( '.', '-', $hashedVERPPart[0] );
			$failedUser[ 'rawUserId' ] = base_convert( $hashedVERPPart[1], 36, 10 );
			$failedUser[ 'rawEmail' ] = self::getOriginalEmail( $failedUser );
			return $failedUser;
		} else {
			wfDebugLog( 'BounceHandler',
			"Error: Hash validation failed. Expected hash of $hashedData, got $hashedVERPPart[3]." );
		}
	}

	/**
	 * Generate Original Email Id from a hashed emailId
	 *
	 * @param array $failedUser The failed user details
	 * @return string $rawEmail The emailId of the failing recipient
	 */
	public function getOriginalEmail( $failedUser ) {
		// In multiple wiki deployed case, the $wikiId can help correctly identify the user after looking up in
		// the required database.
		$wikiId = $failedUser[ 'wikiId' ];
		$rawUserId = $failedUser[ 'rawUserId' ];
		$dbr = wfGetDB( DB_SLAVE, array(), $wikiId );
		$res = $dbr->selectRow(
			'user',
			array( 'user_email' ),
			array(
			'user_id' => $rawUserId,
		),
			__METHOD__
			);
		if( $res !== false ) {
			$rawEmail = $res->user_email;
			return $rawEmail;
		} else {
			wfDebugLog( 'BounceHandler',"Error fetching email_id of user_id $rawUserId from Database $wikiId." );
		}
	}

	/**
	 * Check for a permanent failure
	 *
	 * @param array $emailHeaders
	 * @return bool
	 */
	protected function checkPermanentFailure( $emailHeaders ) {
		$permanentFailure = $emailHeaders[ 'x-failed-recipients' ];
		if ( $permanentFailure == null ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Handle unrecognized bounces by notifying wiki admins with the full email
	 *
	 * @param string $email
	 * @param string $to
	 */
	public function handleUnrecognizedBounces( $email, $to ) {
		global $wgUnrecognizedBounceNotify, $wgPasswordSender;

		wfDebugLog( 'BounceHandler', "Received temporary bounce from $to" );
		$handleUnIdentifiedBounce = new ProcessUnRecognizedBounces( $wgUnrecognizedBounceNotify, $wgPasswordSender );
		$handleUnIdentifiedBounce->processUnRecognizedBounces( $email );
	}

}