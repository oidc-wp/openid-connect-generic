
# OpenId Connect Generic Changelog

**3.2.0**

* Feature: @robbiepaul - trigger core action `wp_login` when user is logged in through this plugin
* Feature: @moriyoshi - Determine the WP_User display name with replacement tokens on the settings page. Tokens can be any property of the user_claim. 
* Bug fix: Provide port to the request header when requesting the user_claim

**3.1.0**

* Feature: @rwasef1830 - Refresh tokens 
* Feature: @rwasef1830 - Integrated logout support with end_session endpoint
* Feature: May use an alternate redirect_uri that doesn't rely on admin-ajax
* Feature: @ahatherly - Support for IDP behind reverse proxy
* Bug fix: @robertstaddon - case insensitive check for Bearer token
* Bug fix: @rwasef1830 - "redirect to origin when auto-sso" cookie issue
* Bug fix: @rwasef1830 - PHP Warnings headers already sent due to attempts to redirect and set cookies during login form message
* Bug fix: @rwasef1830 - expire session when access_token expires if no refresh token found
* UX fix: @rwasef1830 - Show login button on error redirect when using auto-sso

**3.0.8**

* Feature: @wgengarelly - Added `openid-connect-generic-update-user-using-current-claim` action hook allowing other plugins/themes
  to take action using the fresh claims received when an existing user logs in.

**3.0.7**

* Bug fix: @wgengarelly - When requesting userinfo, send the access token using the Authorization header field as recommended in 
section 5.3.1 of the specs. 

**3.0.6**

* Bug fix: @robertstaddon - If "Link Existing Users" is enabled, allow users who login with OpenID Connect to also log in with WordPress credentials

**3.0.5**

* Feature: @robertstaddon - Added `[openid_connect_generic_login_button]` shortcode to allow the login button to be placed anywhere
* Feature: @robertstaddon - Added setting to "Redirect Back to Origin Page" after a successful login instead of redirecting to the home page.

**3.0.4**

* Feature: @robertstaddon - Added setting to allow linking existing WordPress user accounts with newly-authenticated OpenID Connect login

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

