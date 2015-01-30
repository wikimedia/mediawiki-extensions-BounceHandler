<?php
/**
 * Class ProcessBounceWithPlancake
 *
 * Extract email headers of a bounce email with the Plancake email parser library
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
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
		$emailHeaders['date'] = $decoder->getHeader( 'Date' );
		$emailHeaders['x-failed-recipients'] = $decoder->getHeader( 'X-Failed-Recipients' );
		try {
			$emailHeaders['subject'] = $decoder->getSubject();
		} catch( Exception $e ) {
			wfDebugLog( 'BounceHandler', "Plancake Mail Parser: Couldn't parse the bounce email subject
			header, got exception {$e->getCode()}" );
		}

		return $emailHeaders;
	}

}
