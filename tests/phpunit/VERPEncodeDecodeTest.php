<?php

use MediaWiki\Extension\BounceHandler\ProcessBounceWithRegex;
use MediaWiki\Extension\BounceHandler\VerpAddressGenerator;

/**
 * Class VERPEncodeDecodeTest
 *
 * @group Database
 * @covers \MediaWiki\Extension\BounceHandler\VerpAddressGenerator
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class VERPEncodeDecodeTest extends MediaWikiIntegrationTestCase {

	/**
	 * Tests that the extension encodes and decodes the email address correctly
	 */
	public function testVERPEncodingDecoding() {
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
	}

}
