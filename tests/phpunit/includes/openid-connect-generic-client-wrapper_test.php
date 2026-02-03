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

		// Set expired token in user meta.
		$expired_token_response = array(
			'access_token'  => 'expired_token',
			'expires_in'    => 3600,
			'time'          => time() - 7200, // Expired 2 hours ago.
			'refresh_token' => 'valid_refresh_token',
		);
		update_user_meta( $user->ID, 'openid-connect-generic-last-token-response', $expired_token_response );

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
		$updated_token = get_user_meta( $user->ID, 'openid-connect-generic-last-token-response', true );
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
		update_user_meta( $user->ID, 'openid-connect-generic-last-token-response', $valid_token_response );

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

}
