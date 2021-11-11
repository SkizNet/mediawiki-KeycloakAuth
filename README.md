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

### $wgKeycloakAuthPortalUrl
*Default:* `''`

If set, the extension will add a button to Special:Preferences leading the user to this URL.
This should be set to the Keycloak portal where they can view and update their email and password.
If left as the default (an empty string), no button will be added to Special:Preferences.

### $wgKeycloakAuthLoginUrl
*Default:* `''`

If set, the "Log in" URL will be replaced with this value when the wiki is viewed by a logged-out user.
This should be set to the URL needed to initiate the login flow in oauth2_proxy.

### $wgKeycloakAuthLogoutUrl
*Default:* `''`

If set, the "Log out" URL will lead here when a user has been logged in via KeycloakAuth. Users logged in
through other means will not be impacted. If unset, the "Log out" link will be removed for users logged in
via KeycloakAuth.
