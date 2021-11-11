<?php

namespace KeycloakAuth;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use RuntimeException;

class Hooks implements LoadExtensionSchemaUpdatesHook {
	/**
	 * Create our keycloak_user table.
	 *
	 * This hook cannot exist in a class that takes service dependencies!
	 *
	 * @param DatabaseUpdater $updater DatabaseUpdater subclass
	 * @return void To continue hook execution
	 *
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();

		switch ( $type ) {
			case 'mysql':
			case 'postgres':
			case 'sqlite':
				break;
			default:
				throw new RuntimeException( "The KeycloakAuth extension does not support the $type database" );
		}

		$updater->addExtensionTable(
			'keycloak_user',
			dirname( __FILE__, 2 ) . "/sql/$type/keycloak_user.sql"
		);
	}
}
