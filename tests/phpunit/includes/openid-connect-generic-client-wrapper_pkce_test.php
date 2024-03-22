<?php
/**
 * Class OpenID_Connect_Generic_Client_Wrapper_Pkce_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin OIDC/oAuth client wrapper class test case.
 */
class OpenID_Connect_Generic_Client_Wrapper_Pkce_Test extends WP_UnitTestCase {

	protected $openid_client_pkce;

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$settings = new OpenID_Connect_Generic_Option_Settings(
		// Default settings values.
			array(
				// OAuth client settings.
				'login_type'               => defined( 'OIDC_LOGIN_TYPE' ) ? OIDC_LOGIN_TYPE : 'button',
				'client_id'                => defined( 'OIDC_CLIENT_ID' ) ? OIDC_CLIENT_ID : '',
				'client_secret'            => defined( 'OIDC_CLIENT_SECRET' ) ? OIDC_CLIENT_SECRET : '',
				'scope'                    => defined( 'OIDC_CLIENT_SCOPE' ) ? OIDC_CLIENT_SCOPE : '',
				'endpoint_login'           => defined( 'OIDC_ENDPOINT_LOGIN_URL' ) ? OIDC_ENDPOINT_LOGIN_URL : '',
				'endpoint_userinfo'        => defined( 'OIDC_ENDPOINT_USERINFO_URL' ) ? OIDC_ENDPOINT_USERINFO_URL : '',
				'endpoint_token'           => defined( 'OIDC_ENDPOINT_TOKEN_URL' ) ? OIDC_ENDPOINT_TOKEN_URL : '',
				'endpoint_end_session'     => defined( 'OIDC_ENDPOINT_LOGOUT_URL' ) ? OIDC_ENDPOINT_LOGOUT_URL : '',
				'acr_values'               => defined( 'OIDC_ACR_VALUES' ) ? OIDC_ACR_VALUES : '',
				'enable_pkce'              => true,

				// Non-standard settings.
				'no_sslverify'             => 0,
				'http_request_timeout'     => 5,
				'identity_key'             => 'preferred_username',
				'nickname_key'             => 'preferred_username',
				'email_format'             => '{email}',
				'displayname_format'       => '',
				'identify_with_username'   => false,
				'state_time_limit'         => 180,

				// Plugin settings.
				'enforce_privacy'          => defined( 'OIDC_ENFORCE_PRIVACY' ) ? intval( OIDC_ENFORCE_PRIVACY ) : 0,
				'alternate_redirect_uri'   => 0,
				'token_refresh_enable'     => 1,
				'link_existing_users'      => defined( 'OIDC_LINK_EXISTING_USERS' ) ? intval( OIDC_LINK_EXISTING_USERS ) : 0,
				'create_if_does_not_exist' => defined( 'OIDC_CREATE_IF_DOES_NOT_EXIST' ) ? intval( OIDC_CREATE_IF_DOES_NOT_EXIST ) : 1,
				'redirect_user_back'       => defined( 'OIDC_REDIRECT_USER_BACK' ) ? intval( OIDC_REDIRECT_USER_BACK ) : 0,
				'redirect_on_logout'       => defined( 'OIDC_REDIRECT_ON_LOGOUT' ) ? intval( OIDC_REDIRECT_ON_LOGOUT ) : 1,
				'enable_logging'           => defined( 'OIDC_ENABLE_LOGGING' ) ? intval( OIDC_ENABLE_LOGGING ) : 0,
				'log_limit'                => defined( 'OIDC_LOG_LIMIT' ) ? intval( OIDC_LOG_LIMIT ) : 1000,
			)
		);

		$logger = new OpenID_Connect_Generic_Option_Logger( 'error', $settings->enable_logging, $settings->log_limit );

		$this->openid_client_pkce = new OpenID_Connect_Generic( $settings, $logger );
		$this->openid_client_pkce->init();
	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $this->openid_client_pkce );

		parent::tearDown();
	}

	/**
	 * @covers OpenID_Connect_Generic_Client_Wrapper::get_authentication_url
	 */
	public function test_plugin_client_wrapper_authentication_url_contain_pkce_parameters() {
		// Generate an authentication URL.
		$authentication_url = $this->openid_client_pkce->client_wrapper->get_authentication_url();

		// Extract the URL querystring and fill the `$params` array with the parameters.
		parse_str(
			parse_url( $authentication_url, PHP_URL_QUERY ),
			$params
		);

		// Asserts required parameters are present.
		$this->assertArrayHasKey( 'code_challenge', $params, 'check for PKCE parameter "code_challenge" in the authentication URL when PKCE option is enable.' );
		$this->assertArrayHasKey( 'code_challenge_method', $params, 'check for PKCE parameter "code_challenge_method" in the authentication URL when PKCE option is enable.' );

		// Assert state contain the required code
		$state      = $params['state'];
		$state_data = get_transient( 'openid-connect-generic-state--' . sanitize_text_field( $state ) );
		$this->assertNotEmpty( $state_data[ $state ]['code_verifier'], 'check for non-empty "code_verifier" in the state.' );
	}

	/**
	 * @covers OpenID_Connect_Generic_Client_Wrapper::alter_authentication_token_request
	 */
	public function test_plugin_client_wrapper_filter_for_get_authentication_token_request_exist() {
		$this->assertNotFalse(
			has_filter(
				'openid-connect-generic-alter-request',
				array( $this->openid_client_pkce->client_wrapper, 'alter_authentication_token_request')
			)
		);
	}

	/**
	 * @covers OpenID_Connect_Generic_Client_Wrapper::alter_authentication_token_request
	 */
	public function test_plugin_client_wrapper_code_verifier_is_included_in_get_authentication_token_request() {
		// Generate an authentication URL.
		$authentication_url = $this->openid_client_pkce->client_wrapper->get_authentication_url();

		// Extract the URL querystring and fill the `$params` array with the parameters.
		parse_str(
			parse_url( $authentication_url, PHP_URL_QUERY ),
			$params
		);

		// Assert the request body include the `code_verifier`.
		$state         = $params['state'];
		$state_data    = get_transient( 'openid-connect-generic-state--' . sanitize_text_field( $state ) );
		$request       = [
			'body' => [],
		];
		$_GET['state'] = $state;
		$request       = $this->openid_client_pkce->client_wrapper->alter_authentication_token_request( $request, 'get-authentication-token' );
		$this->assertEquals( $state_data[ $state ]['code_verifier'], $request['body']['code_verifier'] );
		unset( $_GET['state'] );
	}
}
