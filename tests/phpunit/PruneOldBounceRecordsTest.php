<?php

use MediaWiki\Extension\BounceHandler\PruneOldBounceRecords;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

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
		// Delete all old rows
		$bounceRecordMaxAge = -1;
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$pruneOldRecordsTester->pruneOldRecords();
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 0, $res );

		$bounceRecordMaxAge = 3;
		$this->insertDelayedBounce( 4, $dbw );
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );

		// We have one bounce from above in the DB
		$this->assertSame( 1, $res );
		$pruneOldRecordsTester->pruneOldRecords();

		// reset
		$bounceRecordMaxAge = -1;
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 0, $res );
	}

	public function testMultipleOldRows() {
		$icp = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $icp->getPrimaryDatabase();
		$dbr = $icp->getReplicaDatabase();
		$bounceRecordMaxAge = -1;
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$pruneOldRecordsTester->pruneOldRecords();
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 0, $res );

		// Insert the First bounce with 4 seconds
		$this->insertDelayedBounce( 4, $dbw );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertSame( 1, $res );

		// Insert Second Bounce
		$this->insertDelayedBounce( 0, $dbw );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertEquals( 2, $res );

		// Insert Third Bounce
		$this->insertDelayedBounce( 0, $dbw );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertEquals( 3, $res );

		$bounceRecordMaxAge = 3;
		$pruneOldRecordsTester = new PruneOldBounceRecords( $bounceRecordMaxAge );
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		// Only one among the three would be that old.
		$this->assertSame( 1, $res );

		$pruneOldRecordsTester->pruneOldRecords();

		$bounceRecordMaxAge = -1;
		$res = $this->getOldRecordsCount( $bounceRecordMaxAge, $dbr );
		$this->assertEquals( 2, $res );
	}

	/**
	 * @param int $delayTime
	 * @param IDatabase $dbw
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
	 * @param IDatabase|IReadableDatabase $dbr
	 *
	 * @return int
	 */
	protected function getOldRecordsCount( $bounceRecordMaxAge, $dbr ) {
		$maximumRecordAge = time() - $bounceRecordMaxAge;
		return $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'bounce_records' )
			->where( $dbr->expr( 'br_timestamp', '<', $dbr->timestamp( $maximumRecordAge ) ) )
			->limit( 100 )
			->caller( __METHOD__ )->fetchRowCount();
	}

}
