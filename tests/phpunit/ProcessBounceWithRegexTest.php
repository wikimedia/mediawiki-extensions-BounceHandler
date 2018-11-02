<?php
/**
 * Class ProcessBounceWithRegexTest
 *
 * @covers ProcessBounceWithRegex
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class ProcessBounceWithRegexTest extends MediaWikiTestCase {

	public static function provideBounceStatusEmails() {
		$email1 = file_get_contents( __DIR__ . '/bounce_emails/emailStatus1' );
		$email2 = file_get_contents( __DIR__ . '/bounce_emails/emailStatus2' );
		$email3 = file_get_contents( __DIR__ . '/bounce_emails/emailStatus3' );
		$email4 = file_get_contents( __DIR__ . '/bounce_emails/oracle7' );

		return [
			[
				$email1, [ 'x-failed-recipients' => 'bounceduserfortest@gmail.com',
				'to' => 'wiki-testwiki-2-ng0kgh-4UPcJ1Ejt0cA3hkR@mediawiki-verp.test',
				'subject' => 'Mail delivery failed: returning message to sender',
				'date' => 'Wed, 03 Dec 2014 16:00:19 +0000' ]
			],
			[
				$email2, [ 'to' => 'testemailfailure@outlook.com',
				'date' => 'Wed, 3 Dec 2014 15:30:52 -0800',
				'subject' => 'Delivery Status Notification (Failure)',
				'status' => '5.5.0' ]
			],
			[
				$email3, [ 'to' => 'wiki-testwiki-2-ng0kgh-4UPcJ1Ejt0cA3hkR@mediawiki-verp.test',
				'date' => 'Wed, 03 Dec 2014 16:00:19 +0000',
				'subject' => 'Mail delivery failed: returning message to sender',
				'smtp-code' => '550' ]
			],
			[
				$email4, [ 'to' => 'f...@studenti.unimi.it',
				'date' => 'Tue, 24 Feb 2015 07:24:17 +0100',
				'subject' => 'Delayed Mail (still being retried)',
				'smtp-code' => '421' ]
			],
		];
	}

	/**
	 * @dataProvider provideBounceStatusEmails
	 * @param $emailStatus
	 * @param $expected
	 */
	public function testExtractHeadersWithStatus( $emailStatus, $expected ) {
		$regexClass = new ProcessBounceWithRegex;
		$regexResult = $regexClass->extractHeaders( $emailStatus );
		$this->assertArrayEquals( $expected, $regexResult );
	}

}
