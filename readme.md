# OpenID Connect Generic Client

License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

## Description

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while 
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

## Table of Contents

- [Installation](#installation)
    - [Composer](#composer)
- [Frequently Asked Questions](#frequently-asked-questions)
    - [What is the client's Redirect URI?](#what-is-the-clients-redirect-uri)
    - [Can I change the client's Redirect URI?](#can-i-change-the-clients-redirect-uri)
- [Hooks](#hooks)
    - [Filters](#filters)
        - [openid-connect-generic-alter-request](#openid-connect-generic-alter-request)
        - [openid-connect-generic-login-button-text](#openid-connect-generic-login-button-text)
        - [openid-connect-generic-auth-url](#openid-connect-generic-auth-url)
        - [openid-connect-generic-user-login-test](#openid-connect-generic-user-login-test)
        - [openid-connect-generic-user-creation-test](#openid-connect-generic-user-creation-test)
        - <del>[openid-connect-generic-alter-user-claim](#openid-connect-generic-alter-user-claim)</del>
        - [openid-connect-generic-alter-user-data](#openid-connect-generic-alter-user-data)
        - [openid-connect-generic-settings-fields](#openid-connect-generic-settings-fields)
    - [Actions](#actions)
        - [openid-connect-generic-user-create](#openid-connect-generic-user-create)
        - [openid-connect-generic-user-update](#openid-connect-generic-user-update)
        - [openid-connect-generic-update-user-using-current-claim](#openid-connect-generic-update-user-using-current-claim)
        - [openid-connect-generic-redirect-user-back](#openid-connect-generic-redirect-user-back)


## Installation

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

### Composer

[OpenID Connect Generic on packagist](https://packagist.org/packages/daggerhart/openid-connect-generic)

Installation:

`composer require daggerhart/openid-connect-generic`


## Frequently Asked Questions

### What is the client's Redirect URI?

Most OAuth2 servers should require a whitelist of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  `https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize`

Replace `example.com` with your domain name and path to WordPress.

### Can I change the client's Redirect URI?

Some OAuth2 servers do not allow for a client redirect URI to contain a query string. The default URI provided by 
this module leverages WordPress's `admin-ajax.php` endpoint as an easy way to provide a route that does not include
HTML, but this will naturally involve a query string. Fortunately, this plugin provides a setting that will make use of 
an alternate redirect URI that does not include a query string.

On the settings page for this plugin (Dashboard > Settings > OpenID Connect Generic) there is a checkbox for 
**Alternate Redirect URI**. When checked, the plugin will use the Redirect URI 
`https://example.com/openid-connect-authorize`.

## Hooks

This plugin provides a number of hooks to allow for a significant amount of customization of the plugin operations from 
elsewhere in the WordPress system.

### Filters

Filters are WordPress hooks that are used to modify data. The first argument in a filter hook is always expected to be
returned at the end of the hook.

WordPress filters API - [`add_filter()`](https://developer.wordpress.org/reference/functions/add_filter/) and 
[`apply_filters()`](https://developer.wordpress.org/reference/functions/apply_filters/).

Most often you'll only need to use `add_filter()` to hook into this plugin's code.

#### `openid-connect-generic-alter-request`

Hooks directly into client before requests are sent to the OpenID Server.

Provides 2 arguments: the request array being sent to the server, and the operation currently being executed by this 
plugin.

Possible operations:

- get-authentication-token
- refresh-token
- get-userinfo

```
add_filter('openid-connect-generic-alter-request', function( $request, $operation ) {
    if ( $operation == 'get-authentication-token' ) {
        $request['some_key'] = 'modified value';
    }
    
    return $request;
}, 10, 2);
```

#### `openid-connect-generic-login-button-text`

Modify the login button text. Default value is `__( 'Login with OpenID Connect' )`.

Provides 1 argument: the current login button text.

```
add_filter('openid-connect-generic-login-button-text', function( $text ) {
    $text = __('Login to my super cool IDP server');
    
    return $text;
});
```

#### `openid-connect-generic-auth-url`

Modify the authentication URL before presented to the user. This is the URL that will send the user to the IDP server 
for login.

Provides 1 argument: the plugin generated URL.

```
add_filter('openid-connect-generic-auth-url', function( $url ) {
    // Add some custom data to the url.
    $url.= '&my_custom_data=123abc';
    return $url;
}); 
```

#### `openid-connect-generic-user-login-test`

Determine whether or not the user should be logged into WordPress.

Provides 2 arguments: the boolean result of the test (default `TRUE`), and the `$user_claim` array from the server.

```
add_filter('openid-connect-generic-user-login-test', function( $result, $user_claim ) {
    // Don't let Terry login.
    if ( $user_claim['email'] == 'terry@example.com' ) {
        $result = FALSE;
    }
    
    return $result;
}, 10, 2);
```

#### `openid-connect-generic-user-creation-test`

Determine whether or not the user should be created. This filter is called when a new user is trying to login and they
do not currently exist within WordPress.

Provides 2 arguments: the boolean result of the test (default `TRUE`), and the `$user_claim` array from the server.

```
add_filter('', function( $result, $user_claim ) {
    // Don't let anyone from example.com create an account.
    $email_array = explode( '@', $user_claim['email'] );
    if ( $email_array[1] == 'example.com' ) {
        $result = FALSE;
    }
    
    return $result;
}, 10, 2) 
```

#### <del>`openid-connect-generic-alter-user-claim`</del>

Modify the `$user_claim` before the plugin builds the `$user_data` array for new user created.

**Deprecated** - This filter is not very useful due to some changes that were added later. Recommend not using this 
filter, and using the `openid-connect-generic-alter-user-data` filter instead. Practically, you can only change the 
user's `first_name` and `last_name` values with this filter, but you could easily do that in 
`openid-connect-generic-alter-user-data` as well. 

Provides 1 argument: the `$user_claim` from the server.

```
// Not a great example because the hook isn't very useful.
add_filter('openid-connect-generic-alter-user-claim', function( $user_claim ) {
    // Use the beginning of the user's email address as the user's first name. 
    if ( empty( $user_claim['given_name'] ) ) {
        $email_array = explode( '@', $user_claim['email'] );
        $user_claim['given_name'] = $email_array[0];
    }
    
    return $user_claim;
});
```

#### `openid-connect-generic-alter-user-data`

Modify a new user's data immediately before the user is created.

Provides 2 arguments: the `$user_data` array that will be sent to `wp_insert_user()`, and the `$user_claim` from the 
server.

```
add_filter('openid-connect-generic-alter-user-claim', function( $user_data, $user_claim ) {
    // Don't register any user with their real email address. Create a fake internal address.
    if ( !empty( $user_data['user_email'] ) ) {
        $email_array = explode( '@', $user_data['user_email'] );
        $email_array[1] = 'my-fake-domain.co';
        $user_data['user_email'] = implode( '@', $email_array );
    }
    
    return $user_data;
}, 10, 2);
```

#### `openid-connect-generic-settings-fields`

For extending the plugin with a new setting field (found on Dashboard > Settings > OpenID Connect Generic) that the site 
administrator can modify. Also useful to alter the existing settings fields.

See `/includes/openid-connect-generic-settings-page.php` for how fields are constructed.

New settings fields will be automatically saved into the wp_option for this plugin's settings, and will be available in 
the `\OpenID_Connect_Generic_Option_Settings` object this plugin uses. 

**Note:** It can be difficult to get a copy of the settings from within other hooks. The easiest way to make use of 
settings in your custom hooks is to call 
`$settings = get_option('openid_connect_generic_settings', array());`.

Provides 1 argument: the existing fields array.

```
add_filter('openid-connect-generic-settings-fields', function( $fields ) {

    // Modify an existing field's title.
    $fields['endpoint_userinfo']['title'] = __('User information endpoint url');
    
    // Add a new field that is a simple checkbox.
    $fields['block_terry'] = array(
        'title' => __('Block Terry'),
        'description' => __('Prevent Terry from logging in'),
        'type' => 'checkbox',
        'section' => 'authorization_settings',
    );
    
    // A select field that provides options.
    
    $fields['deal_with_terry'] = array(
        'title' => __('Manage Terry'),
        'description' => __('How to deal with Terry when he tries to log in.'),
        'type' => 'select',
        'options' => array(
            'allow' => __('Allow login'),
            'block' => __('Block'),
            'redirect' => __('Redirect'),
        ),
        'section' => 'authorization_settings',
    );
    
    return $fields;
});
```
"Sections" are where your setting appears on the admin settings page. Keys for settings sections:

- client_settings
- user_settings
- authorization_settings
- log_settings

Field types:

- text
- checkbox
- select (requires an array of "options")

### Actions

WordPress actions are generic events that other plugins can react to.

Actions API: [`add_action`](https://developer.wordpress.org/reference/functions/add_action/) and [`do_actions`](https://developer.wordpress.org/reference/functions/do_action/)

You'll probably only ever want to use `add_action` when hooking into this plugin.

#### `openid-connect-generic-user-create`

React to a new user being created by this plugin.

Provides 2 arguments: the `\WP_User` object that was created, and the `$user_claim` from the IDP server.

``` 
add_action('openid-connect-generic-user-create', function( $user, $user_claim ) {
    // Send the user an email when their account is first created.
    wp_mail( 
        $user->user_email,
        __('Welcome to my web zone'),
        "Hi {$user->first_name},\n\nYour account has been created at my cool website.\n\n Enjoy!"
    ); 
}, 10, 2);
``` 

#### `openid-connect-generic-user-update`

React to the user being updated after login. This is the event that happens when a user logins and they already exist as 
a user in WordPress, as opposed to a new WordPress user being created.

Provides 1 argument: the user's WordPress user ID.

``` 
add_action('openid-connect-generic-user-update', function( $uid ) {
    // Keep track of the number of times the user has logged into the site.
    $login_count = get_user_meta( $uid, 'my-user-login-count', TRUE);
    $login_count += 1;
    add_user_meta( $uid, 'my-user-login-count', $login_count, TRUE);
});
```

#### `openid-connect-generic-update-user-using-current-claim`

React to an existing user logging in (after authentication and authorization).

Provides 2 arguments: the `WP_User` object, and the `$user_claim` provided by the IDP server.

```
add_action('openid-connect-generic-update-user-using-current-claim', function( $user, $user_claim) {
    // Based on some data in the user_claim, modify the user.
    if ( !empty( $user_claim['wp_user_role'] ) ) {
        if ( $user_claim['wp_user_role'] == 'should-be-editor' ) {
            $user->set_role( 'editor' );
        }
    }
}, 10, 2); 
```

#### `openid-connect-generic-redirect-user-back`

React to a user being redirected after a successful login. This hook is the last hook that will fire when a user logs 
in. It will only fire if the plugin setting "Redirect Back to Origin Page" is enabled at Dashboard > Settings > 
OpenID Connect Generic. It will fire for both new and existing users.

Provides 2 arguments: the url where the user will be redirected, and the `WP_User` object.

```
add_action('openid-connect-generic-redirect-user-back', function( $redirect_url, $user ) {
    // Take over the redirection complete. Send users somewhere special based on their capabilities.
    if ( $user->has_cap( 'edit_users' ) ) {
        wp_redirect( admin_url( 'users.php' ) );
        exit();
    }
}, 10, 2); 
```

### User Meta Data

This plugin stores meta data about the user for both practical and debugging purposes.

* `openid-connect-generic-subject-identity` - The identity of the user provided by the IDP server.
* `openid-connect-generic-last-id-token-claim` - The user's most recent `id_token` claim, decoded and stored as an array.
* `openid-connect-generic-last-user-claim` - The user's most recent `user_claim`, stored as an array.
* `openid-connect-generic-last-token-response` - The user's most recent `token_response`, stored as an array.
