=== OpenID Connect Generic Client ===
Contributors: daggerhart, tnolte
Tags: security, login, oauth2, openidconnect, apps, authentication, autologin, sso
Requires at least: 6.7
Tested up to: 7.1
Stable tag: 3.12.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

== Description ==

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

Please submit issues to the Github repo: https://github.com/oidc-wp/openid-connect-generic

== Installation ==

1. In WordPress, go to Plugins > Add New, search for "OpenID Connect Generic", then install and activate it.
1. Go to Settings > OpenID Connect Client and configure it for your identity provider.

To install manually, download the zip from https://wordpress.org/plugins/daggerhart-openid-connect-generic/ and upload it under Plugins > Add New > Upload Plugin. Don't use GitHub's "Download ZIP": it doesn't include the plugin's dependencies.

= Installing with Composer =

* WPackagist: `composer require wpackagist-plugin/daggerhart-openid-connect-generic` installs the WordPress.org package.
* Packagist: `composer require daggerhart/openid-connect-generic` installs the dependencies in your project's `vendor/` folder, so your site must load the project's `vendor/autoload.php` (Bedrock does). Supported from 3.12.0.

= Installing from source =

Clone the repository and run `composer install --no-dev` in the plugin folder before activating it.

== Frequently Asked Questions ==

= What is the client's Redirect URI? =

Most OAuth2 servers will require whitelisting a set of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize

Replace `example.com` with your domain name and path to WordPress.

= Can I change the client's Redirect URI? =

Some OAuth2 servers do not allow for a client redirect URI to contain a query string. The default URI provided by
this module leverages WordPress's `admin-ajax.php` endpoint as an easy way to provide a route that does not include
HTML, but this will naturally involve a query string. Fortunately, this plugin provides a setting that will make use of
an alternate redirect URI that does not include a query string.

On the settings page for this plugin (Dashboard > Settings > OpenID Connect Generic) there is a checkbox for
**Alternate Redirect URI**. When checked, the plugin will use the Redirect URI
`https://example.com/openid-connect-authorize`.

== Upgrade Notice ==

= 3.12.0 =

Requires PHP 8.2 and WordPress 6.7. Check your PHP version before updating. If you use Alternate Redirect URI with WordPress in a subdirectory, the redirect URI changes: an admin notice shows the new one to allow at your identity provider.

= 3.11.5 =

Fixes logins failing with "Invalid state" on Pantheon and some other managed hosts after updating to 3.11.4.

= 3.11.4 =

SECURITY UPDATE: Hardens login and account linking. Requires PHP 8.0. If you use Link Existing Users, review the new Link Users Without Verified Email setting after updating.

= 3.11.3 =

SECURITY UPDATE: 3.11.x branch - Fixes authentication vulnerabilities including JWT signature bypass and SSRF protection. Update immediately and configure JWKS endpoint in settings.

== Changelog ==

= 3.12.0 =

