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
				'https://dev-test.us.auth0.com',
			),
			'keycloak_with_path'      => array(
				'https://auth.example.com/realms/myrealm/protocol/openid-connect/auth',
				'https://auth.example.com',
			),
			'okta_with_path'          => array(
				'https://dev-123456.okta.com/oauth2/default/v1/authorize',
				'https://dev-123456.okta.com',
			),
			'with_non_standard_port'  => array(
				'https://localhost:8443/oauth/authorize',
				'https://localhost:8443',
			),
			'with_standard_https_port' => array(
				'https://example.com:443/authorize',
				'https://example.com',
			),
			'with_standard_http_port' => array(
				'http://example.com:80/authorize',
				'http://example.com',
			),
			'already_base_url'        => array(
				'https://example.com/',
				'https://example.com',
			),
			'no_trailing_slash'       => array(
				'https://example.com',
				'https://example.com',
			),
			'azure_ad'                => array(
				'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'https://login.microsoftonline.com',
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
			'iss' => 'https://example.com',  // Matches derived issuer.
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
			'iss' => 'https://evil.com',  // Wrong issuer.
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
	 * Note: Auth0 includes trailing slash in issuer, validation should handle both with/without.
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
			'iss' => 'https://dev-emypzqmunz78not4.us.auth0.com/',  // Auth0 includes trailing slash in actual tokens.
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
			'iss' => 'https://example.com',
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
			'iss' => 'https://example.com',
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
			'iss' => 'https://example.com',
			'aud' => array( 'test_client', 'other_client' ),  // Audience as array.
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_id_token_claim uses configured issuer over derived issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_explicit_issuer() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://login.example.com/authorize',
				'issuer'         => 'https://issuer.example.com', // Explicit issuer differs from login endpoint.
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://issuer.example.com',  // Matches configured issuer, not derived from endpoint_login.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_user_claim accepts an integer userinfo sub equal to the id_token sub.
	 *
	 * @group ClientTests
	 */
	public function test_validate_user_claim_with_integer_sub() {
		$client = $this->create_client();

		$this->assertTrue( $client->validate_user_claim( array( 'sub' => 35 ), array( 'sub' => '35' ) ) );

		$result = $client->validate_user_claim( array( 'sub' => 36 ), array( 'sub' => '35' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'incorrect-user-claim', $result->get_error_code() );
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
			'issuer'             => '',
			'jwks_cache_ttl'     => 3600,
			'state_time_limit'   => 180,
			'allow_internal_idp' => false,
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
			$merged['issuer'],
			$merged['jwks_cache_ttl'],
			$merged['state_time_limit'],
			$merged['allow_internal_idp'],
			$logger
		);
	}

	/**
	 * Create a client whose header and cookie output can be controlled.
	 *
	 * @param bool $headers_sent Whether headers count as already sent.
	 *
	 * @return OpenID_Connect_Generic_Client
	 */
	private function create_browser_client( bool $headers_sent = false ): OpenID_Connect_Generic_Client {
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		return new class( $headers_sent, $logger ) extends OpenID_Connect_Generic_Client {
			/**
			 * @var bool
			 */
			public $sent;

			/**
			 * @param bool                                 $headers_sent Whether headers count as already sent.
			 * @param OpenID_Connect_Generic_Option_Logger $logger       Logger.
			 */
			public function __construct( bool $headers_sent, $logger ) {
				$this->sent = $headers_sent;
				parent::__construct( 'test_client', 'test_secret', 'openid', 'https://idp.example.com/authorize', '', 'https://idp.example.com/token', 'https://example.com/callback', '', '', '', 3600, 180, false, $logger );
			}

			protected function headers_sent() {
				return $this->sent;
			}

			protected function send_browser_cookie( $browser_id ) {
			}
		};
	}

	/**
	 * Start a login in the current browser and return its state.
	 *
	 * @param OpenID_Connect_Generic_Client $client The client.
	 *
	 * @return string
	 */
	private function start_bound_state( OpenID_Connect_Generic_Client $client ): string {
		$state = $client->new_state( home_url() );
		$client->bind_state( $state, $client->ensure_browser_id() );

		return $state;
	}

	/**
	 * Clear the browser cookie after each state test.
	 *
	 * @return void
	 */
	private function clear_browser_cookie(): void {
		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
	}

	/**
	 * A state is accepted once, in the browser that started the login.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_check_state_accepts_state_once_in_starting_browser() {
		$this->clear_browser_cookie();
		$client = $this->create_browser_client();
		$state = $this->start_bound_state( $client );

		$this->assertTrue( $client->check_state( $state ), 'The starting browser should be accepted.' );
		$this->assertFalse( $client->check_state( $state ), 'A state should only be accepted once.' );

		$this->clear_browser_cookie();
	}

	/**
	 * A state returned in a different browser is refused.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_check_state_refuses_other_browser() {
		$this->clear_browser_cookie();
		$client = $this->create_browser_client();
		$state = $this->start_bound_state( $client );

		$_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] = str_repeat( 'a', 32 );
		$this->assertFalse( $client->check_state( $state ), 'Another browser should be refused.' );

		$this->clear_browser_cookie();
		$this->assertFalse( $client->check_state( $state ), 'A browser without the cookie should be refused.' );
	}

	/**
	 * A state that was never linked to a browser is refused.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_check_state_refuses_unbound_state() {
		$this->clear_browser_cookie();
		$client = $this->create_browser_client();
		$client->ensure_browser_id();
		$state = $client->new_state( home_url() );

		$this->assertFalse( $client->check_state( $state ) );

		$this->clear_browser_cookie();
	}

	/**
	 * Malformed state values are refused without touching storage.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_check_state_refuses_malformed_state() {
		$client = $this->create_browser_client();

		$this->assertFalse( $client->check_state( '../etc' ) );
		$this->assertFalse( $client->check_state( array( 'a' ) ) );
	}

	/**
	 * The browser cookie is created only while headers can still be sent, and reused after.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_ensure_browser_id() {
		$this->clear_browser_cookie();

		$this->assertSame( '', $this->create_browser_client( true )->ensure_browser_id(), 'No cookie can be set after headers are sent.' );

		$browser_id = $this->create_browser_client()->ensure_browser_id();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $browser_id );
		$this->assertSame( $browser_id, $this->create_browser_client( true )->ensure_browser_id(), 'An existing cookie should be reused.' );

		$this->clear_browser_cookie();
	}

	/**
	 * Deleting a state removes everything stored with it.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_delete_state() {
		$this->clear_browser_cookie();
		$client = $this->create_browser_client();
		$state = $this->start_bound_state( $client );
		$client->set_state_url( $state, 'https://idp.example.com/authorize' );

		$client->delete_state( $state );

		$this->assertFalse( get_transient( 'openid-connect-generic-state--' . $state ) );
		$this->assertFalse( $client->is_state_bound( $state ) );
		$this->assertSame( '', $client->get_state_url( $state ) );

		$this->clear_browser_cookie();
	}

	/**
	 * The id_token nonce must match the one sent for the state.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_validate_id_token_nonce() {
		$client = $this->create_browser_client();
		$state = $client->new_state( home_url() );
		$nonce = $client->new_nonce( $state );

		$this->assertTrue( $client->validate_id_token_nonce( array( 'nonce' => $nonce ), $state ), 'A matching nonce should be accepted.' );

		$mismatch = $client->validate_id_token_nonce( array( 'nonce' => str_repeat( 'd', 32 ) ), $state );
		$this->assertWPError( $mismatch, 'A different nonce should be refused.' );
		$this->assertSame( 'invalid-nonce', $mismatch->get_error_code() );

		$this->assertWPError( $client->validate_id_token_nonce( array( 'nonce' => array( $nonce ) ), $state ), 'A non-string nonce should be refused.' );

		$other_state = $client->new_state( home_url() );
		$this->assertWPError( $client->validate_id_token_nonce( array( 'nonce' => $nonce ), $other_state ), 'A nonce from another login should be refused.' );

		$client->delete_state( $state );
		$this->assertWPError( $client->validate_id_token_nonce( array( 'nonce' => $nonce ), $state ), 'A nonce should not outlive its state.' );
	}

	/**
	 * An id_token without a nonce claim is allowed for IDPs that do not return it.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_validate_id_token_nonce_allows_missing_claim() {
		$client = $this->create_browser_client();
		$state = $client->new_state( home_url() );
		$client->new_nonce( $state );

		$this->assertTrue( $client->validate_id_token_nonce( array( 'sub' => 'user' ), $state ) );
	}

	/**
	 * The code challenge is the S256 hash of the stored verifier, and the verifier is sent with the token request.
	 *
	 * @group ClientTests
	 * @group StateTests
	 */
	public function test_pkce_code_challenge_and_token_request() {
		$client = $this->create_browser_client();
		$state = $client->new_state( home_url() );

		$challenge = $client->new_code_challenge( $state );
		$verifier = $client->get_code_verifier( $state );

		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier, 'The verifier should meet the RFC 7636 format.' );
		$this->assertSame( rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ), $challenge );

		$sent_body = null;
		$capture = static function ( $request, $operation ) use ( &$sent_body ) {
			if ( 'get-authentication-token' === $operation ) {
				$sent_body = $request['body'];
			}
			return $request;
		};
		$respond = static function () {
			return array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
			);
		};
		add_filter( 'openid-connect-generic-alter-request', $capture, 10, 2 );
		add_filter( 'pre_http_request', $respond );

		$client->request_authentication_token( 'auth-code', $verifier );
		$this->assertSame( $verifier, $sent_body['code_verifier'] );

		$client->request_authentication_token( 'auth-code' );
		$this->assertArrayNotHasKey( 'code_verifier', $sent_body, 'No verifier should be sent when PKCE was not used.' );

		remove_filter( 'openid-connect-generic-alter-request', $capture, 10 );
		remove_filter( 'pre_http_request', $respond );

		$client->delete_state( $state );
		$this->assertSame( '', $client->get_code_verifier( $state ) );
	}

}
