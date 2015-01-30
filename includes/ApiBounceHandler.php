<?php
/**
 * Class ApiBounceHandler
 *
 * API to handle e-mail bounces
 *
 * @file
 * @ingroup API
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class ApiBounceHandler extends ApiBase {
	public function execute() {
		global $wgBounceHandlerInternalIPs;

		$requestIP = $this->getRequest()->getIP();
		$inRangeIP = false;
		foreach( $wgBounceHandlerInternalIPs as $internalIP ) {
			if ( IP::isInRange( $requestIP, $internalIP ) ) {
				$inRangeIP = true;
				break;
			}
		}
		if ( !$inRangeIP ) {
			wfDebugLog( 'BounceHandler', "POST received from restricted IP $requestIP" );
			$this->dieUsage( 'This API module is for internal use only.', 'invalid-ip' );
		}

		$params = $this->extractRequestParams();

		// Extract the wiki ID from the Verp address (also verifies the hash)
		$bounceProcessor = new ProcessBounceWithRegex();
		$emailHeaders = $bounceProcessor->extractHeaders( $params['email'] );
		$to = isset( $emailHeaders['to'] ) ? $emailHeaders['to'] : '';
		$failedUser = strlen( $to ) ? $bounceProcessor->getUserDetails( $to ) : array();

		// Route the job to the wiki that the email was sent from.
		// This way it can easily unconfirm the user's email using the User methods.
		if ( isset( $failedUser['wikiId'] ) ) {
			$title = Title::newFromText( 'BounceHandler Job' );
			$job = new BounceHandlerJob( $title, $params );
			JobQueueGroup::singleton( $failedUser['wikiId'] )->push( $job );

			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				array ( 'submitted' => 'job' )
			);
		} else {
			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				array ( 'submitted' => 'failure' )
			);
		}

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
