<?php

/**
 * Class UnSubscribeUserTest
 *
 * @group Database
 * @covers BounceHandlerActions
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class UnSubscribeUserTest extends MediaWikiTestCase {

	public function testUnSubscribeUser() {
		$user = User::newFromName( 'TestUser' );
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

		$this->setMwGlobals(
			[
				'wgVERPprefix' => $prefix,
				'wgVERPalgorithm' => $algorithm,
				'wgVERPsecret' => $secretKey,
				'wgVERPdomainPart' => $domain,
				'wgBounceHandlerUnconfirmUsers' => true,
				'wgBounceRecordPeriod' => $bounceRecordPeriod,
				'wgBounceRecordLimit' => $bounceRecordLimit
			]
		);

		$this->setMwGlobals( 'wgVERPAcceptTime', 259200 );

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

		$newUser = User::newFromId( $uid );
		$newUser->load( User::READ_LATEST );
		$this->assertFalse( $newUser->isEmailConfirmed() );
	}

}
