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
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		$this->client_wrapper = OpenID_Connect_Generic::instance()->client_wrapper;

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
	 * Test plugin alternate_redirect_uri_parse_request() method.
	 *
	 * @group ClientWrapperTests
	 */
	public function test_plugin_client_wrapper_alternate_redirect_uri_parse_request() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	public function test_plugin_client_wrapper_remember_me() {
		// Set the remember me option to true
		add_filter( 'openid-connect-generic-remember-me', '__return_true' );

		// Create a user and log in using the login function of the client wrapper
		$user = $this->factory()->user->create_and_get( array( 'user_login' => 'test-remember-me-user' ) );
		$this->client_wrapper->login_user( $user, array(
			'expires_in' => 14 * HOUR_IN_SECONDS, // This does not influence the length of the cookie
		), array(), array(), '' );

		// Retrieve the session tokens
		$manager = WP_Session_Tokens::get_instance( $user->ID );
		$token = $manager->get_all()[0];

		// Assert if the token is set to expire in 14 days, with some seconds as a timing margin
		$this->assertGreaterThan( time() + 13 * DAY_IN_SECONDS, $token['expiration'] );
		$this->assertLessThan( time() + 15 * DAY_IN_SECONDS, $token['expiration'] );

		// Reset the remember me option
		remove_filter( 'openid-connect-generic-remember-me', '__return_true' );
	}

}
