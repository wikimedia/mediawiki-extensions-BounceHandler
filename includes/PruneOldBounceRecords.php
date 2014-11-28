<?php
/**
 * Class PruneOldBounceRecords
 *
 * Prune old bounce records from the 'bounce_records' table
 *
 * Copyright (c) 2014, Tony Thomas <01tonythomas@gmail.com>
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Tony Thomas <01tonythomas@gmail.com>
 * @license GPL-2.0
 * @ingroup Extensions
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
				array (
					'br_id' => $idArray
				),
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
		$idArray = array();
		$maximumRecordAge = time() - $this->bounceRecordMaxAge;
		$dbr = ProcessBounceEmails::getBounceRecordDB( DB_SLAVE, $wikiId );
		$res = $dbr->select(
			'bounce_records',
			array( 'br_id' ),
			'br_timestamp < ' . $dbr->addQuotes( $dbr->timestamp( $maximumRecordAge ) ),
			__METHOD__,
			array( 'LIMIT' => 100 )
		);

		foreach( $res as $row ) {
			$idArray[] = (int)$row->br_id;
		}

		return $idArray;
	}

}