<?php
/**
 * Class ProcessUnRecognizedBounces
 *
 * Process unrecognized bounce by notifying administrators
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class ProcessUnRecognizedBounces {
	/**
	 * @var string
	 */
	protected $passwordSender;

	/**
	 * @var array
	 */
	protected $unrecognizedBounceNotify;

	/**
	 * @param array $unrecognizedBounceNotify The array of admins to be notified
	 *   on a bounce parse failure
	 * @param string $passwordSender The default email Return path address
	 */
	public function __construct( array $unrecognizedBounceNotify, $passwordSender ) {
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
