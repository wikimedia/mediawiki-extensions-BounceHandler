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

	private int $bounceRecordMaxAge;

	public function __construct( int $bounceRecordMaxAge ) {
		$this->bounceRecordMaxAge = $bounceRecordMaxAge;
	}

	/**
	 * Prune old bounce records
	 */
	public function pruneOldRecords() {
		$idArray = $this->getOldRecords();
		$idArrayCount = count( $idArray );
		if ( $idArrayCount > 0 ) {
			$dbw = ProcessBounceEmails::getBounceRecordPrimaryDB();
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'bounce_records' )
				->where( [
					'br_id' => $idArray
				] )
				->caller( __METHOD__ )
				->execute();
			wfDebugLog( 'BounceHandler', "Pruned $idArrayCount bounce records." );
		}
	}

	/**
	 * Get Old bounce records from DB
	 *
	 * @return int[]
	 */
	private function getOldRecords() {
		$idArray = [];
		$maximumRecordAge = time() - $this->bounceRecordMaxAge;
		$dbr = ProcessBounceEmails::getBounceRecordReplicaDB();
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
