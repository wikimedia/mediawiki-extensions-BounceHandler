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
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class ApiBounceHandlerTest extends ApiTestCase {


	function setUp() {
		parent::setUp();
		$this->doLogin( 'sysop' );
	}

	public static function provideBounceEmails() {
		$email = file_get_contents( __DIR__ .'/bounce_emails/email1' );
		return array (
			array ( $email )
		);
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
			array(
				'wgVERPprefix' => $prefix,
				'wgVERPalgorithm' => $algorithm,
				'wgVERPsecret' => $secretKey,
				'wgVERPdomainPart' => $domain,
				'wgBounceHandlerUnconfirmUsers' => true,
				'wgBounceRecordPeriod' => $bounceRecordPeriod,
				'wgBounceRecordLimit' => $bounceRecordLimit,
				'wgBounceHandlerInternalIPs'=> array( '127.0.0.1' )
			)
		);

		$encodeVERP = new VerpAddressGenerator( $prefix, $algorithm, $secretKey, $domain );
		$encodedAddress = $encodeVERP->generateVERP( $uid );

		$replace = array( "{VERP_ADDRESS}" => $encodedAddress );
		$email = strtr( $email, $replace );

		list( $apiResult ) = $this->doApiRequest( array(
			'action' => 'bouncehandler',
			'email' => $email
		) );

		$this->assertEquals( 'job', $apiResult['bouncehandler']['submitted'] );
	}

	/**
	 * Tests API request from an unknown IP
	 *
	 * @dataProvider provideBounceEmails
	 * @param $email
	 * @expectedException UsageException
	 * @expectedExceptionMessage This API module is for internal use only.
	 */
	function testBounceHandlerWithBadIPPasses( $email ) {
		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', array( '111.111.111.111' ) );
		$this->doApiRequest( array(
			'action' => 'bouncehandler',
			'email' => $email
		) );
	}

	/**
	 * Tests API request with null 'email' param
	 *
	 * @expectedException UsageException
	 * @expectedExceptionMessage The email parameter must be set
	 */
	function testBounceHandlerWithNullParams() {
		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', array( '127.0.0.1' ) );
		$this->doApiRequest( array(
			'action' => 'bouncehandler',
			'email' => ''
		) );

	}

	/**
	 * Tests API with Wrong params
	 *
	 * @dataProvider provideBounceEmails
	 * @param $email
	 * @expectedException UsageException
	 * @expectedExceptionMessage The email parameter must be set
	 */
	function testBounceHandlerWithWrongParams( $email ) {
		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', array( '127.0.0.1' ) );
		$this->doApiRequest( array(
			'action' => 'bouncehandler',
			'foo' => $email
		) );
	}

}
