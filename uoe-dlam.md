This repository is a fork of [openid-connect-generic](https://github.com/oidc-wp/openid-connect-generic) WordPress plug-in.

It contains some changes to support its use in the University of Edinburgh Blogging platform.

# Changes

Here is a list of changes made to the original plug-in ([details in PR #1](https://github.com/uoe-dlam/openid-connect-generic/pull/1)):

-   Use of `get_site_transient` instead of `get_transient`
-   Some options were moved to config (wp-config.php):
    -   'login_button_text' => 'OIDC_LOGIN_BUTTON_TEXT',
    -   'email_format' => 'OIDC_ENDPOINT_EMAIL_FORMAT',
    -   'alternate_redirect_uri' => 'OIDC_ALTERNATE_REDIRECT_URI',
    -   'identity_key' => 'OIDC_IDENTITY_KEY',
    -   'identify_with_username' => 'OIDC_IDENTIFY_WITH_USERNAME',
-   Client secret has been removed from being displayed on the plug-in page,
-   Client secret is stored in an external file (a change requires raising a Unidesk call),
-   The redirect uri has been set to the main blog (site): `$redirect_uri = get_admin_url( get_main_site_id() ) . 'admin-ajax.php?action=openid-connect-authorize';`,
-   The discovery form (Quick Setup: Import from Discovery Document) has been removed,
-   The plug-in setup form is only available in the main blog site dashboard.

# Build plugin

To get plug-in build for the WordPress please run:

-   `npm run grunt build-dist`

This will then update the content of the `dist` directory with the latest changes.

Please zip the content of this directory as `daggerhart-openid-connect-generic.zip` and upload to the Blogs platform with standard WordPress upload plug-in form.

## Important

The decompressed folder name should be **`daggerhart-openid-connect-generic`**.

Please make sure you do not include any `git` related folders and files.

# Example of wp-config.php entry:

`define('OIDC_CLIENT_ID', '[YOUR CLIENT ID]');`

`define('OIDC_CLIENT_SECRET', getenv('OIDC_CLIENT_SECRET'));`

`define('OIDC_ENDPOINT_LOGIN_URL', 'https://login.microsoftonline.com/[YOUR TENANT ID]/oauth2/authorize?domain_hint=ed.ac.uk');`

`define('OIDC_ENDPOINT_USERINFO_URL', 'https://login.microsoftonline.com/[YOUR TENANT ID]/openid/userinfo');`

`define('OIDC_ENDPOINT_TOKEN_URL', 'https://login.microsoftonline.com/[YOUR TENANT ID]/oauth2/token');`

`define('OIDC_ENDPOINT_JWKS_URL', 'https://login.microsoftonline.com/common/discovery/keys');`

`define('OIDC_ENDPOINT_LOGOUT_URL', 'https://login.microsoftonline.com/[YOUR TENANT ID]/oauth2/logout');`

`define('OIDC_ISSUER', 'https://sts.windows.net/[YOUR TENANT ID]/');`

`define('OIDC_CLIENT_SCOPE', 'email profile openid User.Read');`

`define('OIDC_LOGIN_TYPE', 'auto');`

`define('OIDC_ENFORCE_PRIVACY', false);`

`define('OIDC_ALTERNATE_REDIRECT_URI', false);`

`define('OIDC_CREATE_IF_DOES_NOT_EXIST', false);`

`define('OIDC_LINK_EXISTING_USERS', true);`

`define('OIDC_REDIRECT_USER_BACK', true);`

`define('OIDC_ENABLE_LOGGING', false);`

`define( 'OIDC_ENDPOINT_EMAIL_FORMAT', '{unique_name}' );`

`define( 'OIDC_LOGIN_BUTTON_TEXT', 'Login with SSO (EntraID)' );`

`define( 'OIDC_IDENTITY_KEY', 'unique_name' );`

`define( 'OIDC_IDENTIFY_WITH_USERNAME', true );`

Set up OpenID on main blogs dashboard (Settings --> OpenID Connect Client) and set:

-   Nickname Key: `name`
-   Display Name Formatting: `{name}`

Leave the rest of the options unchanged.
