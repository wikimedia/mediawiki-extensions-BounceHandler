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

	public static function provideBounceEmails() {
		$email = file_get_contents( __DIR__ .'/bounce_emails/email2' );
		return array (
			array ( $email )
		);
	}

	public static function provideBounceStatusEmails() {
		$email1 = file_get_contents( __DIR__ .'/bounce_emails/emailStatus1' );
		$email2 = file_get_contents( __DIR__ .'/bounce_emails/emailStatus2' );
		$email3 = file_get_contents( __DIR__ .'/bounce_emails/emailStatus3' );

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
			)
		);
	}

	/**
	 * @dataProvider provideBounceEmails
	 * @param $email
	 */
	function testExtractHeaders( $email ) {
		if ( !class_exists( 'PlancakeEmailParser' ) ) {
			$this->markTestSkipped( "This test requires the Plancake Email Parser library" );
		}
		$regexClass = new ProcessBounceWithRegex;
		$regexResult = $regexClass->extractHeaders( $email );

		$plancakeClass = new ProcessBounceWithPlancake;
		$plancakeResult = $plancakeClass->extractHeaders( $email );

		$this->assertArrayEquals( $regexResult, $plancakeResult );
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
