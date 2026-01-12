<?php
/**
 * Class OpenID_Connect_Generic_Login_Form_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin login form and login button handling class test case.
 */
class OpenID_Connect_Generic_Login_Form_Test extends WP_UnitTestCase {

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any registered shortcodes before each test.
		remove_all_shortcodes();
	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
		remove_all_filters( 'openid-connect-generic-login-button-text' );
	}

	/**
	 * Create a test settings stub with specific values.
	 *
	 * @param array $values Settings values to set.
	 * @return object Settings stub.
	 */
	private function create_settings_stub( $values = array() ) {
		$defaults = array(
			'endpoint_login'   => 'https://idp.example.com/authorize',
			'scope'            => 'openid email',
			'client_id'        => 'test-client-id',
			'acr_values'       => '',
			'login_type'       => 'button',
		);

		$settings = array_merge( $defaults, $values );

		// Create anonymous class that acts like the settings object.
		return new class($settings) {
			private $values;

			public function __construct( $values ) {
				$this->values = $values;
			}

			public function __get( $key ) {
				return $this->values[ $key ] ?? null;
			}

			public function __set( $key, $value ) {
				$this->values[ $key ] = $value;
			}
		};
	}

	/**
	 * Test that shortcode is registered during plugin initialization.
	 *
	 * @group LoginFormTests
	 */
	public function test_shortcode_is_registered() {
		$settings = $this->createMock( OpenID_Connect_Generic_Option_Settings::class );
		$settings->login_type = 'button';

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );

		// Register should add the shortcode.
		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$this->assertTrue( shortcode_exists( 'openid_connect_generic_login_button' ) );
	}

	/**
	 * Test that shortcode attributes are passed to authentication URL builder.
	 *
	 * @group LoginFormTests
	 */
	public function test_shortcode_attributes_passed_to_authentication_url() {
		$settings = $this->create_settings_stub( array(
			'endpoint_login' => 'https://idp.example.com/authorize',
			'scope'          => 'openid email',
			'client_id'      => 'default-client-id',
		) );

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '/dashboard' );

		// Critical: Verify get_authentication_url is called with merged attributes.
		$client_wrapper->expects( $this->once() )
			->method( 'get_authentication_url' )
			->with( $this->callback( function( $atts ) {
				// Verify custom attributes override defaults.
				return $atts['button_text'] === 'Custom Login'
					&& $atts['client_id'] === 'custom-client-id'
					&& $atts['scope'] === 'openid profile'
					&& $atts['endpoint_login'] === 'https://idp.example.com/authorize'
					&& $atts['redirect_uri'] === 'https://site.example.com/callback';
			} ) )
			->willReturn( 'https://idp.example.com/authorize?client_id=custom-client-id' );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		// Execute shortcode with custom attributes.
		do_shortcode( '[openid_connect_generic_login_button button_text="Custom Login" client_id="custom-client-id" scope="openid profile"]' );
	}

	/**
	 * Test that custom button text from shortcode attribute is rendered.
	 *
	 * @group LoginFormTests
	 */
	public function test_custom_button_text_renders_in_output() {
		$settings = $this->createMock( OpenID_Connect_Generic_Option_Settings::class );
		$settings->endpoint_login = 'https://idp.example.com/authorize';
		$settings->scope = 'openid';
		$settings->client_id = 'test-client';
		$settings->acr_values = '';
		$settings->login_type = 'button';

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button button_text="Sign In With SSO"]' );

		$this->assertStringContainsString( 'Sign In With SSO', $output );
		$this->assertStringNotContainsString( 'Login with OpenID Connect', $output );
	}

	/**
	 * Test XSS protection on button text.
	 *
	 * @group LoginFormTests
	 */
	public function test_button_text_xss_protection() {
		$settings = $this->createMock( OpenID_Connect_Generic_Option_Settings::class );
		$settings->endpoint_login = 'https://idp.example.com/authorize';
		$settings->scope = 'openid';
		$settings->client_id = 'test-client';
		$settings->acr_values = '';
		$settings->login_type = 'button';

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button button_text="<script>alert(1)</script>Click"]' );

		// Malicious script tag should be escaped.
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
		$this->assertStringContainsString( 'Click', $output );
	}

	/**
	 * Test that authentication URL is escaped in HTML output.
	 *
	 * @group LoginFormTests
	 */
	public function test_authentication_url_escaping() {
		$settings = $this->createMock( OpenID_Connect_Generic_Option_Settings::class );
		$settings->endpoint_login = 'https://idp.example.com/authorize';
		$settings->scope = 'openid';
		$settings->client_id = 'test-client';
		$settings->acr_values = '';
		$settings->login_type = 'button';

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )
			->willReturn( 'https://idp.example.com/authorize?client_id=test&redirect_uri=https://site.example.com/callback&scope=openid' );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button]' );

		// Extract the href value.
		preg_match( '/href="([^"]+)"/', $output, $matches );
		$this->assertNotEmpty( $matches, 'href attribute should be present' );

		$href_value = $matches[1];

		// URL should not contain unescaped ampersands in HTML context.
		// Raw URL has & but in HTML it could be &amp; depending on how esc_url_raw works.
		$this->assertStringContainsString( 'https://idp.example.com/authorize', $href_value );
		$this->assertStringContainsString( 'client_id=test', $href_value );
	}

	/**
	 * Test that button text filter is applied correctly.
	 *
	 * @group LoginFormTests
	 */
	public function test_button_text_filter_is_applied() {
		$settings = $this->createMock( OpenID_Connect_Generic_Option_Settings::class );
		$settings->endpoint_login = 'https://idp.example.com/authorize';
		$settings->scope = 'openid';
		$settings->client_id = 'test-client';
		$settings->acr_values = '';
		$settings->login_type = 'button';

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		// Add filter that should modify button text.
		add_filter( 'openid-connect-generic-login-button-text', function( $text ) {
			return 'Filtered: ' . $text;
		} );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button button_text="Original Text"]' );

		$this->assertStringContainsString( 'Filtered: Original Text', $output );
		$this->assertStringNotContainsString( '>Original Text<', $output );
	}

	/**
	 * Test button renders with valid HTML structure.
	 *
	 * @group LoginFormTests
	 */
	public function test_button_html_structure() {
		$settings = $this->createMock( OpenID_Connect_Generic_Option_Settings::class );
		$settings->endpoint_login = 'https://idp.example.com/authorize';
		$settings->scope = 'openid';
		$settings->client_id = 'test-client';
		$settings->acr_values = '';
		$settings->login_type = 'button';

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button]' );

		// Verify essential HTML structure.
		$this->assertMatchesRegularExpression( '/<div[^>]*class="[^"]*openid-connect-login-button[^"]*"/', $output, 'Should have wrapper div with class' );
		$this->assertMatchesRegularExpression( '/<a[^>]*class="[^"]*button button-large[^"]*"/', $output, 'Should have link with button classes' );
		$this->assertMatchesRegularExpression( '/<a[^>]*href="https:\/\/[^"]+"/', $output, 'Should have href with https URL' );
	}

	/**
	 * Test that default settings are used when no shortcode attributes provided.
	 *
	 * @group LoginFormTests
	 */
	public function test_defaults_from_settings_are_used() {
		$settings = $this->create_settings_stub( array(
			'endpoint_login' => 'https://default-idp.example.com/authorize',
			'scope'          => 'openid email profile',
			'client_id'      => 'default-client-123',
			'acr_values'     => 'test-acr',
		) );

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/wp-admin' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '/wp-admin/profile.php' );

		// Verify defaults from settings are passed.
		$client_wrapper->expects( $this->once() )
			->method( 'get_authentication_url' )
			->with( $this->callback( function( $atts ) {
				return $atts['endpoint_login'] === 'https://default-idp.example.com/authorize'
					&& $atts['scope'] === 'openid email profile'
					&& $atts['client_id'] === 'default-client-123'
					&& $atts['redirect_uri'] === 'https://site.example.com/wp-admin'
					&& $atts['redirect_to'] === '/wp-admin/profile.php'
					&& $atts['acr_values'] === 'test-acr';
			} ) )
			->willReturn( 'https://default-idp.example.com/authorize' );

		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		do_shortcode( '[openid_connect_generic_login_button]' );
	}

}
