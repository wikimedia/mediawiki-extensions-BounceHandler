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
			if ( trim( $emailLine ) == "" ) {
				// Empty line denotes that the header part is finished
				break;
			}
		}
		return $emailHeaders;
	}

}
