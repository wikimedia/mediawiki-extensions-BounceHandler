<?php

namespace MediaWiki\Extension\BounceHandler;

use MailAddress;
use UserMailer;

/**
 * Class ProcessUnRecognizedBounces
 *
 * Process unrecognized bounce by notifying administrators
 *
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class ProcessUnRecognizedBounces {
	protected string $passwordSender;

	protected array $unrecognizedBounceNotify;

	/**
	 * @param array $unrecognizedBounceNotify The array of admins to be notified
	 *   on a bounce parse failure
	 * @param string $passwordSender The default email Return path address
	 */
	public function __construct( array $unrecognizedBounceNotify, string $passwordSender ) {
		$this->unrecognizedBounceNotify = $unrecognizedBounceNotify;
		$this->passwordSender = $passwordSender;
	}

	/**
	 * Notify the system administrator about a temporary bounce which failed to get parsed
	 *
	 * @param string $email The received email bounce
	 */
	public function processUnRecognizedBounces( $email ) {
		if ( !$this->unrecognizedBounceNotify ) {
			return;
		}
		$subject = 'bouncehandler-notify_subject';
		$sender = new MailAddress( $this->passwordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text() );
		$to = [];
		foreach ( $this->unrecognizedBounceNotify as $notifyEmails ) {
			$to[] = new MailAddress( $notifyEmails );
		}
		UserMailer::send(
			$to,
			$sender,
			$subject,
			$email,
			[ 'replyTo' => $sender ]
		);
	}
}
