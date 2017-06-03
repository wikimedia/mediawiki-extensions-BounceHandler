<?php
/**
 * Class PruneOldBounceRecords
 *
 * Prune old bounce records from the 'bounce_records' table
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas <01tonythomas@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
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
		if ( $idArrayCount  > 0 ) {
			$dbw = ProcessBounceEmails::getBounceRecordDB( DB_MASTER, $wikiId );
			$dbw->delete(
				'bounce_records',
				[
					'br_id' => $idArray
				],
				__METHOD__
			);
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
		$dbr = ProcessBounceEmails::getBounceRecordDB( DB_SLAVE, $wikiId );
		$res = $dbr->select(
			'bounce_records',
			[ 'br_id' ],
			'br_timestamp < ' . $dbr->addQuotes( $dbr->timestamp( $maximumRecordAge ) ),
			__METHOD__,
			[ 'LIMIT' => 100 ]
		);

		foreach ( $res as $row ) {
			$idArray[] = (int)$row->br_id;
		}

		return $idArray;
	}

}
