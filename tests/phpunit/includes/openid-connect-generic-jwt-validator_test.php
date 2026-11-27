<?php
/**
 * Test the OpenID_Connect_Generic_JWT_Validator class.
 *
 * @package OpenID_Connect_Generic
 */

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class OpenID_Connect_Generic_JWT_Validator_Test
 *
 * Tests for the JWT validation functionality, particularly for compatibility
 * with identity providers like Microsoft Entra ID that don't include "alg"
 * in their JWKS responses.
 */
class OpenID_Connect_Generic_JWT_Validator_Test extends WP_UnitTestCase {

	/**
	 * The JWT validator instance.
	 *
	 * @var OpenID_Connect_Generic_JWT_Validator
	 */
	private $validator;

	/**
	 * Mock logger instance.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * RSA private key for signing test tokens.
	 *
	 * @var resource|OpenSSLAsymmetricKey
	 */
	private $private_key;

	/**
	 * RSA public key for verifying test tokens.
	 *
	 * @var resource|OpenSSLAsymmetricKey
	 */
	private $public_key;

	/**
	 * Set up test environment before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Generate RSA key pair for testing.
		$config = array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);

		$res = openssl_pkey_new( $config );
		openssl_pkey_export( $res, $private_key_pem );
		$this->private_key = openssl_pkey_get_private( $private_key_pem );

		$public_key_details = openssl_pkey_get_details( $res );
		$this->public_key = openssl_pkey_get_public( $public_key_details['key'] );

		// Create a mock logger.
		$this->logger = $this->getMockBuilder( OpenID_Connect_Generic_Option_Logger::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up transients that might have been set.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_openid_connect_jwks_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_openid_connect_jwks_%'" );
	}

	/**
	 * Helper method to create a JWT token.
	 *
	 * @param array  $payload The JWT payload.
	 * @param string $alg The algorithm to use (default: RS256).
	 * @param string $kid The key ID to include in the header (default: test-key-id).
	 *
	 * @return string The encoded JWT token.
	 */
	private function create_jwt_token( $payload, $alg = 'RS256', $kid = 'test-key-id' ) {
		return JWT::encode( $payload, $this->private_key, $alg, $kid );
	}

	/**
	 * Helper method to create a JWKS array from the public key.
	 *
	 * @param bool $include_alg Whether to include the "alg" parameter.
	 *
	 * @return array The JWKS array.
	 */
	private function create_jwks( $include_alg = true ) {
		$key_details = openssl_pkey_get_details( $this->public_key );

		$jwk = array(
			'kty' => 'RSA',
			'use' => 'sig',
			'kid' => 'test-key-id',
			'n'   => rtrim( str_replace( array( '+', '/' ), array( '-', '_' ), base64_encode( $key_details['rsa']['n'] ) ), '=' ),
			'e'   => rtrim( str_replace( array( '+', '/' ), array( '-', '_' ), base64_encode( $key_details['rsa']['e'] ) ), '=' ),
		);

		// Conditionally include the "alg" parameter.
		if ( $include_alg ) {
			$jwk['alg'] = 'RS256';
		}

		return array(
			'keys' => array( $jwk ),
		);
	}

	/**
	 * Helper method to create a validator instance with mocked HTTP responses.
	 *
	 * @param array  $jwks The JWKS to return.
	 * @param string $jwks_uri The JWKS URI.
	 * @param string $issuer The expected issuer.
	 *
	 * @return OpenID_Connect_Generic_JWT_Validator
	 */
	private function create_validator_with_mocked_jwks( $jwks, $jwks_uri = 'https://example.com/jwks', $issuer = 'https://example.com' ) {
		// Mock the JWKS fetch by setting a transient.
		$cache_key = 'openid_connect_jwks_' . md5( $jwks_uri );
		set_transient( $cache_key, $jwks, 3600 );

		return new OpenID_Connect_Generic_JWT_Validator(
			$jwks_uri,
			'test-client-id',
			$issuer,
			3600,
			false,
			$this->logger
		);
	}

