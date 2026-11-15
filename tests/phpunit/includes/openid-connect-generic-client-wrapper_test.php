<?php
/**
 * Class OpenID_Connect_Generic_Client_Wrapper_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin OIDC/oAuth client wrapper class test case.
 */
class OpenID_Connect_Generic_Client_Wrapper_Test extends WP_UnitTestCase {

	/**
	 * @var OpenID_Connect_Generic_Client_Wrapper
	 */
	private $client_wrapper;

	/**
	 * @var WP_User_Meta_Session_Tokens
	 */
	private $manager;

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

		remove_all_filters( 'session_token_manager' );
		$user_id        = self::factory()->user->create();
		$this->manager  = WP_Session_Tokens::get_instance( $user_id );

		$this->client_wrapper = OpenID_Connect_Generic::instance()->client_wrapper;

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		unset( $this->client_wrapper );

		parent::tearDown();

	}

	/**
	 * Test plugin alternate_redirect_uri_parse_request() method.
	 *
	 * @group ClientWrapperTests
	 */
	public function test_plugin_client_wrapper_alternate_redirect_uri_parse_request() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	/**
	 * Test that get_redirect_to() ignores a non-string redirect_to request value
	 * instead of passing an array to esc_url_raw().
	 *
	 * @group ClientWrapperTests
	 */
	public function test_get_redirect_to_ignores_array_request_value() {

		$_REQUEST['redirect_to'] = array( '' );

		$redirect_url = $this->client_wrapper->get_redirect_to();

		$this->assertIsString( $redirect_url, 'A non-string redirect_to should not produce a non-string result' );

		unset( $_REQUEST['redirect_to'] );

	}

	/**
	 * Test if by using the remember-me filter, the user session expiration
	 * is set to 14 days, which is the default of WordPress
	 *
	 * @group ClientWrapperTests
	 */
	public function test_plugin_client_wrapper_remember_me() {
		// Set the remember me option to true
		add_filter( 'openid-connect-generic-remember-me', '__return_true' );

		// Create a user and log in using the login function of the client wrapper
		$user = $this->factory()->user->create_and_get( array( 'user_login' => 'test-remember-me-user' ) );
		$this->client_wrapper->login_user( $user, array(
			'expires_in' => 5 * MINUTE_IN_SECONDS,
		), array(), array(), '' );

		// Retrieve the session tokens
		$manager = WP_Session_Tokens::get_instance( $user->ID );
		$token = $manager->get_all()[0];

		// Assert if the token is set to expire in 14 days, with some timing margin
		$this->assertGreaterThan( time() + 13 * DAY_IN_SECONDS, $token['expiration'] );
		$this->assertLessThan( time() + 15 * DAY_IN_SECONDS, $token['expiration'] );

		// Cleanup
		remove_filter( 'openid-connect-generic-remember-me', '__return_true' );
		$manager->destroy_all();
		wp_clear_auth_cookie();
	}

	/**
	 * Test proper handling of saving refresh tokens.
	 *
	 * @group ClientWrapperTests
	 */
	public function test_save_refresh_token() {
		$expiration 				= time() + DAY_IN_SECONDS;
		$token 							= $this->manager->create( $expiration );
		$token_response 		= array(
			"access_token"  => "TlBN45jURg",
			"token_type"    => "Bearer",
			"refresh_token" => "9yNOxJtZa5",
			"expires_in"    => 3600, // Expiration time of the Access Token in seconds since the response was generated. OPTIONAL.
		);

		$this->client_wrapper->save_refresh_token( $this->manager, $token, $token_response );
		$session = $this->manager->get( $token );

		$this->assertArrayHasKey( $this->client_wrapper::COOKIE_TOKEN_REFRESH_KEY, $session, "Session token is missing expected key!"	);
		$this->assertArrayHasKey( 'refresh_token', $session[ $this->client_wrapper::COOKIE_TOKEN_REFRESH_KEY ], "Refresh token is missing key!" );

		$this->manager->destroy( $token );
	}

	/**
	 * Test that ensure_tokens_still_fresh is hooked to init when token refresh is enabled.
	 *
	 * This test would have caught the bug where the method existed but was never hooked.
	 *
	 * @group ClientWrapperTests
	 * @group TokenRefreshTests
	 */
	public function test_token_refresh_hook_registered_when_enabled() {
		// Clean slate - remove all init hooks.
		remove_all_actions( 'init' );

		// Create settings with token refresh enabled.
		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'token_refresh_enable' => 1,
			)
		);

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		// Register the client wrapper.
		$client_wrapper = OpenID_Connect_Generic_Client_Wrapper::register( $client, $settings, $logger );

		// Verify the hook is registered.
		$this->assertGreaterThan(
			0,
			has_action( 'init', array( $client_wrapper, 'ensure_tokens_still_fresh' ) ),
			'ensure_tokens_still_fresh should be hooked to init when token_refresh_enable is true'
		);
	}

	/**
	 * Test that ensure_tokens_still_fresh is NOT hooked when token refresh is disabled.
	 *
	 * @group ClientWrapperTests
	 * @group TokenRefreshTests
	 */
	public function test_token_refresh_hook_not_registered_when_disabled() {
		// Clean slate.
		remove_all_actions( 'init' );

		// Create settings with token refresh DISABLED.
		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'token_refresh_enable' => 0,
			)
		);

		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		// Register the client wrapper.
		$client_wrapper = OpenID_Connect_Generic_Client_Wrapper::register( $client, $settings, $logger );

		// Verify the hook is NOT registered.
		$this->assertFalse(
			has_action( 'init', array( $client_wrapper, 'ensure_tokens_still_fresh' ) ),
			'ensure_tokens_still_fresh should NOT be hooked to init when token_refresh_enable is false'
		);
	}

	/**
	 * Test that ensure_tokens_still_fresh method refreshes expired tokens.
	 *
	 * This tests the core logic of token refresh - not the hook registration.
	 *
	 * @group ClientWrapperTests
	 * @group TokenRefreshTests
	 */
	public function test_ensure_tokens_still_fresh_refreshes_expired_tokens() {
		// Create a logged-in user with expired token.
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		// Create a real session by logging in the user.
		$manager = WP_Session_Tokens::get_instance( $user->ID );
		$session_token = $manager->create( time() + DAY_IN_SECONDS );

		// Set the session token as current BEFORE setting up the refresh token.
		$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user->ID, time() + DAY_IN_SECONDS, 'logged_in', $session_token );

		// Now add refresh token to the session.
		$session = $manager->get( $session_token );
		$session[ OpenID_Connect_Generic_Client_Wrapper::COOKIE_TOKEN_REFRESH_KEY ] = array(
			'refresh_token' => 'valid_refresh_token',
		);
		$manager->update( $session_token, $session );

		// Set expired token in user option.
		$expired_token_response = array(
			'access_token'  => 'expired_token',
			'expires_in'    => 3600,
			'time'          => time() - 7200, // Expired 2 hours ago.
			'refresh_token' => 'valid_refresh_token',
		);
		update_user_option( $user->ID, 'openid-connect-generic-last-token-response', $expired_token_response );

		// Mock the client to expect token refresh.
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->expects( $this->once() )
			->method( 'request_new_tokens' )
			->with( 'valid_refresh_token' )
			->willReturn( array(
				'body' => json_encode( array(
					'access_token'  => 'new_token',
					'expires_in'    => 3600,
					'refresh_token' => 'new_refresh_token',
					'id_token'      => 'new_id_token',
				) ),
			) );

		$client->method( 'get_token_response' )
			->willReturn( array(
				'access_token'  => 'new_token',
				'expires_in'    => 3600,
				'refresh_token' => 'new_refresh_token',
				'id_token'      => 'new_id_token',
			) );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'token_refresh_enable' => 1,
			)
		);

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		// Create wrapper directly (not using register to avoid init hook complications).
		$client_wrapper = new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger );

		// Call the method directly - this proves the logic works.
		$client_wrapper->ensure_tokens_still_fresh();

		// Verify new token was saved.
		$updated_token = get_user_option( 'openid-connect-generic-last-token-response', $user->ID );
		$this->assertEquals( 'new_token', $updated_token['access_token'], 'Token should have been refreshed' );

		// Cleanup.
		unset( $_COOKIE[LOGGED_IN_COOKIE] );
		wp_set_current_user( 0 );
		$manager->destroy_all();
	}

	/**
	 * Test that ensure_tokens_still_fresh does nothing when user not logged in.
	 *
	 * @group ClientWrapperTests
	 * @group TokenRefreshTests
	 */
	public function test_ensure_tokens_still_fresh_skips_when_not_logged_in() {
		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		// Mock client that should NOT be called.
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->expects( $this->never() )
			->method( 'request_new_tokens' );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'token_refresh_enable' => 1,
			)
		);

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$client_wrapper = new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger );

		// Should return early without calling client.
		$client_wrapper->ensure_tokens_still_fresh();
	}

	/**
	 * Test that ensure_tokens_still_fresh skips refresh when token not expired.
	 *
	 * @group ClientWrapperTests
	 * @group TokenRefreshTests
	 */
	public function test_ensure_tokens_still_fresh_skips_when_token_not_expired() {
		// Create logged-in user with VALID token.
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		// Set non-expired token.
		$valid_token_response = array(
			'access_token'  => 'valid_token',
			'expires_in'    => 3600,
			'time'          => time(), // Just created, not expired.
			'refresh_token' => 'refresh_token',
		);
		update_user_option( $user->ID, 'openid-connect-generic-last-token-response', $valid_token_response );

		// Mock client that should NOT be called.
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->expects( $this->never() )
			->method( 'request_new_tokens' );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'token_refresh_enable' => 1,
			)
		);

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$client_wrapper = new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger );

		// Should return early since token is still valid.
		$client_wrapper->ensure_tokens_still_fresh();

		// Cleanup.
		wp_set_current_user( 0 );
	}

	/**
	 * Test backward compatibility: ensure_tokens_still_fresh can read old user_meta data.
	 *
	 * This tests the fallback from get_user_option to get_user_meta for existing installations.
	 *
	 * @group ClientWrapperTests
	 * @group TokenRefreshTests
	 * @group BackwardCompatibility
	 */
	public function test_ensure_tokens_still_fresh_backward_compatibility_with_user_meta() {
		// Create a logged-in user.
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		// Create a real session.
		$manager = WP_Session_Tokens::get_instance( $user->ID );
		$session_token = $manager->create( time() + DAY_IN_SECONDS );

		// Set the session token as current.
		$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user->ID, time() + DAY_IN_SECONDS, 'logged_in', $session_token );

		// Add refresh token to the session.
		$session = $manager->get( $session_token );
		$session[ OpenID_Connect_Generic_Client_Wrapper::COOKIE_TOKEN_REFRESH_KEY ] = array(
			'refresh_token' => 'old_refresh_token',
		);
		$manager->update( $session_token, $session );

		// Store expired token using OLD user_meta function (simulating legacy data).
		$expired_token_response = array(
			'access_token'  => 'old_expired_token',
			'expires_in'    => 3600,
			'time'          => time() - 7200, // Expired 2 hours ago.
			'refresh_token' => 'old_refresh_token',
		);
		update_user_meta( $user->ID, 'openid-connect-generic-last-token-response', $expired_token_response );

		// Mock the client to expect token refresh.
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$client->expects( $this->once() )
			->method( 'request_new_tokens' )
			->with( 'old_refresh_token' )
			->willReturn( array(
				'body' => json_encode( array(
					'access_token'  => 'new_token_from_old_meta',
					'expires_in'    => 3600,
					'refresh_token' => 'new_refresh_token',
					'id_token'      => 'new_id_token',
				) ),
			) );

		$client->method( 'get_token_response' )
			->willReturn( array(
				'access_token'  => 'new_token_from_old_meta',
				'expires_in'    => 3600,
				'refresh_token' => 'new_refresh_token',
				'id_token'      => 'new_id_token',
			) );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'token_refresh_enable' => 1,
			)
		);

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$client_wrapper = new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger );

		// Call the method - should read from old user_meta and update to user_option.
		$client_wrapper->ensure_tokens_still_fresh();

		// Verify new token was saved using user_option.
		$updated_token = get_user_option( 'openid-connect-generic-last-token-response', $user->ID );
		$this->assertEquals( 'new_token_from_old_meta', $updated_token['access_token'], 'Token should have been refreshed and migrated from user_meta to user_option' );

		// Cleanup.
		unset( $_COOKIE[LOGGED_IN_COOKIE] );
		wp_set_current_user( 0 );
		$manager->destroy_all();
	}

	/**
	 * Test that login_user stores tokens and claims using user_option.
	 *
	 * @group ClientWrapperTests
	 * @group UserOptionTests
	 */
	public function test_login_user_stores_tokens_with_user_option() {
		$user = $this->factory()->user->create_and_get();

		$token_response = array(
			'access_token'  => 'test_access_token',
			'expires_in'    => 3600,
			'refresh_token' => 'test_refresh_token',
			'id_token'      => 'test_id_token',
		);

		$id_token_claim = array(
			'sub' => 'test_subject',
			'iss' => 'https://example.com',
		);

		$user_claim = array(
			'email' => 'test@example.com',
			'name'  => 'Test User',
		);

		$this->client_wrapper->login_user( $user, $token_response, $id_token_claim, $user_claim, 'test_subject' );

		// Verify token response was stored with user_option.
		$stored_token = get_user_option( 'openid-connect-generic-last-token-response', $user->ID );
		$this->assertEquals( 'test_access_token', $stored_token['access_token'], 'Token response should be stored with user_option' );

		// Verify id_token claim was stored with user_option.
		$stored_id_claim = get_user_option( 'openid-connect-generic-last-id-token-claim', $user->ID );
		$this->assertEquals( 'test_subject', $stored_id_claim['sub'], 'ID token claim should be stored with user_option' );

		// Verify user claim was stored with user_option.
		$stored_user_claim = get_user_option( 'openid-connect-generic-last-user-claim', $user->ID );
		$this->assertEquals( 'test@example.com', $stored_user_claim['email'], 'User claim should be stored with user_option' );

		// Cleanup session.
		$manager = WP_Session_Tokens::get_instance( $user->ID );
		$manager->destroy_all();
		wp_clear_auth_cookie();
	}

	/**
	 * Test that create_new_user stores subject identity as global user option.
	 *
	 * @group ClientWrapperTests
	 * @group UserOptionTests
	 */
	public function test_create_new_user_stores_subject_identity_globally() {
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );
		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'create_if_does_not_exist' => true,
				'identity_key'             => 'preferred_username',
				'nickname_key'             => 'nickname',
				'email_format'             => '{email}',
				'displayname_format'       => '{name}',
			)
		);
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		$client_wrapper = new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger );

		$subject_identity = 'test_subject_123';
		$user_claim = array(
			'email'              => 'newuser@example.com',
			'preferred_username' => 'newuser',
			'nickname'           => 'New',
			'name'               => 'New User',
		);

		$user = $client_wrapper->create_new_user( $subject_identity, $user_claim );

		$this->assertInstanceOf( 'WP_User', $user, 'Should create a new user' );

		// Verify subject identity is stored with global = true.
		$stored_subject = get_user_option( 'openid-connect-generic-subject-identity', $user->ID );
		$this->assertEquals( $subject_identity, $stored_subject, 'Subject identity should be stored as user option' );

		// In a multisite environment, the global parameter would make this accessible across sites.
		// We're testing that the function is called correctly.
	}

	/**
	 * Test that update_existing_user stores subject identity as global user option.
	 *
	 * @group ClientWrapperTests
	 * @group UserOptionTests
	 */
	public function test_update_existing_user_stores_subject_identity_globally() {
		$user = $this->factory()->user->create_and_get();

		$subject_identity = 'updated_subject_456';

		$updated_user = $this->client_wrapper->update_existing_user( $user->ID, $subject_identity );

		$this->assertInstanceOf( 'WP_User', $updated_user, 'Should return updated user' );
		$this->assertEquals( $user->ID, $updated_user->ID, 'Should be the same user' );

		// Verify subject identity is stored with global = true.
		$stored_subject = get_user_option( 'openid-connect-generic-subject-identity', $user->ID );
		$this->assertEquals( $subject_identity, $stored_subject, 'Subject identity should be stored as user option' );
	}

	/**
	 * Build a client wrapper whose client can be told headers are already sent.
	 *
	 * @param bool $headers_sent Whether headers count as already sent.
	 *
	 * @return array{0: OpenID_Connect_Generic_Client_Wrapper, 1: OpenID_Connect_Generic_Client}
	 */
	private function create_login_start_wrapper( bool $headers_sent ): array {
		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$client = new class( $headers_sent, $logger ) extends OpenID_Connect_Generic_Client {
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
		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'endpoint_login'   => 'https://idp.example.com/authorize',
				'scope'            => 'openid',
				'client_id'        => 'test_client',
				'acr_values'       => '',
				'state_time_limit' => 180,
			)
		);

		return array( new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger ), $client );
	}

	/**
	 * Run handle_login_init() and return where it redirected.
	 *
	 * @param OpenID_Connect_Generic_Client_Wrapper $wrapper The client wrapper.
	 *
	 * @return string
	 */
	private function capture_login_init_redirect( OpenID_Connect_Generic_Client_Wrapper $wrapper ): string {
		$capture = static function ( $location ) {
			throw new RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $capture );

		try {
			$wrapper->handle_login_init();
			return '';
		} catch ( RuntimeException $redirect ) {
			return $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $capture );
		}
	}

	/**
	 * Get the state query argument from a URL.
	 *
	 * @param string $url The URL.
	 * @param string $key The query argument.
	 *
	 * @return string
	 */
	private function get_query_arg( string $url, string $key ): string {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		return isset( $query[ $key ] ) ? $query[ $key ] : '';
	}

	/**
	 * When the cookie can be set, the IDP URL is returned and its state linked to the browser.
	 *
	 * @group ClientWrapperTests
	 * @group StateTests
	 */
	public function test_get_authentication_url_links_state_to_browser() {
		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
		list( $wrapper, $client ) = $this->create_login_start_wrapper( false );

		$url = $wrapper->get_authentication_url();
		$state = $this->get_query_arg( $url, 'state' );

		$this->assertStringStartsWith( 'https://idp.example.com/authorize?', $url );
		$this->assertTrue( $client->is_state_bound_to_browser( $state ) );

		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
	}

	/**
	 * An existing cookie links the state even after headers are sent.
	 *
	 * @group ClientWrapperTests
	 * @group StateTests
	 */
	public function test_get_authentication_url_uses_existing_cookie_after_headers_sent() {
		$_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] = str_repeat( 'b', 32 );
		list( $wrapper, $client ) = $this->create_login_start_wrapper( true );

		$url = $wrapper->get_authentication_url();

		$this->assertStringStartsWith( 'https://idp.example.com/authorize?', $url );
		$this->assertTrue( $client->is_state_bound_to_browser( $this->get_query_arg( $url, 'state' ) ) );

		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
	}

	/**
	 * Without a cookie and with headers sent, the login starts through the login page,
	 * which links the state and continues to the IDP URL built by the filters at render time.
	 *
	 * @group ClientWrapperTests
	 * @group StateTests
	 */
	public function test_login_start_links_state_and_redirects_to_filtered_url() {
		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
		list( $render_wrapper ) = $this->create_login_start_wrapper( true );

		$add_hint = static function ( $url ) {
			return add_query_arg( 'kc_idp_hint', 'render-time', $url );
		};
		add_filter( 'openid-connect-generic-auth-url', $add_hint );
		$start_url = $render_wrapper->get_authentication_url();
		remove_filter( 'openid-connect-generic-auth-url', $add_hint );

		$this->assertStringStartsWith( wp_login_url(), $start_url );
		$state = $this->get_query_arg( $start_url, OpenID_Connect_Generic_Client_Wrapper::LOGIN_START_QUERY_VAR );

		list( $login_wrapper, $login_client ) = $this->create_login_start_wrapper( false );
		$_GET = array(
			OpenID_Connect_Generic_Client_Wrapper::LOGIN_START_QUERY_VAR => $state,
			'login_hint' => 'user@example.com',
			'state'      => 'attacker-state',
			'client_id'  => 'attacker-client',
		);
		$redirect = $this->capture_login_init_redirect( $login_wrapper );
		$_GET = array();

		$this->assertStringStartsWith( 'https://idp.example.com/authorize?', $redirect );
		$this->assertSame( $state, $this->get_query_arg( $redirect, 'state' ), 'The plugin state must not be overridden.' );
		$this->assertSame( 'test_client', $this->get_query_arg( $redirect, 'client_id' ), 'The client ID must not be overridden.' );
		$this->assertSame( 'render-time', $this->get_query_arg( $redirect, 'kc_idp_hint' ), 'Render-time filters should still apply.' );
		$this->assertSame( 'user@example.com', $this->get_query_arg( $redirect, 'login_hint' ), 'Extra arguments should pass through.' );
		$this->assertTrue( $login_client->is_state_bound_to_browser( $state ) );

		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
	}

	/**
	 * A login start link already used by another browser is refused.
	 *
	 * @group ClientWrapperTests
	 * @group StateTests
	 */
	public function test_login_start_refuses_state_linked_to_other_browser() {
		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
		list( $render_wrapper, $client ) = $this->create_login_start_wrapper( true );
		$state = $this->get_query_arg( $render_wrapper->get_authentication_url(), OpenID_Connect_Generic_Client_Wrapper::LOGIN_START_QUERY_VAR );
		$client->bind_state( $state, str_repeat( 'c', 32 ) );

		list( $login_wrapper ) = $this->create_login_start_wrapper( false );
		$_GET = array( OpenID_Connect_Generic_Client_Wrapper::LOGIN_START_QUERY_VAR => $state );
		$redirect = $this->capture_login_init_redirect( $login_wrapper );
		$_GET = array();

		$this->assertStringContainsString( 'login-error=invalid-state', $redirect );

		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
	}

	/**
	 * The login page sets the browser cookie and does nothing else without a start argument.
	 *
	 * @group ClientWrapperTests
	 * @group StateTests
	 */
	public function test_login_init_sets_browser_cookie() {
		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
		list( $wrapper, $client ) = $this->create_login_start_wrapper( false );
		$_GET = array();

		$this->assertSame( '', $this->capture_login_init_redirect( $wrapper ) );
		$this->assertNotSame( '', $client->get_browser_id() );

		unset( $_COOKIE[ OpenID_Connect_Generic_Client::BROWSER_COOKIE ] );
	}

	/**
	 * Build a client wrapper with existing-user linking enabled.
	 *
	 * @param bool $link_unverified_users  Whether linking without a verified email is allowed.
	 * @param bool $identify_with_username Whether users are matched by user name.
	 *
	 * @return OpenID_Connect_Generic_Client_Wrapper
	 */
	private function create_linking_client_wrapper( bool $link_unverified_users, bool $identify_with_username = false ): OpenID_Connect_Generic_Client_Wrapper {
		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'link_existing_users'      => 1,
				'link_unverified_users'    => $link_unverified_users ? 1 : 0,
				'identify_with_username'   => $identify_with_username,
				'create_if_does_not_exist' => 0,
				'identity_key'             => 'preferred_username',
				'nickname_key'             => 'preferred_username',
				'email_format'             => '{email}',
				'displayname_format'       => '',
			)
		);

		return new OpenID_Connect_Generic_Client_Wrapper(
			$this->createMock( OpenID_Connect_Generic_Client::class ),
			$settings,
			$this->createMock( OpenID_Connect_Generic_Option_Logger::class )
		);
	}

	/**
	 * Scenarios for linking an existing user by email.
	 *
	 * @return array<string, array{0: mixed, 1: bool, 2: string|null}>
	 */
	public function link_by_email_provider(): array {
		return array(
			'verified'                       => array( true, false, null ),
			'verified as string'             => array( 'true', false, null ),
			'not verified'                   => array( false, false, 'link-email-not-verified' ),
			'not verified with opt-in'       => array( false, true, 'link-email-not-verified' ),
			'not verified as string'         => array( 'false', true, 'link-email-not-verified' ),
			'claim missing'                  => array( null, false, 'link-requires-verified-email' ),
			'claim missing with opt-in'      => array( null, true, null ),
			'claim unrecognized'             => array( 'yes', false, 'link-requires-verified-email' ),
			'claim unrecognized with opt-in' => array( 'yes', true, null ),
		);
	}

	/**
	 * Linking by email follows the email_verified claim and the opt-in setting.
	 *
	 * @dataProvider link_by_email_provider
	 * @group ClientWrapperTests
	 *
	 * @param mixed       $email_verified        The email_verified claim value, or null to omit it.
	 * @param bool        $link_unverified_users Whether linking without a verified email is allowed.
	 * @param string|null $expected_error        Expected WP_Error code, or null when the user should be linked.
	 */
	public function test_link_existing_user_by_email( $email_verified, bool $link_unverified_users, ?string $expected_error ) {
		$user_id = self::factory()->user->create( array( 'user_email' => 'linked@example.com' ) );
		$user_claim = array(
			'email'              => 'linked@example.com',
			'preferred_username' => 'idp-user',
		);
		if ( null !== $email_verified ) {
			$user_claim['email_verified'] = $email_verified;
		}

		$result = $this->create_linking_client_wrapper( $link_unverified_users )->create_new_user( 'new-subject', $user_claim );

		if ( null === $expected_error ) {
			$this->assertInstanceOf( WP_User::class, $result );
			$this->assertSame( $user_id, $result->ID );
			$this->assertSame( 'new-subject', get_user_option( 'openid-connect-generic-subject-identity', $user_id ) );
		} else {
			$this->assertWPError( $result );
			$this->assertSame( $expected_error, $result->get_error_code() );
			$this->assertFalse( get_user_option( 'openid-connect-generic-subject-identity', $user_id ) );
		}
	}

	/**
	 * Linking by user name requires the opt-in setting, whatever the email claim says.
	 *
	 * @group ClientWrapperTests
	 */
	public function test_link_existing_user_by_username_requires_opt_in() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'idp-user' ) );
		$user_claim = array(
			'email'              => 'someone@example.com',
			'email_verified'     => true,
			'preferred_username' => 'idp-user',
		);

		$refused = $this->create_linking_client_wrapper( false, true )->create_new_user( 'new-subject', $user_claim );
		$this->assertWPError( $refused );
		$this->assertSame( 'link-requires-verified-email', $refused->get_error_code() );

		$linked = $this->create_linking_client_wrapper( true, true )->create_new_user( 'new-subject', $user_claim );
		$this->assertInstanceOf( WP_User::class, $linked );
		$this->assertSame( $user_id, $linked->ID );
	}

	/**
	 * An existing link to another identity is only replaced when the email is verified.
	 *
	 * @group ClientWrapperTests
	 */
	public function test_link_existing_user_replaces_identity_only_when_verified() {
		$user_id = self::factory()->user->create( array( 'user_email' => 'linked@example.com' ) );
		update_user_option( $user_id, 'openid-connect-generic-subject-identity', 'original-subject', true );
		$user_claim = array(
			'email'              => 'linked@example.com',
			'preferred_username' => 'idp-user',
		);

		$refused = $this->create_linking_client_wrapper( true )->create_new_user( 'new-subject', $user_claim );
		$this->assertWPError( $refused );
		$this->assertSame( 'link-identity-mismatch', $refused->get_error_code() );
		$this->assertSame( 'original-subject', get_user_option( 'openid-connect-generic-subject-identity', $user_id ) );

		$user_claim['email_verified'] = true;
		$linked = $this->create_linking_client_wrapper( false )->create_new_user( 'new-subject', $user_claim );
		$this->assertInstanceOf( WP_User::class, $linked );
		$this->assertSame( 'new-subject', get_user_option( 'openid-connect-generic-subject-identity', $user_id ) );
	}

	/**
	 * Linking by user name never replaces an existing link to another identity.
	 *
	 * @group ClientWrapperTests
	 */
	public function test_link_existing_user_by_username_never_replaces_identity() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'idp-user' ) );
		update_user_option( $user_id, 'openid-connect-generic-subject-identity', 'original-subject', true );
		$user_claim = array(
			'email'              => 'someone@example.com',
			'email_verified'     => true,
			'preferred_username' => 'idp-user',
		);

		$result = $this->create_linking_client_wrapper( true, true )->create_new_user( 'new-subject', $user_claim );

		$this->assertWPError( $result );
		$this->assertSame( 'link-identity-mismatch', $result->get_error_code() );
		$this->assertSame( 'original-subject', get_user_option( 'openid-connect-generic-subject-identity', $user_id ) );
	}

	/**
	 * Test that refresh_user_claim stores updated claims using user_option.
	 *
	 * @group ClientWrapperTests
	 * @group UserOptionTests
	 */
	public function test_refresh_user_claim_stores_with_user_option() {
		$user = $this->factory()->user->create_and_get();

		$token_response = array(
			'access_token'  => 'refreshed_access_token',
			'expires_in'    => 3600,
			'id_token'      => 'refreshed_id_token',
		);

		// Mock the client methods.
		$client = $this->createMock( OpenID_Connect_Generic_Client::class );

		$id_token_claim = array(
			'sub' => 'test_subject',
			'iss' => 'https://example.com',
			'aud' => 'test_client',
			'exp' => time() + 3600,
		);

		$user_claim = array(
			'sub'   => 'test_subject',
			'email' => 'refreshed@example.com',
			'name'  => 'Refreshed User',
		);

		$client->method( 'get_id_token_claim' )
			->willReturn( $id_token_claim );

		$client->method( 'validate_id_token_claim' )
			->willReturn( true );

		$client->method( 'get_user_claim' )
			->willReturn( $user_claim );

		$client->method( 'validate_user_claim' )
			->willReturn( true );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'endpoint_userinfo' => 'https://example.com/userinfo',
			)
		);

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );
		$client_wrapper = new OpenID_Connect_Generic_Client_Wrapper( $client, $settings, $logger );

		$result = $client_wrapper->refresh_user_claim( $user, $token_response );

		$this->assertIsArray( $result, 'Should return user claim array' );

		// Verify all three values are stored with user_option.
		$stored_token = get_user_option( 'openid-connect-generic-last-token-response', $user->ID );
		$this->assertEquals( 'refreshed_access_token', $stored_token['access_token'], 'Refreshed token should be stored with user_option' );

		$stored_id_claim = get_user_option( 'openid-connect-generic-last-id-token-claim', $user->ID );
		$this->assertEquals( 'test_subject', $stored_id_claim['sub'], 'Refreshed ID claim should be stored with user_option' );

		$stored_user_claim = get_user_option( 'openid-connect-generic-last-user-claim', $user->ID );
		$this->assertEquals( 'refreshed@example.com', $stored_user_claim['email'], 'Refreshed user claim should be stored with user_option' );
	}

}
