<?php
/**
 * Test the OpenID_Connect_Generic_JWT_Validator class.
 *
 * @package OpenID_Connect_Generic
 */

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
	 *
	 * @return OpenID_Connect_Generic_JWT_Validator
	 */
	private function create_validator_with_mocked_jwks( $jwks, $jwks_uri = 'https://example.com/jwks' ) {
		// Mock the JWKS fetch by setting a transient.
		$cache_key = 'openid_connect_jwks_' . md5( $jwks_uri );
		set_transient( $cache_key, $jwks, 3600 );

		return new OpenID_Connect_Generic_JWT_Validator(
			$jwks_uri,
			'test-client-id',
			'https://example.com',
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
}
