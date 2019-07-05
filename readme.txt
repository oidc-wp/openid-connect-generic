=== OpenID Connect Generic Client ===
Contributors: daggerhart
Donate link: http://www.daggerhart.com/
Tags: security, login, oauth2, openidconnect, apps, authentication, autologin, sso 
Requires at least: 4
Tested up to: 5.2.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

== Description ==

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while 
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

Please submit issues to the Github repo: https://github.com/daggerhart/openid-connect-generic

== Installation ==

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

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


== Changelog ==

= 3.5.0 =

* Readme fix: @thijskh - Fix syntax error in example openid-connect-generic-login-button-text
* Feature: @slavicd - Allow override of the plugin by posting credentials to wp-login.php
* Feature: @gassan - New action on use login
* Fix: @daggerhart - Avoid double question marks in auth url query string
* Fix: @drzraf - wp-cli bootstrap must not inhibit custom rewrite rules
* Syntax change: @mullikine - Change PHP keywords to comply with PSR2

= 3.4.1 =

* Minor documentation update and additional error checking.

= 3.4.0 =

* Feature: @drzraf - New filter hook: ability to filter claim and derived user data before user creation.
* Feature: @anttileppa - State time limit can now be changed on the settings page.
* Fix: @drzraf - Fix PHP notice when using traditional login, $token_response may be empty.
* Fix: @drzraf - Fixed a notice when cookie does not contain expected redirect_url

= 3.3.1 =

* Prefixing classes for more efficient autoloading.
* Avoid altering global wp_remote_post() parameters.
* Minor metadata updates for wp.org

= 3.3.0 =

* Fix: @pjeby - Handle multiple user sessions better by using the `WP_Session_Tokens` object. Predecessor to fixes for multiple other issues: #49, #50, #51

= 3.2.1 =

* Bug fix: @svenvanhal - Exit after issuing redirect. Fixes #46

= 3.2.0 =

* Feature: @robbiepaul - trigger core action `wp_login` when user is logged in through this plugin
* Feature: @moriyoshi - Determine the WP_User display name with replacement tokens on the settings page. Tokens can be any property of the user_claim.
* Feature: New setting to set redirect URL when session expires.
* Feature: @robbiepaul - New filter for modifying authentication URL
* Fix: @cedrox - Adding id_token_hint to logout URL according to spec
* Bug fix: Provide port to the request header when requesting the user_claim

= 3.1.0 =

* Feature: @rwasef1830 - Refresh tokens 
* Feature: @rwasef1830 - Integrated logout support with end_session endpoint
* Feature: May use an alternate redirect_uri that doesn't rely on admin-ajax
* Feature: @ahatherly - Support for IDP behind reverse proxy
* Bug fix: @robertstaddon - case insensitive check for Bearer token
* Bug fix: @rwasef1830 - "redirect to origin when auto-sso" cookie issue
* Bug fix: @rwasef1830 - PHP Warnings headers already sent due to attempts to redirect and set cookies during login form message
* Bug fix: @rwasef1830 - expire session when access_token expires if no refresh token found
* UX fix: @rwasef1830 - Show login button on error redirect when using auto-sso

= 3.0.8 =

* Feature: @wgengarelly - Added `openid-connect-generic-update-user-using-current-claim` action hook allowing other plugins/themes
  to take action using the fresh claims received when an existing user logs in.

= 3.0.7 =

* Bug fix: @wgengarelly - When requesting userinfo, send the access token using the Authorization header field as recommended in 
section 5.3.1 of the specs. 

= 3.0.6 =

* Bug fix: @robertstaddon - If "Link Existing Users" is enabled, allow users who login with OpenID Connect to also log in with WordPress credentials

= 3.0.5 =

* Feature: @robertstaddon - Added `[openid_connect_generic_login_button]` shortcode to allow the login button to be placed anywhere
* Feature: @robertstaddon - Added setting to "Redirect Back to Origin Page" after a successful login instead of redirecting to the home page.

= 3.0.4 =

* Feature: @robertstaddon - Added setting to allow linking existing WordPress user accounts with newly-authenticated OpenID Connect login

= 3.0.3 =

* Using WordPresss's is_ssl() for setcookie()'s "secure" parameter
* Bug fix: Incrementing username in case of collision.
* Bug fix: Wrong error sent when missing token body

= 3.0.2 =

* Added http_request_timeout setting

= 3.0.1 =

* Finalizing 3.0.x api

= 3.0 =

* Complete rewrite to separate concerns
* Changed settings keys for clarity (requires updating settings if upgrading from another version)
* Error logging

= 2.1 =

* Working my way closer to spec. Possible breaking change.  Now checking for preferred_username as priority.
* New username determination to avoid collisions

= 2.0 =

Complete rewrite


