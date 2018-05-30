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

### Installation

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

#### Composer

[OpenID Connect Generic on packagist](https://packagist.org/packages/daggerhart/openid-connect-generic-wordpress)

Installation:

`composer require daggerhart/openid-connect-generic-wordpress`


### Frequently Asked Questions

**What is the client's Redirect URI?**

Most OAuth2 servers should require a whitelist of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  `https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize`

Replace `example.com` with your domain name and path to WordPress.