* Requirements: Minimum PHP raised to 8.2 and minimum WordPress to 6.7. Tested up to WordPress 7.1.
* Fix: Microsoft Entra ID multitenant apps failed issuer validation. Set the Issuer to `https://login.microsoftonline.com/{tenantid}/v2.0`, and `{tenantid}` is replaced with each token's tenant ID (#682).
* Fix: Logins failed with "Incorrect user claim" when the IDP sent the userinfo `sub` as a number and the ID token `sub` as a string. A missing or empty `sub` is now rejected (#673).
* Fix: Refresh tokens weren't used since 3.10.4, because the refresh check was added to `init` too late to run. With Enable Refresh Token on (the default), access tokens are refreshed again when they expire, and a user is logged out if the identity provider rejects the refresh (#675).
* Fix: The token request no longer sends `scope`, which some identity providers, such as OpenIddict, reject. Providers that need it can add it back with the `openid-connect-generic-alter-request` filter (#497).
* Fix: Sites that install the plugin with Composer had a fatal error on load since 3.11.0, because the plugin required its own `vendor/autoload.php`. It now uses the project's autoloader when its own `vendor/` folder is missing, and shows an admin notice instead of a fatal error if its dependencies can't be found (#655).
* Fix: With Alternate Redirect URI enabled, sites with WordPress in a subdirectory (such as Bedrock) sent a callback URL under the WordPress directory, which returned a 404. The callback URL is now relative to the site's home URL (#666). Affected sites see an admin notice with the old and new redirect URIs after updating.
* Improvement: Settings and the logger read their option name from `get_option_name()`, so a subclass can store them under a different option (#667).
* Feature: New `openid-connect-generic-redirect-uri` filter to change the redirect URI sent to the identity provider.
* Feature: New Shortcode Only login type, which leaves the login button off the login page while the `[openid_connect_generic_login_button]` shortcode still works (#621).
* Feature: New Login Button Icon setting, an optional icon shown before the button text and drawn in the button's text colour (#683).

= 3.11.5 =

* Fix: Logins failed with "Invalid state" on hosts that only pass WordPress-style cookie names to PHP, such as Pantheon. The login cookie is now named `wp-openid_connect_generic_browser`, and the new `openid-connect-generic-browser-cookie-name` filter can change it.
* Improvement: When logging is enabled, a failed state check logs which part of the check failed.

= 3.11.4 =

* Security: Login state is now tied to the browser that started the login and can only be used once.
* Security: Authorization requests now include a nonce that is checked against the ID token.
* Security: Added a "Use PKCE" setting. It is on for new installs and off for existing sites until enabled.
* Security: Link Existing Users now requires the identity provider to confirm the email address is verified. Added a "Link Users Without Verified Email" setting for identity providers that don't send this (such as Microsoft Entra ID) and for linking by user name. Sites already linking users have it turned on during the update and see an admin notice asking them to review it.
* Security: Updated firebase/php-jwt to 7.0.
* Fix: Avoid a fatal error when the redirect_to request value is not a string.
* Requirements: Corrected Requires PHP to 8.0. The plugin has required PHP 8.0 since 3.10.5.

= 3.11.3 =

* Feature/improvement: Added configurable issuer setting for JWT validation.

= 3.11.2 =

* Improvement: Support identity providers that omit algorithm parameter in JWKS (Microsoft Entra ID).

= 3.11.1 =

* Fix bug created in 3.11.0 release when comparing issuer to derived expected value.

= 3.11.0 =

**SECURITY RELEASE**

* Security: Added JWT signature verification using JWKS to prevent token forgery
* Security: Enhanced token claim validation (exp, aud, iss, iat)
* Security: Replaced weak state generation with cryptographically secure random_bytes()
* Security: Fixed open redirect vulnerability in authentication flow
* Security: Restricted SSL verification bypass to local development environments only
* Security: Added nonce protection to debug mode to prevent information disclosure
* Security: Added SSRF protection by default through use of wp_safe_remote_* functions
* Feature: Added JWKS endpoint configuration setting
* Feature: Added OpenID Connect discovery document support
* Feature: Added customizable login button text setting
* Improvement: Migrated to Composer-managed dependencies
* Fix: Corrected issuer validation to properly extract base URL from endpoints
* Fix: Identity token timestamp tracking

= 3.10.4 =

* Fix issue with finding users on multisite after switch to user options in place of user meta.
* Improvement: Retry logins for some IDP errors to bypass issue with Safari ITP. Also improves display of error messages that come from the IDP.

= 3.10.3 =

* Fix issue with log corruption causing fatal error.
* Fix: Fallback to a POST request for userinfo when GET fails.
* Fix: Improves multisite compatibility by switching to *_user_options() functions.
* Fix: Fix for WordPress user session length being very short when refresh tokens are enabled.

= 3.10.2 =

* Fix: @socialmedialabs - Regression affecting SSO Auto Login with url handling improvement changes.

= 3.10.1 =

* Chore: @daggerhart - Readme updates and clarifications.
* Chore: @daggerhart - Release workflow updates.
* Improved error handling for malformed urls.
* Fix: @JUVOJustin - Change request for userinfo to GET.
* Feature: @JUVOJustin - New filter for settings values `openid-connect-generic-settings`.
* Feature: @JUVOJustin - New filter for state values `openid-connect-generic-new-state-value`.

= 3.10.0 =

* Chore: @timnolte - Dependency updates.
* Fix: @drzraf - Prevents running the auth url filter twice.
* Fix: @timnolte - Updates the log cleanup handling to properly retain the configured number of log entries.
* Fix: @timnolte - Updates the log display output to reflect the log retention policy.
* Chore: @timnolte - Adds Unit Testing & New Local Development Environment.
* Feature: @timnolte - Updates logging to allow for tracking processing time.
* Feature: @menno-ll - Adds a remember me feature via a new filter.
* Improvement: @menno-ll - Updates WP Cookie Expiration to Same as Session Length.

= 3.9.1 =

* Improvement: @timnolte - Refactors Composer setup and GitHub Actions.
* Improvement: @timnolte - Bumps WordPress tested version compatibility.

= 3.9.0 =

* Feature: @matchaxnb - Added support for additional configuration constants.
* Feature: @schanzen - Added support for agregated claims.
* Fix: @rkcreation - Fixed access token not updating user metadata after login.
* Fix: @danc1248 - Fixed user creation issue on Multisite Networks.
* Feature: @RobjS - Added plugin singleton to support for more developer customization.
* Feature: @jkouris - Added action hook to allow custom handling of session expiration.
* Fix: @tommcc - Fixed admin CSS loading only on the plugin settings screen.
* Feature: @rkcreation - Added method to refresh the user claim.
* Feature: @Glowsome - Added acr_values support & verification checks that it when defined in options is honored.
* Fix: @timnolte - Fixed regression which caused improper fallback on missing claims.
* Fix: @slykar - Fixed missing query string handling in redirect URL.
* Fix: @timnolte - Fixed issue with some user linking and user creation handling.
* Improvement: @timnolte - Fixed plugin settings typos and screen formatting.
* Security: @timnolte - Updated build tooling security vulnerabilities.
* Improvement: @timnolte - Changed build tooling scripts.

= 3.8.5 =

* Fix: @timnolte - Fixed missing URL request validation before use & ensure proper current page URL is setup for Redirect Back.
* Fix: @timnolte - Fixed Redirect URL Logic to Handle Sub-directory Installs.
* Fix: @timnolte - Fixed issue with redirecting user back when the openid_connect_generic_auth_url shortcode is used.

= 3.8.4 =

* Fix: @timnolte - Fixed invalid State object access for redirection handling.
* Improvement: @timnolte - Fixed local wp-env Docker development environment.
* Improvement: @timnolte - Fixed Composer scripts for linting and static analysis.

= 3.8.3 =

* Fix: @timnolte - Fixed problems with proper redirect handling.
* Improvement: @timnolte - Changes redirect handling to use State instead of cookies.
* Improvement: @timnolte - Refactored additional code to meet coding standards.

= 3.8.2 =

* Fix: @timnolte - Fixed reported XSS vulnerability on WordPress login screen.

= 3.8.1 =

* Fix: @timnolte - Prevent SSO redirect on password protected posts.
* Fix: @timnolte - CI/CD build issues.
* Fix: @timnolte - Invalid redirect handling on logout for Auto Login setting.

= 3.8.0 =

* Feature: @timnolte - Ability to use 6 new constants for setting client configuration instead of storing in the DB.
* Improvement: @timnolte - Plugin development & contribution updates.
* Improvement: @timnolte - Refactored to meet WordPress coding standards.
* Improvement: @timnolte - Refactored to provide localization.

--------

[See the previous changelogs here](https://github.com/oidc-wp/openid-connect-generic/blob/main/CHANGELOG.md#changelog)
