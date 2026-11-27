<?php
/**
 * JWT validation and verification class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Authentication
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;

/**
 * OpenID_Connect_Generic_JWT_Validator class.
 *
 * Handles JWT signature verification and claim validation using JWKS.
 *
 * @package  OpenID_Connect_Generic
 * @category Authentication
 */
class OpenID_Connect_Generic_JWT_Validator {

	/**
	 * The JWKS endpoint URL.
	 *
	 * @var string
	 */
	private $jwks_uri;

	/**
	 * The expected client ID (audience).
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * The expected issuer.
	 *
	 * @var string
	 */
	private $issuer;

	/**
	 * JWKS cache TTL in seconds.
	 *
	 * @var int
	 */
	private $cache_ttl;

	/**
	 * Allow HTTP requests to internal/private network endpoints.
	 *
	 * @var bool
	 */
	private $allow_internal_idp;

	/**
	 * Logger instance.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param string                               $jwks_uri           The JWKS endpoint URL.
	 * @param string                               $client_id          The client ID for audience validation.
	 * @param string                               $issuer             The expected issuer.
	 * @param int                                  $cache_ttl          JWKS cache TTL in seconds.
	 * @param bool                                 $allow_internal_idp Allow internal/private network endpoints.
	 * @param OpenID_Connect_Generic_Option_Logger $logger             Logger instance.
	 */
	public function __construct( $jwks_uri, $client_id, $issuer, $cache_ttl, $allow_internal_idp, $logger ) {
		$this->jwks_uri           = $jwks_uri;
		$this->client_id          = $client_id;
		$this->issuer             = $issuer;
		$this->cache_ttl          = $cache_ttl;
		$this->allow_internal_idp = $allow_internal_idp;
		$this->logger             = $logger;
	}