	/**
	 * Test that JWT validation works with JWKS that includes "alg" parameter.
	 */
	public function test_jwt_validation_with_alg_in_jwks() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload );
		$jwks = $this->create_jwks( true ); // Include "alg" parameter.

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Validation should succeed with alg in JWKS. Error: ' . $result->get_error_message() );
		}

		$this->assertIsArray( $result );
		$this->assertEquals( 'user123', $result['sub'] );
		$this->assertEquals( 'test-client-id', $result['aud'] );
	}

	/**
	 * Test that JWT validation works with JWKS WITHOUT "alg" parameter (Microsoft Entra ID scenario).
	 *
	 * This is the main test for the bug fix.
	 */
	public function test_jwt_validation_without_alg_in_jwks() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload );
		$jwks = $this->create_jwks( false ); // Do NOT include "alg" parameter (simulates Entra ID).

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Validation should succeed even without alg in JWKS. Error: ' . $result->get_error_message() );
		}

		$this->assertIsArray( $result );
		$this->assertEquals( 'user123', $result['sub'] );
		$this->assertEquals( 'test-client-id', $result['aud'] );
	}

	/**
	 * Test that validation fails with an expired token.
	 */
	public function test_jwt_validation_fails_with_expired_token() {
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => time() - 3600, // Expired 1 hour ago.
			'iat' => time() - 7200,
		);

		$id_token = $this->create_jwt_token( $payload );
		$jwks = $this->create_jwks( false ); // Without "alg" to test the fix.

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		$this->assertInstanceOf( WP_Error::class, $result, 'Should fail with expired token' );
		$this->assertEquals( 'jwt-verification-failed', $result->get_error_code() );
	}

	/**
	 * Test that validation fails with wrong audience.
	 */
	public function test_jwt_validation_fails_with_wrong_audience() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'wrong-client-id', // Wrong audience.
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload );
		$jwks = $this->create_jwks( false ); // Without "alg" to test the fix.

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		$this->assertInstanceOf( WP_Error::class, $result, 'Should fail with wrong audience' );
		$this->assertEquals( 'invalid-aud', $result->get_error_code() );
	}

	/**
	 * Test that validation fails with wrong issuer.
	 */
	public function test_jwt_validation_fails_with_wrong_issuer() {
		$now = time();
		$payload = array(
			'iss' => 'https://wrong-issuer.com', // Wrong issuer.
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload );
		$jwks = $this->create_jwks( false ); // Without "alg" to test the fix.

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		$this->assertInstanceOf( WP_Error::class, $result, 'Should fail with wrong issuer' );
		$this->assertEquals( 'invalid-iss', $result->get_error_code() );
	}

	/**
	 * Test issuer matching, including the Entra {tenantid} placeholder.
	 *
	 * @dataProvider issuer_matches_provider
	 *
	 * @param mixed  $iss      The token's iss claim.
	 * @param string $expected The expected issuer.
	 * @param mixed  $tid      The token's tid claim.
	 * @param bool   $matches  Whether the issuer should match.
	 */
	public function test_issuer_matches( $iss, $expected, $tid, $matches ) {
		$this->assertSame( $matches, OpenID_Connect_Generic_JWT_Validator::issuer_matches( $iss, $expected, $tid ) );
	}

	/**
	 * Data provider for test_issuer_matches.
	 *
	 * @return array
	 */
	public function issuer_matches_provider() {
		$tenant = '72f988bf-86f1-41af-91ab-2d7cd011db47';
		$other = '9188040d-6c67-4c5b-b112-36a304b66dad';
		$template = 'https://login.microsoftonline.com/{tenantid}/v2.0';

		return array(
			'exact match'                   => array( 'https://example.com', 'https://example.com', null, true ),
			'trailing slash'                => array( 'https://example.com/', 'https://example.com', null, true ),
			'different issuer'              => array( 'https://evil.com', 'https://example.com', null, false ),
			'non-string iss'                => array( array( 'https://example.com' ), 'https://example.com', null, false ),
			'template, tid matches iss'     => array( "https://login.microsoftonline.com/$tenant/v2.0", $template, $tenant, true ),
			'template, uppercase tid'       => array( 'https://login.microsoftonline.com/' . strtoupper( $tenant ) . '/v2.0', $template, strtoupper( $tenant ), true ),
			'template, v1 issuer'           => array( "https://sts.windows.net/$tenant/", 'https://sts.windows.net/{tenantid}/', $tenant, true ),
			'template, tid differs'         => array( "https://login.microsoftonline.com/$tenant/v2.0", $template, $other, false ),
			'template, tid missing'         => array( "https://login.microsoftonline.com/$tenant/v2.0", $template, null, false ),
			'template, tid not a GUID'      => array( 'https://login.microsoftonline.com/evil/v2.0', $template, 'evil', false ),
			'template, tid with path'       => array( 'https://login.microsoftonline.com/x/y/v2.0', $template, 'x/y', false ),
			'template, tid not a string'    => array( "https://login.microsoftonline.com/$tenant/v2.0", $template, array( $tenant ), false ),
			'template, other host'          => array( "https://evil.com/$tenant/v2.0", $template, $tenant, false ),
			'template, literal placeholder' => array( $template, $template, null, false ),
			'no template ignores tid'       => array( "https://login.microsoftonline.com/$tenant/v2.0", "https://login.microsoftonline.com/$other/v2.0", $tenant, false ),
		);
	}

	/**
	 * Test that a signed token from any tenant passes with the {tenantid} issuer.
	 */
	public function test_jwt_validation_with_tenantid_issuer() {
		$now = time();
		$jwks = $this->create_jwks( false );
		$validator = $this->create_validator_with_mocked_jwks( $jwks, 'https://example.com/jwks', 'https://login.microsoftonline.com/{tenantid}/v2.0' );

		foreach ( array( '72f988bf-86f1-41af-91ab-2d7cd011db47', 'b5c2e8a4-1f3d-4e6a-9c7b-0d8e2f1a3b4c' ) as $tid ) {
			$payload = array(
				'iss' => "https://login.microsoftonline.com/$tid/v2.0",
				'tid' => $tid,
				'sub' => 'user123',
				'aud' => 'test-client-id',
				'exp' => $now + 3600,
				'iat' => $now,
			);

			$result = $validator->validate_id_token( $this->create_jwt_token( $payload ) );
			$this->assertIsArray( $result, "Token from tenant $tid should pass" );
		}

		$payload['tid'] = '72f988bf-86f1-41af-91ab-2d7cd011db47';
		$result = $validator->validate_id_token( $this->create_jwt_token( $payload ) );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid-iss', $result->get_error_code() );
	}

	/**
	 * Create a back-channel logout token payload, merging in the given claims.
	 *
	 * @param array $claims Claims to add or override. A null value removes the claim.
	 *
	 * @return array
	 */
	private function logout_token_payload( $claims = array() ) {
		$now     = time();
		$payload = array_merge(
			array(
				'iss'    => 'https://example.com',
				'aud'    => 'test-client-id',
				'iat'    => $now,
				'exp'    => $now + 300,
				'jti'    => 'logout-token-1',
				'sub'    => 'user123',
				'sid'    => 'session-abc',
				'events' => array( 'http://schemas.openid.net/event/backchannel-logout' => new stdClass() ),
			),
			$claims
		);
		return array_filter(
			$payload,
			function ( $value ) {
				return null !== $value;
			}
		);
	}

	/**
	 * Test that a valid logout token passes, including one with only a sid.
	 *
	 * @group JWTValidatorTests
	 */
	public function test_logout_token_validation_succeeds() {
		$validator = $this->create_validator_with_mocked_jwks( $this->create_jwks( true ) );

		$result = $validator->validate_logout_token( $this->create_jwt_token( $this->logout_token_payload() ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'user123', $result['sub'] );
		$this->assertSame( 'session-abc', $result['sid'] );

		$sid_only = $validator->validate_logout_token( $this->create_jwt_token( $this->logout_token_payload( array( 'sub' => null ) ) ) );
		$this->assertIsArray( $sid_only );

		$int_sub = $validator->validate_logout_token( $this->create_jwt_token( $this->logout_token_payload( array( 'sub' => 12345 ) ) ) );
		$this->assertIsArray( $int_sub );
		$this->assertSame( 12345, $int_sub['sub'] );
	}

	/**
	 * Test that logout tokens breaking the back-channel logout rules are rejected.
	 *
	 * @group JWTValidatorTests
	 */
	public function test_logout_token_validation_rejects_invalid_tokens() {
		$validator = $this->create_validator_with_mocked_jwks( $this->create_jwks( true ) );

		$cases = array(
			'missing-logout-event' => array( 'events' => null ),
			'missing-sub-and-sid'  => array(
				'sub' => null,
				'sid' => null,
			),
			'unexpected-nonce'     => array( 'nonce' => 'abc' ),
			'missing-jti'          => array( 'jti' => null ),
			'invalid-sub'          => array( 'sub' => array( 'user123' ) ),
			'invalid-sid'          => array( 'sid' => 12345 ),
			'invalid-aud'          => array( 'aud' => 'another-client' ),
		);

		foreach ( $cases as $error_code => $claims ) {
			$result = $validator->validate_logout_token( $this->create_jwt_token( $this->logout_token_payload( $claims ) ) );
			$this->assertWPError( $result, $error_code );
			$this->assertSame( $error_code, $result->get_error_code() );
		}

		$jti_not_string = $validator->validate_logout_token( $this->create_jwt_token( $this->logout_token_payload( array( 'jti' => 12345 ) ) ) );
		$this->assertSame( 'missing-jti', $jti_not_string->get_error_code() );

		$this->assertWPError( $validator->validate_logout_token( '' ), 'an empty token must be rejected' );
	}

	/**
	 * Test that EC and OKP keys without an "alg" can't be used as an HMAC secret.
	 *
	 * Their public key material is a string, so an HS256 token signed with it would
	 * verify if the token's own algorithm were trusted.
	 *
	 * @group JWTValidatorTests
	 */
	public function test_jwks_without_alg_rejects_hmac_with_ec_and_okp_keys() {
		$b64 = function ( $value ) {
			return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
		};
		$body   = $b64( wp_json_encode( $this->logout_token_payload() ) );
		$header = $b64( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'test-key-id' ) ) );

		$ec_key     = openssl_pkey_new(
			array(
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name'       => 'prime256v1',
			)
		);
		$ec_details = openssl_pkey_get_details( $ec_key )['ec'];
		$ec_jwk     = array(
			'kty' => 'EC',
			'crv' => 'P-256',
			'kid' => 'test-key-id',
			'x'   => $b64( $ec_details['x'] ),
			'y'   => $b64( $ec_details['y'] ),
		);

		$okp_jwk = array(
			'kty' => 'OKP',
			'crv' => 'Ed25519',
			'kid' => 'test-key-id',
			'x'   => $b64( sodium_crypto_sign_publickey( sodium_crypto_sign_keypair() ) ),
		);

		foreach ( array( 'EC' => $ec_jwk, 'OKP' => $okp_jwk ) as $type => $jwk ) {
			// The key material php-jwt would use as the HMAC secret.
			$secret    = JWK::parseKey( $jwk + array( 'alg' => 'HS256' ) )->getKeyMaterial();
			$forged    = $header . '.' . $body . '.' . $b64( hash_hmac( 'sha256', $header . '.' . $body, $secret, true ) );
			$validator = $this->create_validator_with_mocked_jwks( array( 'keys' => array( $jwk ) ) );
			$this->assertWPError( $validator->validate_logout_token( $forged ), "HS256 with an $type public key must be rejected" );
		}

		// A real ES256 token still verifies against the EC key without an "alg".
		$validator = $this->create_validator_with_mocked_jwks( array( 'keys' => array( $ec_jwk ) ) );
		$es256     = JWT::encode( $this->logout_token_payload(), $ec_key, 'ES256', 'test-key-id' );
		$this->assertIsArray( $validator->validate_logout_token( $es256 ) );
	}

	/**
	 * Test that a logout token with a bad signature is rejected.
	 *
	 * @group JWTValidatorTests
	 */
	public function test_logout_token_validation_rejects_bad_signature() {
		$validator = $this->create_validator_with_mocked_jwks( $this->create_jwks( true ) );
		$token     = $this->create_jwt_token( $this->logout_token_payload() );

		// Swap in a different payload, keeping the original signature.
		$parts    = explode( '.', $token );
		$parts[1] = rtrim( strtr( base64_encode( wp_json_encode( $this->logout_token_payload( array( 'sub' => 'someone-else' ) ) ) ), '+/', '-_' ), '=' );

		$result = $validator->validate_logout_token( implode( '.', $parts ) );
		$this->assertWPError( $result );
		$this->assertSame( 'jwt-verification-failed', $result->get_error_code() );
	}

	/**
	 * Test that forged logout tokens are rejected: unsigned, wrongly signed, expired or from another issuer.
	 *
	 * @group JWTValidatorTests
	 */
	public function test_logout_token_validation_rejects_forged_tokens() {
		$b64 = function ( $value ) {
			return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
		};
		$body = $b64( wp_json_encode( $this->logout_token_payload() ) );

		foreach ( array( true, false ) as $jwks_has_alg ) {
			$validator = $this->create_validator_with_mocked_jwks( $this->create_jwks( $jwks_has_alg ) );

			// Unsigned token (alg none).
			$unsigned = $b64( wp_json_encode( array( 'alg' => 'none', 'typ' => 'JWT', 'kid' => 'test-key-id' ) ) ) . '.' . $body . '.';
			$this->assertWPError( $validator->validate_logout_token( $unsigned ), 'alg none must be rejected' );

			// Algorithm confusion: HS256 signed with the public RSA key as the secret.
			$public_pem = openssl_pkey_get_details( $this->public_key )['key'];
			$header     = $b64( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'test-key-id' ) ) );
			$confused   = $header . '.' . $body . '.' . $b64( hash_hmac( 'sha256', $header . '.' . $body, $public_pem, true ) );
			$this->assertWPError( $validator->validate_logout_token( $confused ), 'HS256 with the public key must be rejected' );
		}

		$validator = $this->create_validator_with_mocked_jwks( $this->create_jwks( true ) );

		// Correctly signed, but with someone else's key under the same key ID.
		$other_key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		$other     = JWT::encode( $this->logout_token_payload(), $other_key, 'RS256', 'test-key-id' );
		$this->assertWPError( $validator->validate_logout_token( $other ), 'a token from another key must be rejected' );

		// Expired, and from another issuer.
		$expired = $this->create_jwt_token(
			$this->logout_token_payload(
				array(
					'iat' => time() - 600,
					'exp' => time() - 300,
				)
			)
		);
		$this->assertWPError( $validator->validate_logout_token( $expired ), 'an expired token must be rejected' );

		$wrong_issuer = $this->create_jwt_token( $this->logout_token_payload( array( 'iss' => 'https://attacker.example.com' ) ) );
		$this->assertSame( 'invalid-iss', $validator->validate_logout_token( $wrong_issuer )->get_error_code() );
	}
}
