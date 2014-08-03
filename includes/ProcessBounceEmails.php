<?php
// Check and include Plancake email parser library
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
}
class ProcessBounceEmails {
	/**
	 * Process received bounce emails from Job Queue
	 * @param string $email
	 * @return bool
	 */
	public function processEmail( $email ) {
		global $wgBounceRecordPeriod, $wgBounceRecordLimit, $wgUnrecognizedBounceNotify, $wgPasswordSender;
		$emailHeaders = array();
		$failedUser = array();
		if ( !class_exists( 'PlancakeEmailParser' ) ) {
			wfDebugLog( 'BounceHandler',
			" Plancake email parser library is not installed. Falling back to self parsing the email." );
			// Extract headers from raw email
			$emailHeaders  = self::getHeaders( $email );
		} else {
			// Extract headers using the Plancake library
			$decoder = new PlancakeEmailParser( $email );

			$emailHeaders[ 'to' ] = $decoder->getHeader( 'To' );
			$emailHeaders[ 'subject' ] = $decoder->getSubject();
			$emailHeaders[ 'date' ] = $decoder->getHeader( 'Date' );
			$emailHeaders[ 'x-failed-recipients' ] = $decoder->getHeader( 'X-Failed-Recipients' );
		}
		$to = $emailHeaders[ 'to' ];
		$subject = $emailHeaders[ 'subject' ];
		$emailDate = $emailHeaders[ 'date' ];
		$permanentFailure = $emailHeaders[ 'x-failed-recipients' ];
		// The bounceHandler needs to respond only to permanent failures. Permanently failures will generate
		// bounces with a 'X-Failed-Recipients' header.
		if ( $permanentFailure !== null ) {
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
		} else {
			wfDebugLog( 'BounceHandler', "Received temporary bounce from $to" );
			$handleUnIdentifiedBounce = new ProcessUnRecognizedBounces( $wgUnrecognizedBounceNotify, $wgPasswordSender );
			$handleUnIdentifiedBounce->processUnRecognizedBounces( $email );
		}

	}

	/**
	 * Extract the required headers from the received email
	 *
	 * @param $email
	 * @return string
	 */
	public function getHeaders( $email ) {
		$headers = array();
		$emailLines = explode( "\n", $email );
		foreach ( $emailLines as $emailLine ) {
			if ( preg_match( "/^To: (.*)/", $emailLine, $toMatch ) ) {
				$headers[ 'to' ] = $toMatch[1];
			}
			if ( preg_match( "/^Subject: (.*)/", $emailLine, $subjectMatch ) ) {
				$headers[ 'subject' ] = $subjectMatch[1];
			}
			if ( preg_match( "/^Date: (.*)/", $emailLine, $dateMatch ) ) {
				$headers[ 'date' ] = $dateMatch[1];
			}
			if ( preg_match( "/^X-Failed-Recipients: (.*)/", $emailLine, $failureMatch ) ) {
				$headers[ 'x-failed-recipients' ] = $failureMatch;
			}
			if ( trim( $emailLine ) == "" ) {
				// Empty line denotes that the header part is finished
				break;
			}
		}

		return $headers;
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
			$rawUserId = base_convert( $hashedVERPPart[1], 36, 10 );
			$failedUser[ 'rawEmail' ] = self::getOriginalEmail( $failedUser, $rawUserId );
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
	 * @param string $rawUserId The userId of the failing recipient
	 * @return string $rawEmail The emailId of the failing recipient
	 */
	public function getOriginalEmail( $failedUser, $rawUserId ) {
		// In multiple wiki deployed case, the $wikiId can help correctly identify the user after looking up in
		// the required database.
		$wikiId = $failedUser[ 'wikiId' ];
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

}