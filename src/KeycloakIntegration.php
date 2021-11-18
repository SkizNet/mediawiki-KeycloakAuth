<?php

namespace KeycloakAuth;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserFactory;
use MWException;
use ProxyLookup;
use RequestContext;
use stdClass;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

class KeycloakIntegration {
	public const CONSTRUCTOR_OPTIONS = [
		'KeycloakAuthEmailVariable',
		'KeycloakAuthInsecureHeaders',
		'KeycloakAuthUsernameVariable',
		'KeycloakAuthUuidVariable',
		'KeycloakAuthVariableType'
	];

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var ProxyLookup */
	private $proxyLookup;

	/** @var UserFactory */
	private $userFactory;

	/** @var ServiceOptions */
	private $options;

	/** @var string */
	private $username = '';

	/** @var string */
	private $email = '';

	/** @var bool */
	private $varsExtracted = false;

	/**
	 * Service constructor
	 *
	 * @param ILoadBalancer $loadBalancer
	 * @param ProxyLookup $proxyLookup
	 * @param UserFactory $userFactory
	 * @param ServiceOptions $options
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		ProxyLookup $proxyLookup,
		UserFactory $userFactory,
		ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->loadBalancer = $loadBalancer;
		$this->proxyLookup = $proxyLookup;
		$this->userFactory = $userFactory;
		$this->options = $options;
	}

	/**
	 * Retrieve the username from the request
	 *
	 * @return string Username (empty string if no username can be resolved)
	 * @throws MWException on error
	 */
	public function getUsername() {
		$this->extractVars();

		return $this->username;
	}

	/**
	 * Retrieve the email address from the request
	 *
	 * @return string Email address (empty string if no email can be resolved)
	 * @throws MWException on error
	 */
	public function getEmail() {
		$this->extractVars();

		return $this->email;
	}

	/**
	 * Helper to construct a User from a database row
	 *
	 * @param stdClass $row
	 * @return User
	 */
	private function userFromRow( stdClass $row ) {
		if ( method_exists( $this->userFactory, 'newFromRow' ) ) {
			// 1.36+
			// @phan-suppress-next-line PhanUndeclaredMethod
			return $this->userFactory->newFromRow( $row );
		} else {
			// 1.35
			return User::newFromRow( $row );
		}
	}

	/**
	 * Populate $this->username and $this->email
	 *
	 * @return void
	 * @throws MWException
	 */
	private function extractVars(): void {
		if ( $this->varsExtracted ) {
			return;
		}

		$this->varsExtracted = true;
		$requestContext = RequestContext::getMain();
		$request = $requestContext->getRequest();
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$varType = $this->options->get( 'KeycloakAuthVariableType' );
		$requestIP = null;
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$requestIP = IPUtils::canonicalize( $_SERVER['REMOTE_ADDR'] );
		}

		switch ( $varType ) {
			case 'header':
				if ( $requestIP === null ) {
					wfDebugLog( 'KeycloakAuth', 'Request IP is not specified, skipping auth.' );
					return;
				}

				if ( !$this->options->get( 'KeycloakAuthInsecureHeaders' )
					&& !$this->proxyLookup->isTrustedProxy( $requestIP )
				) {
					// Upstream request isn't a trusted proxy so the headers could be spoofed;
					// don't perform any auth here for security reasons
					wfDebugLog( 'KeycloakAuth', 'Upstream IP is not a trusted proxy, skipping auth.' );
					return;
				}

				$keycloakUuid = $request->getHeader( $this->options->get( 'KeycloakAuthUuidVariable' ) );
				$keycloakEmail = $request->getHeader( $this->options->get( 'KeycloakAuthEmailVariable' ) );
				$keycloakUsername = $request->getHeader( $this->options->get( 'KeycloakAuthUsernameVariable' ) );
				break;
			case 'env':
				$keycloakUuid = getenv( $this->options->get( 'KeycloakAuthUuidVariable' ) );
				$keycloakEmail = getenv( $this->options->get( 'KeycloakAuthEmailVariable' ) );
				$keycloakUsername = getenv( $this->options->get( 'KeycloakAuthUsernameVariable' ) );
				break;
			default:
				wfDebugLog( 'KeycloakAuth', "Invalid value '{$varType}' for \$wgKeycloakAuthVariableType." );
				return;
		}

		if ( $keycloakUuid === false || $keycloakEmail === false || $keycloakUsername === false ) {
			// no Keycloak variables found
			wfDebugLog( 'KeycloakAuth', 'Keycloak variables are missing, skipping auth.' );
			return;
		}

		// usernames are case-sensitive except for the initial character
		// the database will always force that to uppercase, so apply the same transformation here
		$keycloakUsername = ucfirst( $keycloakUsername );

		// check if already mapped
		$result = $dbw->selectRow(
			[ 'keycloak_user', 'user' ],
			'*',
			[ 'ku_uuid' => $keycloakUuid ],
			__METHOD__,
			[],
			[ 'user' => [ 'JOIN', 'ku_user = user_id' ] ]
		);

		if ( $result !== false ) {
			$user = $this->userFromRow( $result );
			$this->username = $user->getName();
			$this->email = $user->getEmail();
			wfDebugLog( 'KeycloakAuth', "Matched $keycloakUuid <=> {$this->username}" );
			return;
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
			$user = $this->userFromRow( $row );
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

			$this->username = $matchedUser->getName();
			$this->email = $matchedUser->getEmail();
			wfDebugLog( 'KeycloakAuth', "Matched $keycloakEmail <=> {$matchedUser->getName()}" );
			return;
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

		$this->username = $keycloakUsername;
		$this->email = $keycloakEmail;
		wfDebugLog( 'KeycloakAuth', "Authenticating as new user $keycloakUsername" );
	}
}
