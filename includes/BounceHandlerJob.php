<?php
/**
 * Job Queue class to receive a POST request
 *
 * @file
 * @ingroup JobQueue
 * @author Tony Thomas
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class BounceHandlerJob extends Job {
	public function __construct( Title $title, array $params ) {
		parent::__construct( 'BounceHandlerJob', $title, $params );
	}

	public function run() {
		$email = $this->params['email'];

		if ( $email ) {
			$bounceProcessor = ProcessBounceEmails::getProcessor();
			$bounceProcessor->handleBounce( $email );
		}

		return true;
	}
}
