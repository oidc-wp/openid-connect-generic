# OpenID Connect Generic Client #
**Contributors:** [daggerhart](https://profiles.wordpress.org/daggerhart), [tnolte](https://profiles.wordpress.org/tnolte)  
**Donate link:** http://www.daggerhart.com/  
**Tags:** security, login, oauth2, openidconnect, apps, authentication, autologin, sso  
**Requires at least:** 4.9  
**Tested up to:** 5.7.1  
**Stable tag:** 3.8.5  
**Requires PHP:** 7.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

## Description ##

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

Please submit issues to the Github repo: https://github.com/daggerhart/openid-connect-generic

## Installation ##

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

## Frequently Asked Questions ##

### What is the client's Redirect URI? ###

Most OAuth2 servers will require whitelisting a set of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize

Replace `example.com` with your domain name and path to WordPress.

### Can I change the client's Redirect URI? ###

Some OAuth2 servers do not allow for a client redirect URI to contain a query string. The default URI provided by
this module leverages WordPress's `admin-ajax.php` endpoint as an easy way to provide a route that does not include
HTML, but this will naturally involve a query string. Fortunately, this plugin provides a setting that will make use of
an alternate redirect URI that does not include a query string.

On the settings page for this plugin (Dashboard > Settings > OpenID Connect Generic) there is a checkbox for
**Alternate Redirect URI**. When checked, the plugin will use the Redirect URI
`https://example.com/openid-connect-authorize`.


## Changelog ##

### 3.8.5

* Fix: @timnolte - Fixes missing URL request validation before use & ensure proper current page URL is setup for Redirect Back.
* Fix: @timnolte - Fixes Redirect URL Logic to Handle Sub-directory Installs.
* Fix: @timnolte - Fixes to provide proper redirect user back for the openid_connect_generic_auth_url shortcode.
###
### 3.8.4 ###

* Fix: @timnolte - Fixed invalid State object access for redirection handling.
* Improvement: @timnolte - Fixed local wp-env Docker development environment.
* Improvement: @timnolte - Fixed Composer scripts for linting and static analysis.

### 3.8.3 ###

* Fix: @timnolte - Fixed problems with proper redirect handling.
* Improvement: @timnolte - Changes redirect handling to use State instead of cookies.
* Improvement: @timnolte - Refactored additional code to meet coding standards.

### 3.8.2 ###

* Fix: @timnolte - Fixed reported XSS vulnerability on WordPress login screen.

### 3.8.1 ###

* Fix: @timnolte - Prevent SSO redirect on password protected posts.
* Fix: @timnolte - CI/CD build issues.
* Fix: @timnolte - Invalid redirect handling on logout for Auto Login setting.

### 3.8.0 ###

* Feature: @timnolte - Ability to use 6 new constants for setting client configuration instead of storing in the DB.
* Improvement: @timnolte - Plugin development & contribution updates.
* Improvement: @timnolte - Refactored to meet WordPress coding standards.
* Improvement: @timnolte - Refactored to provide localization.

### 3.7.1 ###

* Fix: Release Version Number.

### 3.7.0 ###

* Feature: @timnolte - Ability to enable/disable token refresh. Useful for IDPs that don't support token refresh.
* Feature: @timnolte - Support custom redirect URL(`redirect_to`) with the authentication URL & login button shortcodes.
  - Supports additional attribute overrides including login `button_text`, `endpoint_login`, `scope`, `redirect_uri`.

### 3.6.0 ###

* Improvement: @RobjS - Improved error messages during login state failure.
* Improvement: @RobjS - New developer filter for login form button URL.
* Fix: @cs1m0n - Only increment username during new user creation if the "Link existing user" setting is enabled.
* Fix: @xRy-42 - Allow periods and spaces in usernames to match what WordPress core allows.
* Feature: @benochen - New setting named "Create user if does not exist" determines whether new users are created during login attempts.
* Improvement: @flat235 - Username transliteration and normalization.

### 3.5.1 ###

* Fix: @daggerhart - New approach to state management using transients.

### 3.5.0 ###

* Readme fix: @thijskh - Fix syntax error in example openid-connect-generic-login-button-text
* Feature: @slavicd - Allow override of the plugin by posting credentials to wp-login.php
* Feature: @gassan - New action on use login
* Fix: @daggerhart - Avoid double question marks in auth url query string
* Fix: @drzraf - wp-cli bootstrap must not inhibit custom rewrite rules
* Syntax change: @mullikine - Change PHP keywords to comply with PSR2

### 3.4.1 ###

* Minor documentation update and additional error checking.

### 3.4.0 ###

* Feature: @drzraf - New filter hook: ability to filter claim and derived user data before user creation.
* Feature: @anttileppa - State time limit can now be changed on the settings page.
* Fix: @drzraf - Fix PHP notice when using traditional login, $token_response may be empty.
* Fix: @drzraf - Fixed a notice when cookie does not contain expected redirect_url

### 3.3.1 ###

* Prefixing classes for more efficient autoloading.
* Avoid altering global wp_remote_post() parameters.
* Minor metadata updates for wp.org

### 3.3.0 ###

* Fix: @pjeby - Handle multiple user sessions better by using the `WP_Session_Tokens` object. Predecessor to fixes for multiple other issues: #49, #50, #51

### 3.2.1 ###

* Bug fix: @svenvanhal - Exit after issuing redirect. Fixes #46

### 3.2.0 ###

* Feature: @robbiepaul - trigger core action `wp_login` when user is logged in through this plugin
* Feature: @moriyoshi - Determine the WP_User display name with replacement tokens on the settings page. Tokens can be any property of the user_claim.
* Feature: New setting to set redirect URL when session expires.
* Feature: @robbiepaul - New filter for modifying authentication URL
* Fix: @cedrox - Adding id_token_hint to logout URL according to spec
* Bug fix: Provide port to the request header when requesting the user_claim

### 3.1.0 ###

* Feature: @rwasef1830 - Refresh tokens
* Feature: @rwasef1830 - Integrated logout support with end_session endpoint
* Feature: May use an alternate redirect_uri that doesn't rely on admin-ajax
* Feature: @ahatherly - Support for IDP behind reverse proxy
* Bug fix: @robertstaddon - case insensitive check for Bearer token
* Bug fix: @rwasef1830 - "redirect to origin when auto-sso" cookie issue
* Bug fix: @rwasef1830 - PHP Warnings headers already sent due to attempts to redirect and set cookies during login form message
* Bug fix: @rwasef1830 - expire session when access_token expires if no refresh token found
* UX fix: @rwasef1830 - Show login button on error redirect when using auto-sso

### 3.0.8 ###

* Feature: @wgengarelly - Added `openid-connect-generic-update-user-using-current-claim` action hook allowing other plugins/themes
  to take action using the fresh claims received when an existing user logs in.

### 3.0.7 ###

* Bug fix: @wgengarelly - When requesting userinfo, send the access token using the Authorization header field as recommended in
section 5.3.1 of the specs.

### 3.0.6 ###

* Bug fix: @robertstaddon - If "Link Existing Users" is enabled, allow users who login with OpenID Connect to also log in with WordPress credentials

### 3.0.5 ###

* Feature: @robertstaddon - Added `[openid_connect_generic_login_button]` shortcode to allow the login button to be placed anywhere
* Feature: @robertstaddon - Added setting to "Redirect Back to Origin Page" after a successful login instead of redirecting to the home page.

### 3.0.4 ###

* Feature: @robertstaddon - Added setting to allow linking existing WordPress user accounts with newly-authenticated OpenID Connect login

### 3.0.3 ###

* Using WordPresss's is_ssl() for setcookie()'s "secure" parameter
* Bug fix: Incrementing username in case of collision.
* Bug fix: Wrong error sent when missing token body

### 3.0.2 ###

* Added http_request_timeout setting

### 3.0.1 ###

* Finalizing 3.0.x api

### 3.0 ###

* Complete rewrite to separate concerns
* Changed settings keys for clarity (requires updating settings if upgrading from another version)
* Error logging

### 2.1 ###

* Working my way closer to spec. Possible breaking change.  Now checking for preferred_username as priority.
* New username determination to avoid collisions

### 2.0 ###

Complete rewrite


