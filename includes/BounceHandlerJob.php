<?php

namespace MediaWiki\Extension\BounceHandler;

use MediaWiki\JobQueue\Job;
use MediaWiki\Title\Title;

/**
 * Class BounceHandlerJob
 *
 * Job Queue class to receive a POST request
 *
 * @ingroup JobQueue
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class BounceHandlerJob extends Job {
	/** @inheritDoc */
	public function __construct( Title $title, array $params ) {
		parent::__construct( 'BounceHandlerJob', $title, $params );
	}

	/** @inheritDoc */
	public function run() {
		$email = $this->params['email'];

		if ( $email ) {
			$bounceProcessor = ProcessBounceEmails::getProcessor();
			$bounceProcessor->handleBounce( $email );
		}

		return true;
	}
}
