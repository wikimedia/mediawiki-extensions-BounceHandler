<?php
/**
 * Tests for API module
 * @group API
 * @group medium
 */
class ApiBounceHandlerTest extends ApiTestCase {

	/**
	 * @var string
	 */
	static $bounceEmail = "This is a test email";

	function setUp() {
		parent::setUp();
		$this->doLogin( 'sysop' );
	}

	/**
	 * Tests API request from an allowed IP
	 *
	 *
	 */
	function testBounceHandlerWithGoodIPPasses() {
		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', array( '127.0.0.1' ) );
		list( $apiResult ) = $this->doApiRequest( array(
			'action' => 'bouncehandler',
			'email' => self::$bounceEmail
		) );

		$this->assertEquals( 'job', $apiResult['bouncehandler']['submitted'] );
	}

	/**
	 * Tests API request from an unknown IP
	 *
	 * @expectedException UsageException
	 * @expectedExceptionMessage This API module is for internal use only.
	 */
	function testBounceHandlerWithBadIPPasses() {
		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', array( '111.111.111.111' ) );
		$this->doApiRequest( array(
			'action' => 'bouncehandler',
			'email' => self::$bounceEmail
		) );
	}

	/**
	 * Tests API request with null 'email' param
	 *
	 * @expectedException UsageException
	 * @expectedException The email parameter must be set
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
	 * @expectedException UsageException
	 * @expectedException The email parameter must be set
	 */
	function testBounceHandlerWithWrongParams() {
		$this->setMwGlobals( 'wgBounceHandlerInternalIPs', array( '127.0.0.1' ) );
		$this->doApiRequest( array(
			'action' => 'bouncehandler',
			'foo' => self::$bounceEmail
		) );
	}

}