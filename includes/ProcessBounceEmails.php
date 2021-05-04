<?php

namespace MediaWiki\Extension\BounceHandler;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * Class ProcessBounceEmails
 *
 * Methods to process a bounce email
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
abstract class ProcessBounceEmails {
	/**
	 * Receive an email from the job queue and process it
	 *
	 * @param string $email
	 */
	abstract public function handleBounce( $email );

	/**
	 * Generates bounce email processor
	 *
	 * @return ProcessBounceWithRegex
	 */
	public static function getProcessor() {
		return new ProcessBounceWithRegex();
	}

	/**
	 * Process bounce email
	 *
	 * @param array $emailHeaders
	 * @param string $emailRaw
	 *
	 * @return bool
	 */
	public function processEmail( $emailHeaders, $emailRaw ) {
		// The bounceHandler needs to respond only to permanent failures.
		$isPermanentFailure = $this->checkPermanentFailure( $emailHeaders );
		if ( $isPermanentFailure ) {
			return $this->processBounceHeaders( $emailHeaders, $emailRaw );
		}

		return false;
	}

	/**
	 * Process received bounce emails from Job Queue
	 *
	 * @param array $emailHeaders
	 * @param string $emailRaw
	 *
	 * @return bool
	 */
	public function processBounceHeaders( $emailHeaders, $emailRaw ) {
		global $wgBounceRecordPeriod, $wgBounceRecordLimit,
			$wgBounceHandlerUnconfirmUsers, $wgBounceRecordMaxAge;

		$to = $emailHeaders['to'];
		$subject = $emailHeaders['subject'];

		// Get original failed user email and wiki details
		$failedUser = $to ? $this->getUserDetails( $to ) : false;
		if ( is_array( $failedUser ) && isset( $failedUser['wikiId'] )
			&& isset( $failedUser['rawEmail'] ) && isset( $failedUser[ 'bounceTime' ] )
		) {
			$wikiId = $failedUser['wikiId'];
			$originalEmail = $failedUser['rawEmail'];
			$bounceTimestamp = $failedUser['bounceTime'];
			$dbw = self::getBounceRecordDB( DB_PRIMARY, $wikiId );

			$rowData = [
				'br_user_email' => $originalEmail,
				'br_timestamp' => $dbw->timestamp( $bounceTimestamp ),
				'br_reason' => $subject
			];
			$dbw->insert( 'bounce_records', $rowData, __METHOD__ );
			\MediaWiki\MediaWikiServices::getInstance()
				->getStatsdDataFactory()->increment( 'bouncehandler.bounces' );

			if ( $wgBounceRecordMaxAge ) {
				$pruneOldRecords = new PruneOldBounceRecords( $wgBounceRecordMaxAge );
				$pruneOldRecords->pruneOldRecords( $wikiId );
			}

			$takeBounceActions = new BounceHandlerActions(
				$wikiId,
				$wgBounceRecordPeriod,
				$wgBounceRecordLimit,
				$wgBounceHandlerUnconfirmUsers,
				$emailRaw
			);
			$takeBounceActions->handleFailingRecipient( $failedUser, $emailHeaders );
			return true;
		} else {
			wfDebugLog( 'BounceHandler',
				"Error: Failed to extract user details from verp address $to"
			);
			return false;
		}
	}

