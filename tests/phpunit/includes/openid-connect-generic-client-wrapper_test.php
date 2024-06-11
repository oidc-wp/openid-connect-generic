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

}
