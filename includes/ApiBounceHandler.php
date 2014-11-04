<?php
/**
 * API to handle e-mail bounces
 *
 */
class ApiBounceHandler extends ApiBase {
	public function execute() {
		global $wgBounceHandlerInternalIPs;
		$requestIP = $this->getRequest()->getIP();
		$inRangeIP = false;
		foreach( $wgBounceHandlerInternalIPs as $BounceHandlerInternalIPs ) {
			if ( IP::isInRange( $requestIP, $BounceHandlerInternalIPs ) ) {
				$inRangeIP = true;
				break;
			}
		}
		if ( !$inRangeIP ) {
			wfDebugLog( 'BounceHandler', "POST received from restricted IP $requestIP" );
			$this->dieUsage( 'This API module is for internal use only.', 'invalid-ip' );
		}

		$params = $this->extractRequestParams();

		$title = Title::newFromText( 'BounceHandler Job' );
		$job = new BounceHandlerJob( $title, $params );
		JobQueueGroup::singleton()->push( $job );

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			array ( 'submitted' => 'job' )
		);

		return true;
	}

	/**
	 * Mark the API as internal
	 *
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return array(
			'email' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	public function getExamplesMessages() {
		return array(
			'action=bouncehandler&email=This%20is%20a%20test%20email'
				=> 'apihelp-bouncehandler-example-1'
		);
	}

	public function getHelpUrls() {
		return "https://www.mediawiki.org/wiki/Extension:BounceHandler#API";
	}

}