	/**
	 * Validate and extract user info from a given VERP address and
	 *
	 * return the failed user details, if hashes match
	 * @param string $hashedEmail The original hashed Email from bounce email
	 * @return array $failedUser The failed user details
	 */
	public function getUserDetails( $hashedEmail ) {
		global $wgVERPalgorithm, $wgVERPsecret, $wgVERPAcceptTime;

		$failedUser = [];

		$currentTime = (int)wfTimestamp();
		preg_match( '~(.*?)@~', $hashedEmail, $hashedPart );
		if ( !isset( $hashedPart[1] ) ) {
			wfDebugLog( 'BounceHandler',
				"Error: The received address: $hashedEmail does not match the VERP pattern."
			);
			return [];
		}
		$hashedVERPPart = explode( '-', $hashedPart[1] );
		// This would ensure that indexes 0 - 4 in $hashedVERPPart is set
		if ( isset( $hashedVERPPart[4] ) ) {
			$hashedData = $hashedVERPPart[0] . '-' . $hashedVERPPart[1] .
				'-' . $hashedVERPPart[2] . '-' . $hashedVERPPart[3];
		} else {
			wfDebugLog(
				'BounceHandler',
				"Error: Received malformed VERP address: $hashedPart[1], cannot extract details."
			);
			return [];
		}
		$bounceTime = (int)base_convert( $hashedVERPPart[3], 36, 10 );
		// Check if the VERP hash is valid
		if ( base64_encode(
				substr( hash_hmac( $wgVERPalgorithm, $hashedData, $wgVERPsecret, true ), 0, 12 )
			) === $hashedVERPPart[4]
			&& $currentTime - $bounceTime < $wgVERPAcceptTime
		) {
			$failedUser['wikiId'] = str_replace( '.', '-', $hashedVERPPart[1] );
			$failedUser['rawUserId'] = base_convert( $hashedVERPPart[2], 36, 10 );
			$failedEmail = $this->getOriginalEmail( $failedUser );
			$failedUser['rawEmail'] = $failedEmail ? : null;
			$failedUser['bounceTime'] = wfTimestamp( TS_MW, $bounceTime );
		} else {
			wfDebugLog( 'BounceHandler',
				"Error: Hash validation failed. Expected hash of $hashedData, got $hashedVERPPart[4]."
			);
		}

		return $failedUser;
	}

	/**
	 * Generate Original Email Id from a hashed emailId
	 *
	 * @param array $failedUser The failed user details
	 * @return string|false $rawEmail The emailId of the failing recipient
	 */
	public function getOriginalEmail( $failedUser ) {
		// In multiple wiki deployed case, the $wikiId can help correctly
		// identify the user after looking up in the required database.
		$wikiId = $failedUser['wikiId'];
		$rawUserId = $failedUser['rawUserId'];
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB( $wikiId );
		$dbr = $lb->getConnection( DB_REPLICA, [], $wikiId );

		$res = $dbr->selectRow(
			'user',
			[ 'user_email' ],
			[
				'user_id' => $rawUserId,
			],
			__METHOD__
		);
		$lb->reuseConnection( $dbr );
		if ( $res !== false ) {
			return $res->user_email;
		}

		wfDebugLog( 'BounceHandler',
			"Error fetching email_id of user_id $rawUserId from Database $wikiId."
		);
		return false;
	}

	/**
	 * Check for a permanent failure
	 *
	 * @param array $emailHeaders
	 * @return bool
	 */
	protected function checkPermanentFailure( $emailHeaders ) {
		if ( isset( $emailHeaders['status'] ) ) {
			$status = explode( '.', $emailHeaders['status'] );
			// According to RFC1893 status codes starting with 5 mean Permanent Failures
			return $status[0] == 5;
		} elseif ( isset( $emailHeaders['smtp-code'] ) ) {
			return $emailHeaders['smtp-code'] >= 500;
		} elseif ( isset( $emailHeaders['x-failed-recipients'] ) ) {
			// If not status code was found, let's presume that the presence of
			// X-Failed-Recipients means permanent failure
			return true;
		} else {
			return false;
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
		$handleUnIdentifiedBounce = new ProcessUnRecognizedBounces(
			$wgUnrecognizedBounceNotify, $wgPasswordSender );
		$handleUnIdentifiedBounce->processUnRecognizedBounces( $email );
	}

	/**
	 * Get a lazy connection to the bounce table
	 *
	 * @param int $index DB_PRIMARY/DB_REPLICA
	 * @param string $wiki The DB that the bounced email was sent from
	 * @return IDatabase
	 */
	public static function getBounceRecordDB( $index, $wiki ) {
		global $wgBounceHandlerCluster, $wgBounceHandlerSharedDB;

		$wiki = $wgBounceHandlerSharedDB ?: $wiki;

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $wgBounceHandlerCluster
			? $lbFactory->getExternalLB( $wgBounceHandlerCluster )
			: $lbFactory->getMainLB( $wiki );

		return $lb->getConnectionRef( $index, [], $wiki );
	}
}
