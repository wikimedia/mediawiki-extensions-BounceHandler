<?php
namespace MediaWiki\Extension\BounceHandler;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Schema Hooks used by BounceHandler
 *
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

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-bouncehandler',
			'addTable',
			'bounce_records',
			"$path/$type/tables-generated.sql",
			true
		] );

		if ( $type !== 'sqlite' ) {
			// 1.38
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-bouncehandler',
				'modifyField',
				'bounce_records',
				'br_timestamp',
				"$path/$type/patch-bounce_records-br_timestamp.sql",
				true
			] );
		}
	}
}
