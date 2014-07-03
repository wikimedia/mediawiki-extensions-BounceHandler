<?php
//Maintenance script to run Bounce Handler cleanups.

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../..';
}
// Require base maintenance class
require_once( "$IP/maintenance/Maintenance.php" );

class BounceHandlerClearance extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Connect to IMAP server and take action on bounces";
		$this->addArg( "imapuser", "IMAP account Username", false );
		$this->addArg( "imappass", "IMAP account Password", false );
	}
	public function execute() {
		global $wgIMAPuser, $wgIMAPpass, $wgIMAPserver;
		$imapuser = $this->getArg( 0 );
		$imappass = $this->getArg( 1 );
		if ( !$imapuser && $wgIMAPuser === null ) {
			$this->error( "invalid IMAP username.", true );
		} else {
			$imapuser = $wgIMAPuser === null ? $imapuser : $wgIMAPuser;
		}
		if ( !$imappass && $wgIMAPpass === null ) {
			$this->error( "invalid IMAP password.", true );
		} else {
			$imappass = $wgIMAPpass === null ?  $imappass : $wgIMAPpass;
		}
		if ( $wgIMAPserver === null ) {
			$this->error( "invalid IMAP server.", true );
		}

		// Establish IMAP connection
		$conn = imap_open( $wgIMAPserver, $imapuser, $imappass );
		if ( !$conn ) {
			$this->error( imap_last_error(), 1 );
		}
		$emails = imap_search( $conn, 'NEW');
		if ( $emails ) {
			foreach ( $emails as $email_number ) {
				$failedUser = array();
				// Suppress unwanted warnings during IMAP connection
				wfSuppressWarnings();

				$header = imap_header( $conn, $email_number );

				wfRestoreWarnings();
				if ( $header )	{
					$hashedEmail = $header->to[0]->mailbox. "@" . $header->to[0]->host;
					$failedUser = self::getUserDetails( $hashedEmail );
					//Establish connection with required database
					$wikiId = $failedUser[ 'wikiId'];
					$originalEmail = $failedUser[ 'rawEmail' ];
					$bounceTimestamp = wfTimestamp( TS_MW, $header->udate );
					$dbw = wfGetDB( DB_MASTER, array(), $wikiId );
					if( is_array( $failedUser ) ) {
						$rowData = array(
						'br_user' => $originalEmail,
						'br_timestamp' => $bounceTimestamp,
						'br_reason' => $header->subject
						);
						$dbw->insert( 'bounce_records', $rowData, __METHOD__ );
					}
					self::BounceHandlerActions( $wikiId, $originalEmail, $bounceTimestamp );
				}
			}
		}

		// delete messages
		imap_expunge($conn);

		// close IMAP connection
		imap_close($conn);
	}

	/**
	 * Validate and extract user info from a given VERP address and
	 * return the failed user details, if hashes match
	 * @param string $hashedEmail The original hashed Email from bounce email
	 * @return array $failedUser The failed user details
	 * */
	protected static function getUserDetails( $hashedEmail ) {
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
	 * @param array $failedUser The failed user details
	 * @param string $rawUserId The userId of the failing recipient
	 * @return string $rawEmail The emailId of the failing recipient
	 */
	protected static function getOriginalEmail( $failedUser, $rawUserId ) {
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
	 * @param string $dbr the wiki Database pointer
	 * @param string $originalEmail email-id of the failing recipient
	 * @param string $bounceTimestamp The bounce email Timestamp
	 * @retuurn bool
	 */

	protected static function BounceHandlerActions( $wikiId, $originalEmail, $bounceTimestamp ) {
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
				//Unsubscribe the user
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
$maintClass = 'BounceHandlerClearance';
require_once( RUN_MAINTENANCE_IF_MAIN );