=== Hellō Login ===
Contributors: marius1hello
Donate link: https://www.hello.dev/
Tags: security, login, oauth2, openidconnect, apps, authentication, sso
Requires at least: 4.9
Tested up to: 6.1
Stable tag: 1.0.5
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Free and simple plugin provides registration and login with the Hellō Wallet. Users choose from popular social login,
email, or crypto wallet. Setup in 7 clicks, not 7 hours.

== Description ==

Provide your users a registration and login using their choice of popular social login, email, or even a crypto wallet.
No need for you to configure your application at each provider or pay for a premium plugin.

Hellō Login verifies your users' email addresses so you don't have to. No longer do they have to manage another username
and password to use your site.

Hellō is a cloud identity wallet cooperatively operated with a mission to empower users to control their identity. Learn
more at [hello.coop](https://www.hello.coop/).

* Hellō Login installs with Hellō Quickstart to get you up and running in minutes.
* Users control their identity with their Hellō Wallet. No need for you to manage how they login.
* Hellō Login is free for users and early adopting sites. See [hello.coop](https://www.hello.coop/) for details.

Documentation, configuration, and settings can be found in Settings >  Hellō Login

== Installation ==

= Automatic Installation =

1. Search for “hello openid” through 'Plugins > Add New' interface.
1. Find the plugin box of Hellō Login and click on the **Install Now** button.
1. Activate the Hellō Login plugin, then click **Settings**.
1. Click the **Quickstart** button and complete the Quickstart flow.
1. Once back at the Hellō Login Settings page, link your admin account with your Hellō Wallet.

= Manual Installation =

1. Download [Hellō Login](https://downloads.wordpress.org/plugin/hello-login.zip).
1. Upload Hellō Login through 'Plugins > Add New > Upload' interface or upload hello-login folder to the `/wp-content/plugins/` directory.
1. Activate the Hellō Login plugin, then click **Settings**.
1. Click the **Quickstart** button and complete the Quickstart flow.
1. Once back at the Hellō Login Settings page, link your admin account with your Hellō Wallet.

== Frequently Asked Questions ==

= What is Hellō? =

Hellō is a cloud identity wallet that empowers users to prove who they are to any site that accepts Hellō. Learn more at
[hello.coop](https://www.hello.coop/).

= How can users login to their wallet? =

Hellō supports all popular ways to login including Apple, Facebook, GitHub, Google, Line, Microsoft, Twitch, Twitter,
Yahoo, as well as email, phone and crypto wallets. We are adding more methods on a regular basis. See
[hello.coop](https://www.hello.coop/) for a complete list.

= Does Hellō sell user data? =

No. Hellō provides sites user data only with informed consent. User data is only accessible while the user has unlocked
their wallet by logging in with their preferred provider.

= How does Hellō make money? =

The Hellō business model is to charge sites a few cents for each new verified claim the user provides to a site, such as
a phone number and in the future claims such as a professional certification.

= Why is Hellō free to sites now? =

While we are building out the network, we are not charging sites for verified claims and rewarding early adopters of
Hellō with credits for bringing users to Hellō.

= How can I change the image for my site? =

You can update your site configuration at [console.hello.coop](https://console.hello.coop/).

= Where do I submit feature requests or bugs? =

Please submit to [https://github.com/hellocoop/wordpress/issues](https://github.com/hellocoop/wordpress/issues)

== Screenshots ==

1. The `/wp-login.php` page with Hellō Login.
2. The Hellō Wallet login choices offered.

== Changelog ==

= 1.0.5 =

* Feature: added `given_name` and `family_name` scopes as defaults
* Fix: admin account linking done based on curren session
* Feature: link user account on sign-in, when account is matched on email
* Fix: map `nickname` to new username, instead of `sub`

= 1.0.4 =

* Feature: added "Settings" link right in plugin list
* Fix: show "Continue with Hellō" button on login page only if the plugin is configured

= 1.0.3 =

* Feature: added `integration` parameter to Quickstart request

= 1.0.2 =

* First release in WordPress plugin repository
* Feature: toggle settings page content based on settings and current user state
* Feature: collapse username / password form on login page
* Feature: send Privacy Policy and Custom Logo URLs to Quickstart
* Feature: added "Link Hellō" button to settings page

= 1.0.1 =

* WordPress plugin submission feedback
* Improvement: updated "Tested Up To" to 6.1.0
* Fix: input/output sanitization and generation
* Improvement: removed unused global functions
* Improvement: enabled user linking and redirect after login

= 1.0.0 =

* Forked https://github.com/oidc-wp/openid-connect-generic
* Feature: merged PR that adds [PKCE support](https://github.com/oidc-wp/openid-connect-generic/pull/421)
* Feature: integrated Hellō Quickstart
* Feature: removed unnecessary configuration options
* Improvement: renamed all relevant identifiers to be Hellō Login specific

--------

[See pre-fork changelog up to 3.9.1 here](https://github.com/oidc-wp/openid-connect-generic/blob/main/CHANGELOG.md)
