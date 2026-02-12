<?php
/**
 * Class OpenID_Connect_Generic_Settings_Page_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin admin settings page class test case.
 */
class OpenID_Connect_Generic_Settings_Page_Test extends WP_UnitTestCase {

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
	 * Test plugin admin settings do_text_field() method.
	 *
	 * @group SettingsPageTests
	 */
	public function test_plugin_settings_page_do_text_field() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	/**
	 * Test fetch_discovery_document with valid response.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_fetch_discovery_document_success() {
		// Mock wp_remote_get to return valid discovery document.
		add_filter( 'pre_http_request', array( $this, 'mock_valid_discovery_response' ), 10, 3 );

		$settings_page = $this->create_settings_page();
		$discovery = $this->call_private_method(
			$settings_page,
			'fetch_discovery_document',
			array( 'https://example.com/.well-known/openid-configuration' )
		);

		$this->assertIsArray( $discovery );
		$this->assertArrayHasKey( 'authorization_endpoint', $discovery );
		$this->assertArrayHasKey( 'token_endpoint', $discovery );
		$this->assertArrayHasKey( 'jwks_uri', $discovery );
		$this->assertArrayHasKey( 'issuer', $discovery );

		remove_filter( 'pre_http_request', array( $this, 'mock_valid_discovery_response' ) );
	}

	/**
	 * Mock a valid discovery document response.
	 *
	 * @return array Mocked HTTP response.
	 */
	public function mock_valid_discovery_response() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'issuer'                 => 'https://example.com/',
					'authorization_endpoint' => 'https://example.com/authorize',
					'token_endpoint'         => 'https://example.com/token',
					'userinfo_endpoint'      => 'https://example.com/userinfo',
					'jwks_uri'               => 'https://example.com/.well-known/jwks.json',
					'end_session_endpoint'   => 'https://example.com/logout',
				)
			),
		);
	}

	/**
	 * Test fetch_discovery_document with empty URL.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_fetch_discovery_document_empty_url() {
		$settings_page = $this->create_settings_page();
		$result = $this->call_private_method(
			$settings_page,
			'fetch_discovery_document',
			array( '' )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'empty-discovery-url', $result->get_error_code() );
	}

	/**
	 * Test fetch_discovery_document with invalid URL format.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_fetch_discovery_document_invalid_url() {
		$settings_page = $this->create_settings_page();
		$result = $this->call_private_method(
			$settings_page,
			'fetch_discovery_document',
			array( 'not-a-valid-url' )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-discovery-url', $result->get_error_code() );
	}

	/**
	 * Test fetch_discovery_document with missing required fields.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_fetch_discovery_document_missing_required_fields() {
		add_filter( 'pre_http_request', array( $this, 'mock_incomplete_discovery_response' ), 10, 3 );

		$settings_page = $this->create_settings_page();
		$result = $this->call_private_method(
			$settings_page,
			'fetch_discovery_document',
			array( 'https://example.com/.well-known/openid-configuration' )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'discovery-missing-fields', $result->get_error_code() );
		$this->assertStringContainsString( 'token_endpoint', $result->get_error_message() );

		remove_filter( 'pre_http_request', array( $this, 'mock_incomplete_discovery_response' ) );
	}

	/**
	 * Mock an incomplete discovery document response.
	 *
	 * @return array Mocked HTTP response with missing fields.
	 */
	public function mock_incomplete_discovery_response() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'issuer'                 => 'https://example.com/',
					'authorization_endpoint' => 'https://example.com/authorize',
					// Missing token_endpoint and jwks_uri.
				)
			),
		);
	}

	/**
	 * Test fetch_discovery_document with invalid JSON.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_fetch_discovery_document_invalid_json() {
		add_filter( 'pre_http_request', array( $this, 'mock_invalid_json_response' ), 10, 3 );

		$settings_page = $this->create_settings_page();
		$result = $this->call_private_method(
			$settings_page,
			'fetch_discovery_document',
			array( 'https://example.com/.well-known/openid-configuration' )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'discovery-invalid-json', $result->get_error_code() );

		remove_filter( 'pre_http_request', array( $this, 'mock_invalid_json_response' ) );
	}

	/**
	 * Mock an invalid JSON response.
	 *
	 * @return array Mocked HTTP response with invalid JSON.
	 */
	public function mock_invalid_json_response() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => 'this is not valid json',
		);
	}

	/**
	 * Test fetch_discovery_document with HTTP error.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_fetch_discovery_document_http_error() {
		add_filter( 'pre_http_request', array( $this, 'mock_http_error_response' ), 10, 3 );

		$settings_page = $this->create_settings_page();
		$result = $this->call_private_method(
			$settings_page,
			'fetch_discovery_document',
			array( 'https://example.com/.well-known/openid-configuration' )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'discovery-fetch-failed', $result->get_error_code() );

		remove_filter( 'pre_http_request', array( $this, 'mock_http_error_response' ) );
	}

	/**
	 * Mock an HTTP error response.
	 *
	 * @return array Mocked HTTP error response.
	 */
	public function mock_http_error_response() {
		return array(
			'response' => array( 'code' => 404 ),
			'body'     => 'Not Found',
		);
	}

	/**
	 * Test populate_settings_from_discovery maps fields correctly.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_populate_settings_from_discovery() {
		$settings = new OpenID_Connect_Generic_Option_Settings( array() );
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$settings_page = new OpenID_Connect_Generic_Settings_Page( $settings, $logger );

		$discovery = array(
			'authorization_endpoint' => 'https://example.com/auth',
			'token_endpoint'         => 'https://example.com/token',
			'userinfo_endpoint'      => 'https://example.com/userinfo',
			'jwks_uri'               => 'https://example.com/jwks.json',
			'end_session_endpoint'   => 'https://example.com/logout',
		);

		$populated = $this->call_private_method(
			$settings_page,
			'populate_settings_from_discovery',
			array( $discovery )
		);

		// Verify returned list of populated fields.
		$this->assertContains( 'endpoint_login', $populated );
		$this->assertContains( 'endpoint_token', $populated );
		$this->assertContains( 'endpoint_userinfo', $populated );
		$this->assertContains( 'endpoint_jwks', $populated );
		$this->assertContains( 'endpoint_end_session', $populated );

		// Verify settings were actually updated.
		$this->assertEquals( 'https://example.com/auth', $settings->endpoint_login );
		$this->assertEquals( 'https://example.com/token', $settings->endpoint_token );
		$this->assertEquals( 'https://example.com/userinfo', $settings->endpoint_userinfo );
		$this->assertEquals( 'https://example.com/jwks.json', $settings->endpoint_jwks );
		$this->assertEquals( 'https://example.com/logout', $settings->endpoint_end_session );
	}

	/**
	 * Test populate_settings_from_discovery with partial fields.
	 *
	 * @group SettingsPageTests
	 * @group DiscoveryTests
	 */
	public function test_populate_settings_from_discovery_partial() {
		$settings = new OpenID_Connect_Generic_Option_Settings( array() );
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$settings_page = new OpenID_Connect_Generic_Settings_Page( $settings, $logger );

		$discovery = array(
			'authorization_endpoint' => 'https://example.com/auth',
			'token_endpoint'         => 'https://example.com/token',
			'jwks_uri'               => 'https://example.com/jwks.json',
			// Missing userinfo_endpoint and end_session_endpoint.
		);

		$populated = $this->call_private_method(
			$settings_page,
			'populate_settings_from_discovery',
			array( $discovery )
		);

		// Should only populate fields that exist.
		$this->assertContains( 'endpoint_login', $populated );
		$this->assertContains( 'endpoint_token', $populated );
		$this->assertContains( 'endpoint_jwks', $populated );
		$this->assertNotContains( 'endpoint_userinfo', $populated );
		$this->assertNotContains( 'endpoint_end_session', $populated );

		// Verify only provided fields were set.
		$this->assertEquals( 'https://example.com/auth', $settings->endpoint_login );
		$this->assertEquals( 'https://example.com/token', $settings->endpoint_token );
		$this->assertEquals( 'https://example.com/jwks.json', $settings->endpoint_jwks );
	}

	/**
	 * Helper to call private methods for testing.
	 *
	 * @param object $object     The object instance.
	 * @param string $method_name The method name to call.
	 * @param array  $parameters  The method parameters.
	 *
	 * @return mixed The method return value.
	 */
	private function call_private_method( $object, $method_name, $parameters = array() ) {
		$reflection = new ReflectionClass( get_class( $object ) );
		$method = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $parameters );
	}

	/**
	 * Helper to create settings page instance.
	 *
	 * @return OpenID_Connect_Generic_Settings_Page
	 */
	private function create_settings_page() {
		$settings = new OpenID_Connect_Generic_Option_Settings( array() );
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		return new OpenID_Connect_Generic_Settings_Page( $settings, $logger );
	}

}
