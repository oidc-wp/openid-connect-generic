<?php
/**
 * Phpstan bootstrap file.
 *
 * @package   OpenID_Connect_Generic
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @author    Tim Nolte <tim.nolte@ndigitals.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/daggerhart
 */

// Define WordPress language directory.
define( 'WP_LANG_DIR', 'wordpress/src/wp-includes/languages/' );

define( 'COOKIE_DOMAIN', 'localhost' );
define( 'COOKIEPATH', '/');

// Define Plugin Globals.
define( 'OIDC_CLIENT_ID', bin2hex( random_bytes( 32 ) ) );
define( 'OIDC_CLIENT_SECRET', bin2hex( random_bytes( 16 ) ) );
define( 'OIDC_ENDPOINT_LOGIN_URL', 'https://oidc/oauth2/authorize' );
define( 'OIDC_ENDPOINT_USERINFO_URL', 'https://oidc/oauth2/userinfo' );
define( 'OIDC_ENDPOINT_TOKEN_URL', 'https://oidc/oauth2/token' );
define( 'OIDC_ENDPOINT_LOGOUT_URL', 'https://oidc/oauth2/logout' );
