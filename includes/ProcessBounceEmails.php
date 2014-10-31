<?php
abstract class ProcessBounceEmails {
	/**
	 * Recieves an email from the Job queue and process it
	 *
	 * @param string $email
	 */
	abstract public function handleBounce( $email );

	/**
	 * Generates ProcessBounceEmails checking existence of external libraries
	 *
	 * @return ProcessBounceEmails
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
	 * Process bounce email
	 *
	 * @param array $emailHeaders
	 * @return bool
	 */
	public function processEmail( $emailHeaders ) {
		// The bounceHandler needs to respond only to permanent failures.
		$isPermanentFailure = $this->checkPermanentFailure( $emailHeaders );
		if ( $isPermanentFailure ) {
			return $this->processBounceHeaders( $emailHeaders );
		}

		return false;
	}

	/**
	 * Process received bounce emails from Job Queue
	 *
	 * @param array $emailHeaders
	 * @return bool
	 */
	public function processBounceHeaders( $emailHeaders ) {
		global $wgBounceRecordPeriod, $wgBounceRecordLimit, $wgBounceHandlerUnconfirmUsers;
		$to = $emailHeaders['to'];
		$subject = $emailHeaders['subject'];

		// Get original failed user email and wiki details
		$failedUser = $this->getUserDetails( $to );
		if( is_array( $failedUser ) && isset( $failedUser['wikiId'] ) && isset( $failedUser['rawEmail'] )
		&& isset( $failedUser[ 'bounceTime' ] ) ) {
			$wikiId = $failedUser['wikiId'];
			$originalEmail = $failedUser['rawEmail'];
			$bounceTimestamp= $failedUser['bounceTime'];
			$dbw = wfGetDB( DB_MASTER, array(), $wikiId );

			$rowData = array(
				'br_user_email' => $originalEmail,
				'br_timestamp' => $bounceTimestamp,
				'br_reason' => $subject
			);
			$dbw->insert( 'bounce_records', $rowData, __METHOD__ );
			wfGetLB( $wikiId )->reuseConnection( $dbw );

			$takeBounceActions = new BounceHandlerActions( $wikiId, $wgBounceRecordPeriod, $wgBounceRecordLimit,
			$wgBounceHandlerUnconfirmUsers );
			$takeBounceActions->handleFailingRecipient( $failedUser );
			return true;
		} else {
			wfDebugLog( 'BounceHandler', "Error: Failed to extract user details from verp address $to " );
			return false;
		}
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
		$hashedData = $hashedVERPPart[0]. '-'. $hashedVERPPart[1]. '-'. $hashedVERPPart[2]. '-'. $hashedVERPPart[3];
		$bounceTime = base_convert( $hashedVERPPart[3], 36, 10 );
		if ( base64_encode( substr( hash_hmac( $wgVERPalgorithm, $hashedData, $wgVERPsecret, true ), 0, 12 ) ) === $hashedVERPPart[4]
		&& $currentTime - $bounceTime < $wgVERPAcceptTime ) {
			$failedUser['wikiId'] = str_replace( '.', '-', $hashedVERPPart[1] );
			$failedUser['rawUserId'] = base_convert( $hashedVERPPart[2], 36, 10 );
			$failedEmail = self::getOriginalEmail( $failedUser );
			$failedUser['rawEmail'] = $failedEmail ? : null;
			$failedUser['bounceTime'] = $bounceTime;
		} else {
			wfDebugLog( 'BounceHandler',
			"Error: Hash validation failed. Expected hash of $hashedData, got $hashedVERPPart[3]." );
		}
		return $failedUser;
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
		$wikiId = $failedUser['wikiId'];
		$rawUserId = $failedUser['rawUserId'];
		$dbr = wfGetDB( DB_SLAVE, array(), $wikiId );
		$res = $dbr->selectRow(
			'user',
			array( 'user_email' ),
			array(
				'user_id' => $rawUserId,
			),
			__METHOD__
		);
		wfGetLB( $wikiId )->reuseConnection( $dbr );
		if( $res !== false ) {
			$rawEmail = $res->user_email;
			return $rawEmail;
		}

		wfDebugLog( 'BounceHandler',"Error fetching email_id of user_id $rawUserId from Database $wikiId." );
		return false;
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
