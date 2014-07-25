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

		$email = $this->getMain()->getVal( 'email' );
		$params = array ( 'email' => $email );
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

}