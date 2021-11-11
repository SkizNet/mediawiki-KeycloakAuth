<?php

namespace KeycloakAuth;

use MediaWiki\User\UserFactory;
use MWException;
use ProxyLookup;
use RequestContext;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class KeycloakIntegration {
	private const UUID_HEADER = 'X-Forwarded-User';
	private const EMAIL_HEADER = 'X-Forwarded-Email';
	private const NAME_HEADER = 'X-Forwarded-Preferred-Username';

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var ProxyLookup */
	private $proxyLookup;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * Service constructor
	 *
	 * @param ILoadBalancer $loadBalancer
	 * @param ProxyLookup $proxyLookup
	 * @param UserFactory $userFactory
	 */
	public function __construct( ILoadBalancer $loadBalancer, ProxyLookup $proxyLookup, UserFactory $userFactory ) {
		$this->loadBalancer = $loadBalancer;
		$this->proxyLookup = $proxyLookup;
		$this->userFactory = $userFactory;
	}

	/**
	 * Retrieve the username from request headers
	 *
	 * @return false|string Username, or false if no username can be resolved
	 * @throws MWException on error
	 */
	public function getUsername() {
		$requestContext = RequestContext::getMain();
		$request = $requestContext->getRequest();
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );

		if ( !$this->proxyLookup->isTrustedProxy( $request->getIP() ) ) {
			// Upstream request isn't a trusted proxy so the headers could be spoofed;
			// don't perform any auth here for security reasons
			wfDebugLog( 'KeycloakAuth', 'Upstream IP is not a trusted proxy, skipping auth.' );
			return false;
		}

		$keycloakUuid = $request->getHeader( self::UUID_HEADER );
		$keycloakEmail = $request->getHeader( self::EMAIL_HEADER );
		$keycloakUsername = $request->getHeader( self::NAME_HEADER );

		if ( $keycloakUuid === false || $keycloakEmail === false || $keycloakUsername === false ) {
			// no Keycloak headers found
			wfDebugLog( 'KeycloakAuth', 'Keycloak headers are missing, skipping auth.' );
			return false;
		}

		// usernames are case-sensitive except for the initial character
		// the database will always force that to uppercase, so apply the same transformation here
		$keycloakUsername = ucfirst( $keycloakUsername );

		// check if already mapped
		$mappedUserName = $dbw->selectField(
			[ 'keycloak_user', 'user' ],
			'user_name',
			[ 'ku_uuid' => $keycloakUuid ],
			__METHOD__,
			[],
			[ 'user' => [ 'JOIN', 'ku_user = user_id' ] ]
		);

		if ( $mappedUserName !== false ) {
			wfDebugLog( 'KeycloakAuth', "Matched $keycloakUuid <=> $mappedUserName" );
			return $mappedUserName;
		}

		// check for an email match
		$result = $dbw->select(
			'user',
			'*',
			[ 'user_email' => $keycloakEmail ],
			__METHOD__
		);

		$matchedUsers = [];
		$matchedUser = null;
		foreach ( $result as $row ) {
			if ( method_exists( $this->userFactory, 'newFromRow' ) ) {
				// 1.36+
				// @phan-suppress-next-line PhanUndeclaredMethod
				$user = $this->userFactory->newFromRow( $row );
			} else {
				// 1.35
				$user = User::newFromRow( $row );
			}

			if ( !$user->isEmailConfirmed() ) {
				continue;
			}

			$matchedUsers[] = $user;
		}

		$count = count( $matchedUsers );
		wfDebugLog( 'KeycloakAuth', "Found $count matches for $keycloakEmail" );

		if ( $count === 1 ) {
			$matchedUser = $matchedUsers[0];
		} elseif ( $count > 1 ) {
			// multiple users registered with the same email;
			// check for a matching Keycloak username
			// otherwise fail loudly since we don't know how to map this user
			foreach ( $matchedUsers as $user ) {
				if ( $user->getName() === $keycloakUsername ) {
					$matchedUser = $user;
					break;
				}
			}
		}

		if ( $matchedUser !== null ) {
			$dbw->insert(
				'keycloak_user',
				[
					'ku_uuid' => $keycloakUuid,
					'ku_user' => $matchedUser->getId()
				],
				__METHOD__
			);

			wfDebugLog( 'KeycloakAuth', "Matched $keycloakEmail <=> {$matchedUser->getName()}" );
			return $matchedUser->getName();
		}

		// no matches, use their preferred username to create a new user
		// for security, we don't allow this to target existing users
		$existingUser = $dbw->selectField(
			'user',
			'user_id',
			[ 'user_name' => $keycloakUsername ],
			__METHOD__
		);

		if ( $existingUser !== false ) {
			throw new MWException(
				"User \"{$keycloakUsername}\" already exists but doesn't have email {$keycloakEmail}."
				. " Authentication aborted for security reasons."
			);
		}

		wfDebugLog( 'KeycloakAuth', "Authenticating as new user $keycloakUsername" );
		return $keycloakUsername;
	}

	/**
	 * Retrieve the email address from Keycloak headers
	 *
	 * @return string Email address
	 * @throws MWException on error
	 */
	public function getEmail() {
		$requestContext = RequestContext::getMain();
		$request = $requestContext->getRequest();

		if ( !$this->proxyLookup->isTrustedProxy( $request->getIP() ) ) {
			throw new MWException(
				'Cannot trust X-Forwarded-Email header as request did not come from a trusted proxy'
			);
		}

		$email = $request->getHeader( self::EMAIL_HEADER );
		if ( $email === false ) {
			throw new MWException( 'X-Forwarded-Email header not found' );
		}

		return $email;
	}
}
