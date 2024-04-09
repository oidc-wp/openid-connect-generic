# OpenId Connect Generic Changelog

**3.10.0**

- Chore: @timnolte - Dependency updates.
- Fix: @drzraf - Prevents running the auth url filter twice.
- Fix: @timnolte - Updates the log cleanup handling to properly retain the configured number of log entries.
- Fix: @timnolte - Updates the log display output to reflect the log retention policy.
- Chore: @timnolte - Adds Unit Testing & New Local Development Environment.
- Feature: @timnolte - Updates logging to allow for tracking processing time.
- Feature: @menno-ll - Adds a remember me feature via a new filter.
- Improvement: @menno-ll - Updates WP Cookie Expiration to Same as Session Length.

**3.9.1**

- Improvement: @timnolte - Refactors Composer setup and GitHub Actions.
- Improvement: @timnolte - Bumps WordPress tested version compatibility.

**3.9.0**

- Feature: @matchaxnb - Added support for additional configuration constants.
- Feature: @schanzen - Added support for agregated claims.
- Fix: @rkcreation - Fixed access token not updating user metadata after login.
- Fix: @danc1248 - Fixed user creation issue on Multisite Networks.
- Feature: @RobjS - Added plugin singleton to support for more developer customization.
- Feature: @jkouris - Added action hook to allow custom handling of session expiration.
- Fix: @tommcc - Fixed admin CSS loading only on the plugin settings screen.
- Feature: @rkcreation - Added method to refresh the user claim.
- Feature: @Glowsome - Added acr_values support & verification checks that it when defined in options is honored.
- Fix: @timnolte - Fixed regression which caused improper fallback on missing claims.
- Fix: @slykar - Fixed missing query string handling in redirect URL.
- Fix: @timnolte - Fixed issue with some user linking and user creation handling.
- Improvement: @timnolte - Fixed plugin settings typos and screen formatting.
- Security: @timnolte - Updated build tooling security vulnerabilities.
- Improvement: @timnolte - Changed build tooling scripts.

**3.8.5**

- Fix: @timnolte - Fixed missing URL request validation before use & ensure proper current page URL is setup for Redirect Back.
- Fix: @timnolte - Fixed Redirect URL Logic to Handle Sub-directory Installs.
- Fix: @timnolte - Fixed issue with redirecting user back when the openid_connect_generic_auth_url shortcode is used.

**3.8.4**

- Fix: @timnolte - Fixed invalid State object access for redirection handling.
- Improvement: @timnolte - Fixed local wp-env Docker development environment.
- Improvement: @timnolte - Fixed Composer scripts for linting and static analysis.

**3.8.3**

- Fix: @timnolte - Fixed problems with proper redirect handling.
- Improvement: @timnolte - Changes redirect handling to use State instead of cookies.
- Improvement: @timnolte - Refactored additional code to meet coding standards.

**3.8.2**

- Fix: @timnolte - Fixed reported XSS vulnerability on WordPress login screen.

**3.8.1**

- Fix: @timnolte - Prevent SSO redirect on password protected posts.
- Fix: @timnolte - CI/CD build issues.
- Fix: @timnolte - Invalid redirect handling on logout for Auto Login setting.

**3.8.0**

- Feature: @timnolte - Ability to use 6 new constants for setting client configuration instead of storing in the DB.
- Improvement: @timnolte - NPM version requirements for development.
- Improvement: @timnolte - Travis CI build fixes.
- Improvement: @timnolte - GrumPHP configuration updates for code contributions.
- Improvement: @timnolte - Refactored to meet WordPress coding standards.
- Improvement: @timnolte - Refactored to provide localization.
- Improvement: @timnolte - Refactored to provide a Docker-based local development environment.

**3.7.1**

- Fix: Release Version Number.

**3.7.0**

- Feature: @timnolte - Ability to enable/disable token refresh. Useful for IDPs that don't support token refresh.
- Feature: @timnolte - Support custom redirect URL(`redirect_to`) with the authentication URL & login button shortcodes.
- Supports additional attribute overrides including login `button_text`, `endpoint_login`, `scope`, `redirect_uri`.

**3.6.0**

- Improvement: @RobjS - Improved error messages during login state failure.
- Improvement: @RobjS - New developer filter for login form button URL.
- Fix: @cs1m0n - Only increment username during new user creation if the "Link existing user" setting is enabled.
- Fix: @xRy-42 - Allow periods and spaces in usernames to match what WordPress core allows.
- Feature: @benochen - New setting named "Create user if does not exist" determines whether new users are created during login attempts.
- Improvement: @flat235 - Username transliteration and normalization.

