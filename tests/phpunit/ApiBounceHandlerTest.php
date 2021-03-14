<?php

use MediaWiki\Extension\BounceHandler\VerpAddressGenerator;

/**
 * Class ApiBounceHandlerTest
 *
 * Tests for API module
 *
 * @group API
 * @group medium
 * @group Database
 * @covers \MediaWiki\Extension\BounceHandler\ApiBounceHandler
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class ApiBounceHandlerTest extends ApiTestCase {

	public static function provideBounceEmails() {
		$email = file_get_contents( __DIR__ . '/bounce_emails/email1' );
		return [
			[ $email ]
		];
	}

	/**
	 * @dataProvider provideBounceEmails
	 */
	public function testBounceHandlerWithGoodIPPasses( $email ) {
		$user = User::newFromName( 'TestUser' );
		$user->setEmail( 'bob@example.ext' );
		$user->addToDatabase();

		$uid = $user->getId();

		$prefix = 'wiki';
		$algorithm = 'md5';
		$secretKey = 'mySecret';
		$domain = 'testwiki.org';
		$bounceRecordPeriod = 604800;
		$bounceRecordLimit = 3;

		$this->setMwGlobals(
			[
				'wgVERPprefix' => $prefix,
				'wgVERPalgorithm' => $algorithm,
				'wgVERPsecret' => $secretKey,
				'wgVERPdomainPart' => $domain,
				'wgBounceHandlerUnconfirmUsers' => true,
				'wgBounceRecordPeriod' => $bounceRecordPeriod,
				'wgBounceRecordLimit' => $bounceRecordLimit,
				'wgBounceHandlerInternalIPs' => [ '127.0.0.1' ]
			]
		);

		$encodeVERP = new VerpAddressGenerator( $prefix, $algorithm, $secretKey, $domain );
		$encodedAddress = $encodeVERP->generateVERP( $uid );

		$replace = [ "{VERP_ADDRESS}" => $encodedAddress ];
		$email = strtr( $email, $replace );

		list( $apiResult ) = $this->doApiRequest( [
			'action' => 'bouncehandler',
			'email' => $email
		] );

		$this->assertEquals( 'job', $apiResult['bouncehandler']['submitted'] );
	}

	/**
	 * Tests API request from an unknown IP
	 *
	 * @dataProvider provideBounceEmails
	 */
	public function testBounceHandlerWithBadIPPasses( $email ) {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'This API module is for internal use only.' );

		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', [ '111.111.111.111' ] );
		$this->doApiRequest( [
			'action' => 'bouncehandler',
			'email' => $email
		] );
	}

	/**
	 * Tests API request with null 'email' param
	 */
	public function testBounceHandlerWithNullParams() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "email" parameter must be set.' );

		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', [ '127.0.0.1' ] );
		$this->doApiRequest( [
			'action' => 'bouncehandler',
			'email' => ''
		] );
	}

	/**
	 * Tests API with Wrong params
	 *
	 * @dataProvider provideBounceEmails
	 */
	public function testBounceHandlerWithWrongParams( $email ) {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "email" parameter must be set.' );

		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', [ '127.0.0.1' ] );
		$this->doApiRequest( [
			'action' => 'bouncehandler',
			'foo' => $email
		] );
	}

}
