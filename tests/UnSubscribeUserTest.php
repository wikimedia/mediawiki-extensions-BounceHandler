<?php

/**
 * Class UnSubscribeUserTest
 *
 * @group Database
 * @covers BounceHandlerActions
 * @author Tony Thomas
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
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
				'wgBounceRecordLimit' => $bounceRecordLimit
			)
		);

		$this->setMwGlobals( 'wgVERPAcceptTime', 259200 );

		$encodeVERP = new VerpAddressGenerator( $prefix, $algorithm, $secretKey, $domain );
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
