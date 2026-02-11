<?php
/**
 * Class OpenID_Connect_Generic_Client_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin OIDC/oAuth client class test case.
 */
class OpenID_Connect_Generic_Client_Test extends WP_UnitTestCase {

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		parent::tearDown();

	}

	/**
	 * Test plugin get_redirect_uri() method.
	 *
	 * @group ClientTests
	 */
	public function test_plugin_client_get_redirect_uri() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	/**
	 * Test get_issuer_from_endpoint extracts base URL correctly.
	 *
	 * @dataProvider issuer_extraction_provider
	 * @group ClientTests
	 * @group IssuerValidation
	 *
	 * @param string $endpoint_url    The endpoint URL to test.
	 * @param string $expected_issuer The expected extracted issuer.
	 */
	public function test_get_issuer_from_endpoint( $endpoint_url, $expected_issuer ) {
		$client = $this->create_client();
		$actual = $client->get_issuer_from_endpoint( $endpoint_url );
		$this->assertEquals( $expected_issuer, $actual );
	}

	/**
	 * Data provider for issuer extraction tests.
	 *
	 * @return array Test cases with endpoint URLs and expected issuers.
	 */
	public function issuer_extraction_provider() {
		return array(
			'auth0_authorize'         => array(
				'https://dev-test.us.auth0.com/authorize',
				'https://dev-test.us.auth0.com/',
			),
			'keycloak_with_path'      => array(
				'https://auth.example.com/realms/myrealm/protocol/openid-connect/auth',
				'https://auth.example.com/',
			),
			'okta_with_path'          => array(
				'https://dev-123456.okta.com/oauth2/default/v1/authorize',
				'https://dev-123456.okta.com/',
			),
			'with_non_standard_port'  => array(
				'https://localhost:8443/oauth/authorize',
				'https://localhost:8443/',
			),
			'with_standard_https_port' => array(
				'https://example.com:443/authorize',
				'https://example.com/',
			),
			'with_standard_http_port' => array(
				'http://example.com:80/authorize',
				'http://example.com/',
			),
			'already_base_url'        => array(
				'https://example.com/',
				'https://example.com/',
			),
			'no_trailing_slash'       => array(
				'https://example.com',
				'https://example.com/',
			),
			'azure_ad'                => array(
				'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'https://login.microsoftonline.com/',
			),
		);
	}

	/**
	 * Test validate_id_token_claim accepts matching issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_matching_issuer() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com/',  // Matches derived issuer.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_id_token_claim rejects mismatched issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_wrong_issuer() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://evil.com/',  // Wrong issuer.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-iss', $result->get_error_code() );
	}

	/**
	 * Test validate_id_token_claim with Auth0 style issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_auth0_format() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://dev-emypzqmunz78not4.us.auth0.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'auth0|123456',
			'iss' => 'https://dev-emypzqmunz78not4.us.auth0.com/',  // Auth0 format with trailing slash.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_id_token_claim rejects expired token.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_rejects_expired() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com/',
			'aud' => 'test_client',
			'exp' => time() - 3600,  // Expired 1 hour ago.
			'iat' => time() - 7200,
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertWPError( $result );
		$this->assertEquals( 'token-expired', $result->get_error_code() );
	}

	/**
	 * Test validate_id_token_claim rejects wrong audience.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_rejects_wrong_audience() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com/',
			'aud' => 'wrong_client',  // Wrong audience.
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-aud', $result->get_error_code() );
	}

	/**
	 * Test validate_id_token_claim accepts audience as array.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_audience_array() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com/',
			'aud' => array( 'test_client', 'other_client' ),  // Audience as array.
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Helper to create client instance for testing.
	 *
	 * @param array $settings Optional settings to override defaults.
	 *
	 * @return OpenID_Connect_Generic_Client
	 */
	private function create_client( $settings = array() ) {
		$default_settings = array(
			'client_id'          => 'test_client',
			'client_secret'      => 'test_secret',
			'scope'              => 'openid email profile',
			'endpoint_login'     => 'https://example.com/authorize',
			'endpoint_userinfo'  => '',
			'endpoint_token'     => 'https://example.com/token',
			'redirect_uri'       => 'https://example.com/callback',
			'acr_values'         => '',
			'endpoint_jwks'      => '',
			'jwks_cache_ttl'     => 3600,
			'state_time_limit'   => 180,
		);

		$merged = array_merge( $default_settings, $settings );

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		return new OpenID_Connect_Generic_Client(
			$merged['client_id'],
			$merged['client_secret'],
			$merged['scope'],
			$merged['endpoint_login'],
			$merged['endpoint_userinfo'],
			$merged['endpoint_token'],
			$merged['redirect_uri'],
			$merged['acr_values'],
			$merged['endpoint_jwks'],
			$merged['jwks_cache_ttl'],
			$merged['state_time_limit'],
			$logger
		);
	}

}