	/**
	 * Make a safe HTTP GET request with optional internal endpoint support.
	 *
	 * By default, uses wp_safe_remote_get() which blocks requests to internal/private
	 * networks (SSRF protection). If allow_internal_idp is enabled, uses wp_remote_get()
	 * to allow connections to localhost and private network identity providers.
	 *
	 * @param string $url  The URL to request.
	 * @param array  $args Optional. Request arguments.
	 *
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	private function http_get( $url, $args = array() ) {
		if ( $this->allow_internal_idp ) {
			return wp_remote_get( $url, $args );
		}
		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Fetch JWKS from the IDP endpoint with caching.
	 *
	 * @return array|WP_Error Array of keys or WP_Error on failure.
	 */
	private function fetch_jwks() {
		// Check cache first.
		$cache_key = 'openid_connect_jwks_' . md5( $this->jwks_uri );
		$cached_jwks = get_transient( $cache_key );

		if ( false !== $cached_jwks ) {
			return $cached_jwks;
		}

		// Fetch JWKS from IDP.
		$response = $this->http_get( $this->jwks_uri, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( $response, 'jwks-fetch-failed' );
			return new WP_Error(
				'jwks-fetch-failed',
				__( 'Failed to fetch JWKS from identity provider.', 'daggerhart-openid-connect-generic' ),
				$response
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error = new WP_Error(
				'jwks-fetch-failed',
				sprintf(
					/* translators: %d is the HTTP response code */
					__( 'JWKS endpoint returned HTTP %d', 'daggerhart-openid-connect-generic' ),
					$response_code
				)
			);
			$this->logger->log( $error, 'jwks-fetch-failed' );
			return $error;
		}

		$body = wp_remote_retrieve_body( $response );
		$jwks = json_decode( $body, true );

		if ( ! $jwks || ! isset( $jwks['keys'] ) ) {
			$error = new WP_Error(
				'jwks-invalid-format',
				__( 'Invalid JWKS format received from identity provider.', 'daggerhart-openid-connect-generic' )
			);
			$this->logger->log( $error, 'jwks-invalid-format' );
			return $error;
		}

		// Cache the JWKS.
		set_transient( $cache_key, $jwks, $this->cache_ttl );

		return $jwks;
	}

	/**
	 * Check a token's issuer against the expected issuer.
	 *
	 * An expected issuer containing `{tenantid}` (Microsoft Entra multitenant
	 * discovery documents) matches when the placeholder is replaced with the
	 * token's `tid` claim, which must be a GUID.
	 *
	 * @param mixed  $iss             The token's iss claim.
	 * @param string $expected_issuer The expected issuer.
	 * @param mixed  $tid             The token's tid claim, if any.
	 *
	 * @return bool
	 */
	public static function issuer_matches( $iss, $expected_issuer, $tid = null ) {
		if ( ! is_string( $iss ) || ! is_string( $expected_issuer ) ) {
			return false;
		}

		if ( false !== strpos( $expected_issuer, '{tenantid}' ) ) {
			if ( ! is_string( $tid ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tid ) ) {
				return false;
			}
			$expected_issuer = str_replace( '{tenantid}', $tid, $expected_issuer );
		}

		return rtrim( $iss, '/' ) === rtrim( $expected_issuer, '/' );
	}

	/**
	 * Validate JWT claims.
	 *
	 * @param object $decoded_jwt The decoded JWT payload.
	 * @param bool   $require_sub Whether the token must have a subject claim.
	 *
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	private function validate_jwt_claims( $decoded_jwt, $require_sub = true ) {
		// Validate subject (sub) claim.
		if ( $require_sub && ( ! isset( $decoded_jwt->sub ) || empty( $decoded_jwt->sub ) ) ) {
			return new WP_Error(
				'missing-sub',
				__( 'Token missing subject claim.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Validate expiration (exp) claim - JWT library already validates this, but double-check.
		if ( ! isset( $decoded_jwt->exp ) ) {
			return new WP_Error(
				'missing-exp',
				__( 'Token missing expiration claim.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Validate issued at (iat) claim.
		if ( ! isset( $decoded_jwt->iat ) ) {
			return new WP_Error(
				'missing-iat',
				__( 'Token missing issued at claim.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Validate audience (aud) claim.
		if ( ! isset( $decoded_jwt->aud ) ) {
			return new WP_Error(
				'missing-aud',
				__( 'Token missing audience claim.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Audience can be string or array.
		$aud = $decoded_jwt->aud;
		$audience_valid = false;

		if ( is_array( $aud ) ) {
			$audience_valid = in_array( $this->client_id, $aud, true );
		} elseif ( is_string( $aud ) ) {
			$audience_valid = ( $aud === $this->client_id );
		}

		if ( ! $audience_valid ) {
			return new WP_Error(
				'invalid-aud',
				__( 'Token audience does not match client.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Validate issuer (iss) claim if configured.
		if ( ! empty( $this->issuer ) ) {
			if ( ! isset( $decoded_jwt->iss ) ) {
				return new WP_Error(
					'missing-iss',
					__( 'Token missing issuer claim.', 'daggerhart-openid-connect-generic' )
				);
			}

			$tid = isset( $decoded_jwt->tid ) ? $decoded_jwt->tid : null;
			if ( ! self::issuer_matches( $decoded_jwt->iss, $this->issuer, $tid ) ) {
				$this->logger->log(
					sprintf(
						'Issuer mismatch - Expected: "%s", Received: "%s". Configure the correct issuer in Settings > OpenID Connect Client > Issuer field, or via the OIDC_ISSUER constant.',
						$this->issuer,
						$decoded_jwt->iss
					),
					'issuer-mismatch'
				);
				return new WP_Error(
					'invalid-iss',
					__( 'Token issuer does not match expected issuer.', 'daggerhart-openid-connect-generic' )
				);
			}
		}

		return true;
	}

	/**
	 * Extract the algorithm from JWT header.
	 *
	 * @param string $id_token The JWT ID token.
	 *
	 * @return string|null The algorithm from JWT header or null if not found.
	 */
	private function get_jwt_header_alg( $id_token ) {
		$token_parts = explode( '.', $id_token );
		if ( count( $token_parts ) < 2 ) {
			return null;
		}

		$header_base64 = $token_parts[0];
		$header_json = JWT::urlsafeB64Decode( $header_base64 );
		$header = json_decode( $header_json, true );

		return isset( $header['alg'] ) ? $header['alg'] : null;
	}

	/**
	 * Enrich JWKS with algorithm from JWT header if missing.
	 *
	 * Some identity providers (like Microsoft Entra ID) return JWKs without
	 * the "alg" parameter. This method adds an algorithm to each key that's
	 * missing it, ensuring compatibility with the Firebase JWT library which
	 * requires "alg" to be present.
	 *
	 * The JWT header's algorithm is only used when it fits the key type, so a
	 * token can't choose an HMAC algorithm and use a public key as the secret.
	 * Keys of other types without an "alg" are left out.
	 *
	 * @param array  $jwks      The JWKS array with keys.
	 * @param string $id_token  The JWT ID token.
	 *
	 * @return array The enriched JWKS array.
	 */
	private function enrich_jwks_with_alg( $jwks, $id_token ) {
		// Extract algorithm from JWT header.
		$jwt_alg = $this->get_jwt_header_alg( $id_token );

		if ( ! isset( $jwks['keys'] ) || ! is_array( $jwks['keys'] ) ) {
			return $jwks;
		}

		foreach ( $jwks['keys'] as $index => $key ) {
			if ( ! is_array( $key ) || isset( $key['alg'] ) ) {
				continue;
			}

			$algs = $this->get_algs_for_key( $key );
			if ( empty( $algs ) ) {
				unset( $jwks['keys'][ $index ] );
				continue;
			}

			// Use the JWT header's algorithm if it fits this key, otherwise the key type's default.
			$jwks['keys'][ $index ]['alg'] = in_array( $jwt_alg, $algs, true ) ? $jwt_alg : $algs[0];
		}
		$jwks['keys'] = array_values( $jwks['keys'] );

		return $jwks;
	}

	/**
	 * The signature algorithms that can be used with a JWK, by its key type, default first.
	 *
	 * @param array $key The JWK.
	 *
	 * @return string[] The algorithms, or an empty array for unsupported key types.
	 */
	private function get_algs_for_key( $key ) {
		switch ( $key['kty'] ?? '' ) {
			case 'RSA':
				return array( 'RS256', 'RS384', 'RS512' );

			case 'EC':
				$curve_algs = array(
					'P-256' => 'ES256',
					'P-384' => 'ES384',
				);
				return isset( $curve_algs[ $key['crv'] ?? '' ] ) ? array( $curve_algs[ $key['crv'] ] ) : array();

			case 'OKP':
				return array( 'EdDSA' );

			default:
				return array();
		}
	}

	/**
	 * Validate and verify an ID token.
	 *
	 * @param string $id_token The JWT ID token to validate.
	 *
	 * @return array|WP_Error Array of claims if valid, WP_Error on failure.
	 */
	public function validate_id_token( $id_token ) {
		$decoded_jwt = $this->decode_jwt( $id_token );
		if ( is_wp_error( $decoded_jwt ) ) {
			return $decoded_jwt;
		}

		// Validate additional claims.
		$claims_valid = $this->validate_jwt_claims( $decoded_jwt );
		if ( is_wp_error( $claims_valid ) ) {
			$this->logger->log( $claims_valid, 'jwt-claims-invalid' );
			return $claims_valid;
		}

		// Convert stdClass to array for consistency with existing code.
		return json_decode( json_encode( $decoded_jwt ), true );
	}

	/**
	 * Validate and verify a back-channel logout token.
	 *
	 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html#Validation
	 *
	 * @param string $logout_token The JWT logout token to validate.
	 *
	 * @return array|WP_Error Array of claims if valid, WP_Error on failure.
	 */
	public function validate_logout_token( $logout_token ) {
		$decoded_jwt = $this->decode_jwt( $logout_token );
		if ( is_wp_error( $decoded_jwt ) ) {
			return $decoded_jwt;
		}

		// A logout token may identify the user by sub, sid or both.
		$claims_valid = $this->validate_jwt_claims( $decoded_jwt, false );
		if ( ! is_wp_error( $claims_valid ) ) {
			$claims_valid = $this->validate_logout_token_claims( $decoded_jwt );
		}
		if ( is_wp_error( $claims_valid ) ) {
			$this->logger->log( $claims_valid, 'logout-token-invalid' );
			return $claims_valid;
		}

		return json_decode( json_encode( $decoded_jwt ), true );
	}

	/**
	 * Validate the claims specific to a back-channel logout token.
	 *
	 * @param object $decoded_jwt The decoded JWT payload.
	 *
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	private function validate_logout_token_claims( $decoded_jwt ) {
		$logout_event = 'http://schemas.openid.net/event/backchannel-logout';
		if ( ! isset( $decoded_jwt->events ) || ! isset( $decoded_jwt->events->{ $logout_event } ) ) {
			return new WP_Error( 'missing-logout-event', __( 'Logout token missing back-channel logout event.', 'daggerhart-openid-connect-generic' ) );
		}

		if ( empty( $decoded_jwt->sub ) && empty( $decoded_jwt->sid ) ) {
			return new WP_Error( 'missing-sub-and-sid', __( 'Logout token missing both subject and session ID claims.', 'daggerhart-openid-connect-generic' ) );
		}

		// Like ID tokens, the sub must be a string or an integer.
		if ( isset( $decoded_jwt->sub ) && ! is_string( $decoded_jwt->sub ) && ! is_int( $decoded_jwt->sub ) ) {
			return new WP_Error( 'invalid-sub', __( 'Logout token subject claim must be a string or integer.', 'daggerhart-openid-connect-generic' ) );
		}

		if ( isset( $decoded_jwt->sid ) && ! is_string( $decoded_jwt->sid ) ) {
			return new WP_Error( 'invalid-sid', __( 'Logout token session ID claim must be a string.', 'daggerhart-openid-connect-generic' ) );
		}

		if ( isset( $decoded_jwt->nonce ) ) {
			return new WP_Error( 'unexpected-nonce', __( 'Logout token must not contain a nonce claim.', 'daggerhart-openid-connect-generic' ) );
		}

		if ( empty( $decoded_jwt->jti ) || ! is_string( $decoded_jwt->jti ) ) {
			return new WP_Error( 'missing-jti', __( 'Logout token missing JWT ID claim.', 'daggerhart-openid-connect-generic' ) );
		}

		return true;
	}

	/**
	 * Verify a JWT's signature against the JWKS and decode it.
	 *
	 * @param string $jwt The JWT to verify.
	 *
	 * @return object|WP_Error The decoded JWT payload, WP_Error on failure.
	 */
	private function decode_jwt( $jwt ) {
		// Check if JWKS URI is configured.
		if ( empty( $this->jwks_uri ) ) {
			$error = new WP_Error(
				'jwks-not-configured',
				__( 'JWKS URI not configured. JWT signature verification requires JWKS endpoint.', 'daggerhart-openid-connect-generic' )
			);
			$this->logger->log( $error, 'jwks-not-configured' );
			return $error;
		}

		// Fetch JWKS.
		$jwks = $this->fetch_jwks();
		if ( is_wp_error( $jwks ) ) {
			return $jwks;
		}

		// Enrich JWKS with algorithm from JWT header if keys are missing "alg".
		// This ensures compatibility with providers like Microsoft Entra ID that
		// don't include "alg" in their JWKS.
		$jwks = $this->enrich_jwks_with_alg( $jwks, $jwt );

		// Verify JWT signature and decode.
		try {
			// Parse JWKS into Key objects.
			$keys = JWK::parseKeySet( $jwks );

			// Decode and verify JWT signature.
			// The JWT library will automatically validate exp, nbf, and signature.
			return JWT::decode( $jwt, $keys );

		} catch ( Exception $e ) {
			$error = new WP_Error(
				'jwt-verification-failed',
				sprintf(
					/* translators: %s is the error message */
					__( 'JWT verification failed: %s', 'daggerhart-openid-connect-generic' ),
					$e->getMessage()
				)
			);
			$this->logger->log( $error, 'jwt-verification-failed' );
			return $error;
		}
	}
}
