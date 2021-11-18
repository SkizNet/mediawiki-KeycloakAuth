<?php

use KeycloakAuth\KeycloakIntegration;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;

return [
	'KeycloakIntegration' => static function ( MediaWikiServices $services ): KeycloakIntegration {
		return new KeycloakIntegration(
			$services->getDBLoadBalancer(),
			$services->getProxyLookup(),
			$services->getUserFactory(),
			new ServiceOptions(
				KeycloakIntegration::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	}
];
