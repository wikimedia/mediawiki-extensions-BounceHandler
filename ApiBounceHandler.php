<?php
/**
 * API to handle e-mail bounces
 *
 */
class ApiBounceHandler extends ApiBase {
	public function execute() {
		global $wgBounceHandlerInternalIPs;
		$requestIP = $this->getRequest()->getIP();
		$inRangeIP = false;
		foreach( $wgBounceHandlerInternalIPs as $BounceHandlerInternalIPs ) {
			if ( IP::isInRange( $requestIP, $BounceHandlerInternalIPs ) ) {
				$inRangeIP = true;
				break;
			}
		}
		if ( !$inRangeIP ) {
			wfDebugLog( 'BounceHandler', "POST received from restricted IP $requestIP" );
			$this->dieUsage( 'This API module is for internal use only.', 'invalid-ip' );
		}

		$email = $this->getMain()->getVal( 'email' );
		$emailHeaders = array();
		$failedUser = array();

		// Extract headers from raw email
		$emailHeaders  = self::getHeaders( $email );
		// Extract required header fields
		$to = $emailHeaders[ 'to' ];
		$subject = $emailHeaders[ 'subject' ];
		$emailDate = $emailHeaders[ 'date' ];
		$permanentFailure = $emailHeaders[ 'permanentFailure'];

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
			self::BounceHandlerActions( $wikiId, $originalEmail, $bounceTimestamp );
			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				array ( 'result' => 'success', 'user' => $originalEmail, 'status' => 'recorded' )
			);
		} else {
			wfDebugLog( 'BounceHandler', "Received temporary bounce from $to" );
			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				array( 'result' => 'failure' , 'user' => $to, 'status' => 'invalid bounce' )
			);
		}
	}

	/**
	 * Extract the required headers from the received email
	 *
	 * @param $email
	 * @return string
	 */
	protected function getHeaders( $email ) {
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
				$headers[ 'permanentFailure' ] = $failureMatch;
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
	protected function getUserDetails( $hashedEmail ) {
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
	 * @param string $rawUserId The userId of the failing recipient
	 * @return string $rawEmail The emailId of the failing recipient
	 */
	protected function getOriginalEmail( $failedUser, $rawUserId ) {
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
		} else {
			wfDebugLog( 'BounceHandler',"Error fetching email_id of user_id $rawUserId from Database $wikiId." );
		}

		return $rawEmail;
	}

	/**
	 * Perform actions on users who failed to receive emails in a given period
	 *
	 * @param string $wikiId The database id of the failing recipient
	 * @param string $originalEmail The email-id of the failing recipient
	 * @param string $bounceTimestamp The bounce mail timestamp
	 * @return bool
	 */
	public static function BounceHandlerActions( $wikiId, $originalEmail, $bounceTimestamp ) {
		global $wgBounceRecordPeriod, $wgBounceRecordLimit;
		$unixTime = wfTimestamp();
		$bounceValidPeriod = wfTimestamp( TS_MW, $unixTime - $wgBounceRecordPeriod );
		$dbr = wfGetDB( DB_SLAVE, array(), $wikiId );
		$res = $dbr->selectRow( 'bounce_records',
			array(
				'COUNT(*) as total_count'
			),
			array(
				'br_user'=> $originalEmail
			),
			__METHOD__
		);
		if( $res !== false ) {
			if ( $res->total_count > $wgBounceRecordLimit ) {
				//Un-subscribe the user
				$dbw = wfGetDB( DB_MASTER, array(), $wikiId );
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
							Limit $wgBounceRecordLimit" );
				} else {
					wfDebugLog( 'BounceHandler', "Failed to un-subscribe the failing recipient $originalEmail" );
				}
			}
		} else {
			wfDebugLog( 'BounceHandler',"Error fetching the count of past bounces for user $originalEmail" );
		}

		return true;
	}

}