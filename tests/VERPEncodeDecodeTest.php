<?php

/**
 * Class VERPEncodeDecodeTest
 *
 * @group Database
 */
class VERPEncodeDecodeTest extends MediaWikiTestCase {

	/**
	 * Tests that the extension encodes and decodes the email address correctly
	 */
	function testVERPEncodingDecoding() {

		$user = User::newFromName( 'TestUser' );
		$user->setEmail( 'bob@example.ext' );
		$user->addToDatabase();

		$uid = $user->getId();

		$algorithm = 'md5';
		$secretKey = 'mySecret';
		$server = 'http://testwiki.org';
		$smtp = array();

		$this->setMwGlobals( array(
			'wgVERPalgorithm' => $algorithm,
			'wgVERPsecret' => $secretKey,
			'wgServer' => $server,
			'wgSMTP' => $smtp
			)
		);
		$this->setMwGlobals( 'wgVERPAcceptTime', 259200 );

		$encodeVERP = new VerpAddressGenerator( $algorithm, $secretKey, $server, $smtp );
		$encodedAddress = $encodeVERP->generateVERP( $uid );

		$decodeVERPwithRegex = new ProcessBounceWithRegex();

		$userDetailsWithRegex = $decodeVERPwithRegex->getUserDetails( $encodedAddress );
		$decodeAddressWithRegex = $decodeVERPwithRegex->getOriginalEmail( $userDetailsWithRegex );

		// Check if the source address and the decoded address match
		$this->assertEquals( $user->getEmail() , $decodeAddressWithRegex );

		if ( !class_exists( 'PlancakeEmailParser' ) ) {
			$this->markTestSkipped( "This test requires the Plancake Email Parser library" );
		} else {
			$decodeVERPwithPlancake = new ProcessBounceWithPlancake();

			$userDetailsWithPlancake = $decodeVERPwithPlancake->getUserDetails( $encodedAddress );
			$decodeAddressWithPlancake = $decodeVERPwithPlancake->getOriginalEmail( $userDetailsWithPlancake );

			// Check if the source address and the decoded address match
			$this->assertEquals( $user->getEmail() , $decodeAddressWithPlancake );

			// Check if both tests produced the same output
			$this->assertEquals( $decodeAddressWithPlancake, $decodeAddressWithRegex );
		}
	}

}