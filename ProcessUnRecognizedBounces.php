<?php
class ProcessUnRecognizedBounces {
	/**
	 * @var string
	 */
	protected $passwordSender;

	/**
	 * @var array
	 */
	protected $unrecognizedBounceNotify;

	public function __construct( $unrecognizedBounceNotify, $passwordSender ) {
		$this->unrecognizedBounceNotify = $unrecognizedBounceNotify;
		$this->passwordSender = $passwordSender;
	}

	/**
	 * Notify the system administrator about a temporary bounce which failed to get parsed
	 *
	 * @param string $email The received email bounce
	 */
	public function processUnRecognizedBounces( $email ) {
		$subject = 'bouncehandler-notify_subject';
		$sender = new MailAddress( $this->passwordSender, wfMessage( 'emailsender' )->inContentLanguage()->text() );
		$to = array();
		if ( isset( $this->urecognizedBounceNotify ) ) {
			foreach ( $this->unrecognizedBounceNotify as $notifyEmails ) {
				$to[] = new MailAddress( $notifyEmails );
			}
			UserMailer::send( $to, $sender, $subject, $email, $sender );
		} else {
			wfDebugLog( 'BounceHandler', "Cannot send notification to administrators" );
		}
	}
}