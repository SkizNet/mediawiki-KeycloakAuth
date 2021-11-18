# KeycloakAuth

This extension configures MediaWiki to accept Keycloak authentication headers as presented by oauth2_proxy.

## Requirements

- MediaWiki 1.35+
- MySQL/MariaDB, Postgres, or SQLite
- The Auth_remoteuser MediaWiki extension

## Installation

1. Download the extension to your `extensions/` directory.
2. Add `wfLoadExtension( 'KeycloakAuth' );` to your LocalSettings.php
3. Run `maintenance/update.php` to initialize extension tables in the database
4. Verify the extension is installed by visiting Special:Version

## Configuration

The extension will automatically configure Auth_remoteuser.
Any changes made by the user to Auth_remoteuser configuration may cause the extension
to not work as anticipated.

In addition to the configuration below, you will need to ensure that your oauth2_proxy instance is recognized
by MediaWiki as a trusted proxy. You do this by listing its IP or IP range in CIDR format in `$wgCdnServersNoPurge`
([see documentation on mediawiki.org](https://www.mediawiki.org/wiki/Manual:$wgCdnServersNoPurge)).
For example, the following will mark the IP address `10.2.3.4` as a trusted proxy server.
```php
$wgCdnServersNoPurge = [
	'10.2.3.4/32'
];
```

In most cases, you will also want to ensure that users cannot create local wiki accounts, but are able to
have accounts created on their behalf by the KeycloakAuth extension:
```php
// MediaWiki's default configuration allows * and sysop
// to create local accounts; remove that capability
$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['sysop']['createaccount'] = false;
// Ensure that everyone can have accounts created
// automatically on their behalf
$wgGroupPermissions['*']['autocreateaccount'] = true;
```

### $wgKeycloakAuthEmailVariable
*Default:* `'X-Forwarded-Email'`

The variable (header or environment variable) used to determine the user's email address.

### $wgKeycloakAuthInsecureHeaders
*Default:* `false`

Allow reading variables from HTTP headers even if the upstream IP is not a trusted proxy.
**This is a security risk and should only be enabled after careful examination of the webserver environment.**

### $wgKeycloakAuthLoginUrl
*Default:* `''`

If set, the "Log in" URL will be replaced with this value when the wiki is viewed by a logged-out user.
This should be set to the URL needed to initiate the login flow in oauth2_proxy.

### $wgKeycloakAuthLogoutUrl
*Default:* `''`

If set, the "Log out" URL will lead here when a user has been logged in via KeycloakAuth. Users logged in
through other means will not be impacted. If unset, the "Log out" link will be removed for users logged in
via KeycloakAuth.

### $wgKeycloakAuthPortalUrl
*Default:* `''`

If set, the extension will add a button to Special:Preferences leading the user to this URL.
This should be set to the Keycloak portal where they can view and update their email and password.
If left as the default (an empty string), no button will be added to Special:Preferences.

### $wgKeycloakAuthUsernameVariable
*Default:* `'X-Forwarded-Preferred-Username'`

The variable (header or environment variable) used to determine the user's username.
This value is not used if the Keycloak UUID is already mapped to a MediaWiki user.

### $wgKeycloakAuthUuidVariable
*Default:* `'X-Forwarded-User'`

The variable (header or environment variable) used to determine the user's Keycloak UUID.

### $wgKeycloakAuthVariableType
*Default:* `'header'`

- If set to `'header'`, the variables will be read from incoming HTTP headers.
- If set to `'env'`, the variables will be read from environment variables.

By default, the extension will refuse to operate if this is set to `'header'` and the upstream
IP is not a trusted proxy.
