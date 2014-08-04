<?php
class ProcessBounceWithRegex extends ProcessBounceEmails {
	/**
	 * Process bounecs with common regex functions
	 *
	 * @param string $email
	 */
	public function processEmail( $email ) {
		global $wgUnrecognizedBounceNotify, $wgPasswordSender;
		$emailHeaders = array();
		$emailLines = explode( "\n", $email );
		foreach ( $emailLines as $emailLine ) {
			if ( preg_match( "/^To: (.*)/", $emailLine, $toMatch ) ) {
				$emailHeaders[ 'to' ] = $toMatch[1];
			}
			if ( preg_match( "/^Subject: (.*)/", $emailLine, $subjectMatch ) ) {
				$emailHeaders[ 'subject' ] = $subjectMatch[1];
			}
			if ( preg_match( "/^Date: (.*)/", $emailLine, $dateMatch ) ) {
				$emailHeaders[ 'date' ] = $dateMatch[1];
			}
			if ( preg_match( "/^X-Failed-Recipients: (.*)/", $emailLine, $failureMatch ) ) {
				$emailHeaders[ 'x-failed-recipients' ] = $failureMatch;
			}
			if ( trim( $emailLine ) == "" ) {
				// Empty line denotes that the header part is finished
				break;
			}
		}
		// The bounceHandler needs to respond only to permanent failures. Permanently failures will generate
		// bounces with a 'X-Failed-Recipients' header.
		$permanentFailure = $emailHeaders[ 'x-failed-recipients' ];
		$to = $emailHeaders[ 'to' ];
		if ( $permanentFailure == null ) {
			$this->handleUnrecognizedBounces( $email, $to );
		} else {
			$this->processBounceHeaders( $emailHeaders );
		}
	}
}