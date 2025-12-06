<?php
/**
 * Class ApiBounceHandler
 *
 * API to handle e-mail bounces
 *
 * @file
 * @ingroup API
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\BounceHandler;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Title\Title;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;

class ApiBounceHandler extends ApiBase {

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
	) {
		parent::__construct( $mainModule, $moduleName );
	}

	/** @inheritDoc */
	public function execute() {
		$requestIP = $this->getRequest()->getIP();
		$inRangeIP = false;
		foreach ( $this->getConfig()->get( 'BounceHandlerInternalIPs' ) as $internalIP ) {
			if ( IPUtils::isInRange( $requestIP, $internalIP ) ) {
				$inRangeIP = true;
				break;
			}
		}
		if ( !$inRangeIP ) {
			wfDebugLog( 'BounceHandler', "POST received from restricted IP $requestIP" );
			$this->dieWithError( 'apierror-bouncehandler-internalonly', 'invalid-ip' );
		}

		$params = $this->extractRequestParams();

		// Extract the wiki ID from the Verp address (also verifies the hash)
		$bounceProcessor = new ProcessBounceWithRegex();
		$emailHeaders = $bounceProcessor->extractHeaders( $params['email'] );
		$to = $emailHeaders['to'] ?? '';
		$failedUser = strlen( $to ) ? $bounceProcessor->getUserDetails( $to ) : [];

		// Route the job to the wiki that the email was sent from.
		// This way it can easily unconfirm the user's email using the User methods.
		if ( isset( $failedUser['wikiId'] ) ) {
			$title = Title::newFromText( 'BounceHandler Job' );
			$job = new BounceHandlerJob( $title, $params );
			$this->jobQueueGroupFactory->makeJobQueueGroup( $failedUser['wikiId'] )->push( $job );

			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				[ 'submitted' => 'job' ]
			);
		} else {
			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				[ 'submitted' => 'failure' ]
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

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'email' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=bouncehandler&email=This%20is%20a%20test%20email'
				=> 'apihelp-bouncehandler-example-1'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return "https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:BounceHandler#API";
	}

}
