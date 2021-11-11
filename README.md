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
