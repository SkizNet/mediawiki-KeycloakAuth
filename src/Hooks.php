<?php

namespace KeycloakAuth;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use OOUI\ButtonWidget;
use RuntimeException;
use User;

class Hooks implements GetPreferencesHook, LoadExtensionSchemaUpdatesHook {
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

	/**
	 * Add a button leading to the shared account management portal, if specified.
	 *
	 * @param User $user User whose preferences are being modified
	 * @param array &$preferences Preferences description array, to be fed to an HTMLForm object
	 * @return void To continue hook execution
	 *
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$portalUrl = $config->get( 'KeycloakAuthPortalUrl' );
		$sessionProvider = (string)$user->getRequest()->getSession()->getProvider();

		if (
			$portalUrl === ''
			|| $sessionProvider !== 'MediaWiki\Extension\Auth_remoteuser\AuthRemoteuserSessionProvider'
		) {
			// not configured or not using Auth_remoteuser
			return;
		}

		$button = new ButtonWidget( [
			'href' => $portalUrl,
			'label' => wfMessage( 'keycloakauth-prefs-button' )->text()
		] );

		$preferences['keycloakauth'] = [
			'section' => 'personal/info',
			'type' => 'info',
			'raw' => true,
			'label-message' => 'keycloakauth-prefs-label',
			'help-message' => 'keycloakauth-prefs-help',
			'default' => (string)$button
		];
	}
}
