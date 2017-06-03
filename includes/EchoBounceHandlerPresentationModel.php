<?php

class EchoBounceHandlerPresentationModel extends EchoEventPresentationModel {
	/**
	 * {@inheritdoc}
	 */
	public function getIconType() {
		return 'placeholder';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'ConfirmEmail' )->getFullURL(),
			'label' => $this->msg( 'notification-link-text-change-email' )->text(),
		];
	}

	/**
	 * {@inheritdoc}
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
