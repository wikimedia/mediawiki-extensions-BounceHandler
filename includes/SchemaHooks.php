<?php
namespace MediaWiki\Extension\BounceHandler;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Schema Hooks used by BounceHandler
 *
 * @file
 * @ingroup Hooks
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license GPL-2.0-or-later
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/**
	 * Add tables to the database
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$path = dirname( __DIR__ ) . '/sql';

		$updater->addExtensionTable( 'bounce_records', "$path/$type/tables-generated.sql" );

		if ( $type !== 'sqlite' ) {
			// 1.38
			$updater->modifyExtensionField(
				'bounce_records', 'br_timestamp', "$path/$type/patch-bounce_records-br_timestamp.sql"
			);
		}
	}
}
