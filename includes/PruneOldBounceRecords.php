<?php

namespace MediaWiki\Extension\BounceHandler;

/**
 * Class PruneOldBounceRecords
 *
 * Prune old bounce records from the 'bounce_records' table
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas <01tonythomas@gmail.com>
 * @license GPL-2.0-or-later
 */
class PruneOldBounceRecords {

	/**
	 * @var int
	 */
	private $bounceRecordMaxAge;

	/**
	 * @param int $bounceRecordMaxAge
	 */
	public function __construct( $bounceRecordMaxAge ) {
		$this->bounceRecordMaxAge = $bounceRecordMaxAge;
	}

	/**
	 * Prune old bounce records
	 *
	 * @param string $wikiId
	 *
	 */
	public function pruneOldRecords( $wikiId ) {
		$idArray = $this->getOldRecords( $wikiId );
		$idArrayCount = count( $idArray );
		if ( $idArrayCount > 0 ) {
			$dbw = ProcessBounceEmails::getBounceRecordDB( DB_PRIMARY, $wikiId );
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'bounce_records' )
				->where( [
					'br_id' => $idArray
				] )
				->caller( __METHOD__ )
				->execute();
			wfDebugLog( 'BounceHandler', "Pruned $idArrayCount bounce records from $wikiId wiki." );
		}
	}

	/**
	 * Get Old bounce records from DB
	 *
	 * @param string $wikiId
	 * @return int[]
	 */
	private function getOldRecords( $wikiId ) {
		$idArray = [];
		$maximumRecordAge = time() - $this->bounceRecordMaxAge;
		$dbr = ProcessBounceEmails::getBounceRecordDB( DB_REPLICA, $wikiId );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'br_id' ] )
			->from( 'bounce_records' )
			->where( $dbr->expr( 'br_timestamp', '<', $dbr->timestamp( $maximumRecordAge ) ) )
			->limit( 100 )
			->caller( __METHOD__ )->fetchResultSet();

		foreach ( $res as $row ) {
			$idArray[] = (int)$row->br_id;
		}

		return $idArray;
	}

}
