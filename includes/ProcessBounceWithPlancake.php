<?php
class ProcessBounceWithPlancake extends ProcessBounceEmails {
	/**
	 * Process bounce email using the Plancake mail parser library
	 *
	 * @param string $email
	 */
	public function processEmail( $email ) {
		global $wgUnrecognizedBounceNotify, $wgPasswordSender;
		$decoder = new PlancakeEmailParser( $email );

		$emailHeaders[ 'to' ] = $decoder->getHeader( 'To' );
		$emailHeaders[ 'subject' ] = $decoder->getSubject();
		$emailHeaders[ 'date' ] = $decoder->getHeader( 'Date' );
		$emailHeaders[ 'x-failed-recipients' ] = $decoder->getHeader( 'X-Failed-Recipients' );

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