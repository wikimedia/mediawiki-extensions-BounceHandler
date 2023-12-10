<?php

namespace MediaWiki\Extension\BounceHandler;

use EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;

class EchoBounceHandlerPresentationModel extends EchoEventPresentationModel {
	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'placeholder';
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'Confirmemail' )->getFullURL(),
			'label' => $this->msg( 'notification-link-text-change-email' )->text(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// params 1 & 2 are automatically added by parent, but for this
		// notification they'll always be null & won't be used in any message;
		// below messages with be param 3 & 4
		$msg->params( $this->event->getExtraParam( 'failed-email' ) );
		$msg->params( $this->getViewingUserForGender() );

		return $msg;
	}
}
