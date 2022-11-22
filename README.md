# Hellō Login #
**Contributors:** [marius1hello](https://profiles.wordpress.org/marius1hello/)  
**Donate link:** https://www.hello.dev/  
**Tags:** security, login, oauth2, openidconnect, apps, authentication, sso  
**Requires at least:** 4.9  
**Tested up to:** 6.1.0  
**Stable tag:** 1.0.4  
**Requires PHP:** 7.2  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A login and registration plugin for the Hellō service.

## Description ##

This plugin integrates the Hellō service with your site, simplifying login and registration of users.
You can request name, nickname, profile picture, as well as a verified email, phone, or ethereum address.

This plugin uses the Hellō Quickstart service to get your site up and running in minutes.

Configuration and settings can be found in the Settings > Hellō Login dashboard page

For details on the Hellō service see [hello.dev](https://hello.dev)

Please submit issues and feature requests to the Github repo: [https://github.com/hellocoop/wordpress](https://github.com/hellocoop/wordpress)

## Installation ##

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin in `Plugins`
1. In `Settings` > `Hellō Login` click the `Quickstart` button and complete Quickstart flow
1. Add the `[hello_login_button]` shortcode to pages for the Hellō login button

## Frequently Asked Questions ##

### How do users login? ###

Hellō offers users all popular social login methods including Apple, Facebook, Google, Line, Microsoft, Twitch, and Yahoo;
email or phone; or popular crypto wallets including MetaMask. The current choices can be seen at [https://wallet.hello.coop](https://wallet.hello.coop)
Hellō lets users change their provider without any effort on your part.

### What claims can I ask for about a user? ###

Hellō supports all popular OpenID Connect claims and we are continually adding claims to Hellō. You can see the full list at [Hellō Claims](https://www.hello.dev/documentation/hello-claims.html)

## Changelog ##

### 1.0.4 ###

* Feature: added "Settings" link right in plugin list
* Fix: show "Continue with Hellō" button on login page only if the plugin is configured

### 1.0.3 ###

* Feature: added `integration` parameter to Quickstart request

### 1.0.2 ###

* First release in WordPress plugin repository
* Feature: toggle settings page content based on settings and current user state
* Feature: collapse username / password form on login page
* Feature: send Privacy Policy and Custom Logo URLs to Quickstart
* Feature: added "Link Hellō" button to settings page

### 1.0.1 ###

* WordPress plugin submission feedback
* Improvement: updated "Tested Up To" to 6.1.0
* Fix: input/output sanitization and generation
* Improvement: removed unused global functions
* Improvement: enabled user linking and redirect after login

### 1.0.0 ###

* Forked https://github.com/oidc-wp/openid-connect-generic
* Feature: merged PR that adds [PKCE support](https://github.com/oidc-wp/openid-connect-generic/pull/421).
* Feature: integrated Hellō Quickstart
* Feature: removed unnecessary configuration options
* Improvement: renamed all relevant identifiers to be Hellō Login specific


--------

[See pre-fork changelog up to 3.9.1 here](https://github.com/oidc-wp/openid-connect-generic/blob/main/CHANGELOG.md)
