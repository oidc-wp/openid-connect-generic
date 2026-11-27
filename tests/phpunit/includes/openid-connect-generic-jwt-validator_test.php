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
	 * @param bool $include_kid Whether to include the "kid" parameter.
	 *
	 * @return array The JWKS array.
	 */
	private function create_jwks( $include_alg = true, $include_kid = true ) {
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

		// Conditionally leave out the "kid" parameter.
		if ( ! $include_kid ) {
			unset( $jwk['kid'] );
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
	 * Test that a token without "kid" validates against a JWKS with a single key without "kid".
	 */
	public function test_jwt_validation_without_kid_with_single_key() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload, 'RS256', null );
		$jwks = $this->create_jwks( true, false );

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Validation should succeed without kid and a single key. Error: ' . $result->get_error_message() );
		}

		$this->assertSame( 'user123', $result['sub'] );
	}

	/**
	 * Test that a token without "kid" validates against a JWKS with a single key that has a "kid".
	 */
	public function test_jwt_validation_without_kid_in_token_with_single_key() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload, 'RS256', null );
		$jwks = $this->create_jwks( false );

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Validation should succeed without kid in the token and a single key. Error: ' . $result->get_error_message() );
		}

		$this->assertSame( 'user123', $result['sub'] );
	}

	/**
	 * Test that a token without "kid" signed by another key fails against a JWKS with a single key.
	 */
	public function test_jwt_validation_without_kid_fails_with_wrong_signature() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$other_key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		$id_token = JWT::encode( $payload, $other_key, 'RS256' );
		$jwks = $this->create_jwks( true, false );

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		$this->assertInstanceOf( WP_Error::class, $result, 'Should fail with a signature from another key' );
		$this->assertSame( 'jwt-verification-failed', $result->get_error_code() );
	}

	/**
	 * Test that a token without "kid" fails against a JWKS with more than one key.
	 */
	public function test_jwt_validation_without_kid_fails_with_multiple_keys() {
		$now = time();
		$payload = array(
			'iss' => 'https://example.com',
			'sub' => 'user123',
			'aud' => 'test-client-id',
			'exp' => $now + 3600,
			'iat' => $now,
		);

		$id_token = $this->create_jwt_token( $payload, 'RS256', null );
		$jwks = $this->create_jwks( true );
		$jwks['keys'][] = array_merge( $jwks['keys'][0], array( 'kid' => 'other-key-id' ) );

		$validator = $this->create_validator_with_mocked_jwks( $jwks );
		$result = $validator->validate_id_token( $id_token );

		$this->assertInstanceOf( WP_Error::class, $result, 'Should fail without kid when the JWKS has more than one key' );
		$this->assertSame( 'jwt-verification-failed', $result->get_error_code() );
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
}
