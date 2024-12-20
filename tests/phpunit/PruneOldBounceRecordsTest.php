<?php

use MediaWiki\Extension\BounceHandler\PruneOldBounceRecords;
use MediaWiki\WikiMap\WikiMap;

/**
 * Class PruneOldBounceRecordsTest
 *
 * @group Database
 * @group medium
 * @covers \MediaWiki\Extension\BounceHandler\PruneOldBounceRecords
 * @author Tony Thomas
 * @license GPL-2.0-or-later
 */
class PruneOldBounceRecordsTest extends MediaWikiIntegrationTestCase {

	/** @var string */
	protected $wikiId;
	/** @var string */
	protected $originalEmail;
	/** @var string */
	protected $subject = "Bounce Email";

	protected function setUp(): void {
		parent::setUp();

		$user = $this->getServiceContainer()->getUserFactory()->newFromName( 'OldUser' );
		$user->setEmail( 'oldbob@example.ext' );
		$user->addToDatabase();

		$user->confirmEmail();
		$user->saveSettings();

		$bounceRecordPeriod = 604800;
		$bounceRecordLimit = 4;
		$bounceHandlerSharedDB = false;
		$bounceHandlerCluster = false;
		$bounceHandlerUnconfirmUsers = false;

		$prefix = 'wiki';
		$algorithm = 'md5';
		$secretKey = 'mySecret';
		$domain = 'testwiki.org';

		$this->overrideConfigValues( [
			'BounceHandlerUnconfirmUsers' => $bounceHandlerUnconfirmUsers,
			'BounceRecordPeriod' => $bounceRecordPeriod,
			'BounceRecordLimit' => $bounceRecordLimit,
			'BounceHandlerSharedDB' => $bounceHandlerSharedDB,
			'BounceHandlerCluster' => $bounceHandlerCluster,
			'VERPAcceptTime' => 259200,
			'VERPprefix' => $prefix,
			'VERPalgorithm' => $algorithm,
			'VERPsecret' => $secretKey,
			'VERPdomainPart' => $domain,
		] );

		$this->originalEmail = $user->getEmail();
		$this->wikiId = WikiMap::getCurrentWikiId();
	}

	public function testPruneDeleteOldSingleRow() {
		$icp = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $icp->getPrimaryDatabase();
		$dbr = $icp->getReplicaDatabase();
		// Delete old rows
		$bounceRecordMaxAge = -1; // To get all the bounces in the Database
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$pruneOldRecordsTester->pruneOldRecords( $this->wikiId ); // Delete all rows
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 0, $res ); // We will have 0 elements after pruning

		$bounceRecordMaxAge = 3;
		$this->insertDelayedBounce( 4, $dbw );
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );

		$this->assertSame( 1, $res ); // We have one bounce from above in the DB
		$pruneOldRecordsTester->pruneOldRecords( $this->wikiId ); // 2 should get deleted

		// reset
		$bounceRecordMaxAge = -1;
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 0, $res ); // We will have 0 elements after pruning
	}

	public function testMultipleOldRows() {
		$icp = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $icp->getPrimaryDatabase();
		$dbr = $icp->getReplicaDatabase();
		$bounceRecordMaxAge = -1; // To get all the bounces in the Database
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$pruneOldRecordsTester->pruneOldRecords( $this->wikiId ); // Delete all rows
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 0, $res ); // We will have 0 elements

		// Insert First bounce
		$this->insertDelayedBounce( 4, $dbw ); // Insert with 4 seconds delay
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 1, $res ); // We will have only one bounce in the record

		// Insert Second Bounce
		$this->insertDelayedBounce( 0, $dbw ); // Insert with 0 delay
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertEquals( 2, $res ); // We will have two bounces in the bounce record as of now

		// Insert Third Bounce
		$this->insertDelayedBounce( 0, $dbw ); // Insert with 0 delay
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertEquals( 3, $res ); // We will have three bounces in the bounce record as of now

		$bounceRecordMaxAge = 3;
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 1, $res ); // Only one among the three would be that old.

		$pruneOldRecordsTester->pruneOldRecords( $this->wikiId ); // 2 should get deleted

		$bounceRecordMaxAge = -1; // To get all the bounces in the Database
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertEquals( 2, $res );
	}

	/**
	 * @param int $delayTime
	 * @param \Wikimedia\Rdbms\IDatabase $dbw
	 */
	protected function insertDelayedBounce( $delayTime, $dbw ) {
		$bounceTimestamp = wfTimestamp( TS_MW, time() - $delayTime );
		$rowData = [
			'br_user_email' => $this->originalEmail,
			'br_timestamp' => $dbw->timestamp( $bounceTimestamp ),
			'br_reason' => $this->subject
		];

		$dbw->newInsertQueryBuilder()
			->insertInto( 'bounce_records' )
			->row( $rowData )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param int $bounceRecordMaxAge
	 * @param \Wikimedia\Rdbms\IDatabase $dbr
	 * @return int
	 */
	protected function getOldRecordsCount( $bounceRecordMaxAge, $dbr ) {
		$maximumRecordAge = time() - $bounceRecordMaxAge;
		$res = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'bounce_records' )
			->where( $dbr->expr( 'br_timestamp', '<', $dbr->timestamp( $maximumRecordAge ) ) )
			->limit( 100 )
			->caller( __METHOD__ )->fetchRowCount();

		return $res;
	}

}
