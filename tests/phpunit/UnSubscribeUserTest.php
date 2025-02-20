<?php

use MediaWiki\Extension\BounceHandler\ProcessBounceWithRegex;
use MediaWiki\Extension\BounceHandler\VerpAddressGenerator;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Class UnSubscribeUserTest
 *
 * @group Database
 * @covers \MediaWiki\Extension\BounceHandler\BounceHandlerActions
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class UnSubscribeUserTest extends MediaWikiIntegrationTestCase {

	public function testUnSubscribeUser() {
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( 'TestUser' );
		$user->setEmail( 'bob@example.ext' );
		$user->addToDatabase();

		$user->confirmEmail();
		$user->saveSettings();

		$this->assertTrue( $user->isEmailConfirmed() );

		$uid = $user->getId();

		$prefix = 'wiki';
		$algorithm = 'md5';
		$secretKey = 'mySecret';
		$domain = 'testwiki.org';
		$bounceRecordPeriod = 604800;
		$bounceRecordLimit = 3;

		$this->overrideConfigValues( [
			'VERPprefix' => $prefix,
			'VERPalgorithm' => $algorithm,
			'VERPsecret' => $secretKey,
			'VERPdomainPart' => $domain,
			'BounceHandlerUnconfirmUsers' => true,
			'BounceRecordPeriod' => $bounceRecordPeriod,
			'BounceRecordLimit' => $bounceRecordLimit,
			'VERPAcceptTime' => 259200,
		] );

		$encodeVERP = new VerpAddressGenerator( $prefix, $algorithm, $secretKey, $domain );
		$encodedAddress = $encodeVERP->generateVERP( $uid );

		$emailRaw = "This is a test email for logging purpose only";
		$emailHeaders['to'] = $encodedAddress;
		$emailHeaders['subject'] = 'Delivery Failed';
		$emailHeaders['x-failed-recipients'] = $user->getEmail();

		$decodeVERPwithRegex = new ProcessBounceWithRegex();
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders, $emailRaw );
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders, $emailRaw );
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders, $emailRaw );
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders, $emailRaw );

		$newUser = $this->getServiceContainer()->getUserFactory()->newFromId( $uid );
		$newUser->load( IDBAccessObject::READ_LATEST );
		$this->assertFalse( $newUser->isEmailConfirmed() );
	}

}
