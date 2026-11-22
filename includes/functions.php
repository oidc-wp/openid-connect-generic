<?php
/**
 * Global OIDCG functions.
 *
 * @package   OpenID_Connect_Generic
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Return a single use authentication URL.
 *
 * @return string
 */
function oidcg_get_authentication_url() {
	$plugin = \OpenID_Connect_Generic::instance();
	if ( ! $plugin ) {
		return '';
	}
	return $plugin->client_wrapper->get_authentication_url();
}

/**
 * Refresh a user claim and update the user metadata.
 *
 * @param WP_User $user             The user object.
 * @param array   $token_response   The token response.
 *
 * @return WP_Error|array
 */
function oidcg_refresh_user_claim( $user, $token_response ) {
	$plugin = \OpenID_Connect_Generic::instance();
	if ( ! $plugin ) {
		return new WP_Error( 'missing-dependencies', __( 'OpenID Connect Generic is not running because its dependencies are missing.', 'daggerhart-openid-connect-generic' ) );
	}
	return $plugin->client_wrapper->refresh_user_claim( $user, $token_response );
}
