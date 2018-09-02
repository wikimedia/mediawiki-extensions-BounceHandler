<?php
/**
 * Class ApiBounceHandlerTest
 *
 * Tests for API module
 *
 * @group API
 * @group medium
 * @group Database
 * @covers ApiBounceHandler
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class ApiBounceHandlerTest extends ApiTestCase {

	function setUp() {
		parent::setUp();
		$this->doLogin( 'sysop' );
	}

	public static function provideBounceEmails() {
		$email = file_get_contents( __DIR__ . '/bounce_emails/email1' );
		return [
			[ $email ]
		];
	}

	/**
	 * @dataProvider provideBounceEmails
	 * @param $email
	 */
	function testBounceHandlerWithGoodIPPasses( $email ) {
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
	 * @param $email
	 */
	function testBounceHandlerWithBadIPPasses( $email ) {
		$this->setExpectedException(
			ApiUsageException::class,
			'This API module is for internal use only.'
		);

		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', [ '111.111.111.111' ] );
		$this->doApiRequest( [
			'action' => 'bouncehandler',
			'email' => $email
		] );
	}

	/**
	 * Tests API request with null 'email' param
	 */
	function testBounceHandlerWithNullParams() {
		$this->setExpectedException( ApiUsageException::class, 'The "email" parameter must be set.' );

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
	 * @param $email
	 */
	function testBounceHandlerWithWrongParams( $email ) {
		$this->setExpectedException( ApiUsageException::class, 'The "email" parameter must be set.' );

		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', [ '127.0.0.1' ] );
		$this->doApiRequest( [
			'action' => 'bouncehandler',
			'foo' => $email
		] );
	}

}
