<?php

class EchoBounceHandlerFormatter extends EchoBasicFormatter {
	/**
	 * @param EchoEvent $event
	 * @param string $param
	 * @param Message $message
	 * @param User $user
	 */
	protected function processParam( EchoEvent $event, $param, Message $message, User $user ) {
		if ( $param === 'failed-email' ) {
			$message->params( $event->getExtraParam( 'failed-email' ) );
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}

	/**
	 * Overriding implementation in EchoBasicFormatter
	 *
	 * @param EchoEvent $event
	 * @param User $user The user receiving the notification
	 * @param string $destination The destination type for the link
	 * @return array including target URL
	 */
	protected function getLinkParams( EchoEvent $event, User $user, $destination ) {
		if ( $destination === 'change-email' ) {
			return array( SpecialPage::getTitleFor( 'ConfirmEmail' ), array() );
		} else {
			return parent::getLinkParams( $event, $user, $destination );
		}
	}
}