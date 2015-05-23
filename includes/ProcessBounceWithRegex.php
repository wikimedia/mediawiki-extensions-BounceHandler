<?php
/**
 * Class ProcessBounceWithRegex
 *
 * Extract email headers of a bounce email using various regex functions
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class ProcessBounceWithRegex extends ProcessBounceEmails {
	/**
	 * Process email using common regex functions
	 *
	 * @param string $email
	 */
	public function handleBounce( $email ) {
		$emailHeaders = $this->extractHeaders( $email );
		$to = $emailHeaders['to'];

		$processEmail = $this->processEmail( $emailHeaders );
		if ( !$processEmail ){
			$this->handleUnrecognizedBounces( $email, $to );
		}
	}

	/**
	 * Parse the single part of delivery status message
	 *
	 * @param string[] $partLines array of strings that contain single lines of the email part
	 * @return string|null String that contains the status code or null if it wasn't found
	 */
	private function parseMessagePart( $partLines ) {
		foreach ( $partLines as $partLine ) {
			if ( preg_match( '/^Content-Type: (.+)/', $partLine, $contentTypeMatch ) ) {
				if ( $contentTypeMatch[1] != 'message/delivery-status' ) {
					break;
				}
			}
			if ( preg_match( '/^Status: (\d\.\d{1,3}\.\d{1,3})/', $partLine, $statusMatch ) ) {
				return $statusMatch[1];
			}
		}
		return null;
	}

	/**
	 * Parse the multi-part delivery status message (DSN) according to RFC3464
	 *
	 * @param string[] $emailLines array of strings that contain single lines of the email
	 * @return string|null String that contains the status code or null if it wasn't found
	 */
	private function parseDeliveryStatusMessage( $emailLines ) {
		for ( $i = 0; $i < count( $emailLines ) - 1; ++$i ) {
			$line = $emailLines[$i] . "\n" . $emailLines[$i + 1];
			if ( preg_match( '/Content-Type: multipart\/report;\s*report-type=delivery-status;' .
				'\s*boundary="(.+?)"/', $line, $contentTypeMatch ) ) {
				$partIndices = array_keys( $emailLines, "--$contentTypeMatch[1]" );
				foreach ( $partIndices as $index ) {
					$result = $this->parseMessagePart( array_slice( $emailLines, $index ) );
					if ( !is_null( $result ) ) {
						return $result;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Extract headers from the received bounce
	 *
	 * @param string $email
	 * @return array $emailHeaders
	 */
	public function extractHeaders( $email ) {
		$emailHeaders = array();
		$emailLines = preg_split( "/(\r?\n|\r)/", $email );
		foreach ( $emailLines as $emailLine ) {
			if ( preg_match( "/^To: (.*)/", $emailLine, $toMatch ) ) {
				$emailHeaders['to'] = $toMatch[1];
			}
			if ( preg_match( "/^Subject: (.*)/", $emailLine, $subjectMatch ) ) {
				$emailHeaders['subject'] = $subjectMatch[1];
			}
			if ( preg_match( "/^Date: (.*)/", $emailLine, $dateMatch ) ) {
				$emailHeaders['date'] = $dateMatch[1];
			}
			if ( preg_match( "/^X-Failed-Recipients: (.*)/", $emailLine, $failureMatch ) ) {
				$emailHeaders['x-failed-recipients'] = $failureMatch[1];
			}
			if ( trim( $emailLine ) == "" ) {
				// Empty line denotes that the header part is finished
				break;
			}
		}
		$status = $this->parseDeliveryStatusMessage( $emailLines );
		if ( !is_null( $status ) ) {
			$emailHeaders['status'] = $status;
		}

		// If the x-failed-recipient header or status code was not found, we should fallback to a heuristic scan
		// of the message for a SMTP status code
		if ( !isset( $emailHeaders['status'] ) && !isset( $emailHeaders['x-failed-recipients'] ) ) {
			foreach ( $emailLines as $emailLine ) {
				if ( preg_match( '/\s+(?:(?P<smtp>[1-5]\d{2}).)?' .
					'(?P<status>[245]\.\d{1,3}\.\d{1,3})?\b/', $emailLine, $statusMatch ) ) {
					if ( isset( $statusMatch['smtp'] ) ) {
						$emailHeaders['smtp-code'] = $statusMatch['smtp'];
						break;
					}
					if ( isset( $statusMatch['status'] ) ) {
						$emailHeaders['status'] = $statusMatch['status'];
						break;
					}
				}
			}
		}
		return $emailHeaders;
	}

}
