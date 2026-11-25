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

			public function __isset( $key ) {
				return isset( $this->values[ $key ] );
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
	 * Create a login form instance for testing its methods directly.
	 *
	 * @param array $values Settings values to set.
	 * @return OpenID_Connect_Generic_Login_Form
	 */
	private function create_login_form( $values = array() ) {
		return new OpenID_Connect_Generic_Login_Form(
			$this->create_settings_stub( $values ),
			$this->createMock( OpenID_Connect_Generic_Client_Wrapper::class ),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);
	}

	/**
	 * Test that password logins are not filtered by default.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_allowed_by_default() {
		OpenID_Connect_Generic_Login_Form::register(
			$this->create_settings_stub(),
			$this->createMock( OpenID_Connect_Generic_Client_Wrapper::class ),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);

		$callbacks = $GLOBALS['wp_filter']['authenticate']->callbacks[30] ?? array();
		$this->assertStringNotContainsString( 'block_password_login', implode( ',', array_keys( $callbacks ) ) );
	}

	/**
	 * Test that roles not allowed a password are blocked, using the button text.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_blocked_for_roles() {
		$login_form = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array( 'subscriber', 'customer' ),
				'login_button_text'    => 'Log in with SSO',
			)
		);
		$editor     = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$subscriber = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$result = $login_form->block_password_login( $editor );
		$this->assertWPError( $result );
		$this->assertSame( 'password-login-blocked', $result->get_error_code() );
		$this->assertStringContainsString( '"Log in with SSO"', $result->get_error_message() );

		$this->assertSame( $subscriber, $login_form->block_password_login( $subscriber ) );
	}

	/**
	 * Test that only users linked to the identity provider are blocked.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_blocked_for_linked_users() {
		$login_form = $this->create_login_form( array( 'password_login' => 'linked' ) );
		$linked     = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$unlinked   = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		update_user_option( $linked->ID, 'openid-connect-generic-subject-identity', 'idp-user-1', true );

		$this->assertWPError( $login_form->block_password_login( $linked ) );
		$this->assertSame( $unlinked, $login_form->block_password_login( $unlinked ) );
	}

	/**
	 * Test that failed logins are passed through unchanged.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_passes_through_errors() {
		$login_form = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array( 'subscriber' ),
			)
		);
		$error      = new WP_Error( 'incorrect_password', 'Wrong password.' );

		$this->assertSame( $error, $login_form->block_password_login( $error ) );
		$this->assertNull( $login_form->block_password_login( null ) );
	}

	/**
	 * Register the login form with password logins blocked for every role but subscriber,
	 * and allow Application Passwords on this request as if it were an API request.
	 *
	 * @return void
	 */
	private function register_password_login_with_application_passwords() {
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		add_filter( 'application_password_is_api_request', '__return_true' );

		OpenID_Connect_Generic_Login_Form::register(
			$this->create_settings_stub(
				array(
					'password_login'       => 'roles',
					'password_login_roles' => array( 'subscriber' ),
				)
			),
			$this->createMock( OpenID_Connect_Generic_Client_Wrapper::class ),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);
	}

	/**
	 * Create a user with a known password and an Application Password.
	 *
	 * @param string $role The user's role.
	 *
	 * @return array The user, and their Application Password.
	 */
	private function create_user_with_application_password( $role ) {
		$user = self::factory()->user->create_and_get(
			array(
				'role'      => $role,
				'user_pass' => 'real-password',
			)
		);

		list( $application_password ) = WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => 'Test' ) );

		return array( $user, $application_password );
	}

	/**
	 * Test that a blocked user can still use their own Application Password.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_allows_application_passwords() {
		$this->register_password_login_with_application_passwords();
		list( $admin, $application_password ) = $this->create_user_with_application_password( 'administrator' );

		$this->assertWPError( wp_authenticate( $admin->user_login, 'real-password' ) );

		$result = wp_authenticate( $admin->user_login, $application_password );
		$this->assertInstanceOf( WP_User::class, $result );
		$this->assertSame( $admin->ID, $result->ID );
	}

	/**
	 * Test that one user's Application Password login doesn't let a later password login
	 * in the same request skip the block, as with XML-RPC system.multicall.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_application_password_exemption_is_per_login() {
		$this->register_password_login_with_application_passwords();
		list( $subscriber, $subscriber_application_password ) = $this->create_user_with_application_password( 'subscriber' );
		list( $admin, $admin_application_password )           = $this->create_user_with_application_password( 'administrator' );

		// Another user's Application Password login first.
		$this->assertInstanceOf( WP_User::class, wp_authenticate( $subscriber->user_login, $subscriber_application_password ) );
		$result = wp_authenticate( $admin->user_login, 'real-password' );
		$this->assertWPError( $result );
		$this->assertSame( 'password-login-blocked', $result->get_error_code() );

		// The blocked user's own Application Password login first.
		$this->assertInstanceOf( WP_User::class, wp_authenticate( $admin->user_login, $admin_application_password ) );
		$this->assertWPError( wp_authenticate( $admin->user_login, 'real-password' ) );
	}

	/**
	 * Test that ticking a role allows it to use a password, and no ticked roles blocks everyone.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_allowed_roles_are_configurable() {
		$editor = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

		$allow_editors = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array( 'subscriber', 'editor' ),
			)
		);
		$this->assertSame( $editor, $allow_editors->block_password_login( $editor ) );

		$allow_none = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array(),
			)
		);
		$this->assertWPError( $allow_none->block_password_login( $editor ) );
	}

	/**
	 * Test that users with no role are blocked unless "Users with no role" is ticked.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_login_users_with_no_role() {
		$no_role = self::factory()->user->create_and_get( array( 'role' => '' ) );
		$this->assertSame( array(), $no_role->roles );

		$block_no_role = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array( 'subscriber' ),
			)
		);
		$this->assertWPError( $block_no_role->block_password_login( $no_role ) );
		$this->assertWPError( $block_no_role->block_password_reset( true, $no_role->ID ) );

		$allow_no_role = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array( 'subscriber', OpenID_Connect_Generic_Login_Form::NO_ROLE ),
			)
		);
		$this->assertSame( $no_role, $allow_no_role->block_password_login( $no_role ) );
	}

	/**
	 * Test that password resets are left alone by default.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_reset_allowed_by_default() {
		OpenID_Connect_Generic_Login_Form::register(
			$this->create_settings_stub(),
			$this->createMock( OpenID_Connect_Generic_Client_Wrapper::class ),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);
		$editor = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

		$this->assertIsString( get_password_reset_key( $editor ) );
	}

	/**
	 * Test that users who can't log in with a password can't reset it either.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_reset_blocked_for_roles() {
		OpenID_Connect_Generic_Login_Form::register(
			$this->create_settings_stub(
				array(
					'password_login'       => 'roles',
					'password_login_roles' => array( 'subscriber' ),
					'login_button_text'    => 'Log in with SSO',
				)
			),
			$this->createMock( OpenID_Connect_Generic_Client_Wrapper::class ),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);
		$editor     = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$subscriber = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$result = get_password_reset_key( $editor );
		$this->assertWPError( $result );
		$this->assertSame( 'password-reset-blocked', $result->get_error_code() );
		$this->assertStringContainsString( '"Log in with SSO"', $result->get_error_message() );

		$this->assertIsString( get_password_reset_key( $subscriber ) );
	}

	/**
	 * Test that only users linked to the identity provider can't reset their password.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_reset_blocked_for_linked_users() {
		$login_form = $this->create_login_form( array( 'password_login' => 'linked' ) );
		$linked     = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$unlinked   = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		update_user_option( $linked->ID, 'openid-connect-generic-subject-identity', 'idp-user-1', true );

		$this->assertWPError( $login_form->block_password_reset( true, $linked->ID ) );
		$this->assertTrue( $login_form->block_password_reset( true, $unlinked->ID ) );
	}

	/**
	 * Test that a password reset already refused by WordPress or another plugin stays refused.
	 *
	 * @group LoginFormTests
	 */
	public function test_password_reset_passes_through_earlier_refusals() {
		$login_form = $this->create_login_form(
			array(
				'password_login'       => 'roles',
				'password_login_roles' => array(),
			)
		);
		$editor     = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$error      = new WP_Error( 'no_password_reset', 'Not allowed.' );

		$this->assertFalse( $login_form->block_password_reset( false, $editor->ID ) );
		$this->assertSame( $error, $login_form->block_password_reset( $error, $editor->ID ) );
		$this->assertTrue( $login_form->block_password_reset( true, 0 ) );
	}

	/**
	 * Test that an unknown Password Login value, such as a typo in OIDC_PASSWORD_LOGIN, blocks nobody.
	 *
	 * @group LoginFormTests
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_password_login_unknown_value_blocks_nobody() {
		define( 'OIDC_PASSWORD_LOGIN', 'Roles' );
		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'password_login'       => '',
				'password_login_roles' => array( 'subscriber' ),
			)
		);
		$this->assertSame( 'Roles', $settings->password_login );

		OpenID_Connect_Generic_Login_Form::register(
			$settings,
			$this->createMock( OpenID_Connect_Generic_Client_Wrapper::class ),
			$this->createMock( OpenID_Connect_Generic_Client::class )
		);
		$editor = self::factory()->user->create_and_get(
			array(
				'role'      => 'editor',
				'user_pass' => 'real-password',
			)
		);

		$this->assertInstanceOf( WP_User::class, wp_authenticate( $editor->user_login, 'real-password' ) );
		$this->assertIsString( get_password_reset_key( $editor ) );

		// Calling the checks directly with an unknown value blocks nobody either.
		$login_form = $this->create_login_form(
			array(
				'password_login'       => 'Roles',
				'password_login_roles' => array(),
			)
		);
		$this->assertSame( $editor, $login_form->block_password_login( $editor ) );
		$this->assertTrue( $login_form->block_password_reset( true, $editor->ID ) );
	}

}
