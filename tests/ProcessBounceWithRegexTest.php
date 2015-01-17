<?php
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
}
