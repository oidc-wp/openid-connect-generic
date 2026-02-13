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
	 * Validate JWT claims.
	 *
	 * @param object $decoded_jwt The decoded JWT payload.
	 *
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	private function validate_jwt_claims( $decoded_jwt ) {
		// Validate subject (sub) claim.
		if ( ! isset( $decoded_jwt->sub ) || empty( $decoded_jwt->sub ) ) {
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

			if ( rtrim( $decoded_jwt->iss, '/' ) !== rtrim( $this->issuer, '/' ) ) {
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
	 * the "alg" parameter. This method adds the algorithm from the JWT header
	 * to each key that's missing it, ensuring compatibility with the Firebase
	 * JWT library which requires "alg" to be present.
	 *
	 * @param array  $jwks      The JWKS array with keys.
	 * @param string $id_token  The JWT ID token.
	 *
	 * @return array The enriched JWKS array.
	 */
	private function enrich_jwks_with_alg( $jwks, $id_token ) {
		// Extract algorithm from JWT header.
		$jwt_alg = $this->get_jwt_header_alg( $id_token );

		// If we couldn't extract the algorithm, default to RS256 (most common for OIDC).
		if ( empty( $jwt_alg ) ) {
			$jwt_alg = 'RS256';
		}

		// Add algorithm to keys that are missing it.
		if ( isset( $jwks['keys'] ) && is_array( $jwks['keys'] ) ) {
			foreach ( $jwks['keys'] as &$key ) {
				if ( ! isset( $key['alg'] ) ) {
					$key['alg'] = $jwt_alg;
				}
			}
		}

		return $jwks;
	}

	/**
	 * Validate and verify an ID token.
	 *
	 * @param string $id_token The JWT ID token to validate.
	 *
	 * @return array|WP_Error Array of claims if valid, WP_Error on failure.
	 */
	public function validate_id_token( $id_token ) {
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
		$jwks = $this->enrich_jwks_with_alg( $jwks, $id_token );

		// Verify JWT signature and decode.
		try {
			// Parse JWKS into Key objects.
			$keys = JWK::parseKeySet( $jwks );

			// Decode and verify JWT signature.
			// The JWT library will automatically validate exp, nbf, and signature.
			$decoded_jwt = JWT::decode( $id_token, $keys );

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

		// Validate additional claims.
		$claims_valid = $this->validate_jwt_claims( $decoded_jwt );
		if ( is_wp_error( $claims_valid ) ) {
			$this->logger->log( $claims_valid, 'jwt-claims-invalid' );
			return $claims_valid;
		}

		// Convert stdClass to array for consistency with existing code.
		return json_decode( json_encode( $decoded_jwt ), true );
	}
}
