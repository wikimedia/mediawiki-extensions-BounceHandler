<?php
/**
 * Class ProcessBounceWithRegexTest
 *
 * @covers ProcessBounceWithRegex
 * @author Tony Thomas
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class ProcessBounceWithRegexTest extends MediaWikiTestCase {
	function setUp() {
		parent::setUp();
	}

	public static function provideBounceStatusEmails() {
		$email1 = file_get_contents( __DIR__ .'/bounce_emails/emailStatus1' );
		$email2 = file_get_contents( __DIR__ .'/bounce_emails/emailStatus2' );
		$email3 = file_get_contents( __DIR__ .'/bounce_emails/emailStatus3' );
		$email4 = file_get_contents( __DIR__ .'/bounce_emails/oracle7' );

		return array(
			array(
				$email1, array( 'x-failed-recipients' => 'bounceduserfortest@gmail.com',
				'to' => 'wiki-testwiki-2-ng0kgh-4UPcJ1Ejt0cA3hkR@mediawiki-verp.wmflabs.org',
				'subject' => 'Mail delivery failed: returning message to sender',
				'date' => 'Wed, 03 Dec 2014 16:00:19 +0000' )
			),
			array(
				$email2, array( 'to' => 'testemailfailure@outlook.com',
				'date' => 'Wed, 3 Dec 2014 15:30:52 -0800',
				'subject' => 'Delivery Status Notification (Failure)',
				'status' => '5.5.0' )
			),
			array(
				$email3, array( 'to' => 'wiki-testwiki-2-ng0kgh-4UPcJ1Ejt0cA3hkR@mediawiki-verp.wmflabs.org',
				'date' => 'Wed, 03 Dec 2014 16:00:19 +0000',
				'subject' => 'Mail delivery failed: returning message to sender',
				'smtp-code' => '550' )
			),
			array(
				$email4, array( 'to' => 'f...@studenti.unimi.it',
				'date' => 'Tue, 24 Feb 2015 07:24:17 +0100',
				'subject' => 'Delayed Mail (still being retried)',
				'smtp-code' => '421' )
			),
		);
	}

	/**
	 * @dataProvider provideBounceStatusEmails
	 * @param $emailStatus
	 * @param $expected
	 */
	function testExtractHeadersWithStatus( $emailStatus, $expected ) {
		$regexClass = new ProcessBounceWithRegex;
		$regexResult = $regexClass->extractHeaders( $emailStatus );
		$this->assertArrayEquals( $expected, $regexResult );
	}

}
