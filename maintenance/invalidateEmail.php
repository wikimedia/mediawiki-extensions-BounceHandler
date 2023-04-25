<?php
/**
 * @copyright © 2017 Wikimedia Foundation and contributors.
 * @section LICENSE
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
 */

use MediaWiki\Extension\BounceHandler\BounceHandlerActions;
use MediaWiki\WikiMap\WikiMap;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class InvalidateEmail extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'BounceHandler' );
		$this->addDescription( "Invalidate a user's email address and leave them a message." );
		$this->addOption( 'userlist',
			'List of usernames to invalidate, one per line', true, true );
		$this->addOption( 'dry-run', 'Do not invalidate addresses' );
	}

	public function execute() {
		$list = $this->getOption( 'userlist' );
		if ( !is_file( $list ) ) {
			$this->output( "ERROR - File not found: {$list}" );
			exit( 1 );
		}
		$file = fopen( $list, 'r' );
		if ( $file === false ) {
			$this->output( "ERROR - Could not open file: {$list}" );
			exit( 1 );
		}
		$bounce = new BounceHandlerActions(
			WikiMap::getCurrentWikiId(), 0, 0, false, 'Email invalidated manually.' );
		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures
		while ( strlen( $username = trim( fgets( $file ) ) ) ) {
			$this->output( "Invalidate email for: {$username}\n" );
			$user = User::newFromName( $username );
			if ( $user && $user->getId() ) {
				if ( !$this->hasOption( 'dry-run' ) ) {
					$bounce->unSubscribeUser(
						[
							'rawEmail' => $user->getEmail(),
							'rawUserId' => $user->getId(),
						],
						[ /* Headers intentionally blank */ ]
					);
				}
			} else {
				$this->output( "ERROR - Unknown user {$username}\n" );
			}
		}
		fclose( $file );
		$this->output( "done.\n" );
	}
}

$maintClass = InvalidateEmail::class;
require_once RUN_MAINTENANCE_IF_MAIN;
