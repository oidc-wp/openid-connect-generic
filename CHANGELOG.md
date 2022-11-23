# Hellō Login Changelog

**1.0.5**

- Feature: added `given_name` and `family_name` scopes as defaults
- Fix: admin account linking done based on curren session
- Feature: link user account on sign-in, when account is matched on email
- Fix: map `nickname` to new username, instead of `sub`

**1.0.4**

- Feature: added "Settings" link right in plugin list
- Fix: show "Continue with Hellō" button on login page only if the plugin is configured

**1.0.3**

- Feature: added `integration` parameter to Quickstart request

**1.0.2**

- First release in WordPress plugin repository
- Feature: toggle settings page content based on settings and current user state
- Feature: collapse username / password form on login page
- Feature: send Privacy Policy and Custom Logo URLs to Quickstart
- Feature: added "Link Hellō" button to settings page

**1.0.1**

- WordPress plugin submission feedback
- Improvement: updated "Tested Up To" to 6.1.0
- Fix: input/output sanitization and generation
- Improvement: removed unused global functions
- Improvement: enabled user linking and redirect after login

**1.0.0**

- Forked https://github.com/oidc-wp/openid-connect-generic
- Feature: merged PR that adds [PKCE support](https://github.com/oidc-wp/openid-connect-generic/pull/421)
- Feature: integrated Hellō Quickstart
- Feature: removed unnecessary configuration options
- Improvement: renamed all relevant identifiers to be Hellō Login specific

--------

[See pre-fork changelog up to 3.9.1 here](https://github.com/oidc-wp/openid-connect-generic/blob/main/CHANGELOG.md)