**3.5.1**

- Fix: @daggerhart - New approach to state management using transients.

**3.5.0**

- Readme fix: @thijskh - Fix syntax error in example openid-connect-generic-login-button-text
- Feature: @slavicd - Allow override of the plugin by posting credentials to wp-login.php
- Feature: @gassan - New action on use login
- Fix: @daggerhart - Avoid double question marks in auth url query string
- Fix: @drzraf - wp-cli bootstrap must not inhibit custom rewrite rules
- Syntax change: @mullikine - Change PHP keywords to comply with PSR2

**3.4.1**

- Minor documentation update and additional error checking.

**3.4.0**

- Feature: @drzraf - New filter hook: ability to filter claim and derived user data before user creation.
- Feature: @anttileppa - State time limit can now be changed on the settings page.
- Fix: @drzraf - Fix PHP notice when using traditional login, $token_response may be empty.
- Fix: @drzraf - Fixed a notice when cookie does not contain expected redirect_url

**3.3.1**

- Prefixing classes for more efficient autoloading.
- Avoid altering global wp_remote_post() parameters.
- Minor metadata updates for wp.org

**3.3.0**

- Fix: @pjeby - Handle multiple user sessions better by using the `WP_Session_Tokens` object. Predecessor to fixes for multiple other issues: #49, #50, #51

**3.2.1**

- Bug fix: @svenvanhal - Exit after issuing redirect. Fixes #46

**3.2.0**

- Feature: @robbiepaul - trigger core action `wp_login` when user is logged in through this plugin
- Feature: @moriyoshi - Determine the WP_User display name with replacement tokens on the settings page. Tokens can be any property of the user_claim.
- Feature: New setting to set redirect URL when session expires.
- Feature: @robbiepaul - New filter for modifying authentication URL
- Fix: @cedrox - Adding id_token_hint to logout URL according to spec
- Bug fix: Provide port to the request header when requesting the user_claim

**3.1.0**

- Feature: @rwasef1830 - Refresh tokens
- Feature: @rwasef1830 - Integrated logout support with end_session endpoint
- Feature: May use an alternate redirect_uri that doesn't rely on admin-ajax
- Feature: @ahatherly - Support for IDP behind reverse proxy
- Bug fix: @robertstaddon - case insensitive check for Bearer token
- Bug fix: @rwasef1830 - "redirect to origin when auto-sso" cookie issue
- Bug fix: @rwasef1830 - PHP Warnings headers already sent due to attempts to redirect and set cookies during login form message
- Bug fix: @rwasef1830 - expire session when access_token expires if no refresh token found
- UX fix: @rwasef1830 - Show login button on error redirect when using auto-sso

**3.0.8**

- Feature: @wgengarelly - Added `openid-connect-generic-update-user-using-current-claim` action hook allowing other plugins/themes
  to take action using the fresh claims received when an existing user logs in.

**3.0.7**

- Bug fix: @wgengarelly - When requesting userinfo, send the access token using the Authorization header field as recommended in
  section 5.3.1 of the specs.

**3.0.6**

- Bug fix: @robertstaddon - If "Link Existing Users" is enabled, allow users who login with OpenID Connect to also log in with WordPress credentials

**3.0.5**

- Feature: @robertstaddon - Added `[openid_connect_generic_login_button]` shortcode to allow the login button to be placed anywhere
- Feature: @robertstaddon - Added setting to "Redirect Back to Origin Page" after a successful login instead of redirecting to the home page.

**3.0.4**

- Feature: @robertstaddon - Added setting to allow linking existing WordPress user accounts with newly-authenticated OpenID Connect login

**3.0.3**

- Using WordPresss's is_ssl() for setcookie()'s "secure" parameter
- Bug fix: Incrementing username in case of collision.
- Bug fix: Wrong error sent when missing token body

**3.0.2**

- Added http_request_timeout setting

**3.0.1**

- Finalizing 3.0.x api

**3.0**

- Complete rewrite to separate concerns
- Changed settings keys for clarity (requires updating settings if upgrading from another version)
- Error logging

**2.1**

- Working my way closer to spec. Possible breaking change. Now checking for preferred_username as priority.
- New username determination to avoid collisions

**2.0**

Complete rewrite
