## OpenID Connect Generic Client

License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

### Description

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while 
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

Originally based on the plugin provided by shirounagi - https://wordpress.org/plugins/generic-openid-connect/

#### Requirements

* PHP 5.4+ with the OpenSSL extensions enabled (Defuse encryption library) 

### Installation

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

### Frequently Asked Questions

**What is the client's Redirect URI?**

Most OAuth2 servers should require a whitelist of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  `https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize`

Replace `example.com` with your domain name and path to WordPress.

### Changelog

**3.2.0**
* Feature: #27 Determine the WP_User display name with replacement tokens on the settings page. Tokens can be any property of the user_claim. 
* Bug fix: #30 Provide port to the request header when requesting the user_claim

**3.1.0**
* Feature: #18 Refresh tokens 
* Feature: #24 Integrated logout support with end_session endpoint
* Feature: #14 May use an alternate redirect_uri that doesn't rely on admin-ajax
* Feature: #25 Support for IDP behind reverse proxy
* Bug fix: #17 case insensitive check for Bearer token
* Bug fix: #20 "redirect to origin when auto-sso" cookie issue
* Bug fix: #12 PHP Warnings headers already sent due to attempts to redirect and set cookies during login form message
* Bug fix: #22 expire session when access_token expires if no refresh token found
* UX fix: #20 Show login button on error redirect when using auto-sso

**3.0.8**
* Feature: #10 Added `openid-connect-generic-update-user-using-current-claim` action hook allowing other plugins/themes
  to take action using the fresh claims received when an existing user logs in.

**3.0.7**
* Bug fix: #9 When requesting userinfo, send the access token using the Authorization header field as recommended in 
section 5.3.1 of the specs. 

**3.0.6**

* Bug fix: #8 If "Link Existing Users" is enabled, allow users who login with OpenID Connect to also log in with WordPress credentials

**3.0.5**

* Feature: #6 Added [openid_connect_generic_login_button] shortcode to allow the login button to be placed anywhere
* Feature: #6 Added setting to "Redirect Back to Origin Page" after a successful login instead of redirecting to the home page.

**3.0.4**

* Feature: #5 Added setting to allow linking existing WordPress user accounts with newly-authenticated OpenID Connect login

**3.0.3**

* Using WordPresss's is_ssl() for setcookie()'s "secure" parameter
* Bug fix: Incrementing username in case of collision.
* Bug fix: Wrong error sent when missing token body

**3.0.2**

* Added http_request_timeout setting

**3.0.1**

* Finalizing 3.0.x api

**3.0**

* Complete rewrite to separate concerns
* Changed settings keys for clarity (requires updating settings if upgrading from another version)
* Error logging

**2.1**

* Working my way closer to spec. Possible breaking change.  Now checking for preferred_username as priority.
* New username determination to avoid collisions

**2.0**

Complete rewrite

