<?php

namespace KeycloakAuth;

use MediaWiki\MediaWikiServices;
use RequestContext;

class Setup {
	/**
	 * extension.json callback
	 */
	public static function callback() {
		self::registerConstants();
		self::registerAuthRemoteuserSettings();
	}

	/**
	 * ExtensionFunctions callback to wire up Auth_remoteuser with this extension.
	 * This will only run if the variable $wgAuthRemoteuserUserName is left at its default (null),
	 * otherwise the user should manually inject Handler::getProcessProxyHeaders() into the variable
	 * at the appropriate point.
	 */
	public static function registerAuthRemoteuserSettings() {
		global $wgAuthRemoteuserUserPrefsForced, $wgAuthRemoteuserUserName, $wgAuthRemoteuserUserUrls;

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

		if ( $wgAuthRemoteuserUserUrls === null ) {
			$wgAuthRemoteuserUserUrls = [];
		}

		if ( !array_key_exists( 'logout', $wgAuthRemoteuserUserUrls ) ) {
			$wgAuthRemoteuserUserUrls['logout'] = static function () {
				$config = MediaWikiServices::getInstance()->getMainConfig();
				$request = RequestContext::getMain()->getRequest();
				$logoutUrl = $config->get( 'KeycloakAuthLogoutUrl' );
				$currentUrl = urlencode( $request->getFullRequestURL() );
				return str_replace( '$1', $currentUrl, $logoutUrl ) ?: 'Special:UserLogout';
			};
		}
	}

	/**
	 * Registers backwards-compat constants
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
