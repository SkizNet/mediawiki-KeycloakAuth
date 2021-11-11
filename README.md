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

None. The extension will automatically configure Auth_remoteuser.
Any changes made by the user to Auth_remoteuser configuration may cause the extension
to not work as anticipated.
