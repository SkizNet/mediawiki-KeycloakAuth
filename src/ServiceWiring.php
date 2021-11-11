<?php

use KeycloakAuth\KeycloakIntegration;
use MediaWiki\MediaWikiServices;

return [
	'KeycloakIntegration' => static function ( MediaWikiServices $services ): KeycloakIntegration {
		return new KeycloakIntegration(
			$services->getDBLoadBalancer(),
			$services->getProxyLookup(),
			$services->getUserFactory()
		);
	}
];
