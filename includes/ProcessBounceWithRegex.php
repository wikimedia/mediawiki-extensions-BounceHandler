<?php
class ProcessBounceWithRegex extends ProcessBounceEmails {
	/**
	 * Process bounces with common regex functions
	 *
	 * @param string $email
	 */
	public function processEmail( $email ) {
		$emailHeaders = $this->extractHeaders( $email );

		// The bounceHandler needs to respond only to permanent failures.
		$isPermanentFailure = $this->checkPermanentFailure( $emailHeaders );
		if ( $isPermanentFailure ) {
			$this->processBounceHeaders( $emailHeaders );
		} else {
			$to = $emailHeaders[ 'to' ];
			$this->handleUnrecognizedBounces( $email, $to );
		}
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
				$emailHeaders[ 'to' ] = $toMatch[1];
			}
			if ( preg_match( "/^Subject: (.*)/", $emailLine, $subjectMatch ) ) {
				$emailHeaders[ 'subject' ] = $subjectMatch[1];
			}
			if ( preg_match( "/^Date: (.*)/", $emailLine, $dateMatch ) ) {
				$emailHeaders[ 'date' ] = $dateMatch[1];
			}
			if ( preg_match( "/^X-Failed-Recipients: (.*)/", $emailLine, $failureMatch ) ) {
				$emailHeaders[ 'x-failed-recipients' ] = $failureMatch[1];
			}
		}
		return $emailHeaders;
	}

}