<?php
class ProcessBounceWithPlancake extends ProcessBounceEmails {
	/**
	 * Process bounce email using the Plancake mail parser library
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
	 * Extract headers from the received bounce email using Plancake mail parser
	 *
	 * @param string $email
	 * @return array $emailHeaders.
	 */
	public function extractHeaders( $email ) {
		$emailHeaders = array();
		$decoder = new PlancakeEmailParser( $email );

		$emailHeaders['to'] = $decoder->getHeader( 'To' );
		$emailHeaders['subject'] = $decoder->getSubject();
		$emailHeaders['date'] = $decoder->getHeader( 'Date' );
		$emailHeaders['x-failed-recipients'] = $decoder->getHeader( 'X-Failed-Recipients' );

		return $emailHeaders;
	}

}