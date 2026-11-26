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
				// Note: button_text is NOT passed to get_authentication_url, only used for rendering.
				return $atts['client_id'] === 'custom-client-id'
					&& $atts['scope'] === 'openid profile'
					&& $atts['endpoint_login'] === 'https://idp.example.com/authorize'
					&& $atts['redirect_uri'] === 'https://site.example.com/callback'
					&& $atts['redirect_to'] === '/dashboard';
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

	/**
	 * Test that the button icon from settings is rendered before the text.
	 *
	 * @group LoginFormTests
	 */
	public function test_button_icon_from_settings_renders_in_output() {
		$settings = $this->create_settings_stub( array(
			'login_button_icon' => 'https://site.example.com/icon.svg',
		) );

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		// Only the plugin's footer output is under test; core's wp_footer callbacks emit deprecation notices.
		remove_all_actions( 'wp_footer' );
		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button button_text="Sign In"]' );

		$this->assertMatchesRegularExpression( '/<a[^>]*><span class="openid-connect-login-button-icon" aria-hidden="true"><\/span>Sign In<\/a>/', $output );

		ob_start();
		do_action( 'wp_footer' );
		$styles = ob_get_clean();

		$this->assertStringContainsString( 'mask:url("https://site.example.com/icon.svg") center/contain no-repeat', $styles );
		$this->assertStringContainsString( 'background-color:currentColor', $styles );
	}

	/**
	 * Test that no icon is rendered when none is configured.
	 *
	 * @group LoginFormTests
	 */
	public function test_no_button_icon_by_default() {
		$settings = $this->create_settings_stub();

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		// Only the plugin's footer output is under test; core's wp_footer callbacks emit deprecation notices.
		remove_all_actions( 'wp_footer' );
		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		$output = do_shortcode( '[openid_connect_generic_login_button]' );

		$this->assertStringNotContainsString( 'openid-connect-login-button-icon', $output );

		ob_start();
		do_action( 'wp_footer' );
		$this->assertStringNotContainsString( 'openid-connect-login-button-icon', ob_get_clean() );
	}

	/**
	 * Test that the button icon URL cannot break out of the CSS.
	 *
	 * @group LoginFormTests
	 */
	public function test_button_icon_url_escaping() {
		$settings = $this->create_settings_stub( array(
			'login_button_icon' => 'https://site.example.com/x.svg");}body{display:none',
		) );

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		// Only the plugin's footer output is under test; core's wp_footer callbacks emit deprecation notices.
		remove_all_actions( 'wp_footer' );
		OpenID_Connect_Generic_Login_Form::register( $settings, $client_wrapper, $client );

		do_shortcode( '[openid_connect_generic_login_button]' );

		ob_start();
		do_action( 'wp_footer' );
		$styles = ob_get_clean();

		// Quotes are removed from the URL, so it cannot break out of the CSS url() value.
		$this->assertStringNotContainsString( '");}', $styles );
	}

	/**
	 * Test where the login button appears for each login type.
	 *
	 * @group LoginFormTests
	 *
	 * @dataProvider login_type_provider
	 *
	 * @param string $login_type        The login type setting.
	 * @param bool   $button_on_login   Whether wp-login.php shows the button.
	 */
	public function test_login_type_controls_login_page_button( $login_type, $button_on_login ) {
		$settings = $this->create_settings_stub( array( 'login_type' => $login_type ) );

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->method( 'get_redirect_uri' )->willReturn( 'https://site.example.com/callback' );

		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturn( 'https://idp.example.com/authorize' );

		$login_form = new OpenID_Connect_Generic_Login_Form( $settings, $client_wrapper, $client );

		$login_page = $login_form->handle_login_page( '' );
		if ( $button_on_login ) {
			$this->assertStringContainsString( 'openid-connect-login-button', $login_page );
		} else {
			$this->assertStringNotContainsString( 'openid-connect-login-button', $login_page );
		}

		// The shortcode button works for every login type.
		$this->assertStringContainsString( 'openid-connect-login-button', $login_form->make_login_button() );
	}

	/**
	 * Data provider for test_login_type_controls_login_page_button.
	 *
	 * @return array
	 */
	public function login_type_provider() {
		return array(
			'button'    => array( 'button', true ),
			'shortcode' => array( 'shortcode', false ),
		);
	}

	/**
	 * Test that the login button is added to the top of the WooCommerce login form.
	 *
	 * @group LoginFormTests
	 */
	public function test_woocommerce_login_at_start() {
		$settings = $this->create_settings_stub( array(
			'woocommerce_login' => 'start',
		) );

		OpenID_Connect_Generic_Login_Form::register( $settings, $this->create_woocommerce_client_wrapper(), $this->createMock( OpenID_Connect_Generic_Client::class ) );

		ob_start();
		do_action( 'woocommerce_login_form_start' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'openid-connect-login-button', $output );
		$this->assertMatchesRegularExpression( '/openid-connect-login-button.*openid-connect-login-divider/s', $output, 'Divider should be under the button' );
		$this->assertFalse( has_action( 'woocommerce_login_form_end' ) );
	}

	/**
	 * Test that the login button is added to the bottom of the WooCommerce login form.
	 *
	 * @group LoginFormTests
	 */
	public function test_woocommerce_login_at_end() {
		$settings = $this->create_settings_stub( array(
			'woocommerce_login' => 'end',
		) );

		// Only the plugin's footer output is under test; core's wp_footer callbacks emit deprecation notices.
		remove_all_actions( 'wp_footer' );
		OpenID_Connect_Generic_Login_Form::register( $settings, $this->create_woocommerce_client_wrapper(), $this->createMock( OpenID_Connect_Generic_Client::class ) );

		ob_start();
		do_action( 'woocommerce_login_form_end' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'openid-connect-login-button', $output );
		$this->assertMatchesRegularExpression( '/openid-connect-login-divider.*openid-connect-login-button/s', $output, 'Divider should be above the button' );

		ob_start();
		do_action( 'wp_footer' );
		$this->assertStringContainsString( '.openid-connect-login-divider', ob_get_clean() );
		$this->assertFalse( has_action( 'woocommerce_login_form_start' ) );
	}

	/**
	 * Test that WooCommerce auto login redirects My Account instead of adding the button.
	 *
	 * @group LoginFormTests
	 */
	public function test_woocommerce_auto_login_registers_redirect_instead_of_button() {
		$settings = $this->create_settings_stub( array(
			'woocommerce_login' => 'auto',
		) );

		OpenID_Connect_Generic_Login_Form::register( $settings, $this->create_woocommerce_client_wrapper(), $this->createMock( OpenID_Connect_Generic_Client::class ) );

		$this->assertNotFalse( has_action( 'template_redirect' ) );
		$this->assertNotFalse( has_filter( 'woocommerce_logout_default_redirect_url' ) );
		$this->assertFalse( has_action( 'woocommerce_login_form_start' ) );
		$this->assertFalse( has_action( 'woocommerce_login_form_end' ) );
	}

	/**
	 * Test that the login button is not added to the WooCommerce login form by default.
	 *
	 * @group LoginFormTests
	 */
	public function test_no_woocommerce_login_by_default() {
		$settings = $this->create_settings_stub( array(
			'woocommerce_login' => '',
		) );

		OpenID_Connect_Generic_Login_Form::register( $settings, $this->create_woocommerce_client_wrapper(), $this->createMock( OpenID_Connect_Generic_Client::class ) );

		$this->assertFalse( has_action( 'woocommerce_login_form_start' ) );
		$this->assertFalse( has_action( 'woocommerce_login_form_end' ) );
	}

	/**
	 * Define test versions of the WooCommerce functions the plugin uses.
	 *
	 * WooCommerce isn't loaded in the tests, so tests that call this run in a separate
	 * process. Each function reads its answer from $GLOBALS['oidc_test_woocommerce'].
	 *
	 * @param array $values The page the test is on: 'account_page' (bool) and 'endpoint' (string).
	 *
	 * @return void
	 */
	private function define_woocommerce_functions( $values = array() ) {
		$GLOBALS['oidc_test_woocommerce'] = array_merge(
			array(
				'account_page' => true,
				'endpoint'     => '',
			),
			$values
		);

		if ( ! function_exists( 'is_account_page' ) ) {
			/**
			 * Test version of WooCommerce's is_account_page().
			 *
			 * @return bool
			 */
			function is_account_page() {
				return $GLOBALS['oidc_test_woocommerce']['account_page'];
			}
		}

		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			/**
			 * Test version of WooCommerce's is_wc_endpoint_url().
			 *
			 * @param string|false $endpoint The endpoint to check.
			 *
			 * @return bool
			 */
			function is_wc_endpoint_url( $endpoint = false ) {
				return $endpoint === $GLOBALS['oidc_test_woocommerce']['endpoint'];
			}
		}

		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			/**
			 * Test version of WooCommerce's wc_get_page_permalink().
			 *
			 * @param string $page The page slug.
			 *
			 * @return string
			 */
			function wc_get_page_permalink( $page ) {
				return 'myaccount' === $page ? home_url( '/my-account/' ) : home_url( '/' );
			}
		}

		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			/**
			 * Test version of WooCommerce's wc_get_checkout_url().
			 *
			 * @return string
			 */
			function wc_get_checkout_url() {
				return home_url( '/checkout/' );
			}
		}
	}

	/**
	 * Create a client wrapper whose login URL shows the page the user returns to.
	 *
	 * @return OpenID_Connect_Generic_Client_Wrapper
	 */
	private function create_woocommerce_client_wrapper() {
		$client_wrapper = $this->createMock( OpenID_Connect_Generic_Client_Wrapper::class );
		$client_wrapper->method( 'get_redirect_to' )->willReturn( '' );
		$client_wrapper->method( 'get_authentication_url' )->willReturnCallback(
			function ( $atts = array() ) {
				return 'https://idp.example.com/authorize?redirect_to=' . rawurlencode( $atts['redirect_to'] ?? '' );
			}
		);
		return $client_wrapper;
	}

	/**
	 * Create a login form with WooCommerce Auto Login.
	 *
	 * @return OpenID_Connect_Generic_Login_Form
	 */
	private function create_woocommerce_auto_login_form() {
		return new OpenID_Connect_Generic_Login_Form(
			$this->create_settings_stub( array( 'woocommerce_login' => 'auto' ) ),
			$this->create_woocommerce_client_wrapper(),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);
	}

	/**
	 * Run the WooCommerce Auto Login redirect and return where it redirected to, or null.
	 *
	 * @param OpenID_Connect_Generic_Login_Form $login_form The login form.
	 *
	 * @return string|null
	 */
	private function get_woocommerce_auto_redirect( $login_form ) {
		// Stop at the redirect, before the exit.
		$stop = function ( $location ) {
			throw new RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $stop );

		try {
			$login_form->handle_redirect_woocommerce_auto();
			return null;
		} catch ( RuntimeException $redirect ) {
			return $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}
	}

	/**
	 * Test that a logged out visit to My Account goes to the identity provider and back to My Account.
	 *
	 * @group LoginFormTests
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_auto_login_redirects_my_account() {
		$this->define_woocommerce_functions();
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertSame(
			'https://idp.example.com/authorize?redirect_to=' . rawurlencode( home_url( '/my-account/' ) ),
			$this->get_woocommerce_auto_redirect( $this->create_woocommerce_auto_login_form() )
		);
	}

	/**
	 * Test that WooCommerce Auto Login leaves other pages, lost password, form posts and logged in users alone.
	 *
	 * @group LoginFormTests
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_auto_login_does_not_redirect() {
		$this->define_woocommerce_functions();
		$login_form = $this->create_woocommerce_auto_login_form();
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$GLOBALS['oidc_test_woocommerce']['account_page'] = false;
		$this->assertNull( $this->get_woocommerce_auto_redirect( $login_form ), 'Other pages' );
		$GLOBALS['oidc_test_woocommerce']['account_page'] = true;

		$GLOBALS['oidc_test_woocommerce']['endpoint'] = 'lost-password';
		$this->assertNull( $this->get_woocommerce_auto_redirect( $login_form ), 'Lost password' );
		$GLOBALS['oidc_test_woocommerce']['endpoint'] = '';

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertNull( $this->get_woocommerce_auto_redirect( $login_form ), 'Form posts' );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		wp_set_current_user( self::factory()->user->create() );
		$this->assertNull( $this->get_woocommerce_auto_redirect( $login_form ), 'Logged in users' );
	}

	/**
	 * Test that logging out of My Account with WooCommerce Auto Login goes to the home page.
	 *
	 * @group LoginFormTests
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_auto_login_logout_goes_home() {
		$this->define_woocommerce_functions();
		$login_form = $this->create_woocommerce_auto_login_form();

		$this->assertSame( home_url( '/' ), $login_form->woocommerce_logout_redirect_url( home_url( '/my-account/' ) ) );
		$this->assertSame( home_url( '/shop/' ), $login_form->woocommerce_logout_redirect_url( home_url( '/shop/' ) ), 'Other logout redirects are left alone' );
	}

	/**
	 * Test that WooCommerce Auto Login shows only the login button on checkout, returning to checkout.
	 *
	 * @group LoginFormTests
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_auto_login_checkout_shows_button_only() {
		$this->define_woocommerce_functions();
		update_option( 'woocommerce_enable_checkout_login_reminder', 'yes' );
		OpenID_Connect_Generic_Login_Form::register(
			$this->create_settings_stub( array( 'woocommerce_login' => 'auto' ) ),
			$this->create_woocommerce_client_wrapper(),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);

		// WooCommerce's checkout login form, which the button replaces.
		add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		if ( ! function_exists( 'woocommerce_checkout_login_form' ) ) {
			/**
			 * Test version of WooCommerce's checkout login form.
			 *
			 * @return void
			 */
			function woocommerce_checkout_login_form() {
				echo '<form class="woocommerce-form-login">';
			}
		}

		ob_start();
		do_action( 'woocommerce_before_checkout_form' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'openid-connect-login-button', $output );
		$this->assertStringContainsString( rawurlencode( home_url( '/checkout/' ) ), $output, 'Returns to checkout' );
		$this->assertStringNotContainsString( 'woocommerce-form-login', $output, 'No username or password fields' );

		// Without WooCommerce's checkout login reminder, neither the form nor the button is shown.
		update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
		add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		ob_start();
		do_action( 'woocommerce_before_checkout_form' );
		$this->assertSame( '', ob_get_clean() );
	}

}
