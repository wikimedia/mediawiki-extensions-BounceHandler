<?php

/**
 * Class UnSubscribeUserTest
 *
 * @group Database
 */
class UnSubscribeUserTest extends MediaWikiTestCase {

	function testUnSubscribeUser() {
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
		$server = 'http://testwiki.org';
		$smtp = array();
		$bounceRecordPeriod = 604800;
		$bounceRecordLimit = 3;

		$this->setMwGlobals(
			array(
				'wgVERPprefix' => $prefix,
				'wgVERPalgorithm' => $algorithm,
				'wgVERPsecret' => $secretKey,
				'wgServer' => $server,
				'wgSMTP' => $smtp,
				'wgBounceHandlerUnconfirmUsers' => true,
				'wgBounceRecordPeriod' => $bounceRecordPeriod,
				'wgBounceRecordLimit' => $bounceRecordLimit
			)
		);

		$this->setMwGlobals( 'wgVERPAcceptTime', 259200 );

		$encodeVERP = new VerpAddressGenerator( $prefix, $algorithm, $secretKey, $server, $smtp );
		$encodedAddress = $encodeVERP->generateVERP( $uid );

		$emailHeaders['to'] = $encodedAddress;
		$emailHeaders['subject'] = 'Delivery Failed';
		$emailHeaders['x-failed-recipients'] = $user->getEmail();

		$decodeVERPwithRegex = new ProcessBounceWithRegex();
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders );
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders );
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders );
		$decodeVERPwithRegex->processBounceHeaders( $emailHeaders );

		$newUser = User::newFromId( $uid );
		$this->assertFalse( $newUser->isEmailConfirmed() );

	}

}