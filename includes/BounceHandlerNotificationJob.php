<?php
/**
 * Class BounceHandlerNotificationJob
 *
 * Class to notify a global user on a particular wiki on his E-mail address becoming un-subscribed
 * @file
 * @ingroup JobQueue
 * @author Tony Thomas
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class BounceHandlerNotificationJob extends Job {
	public function __construct( Title $title, array $params ) {
		parent::__construct( 'BounceHandlerNotificationJob', $title, $params );
	}

	public function run() {
		$failedUserId = $this->params['failed-user-id'];
		$failedUserEmailAddress = $this->params['failed-email'];
		$wikiId = $this->params['wikiId'];
		$bounceRecordPeriod = $this->params['bounceRecordPeriod'];
		$bounceRecordLimit = $this->params['bounceRecordLimit'];
		$bounceHandlerUnconfirmUsers = $this->params['bounceHandlerUnconfirmUsers'];

		if ( $failedUserEmailAddress && $failedUserId ) {
			$unsubscribeNotification = new BounceHandlerActions(
				$wikiId,
				$bounceRecordPeriod,
				$bounceRecordLimit,
				$bounceHandlerUnconfirmUsers
			);
			$unsubscribeNotification->createEchoNotification( $failedUserId, $failedUserEmailAddress );
		}

		return true;
	}
}
