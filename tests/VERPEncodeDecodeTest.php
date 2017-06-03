<?php

/**
 * Class VERPEncodeDecodeTest
 *
 * @group Database
 * @covers VerpAddressGenerator
 * @author Tony Thomas
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
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

		$prefix = 'wiki';
		$algorithm = 'md5';
		$secretKey = 'mySecret';
		$domain = 'testwiki.org';

		$this->setMwGlobals(
			[
				'wgVERPprefix' => $prefix,
				'wgVERPalgorithm' => $algorithm,
				'wgVERPsecret' => $secretKey,
				'wgVERPdomainPart' => $domain
			]
		);
		$this->setMwGlobals( 'wgVERPAcceptTime', 259200 );

		$encodeVERP = new VerpAddressGenerator( $prefix, $algorithm, $secretKey, $domain );
		$encodedAddress = $encodeVERP->generateVERP( $uid );

		$decodeVERPwithRegex = new ProcessBounceWithRegex();

		$userDetailsWithRegex = $decodeVERPwithRegex->getUserDetails( $encodedAddress );
		$decodeAddressWithRegex = $decodeVERPwithRegex->getOriginalEmail( $userDetailsWithRegex );

		// Check if the source address and the decoded address match
		$this->assertEquals( $user->getEmail(), $decodeAddressWithRegex );

		if ( !class_exists( 'PlancakeEmailParser' ) ) {
			$this->markTestSkipped( "This test requires the Plancake Email Parser library" );
		} else {
			$decodeVERPwithPlancake = new ProcessBounceWithPlancake();

			$userDetailsWithPlancake = $decodeVERPwithPlancake->getUserDetails( $encodedAddress );
			$decodeAddressWithPlancake = $decodeVERPwithPlancake->getOriginalEmail(
				$userDetailsWithPlancake );

			// Check if the source address and the decoded address match
			$this->assertEquals( $user->getEmail(), $decodeAddressWithPlancake );

			// Check if both tests produced the same output
			$this->assertEquals( $decodeAddressWithPlancake, $decodeAddressWithRegex );
		}
	}

}
