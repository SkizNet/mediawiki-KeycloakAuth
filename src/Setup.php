<?php

namespace KeycloakAuth;

use MediaWiki\MediaWikiServices;

class Setup {
	/**
	 * ExtensionFunctions callback to wire up Auth_remoteuser with this extension.
	 * This will only run if the variable $wgAuthRemoteuserUserName is left at its default (null),
	 * otherwise the user should manually inject Handler::getProcessProxyHeaders() into the variable
	 * at the appropriate point.
	 */
	public static function registerAuthRemoteuserSettings() {
		global $wgAuthRemoteuserUserName, $wgAuthRemoteuserUserPrefsForced;

		if ( $wgAuthRemoteuserUserName === null ) {
			$wgAuthRemoteuserUserName = static function () {
				/** @var KeycloakIntegration $keycloakIntegration */
				$keycloakIntegration = MediaWikiServices::getInstance()->getService( 'KeycloakIntegration' );
				return $keycloakIntegration->getUsername();
			};
		}

		if ( $wgAuthRemoteuserUserPrefsForced === null ) {
			$wgAuthRemoteuserUserPrefsForced = [];
		}

		if ( !array_key_exists( 'email', $wgAuthRemoteuserUserPrefsForced ) ) {
			$wgAuthRemoteuserUserPrefsForced['email'] = static function () {
				/** @var KeycloakIntegration $keycloakIntegration */
				$keycloakIntegration = MediaWikiServices::getInstance()->getService( 'KeycloakIntegration' );
				return $keycloakIntegration->getEmail();
			};
		}
	}

	/**
	 * extension.json callback, registers backwards-compat constants
	 *
	 * @noinspection PhpDeprecationInspection
	 */
	public static function registerConstants() {
		// 1.35 compat
		if ( !defined( 'DB_PRIMARY' ) ) {
			// phpcs:disable MediaWiki.Usage.DeprecatedConstantUsage.DB_MASTER
			define( 'DB_PRIMARY', DB_MASTER );
		}
	}
}
