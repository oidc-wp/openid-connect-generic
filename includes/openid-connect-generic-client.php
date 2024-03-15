<?php
/**
 * Plugin OIDC/oAuth client class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Authentication
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Client class.
 *
 * Plugin OIDC/oAuth client class.
 *
 * @package  OpenID_Connect_Generic
 * @category Authentication
 */
class OpenID_Connect_Generic_Client {

	/**
	 * The OIDC/oAuth client ID.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::client_id
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * The OIDC/oAuth client secret.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::client_secret
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * The OIDC/oAuth scopes.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::scope
	 *
	 * @var string
	 */
	private $scope;

	/**
	 * The OIDC/oAuth authorization endpoint URL.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::endpoint_login
	 *
	 * @var string
	 */
	private $endpoint_login;

	/**
	 * The OIDC/oAuth User Information endpoint URL.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::endpoint_userinfo
	 *
	 * @var string
	 */
	private $endpoint_userinfo;

	/**
	 * The OIDC/oAuth token validation endpoint URL.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::endpoint_token
	 *
	 * @var string
	 */
	private $endpoint_token;

	/**
	 * The login flow "ajax" endpoint URI.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::redirect_uri
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * The specifically requested authentication contract at the IDP
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::acr_values
	 *
	 * @var string
	 */
	private $acr_values;

	/**
	 * The state time limit. States are only valid for 3 minutes.
	 *
	 * @see OpenID_Connect_Generic_Option_Settings::state_time_limit
	 *
	 * @var int
	 */
	private $state_time_limit = 180;

	/**
	 * The logger object instance.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * Client constructor.
	 *
	 * @param string                               $client_id         @see OpenID_Connect_Generic_Option_Settings::client_id for description.
	 * @param string                               $client_secret     @see OpenID_Connect_Generic_Option_Settings::client_secret for description.
	 * @param string                               $scope             @see OpenID_Connect_Generic_Option_Settings::scope for description.
	 * @param string                               $endpoint_login    @see OpenID_Connect_Generic_Option_Settings::endpoint_login for description.
	 * @param string                               $endpoint_userinfo @see OpenID_Connect_Generic_Option_Settings::endpoint_userinfo for description.
	 * @param string                               $endpoint_token    @see OpenID_Connect_Generic_Option_Settings::endpoint_token for description.
	 * @param string                               $redirect_uri      @see OpenID_Connect_Generic_Option_Settings::redirect_uri for description.
	 * @param string                               $acr_values        @see OpenID_Connect_Generic_Option_Settings::acr_values for description.
	 * @param int                                  $state_time_limit  @see OpenID_Connect_Generic_Option_Settings::state_time_limit for description.
	 * @param OpenID_Connect_Generic_Option_Logger $logger            The plugin logging object instance.
	 */
	public function __construct( $client_id, $client_secret, $scope, $endpoint_login, $endpoint_userinfo, $endpoint_token, $redirect_uri, $acr_values, $state_time_limit, $logger ) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->scope = $scope;
		$this->endpoint_login = $endpoint_login;
		$this->endpoint_userinfo = $endpoint_userinfo;
		$this->endpoint_token = $endpoint_token;
		$this->redirect_uri = $redirect_uri;
		$this->acr_values = $acr_values;
		$this->state_time_limit = $state_time_limit;
		$this->logger = $logger;
	}

	/**
	 * Provides the configured Redirect URI supplied to the IDP.
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		return $this->redirect_uri;
	}

	/**
	 * Provide the configured IDP endpoint login URL.
	 *
	 * @return string
	 */
	public function get_endpoint_login_url() {
		return $this->endpoint_login;
	}

	/**
	 * Validate the request for login authentication
	 *
	 * @param array<string> $request The authentication request results.
	 *
	 * @return array<string>|WP_Error
	 */
	public function validate_authentication_request( $request ) {
		// Look for an existing error of some kind.
		if ( isset( $request['error'] ) ) {
			return new WP_Error( 'unknown-error', 'An unknown error occurred.', $request );
		}

		// Make sure we have a legitimate authentication code and valid state.
		if ( ! isset( $request['code'] ) ) {
			return new WP_Error( 'no-code', 'No authentication code present in the request.', $request );
		}

		// Check the client request state.
		if ( ! isset( $request['state'] ) ) {
			do_action( 'openid-connect-generic-no-state-provided' );
			return new WP_Error( 'missing-state', __( 'Missing state.', 'daggerhart-openid-connect-generic' ), $request );
		}

		if ( ! $this->check_state( $request['state'] ) ) {
			return new WP_Error( 'invalid-state', __( 'Invalid state.', 'daggerhart-openid-connect-generic' ), $request );
		}

		return $request;
	}

	/**
	 * Get the authorization code from the request
	 *
	 * @param array<string>|WP_Error $request The authentication request results.
	 *
	 * @return string|WP_Error
	 */
	public function get_authentication_code( $request ) {
		if ( ! isset( $request['code'] ) ) {
			return new WP_Error( 'missing-authentication-code', __( 'Missing authentication code.', 'daggerhart-openid-connect-generic' ), $request );
		}

		return $request['code'];
	}

	/**
	 * Using the authorization_code, request an authentication token from the IDP.
	 *
	 * @param string|WP_Error $code The authorization code.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public function request_authentication_token( $code ) {

		// Add Host header - required for when the openid-connect endpoint is behind a reverse-proxy.
		$parsed_url = parse_url( $this->endpoint_token );
		$host = $parsed_url['host'];

		$request = array(
			'body' => array(
				'code'          => $code,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'redirect_uri'  => $this->redirect_uri,
				'grant_type'    => 'authorization_code',
				'scope'         => $this->scope,
			),
			'headers' => array( 'Host' => $host ),
		);

		if ( ! empty( $this->acr_values ) ) {
			$request['body'] += array( 'acr_values' => $this->acr_values );
		}

		// Allow modifications to the request.
		$request = apply_filters( 'openid-connect-generic-alter-request', $request, 'get-authentication-token' );

		// Call the server and ask for a token.
		$start_time = microtime( true );
		$response   = wp_remote_post( $this->endpoint_token, $request );
		$end_time   = microtime( true );
		$this->logger->log( $this->endpoint_token, 'request_authentication_token', $end_time - $start_time );

		if ( is_wp_error( $response ) ) {
			$response->add( 'request_authentication_token', __( 'Request for authentication token failed.', 'daggerhart-openid-connect-generic' ) );
		}

		return $response;
	}

	/**
	 * Using the refresh token, request new tokens from the idp
	 *
	 * @param string $refresh_token The refresh token previously obtained from token response.
	 *
	 * @return array|WP_Error
	 */
	public function request_new_tokens( $refresh_token ) {
		$request = array(
			'body' => array(
				'refresh_token' => $refresh_token,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'grant_type'    => 'refresh_token',
			),
		);

		// Allow modifications to the request.
		$request = apply_filters( 'openid-connect-generic-alter-request', $request, 'refresh-token' );

		// Call the server and ask for new tokens.
		$start_time = microtime( true );
		$response   = wp_remote_post( $this->endpoint_token, $request );
		$end_time   = microtime( true );
		$this->logger->log( $this->endpoint_token, 'request_new_tokens', $end_time - $start_time );

		if ( is_wp_error( $response ) ) {
			$response->add( 'refresh_token', __( 'Refresh token failed.', 'daggerhart-openid-connect-generic' ) );
		}

		return $response;
	}

	/**
	 * Extract and decode the token body of a token response
	 *
	 * @param array<mixed>|WP_Error $token_result The token response.
	 *
	 * @return array<mixed>|WP_Error|null
	 */
	public function get_token_response( $token_result ) {
		if ( ! isset( $token_result['body'] ) ) {
			return new WP_Error( 'missing-token-body', __( 'Missing token body.', 'daggerhart-openid-connect-generic' ), $token_result );
		}

		// Extract the token response from token.
		$token_response = json_decode( $token_result['body'], true );

		// Check that the token response body was able to be parsed.
		if ( is_null( $token_response ) ) {
			return new WP_Error( 'invalid-token', __( 'Invalid token.', 'daggerhart-openid-connect-generic' ), $token_result );
		}

		if ( isset( $token_response['error'] ) ) {
			$error = $token_response['error'];
			$error_description = $error;
			if ( isset( $token_response['error_description'] ) ) {
				$error_description = $token_response['error_description'];
			}
			return new WP_Error( $error, $error_description, $token_result );
		}

		return $token_response;
	}

	/**
	 * Exchange an access_token for a user_claim from the userinfo endpoint
	 *
	 * @param string $access_token The access token supplied from authentication user claim.
	 *
	 * @return array|WP_Error
	 */
	public function request_userinfo( $access_token ) {
		// Allow modifications to the request.
		$request = apply_filters( 'openid-connect-generic-alter-request', array(), 'get-userinfo' );

		/*
		 * Section 5.3.1 of the spec recommends sending the access token using the authorization header
		 * a filter may or may not have already added headers - make sure they exist then add the token.
		 */
		if ( ! array_key_exists( 'headers', $request ) || ! is_array( $request['headers'] ) ) {
			$request['headers'] = array();
		}

		$request['headers']['Authorization'] = 'Bearer ' . $access_token;

		// Add Host header - required for when the openid-connect endpoint is behind a reverse-proxy.
		$parsed_url = parse_url( $this->endpoint_userinfo );
		$host = $parsed_url['host'];

		if ( ! empty( $parsed_url['port'] ) ) {
			$host .= ":{$parsed_url['port']}";
		}

		$request['headers']['Host'] = $host;

		// Attempt the request including the access token in the query string for backwards compatibility.
		$start_time = microtime( true );
		$response   = wp_remote_post( $this->endpoint_userinfo, $request );
		$end_time   = microtime( true );
		$this->logger->log( $this->endpoint_userinfo, 'request_userinfo', $end_time - $start_time );

		if ( is_wp_error( $response ) ) {
			$response->add( 'request_userinfo', __( 'Request for userinfo failed.', 'daggerhart-openid-connect-generic' ) );
		}

		return $response;
	}

	/**
	 * Generate a new state, save it as a transient, and return the state hash.
	 *
	 * @param string $redirect_to The redirect URL to be used after IDP authentication.
	 *
	 * @return string
	 */
	public function new_state( $redirect_to ) {
		// New state w/ timestamp.
		$state = md5( mt_rand() . microtime( true ) );
		$state_value = array(
			$state => array(
				'redirect_to' => $redirect_to,
			),
		);
		set_transient( 'openid-connect-generic-state--' . $state, $state_value, $this->state_time_limit );

		return $state;
	}

	/**
	 * Check the existence of a given state transient.
	 *
	 * @param string $state The state hash to validate.
	 *
	 * @return bool
	 */
	public function check_state( $state ) {

		$state_found = true;

		if ( ! get_option( '_transient_openid-connect-generic-state--' . $state ) ) {
			do_action( 'openid-connect-generic-state-not-found', $state );
			$state_found = false;
		}

		$valid = get_transient( 'openid-connect-generic-state--' . $state );

		if ( ! $valid && $state_found ) {
			do_action( 'openid-connect-generic-state-expired', $state );
		}

		return boolval( $valid );
	}

	/**
	 * Get the authorization state from the request
	 *
	 * @param array<string>|WP_Error $request The authentication request results.
	 *
	 * @return string|WP_Error
	 */
	public function get_authentication_state( $request ) {
		if ( ! isset( $request['state'] ) ) {
			return new WP_Error( 'missing-authentication-state', __( 'Missing authentication state.', 'daggerhart-openid-connect-generic' ), $request );
		}

		return $request['state'];
	}

	/**
	 * Ensure that the token meets basic requirements.
	 *
	 * @param array $token_response The token response.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_token_response( $token_response ) {
		/*
		 * Ensure 2 specific items exist with the token response in order
		 * to proceed with confidence:  id_token and token_type == 'Bearer'
		 */
		if ( ! isset( $token_response['id_token'] ) ||
			 ! isset( $token_response['token_type'] ) || strcasecmp( $token_response['token_type'], 'Bearer' )
		) {
			return new WP_Error( 'invalid-token-response', 'Invalid token response', $token_response );
		}

		return true;
	}

	/**
	 * Extract the id_token_claim from the token_response.
	 *
	 * @param array $token_response The token response.
	 *
	 * @return array|WP_Error
	 */
	public function get_id_token_claim( $token_response ) {
		// Validate there is an id_token.
		if ( ! isset( $token_response['id_token'] ) ) {
			return new WP_Error( 'no-identity-token', __( 'No identity token.', 'daggerhart-openid-connect-generic' ), $token_response );
		}

		// Break apart the id_token in the response for decoding.
		$tmp = explode( '.', $token_response['id_token'] );

		if ( ! isset( $tmp[1] ) ) {
			return new WP_Error( 'missing-identity-token', __( 'Missing identity token.', 'daggerhart-openid-connect-generic' ), $token_response );
		}

		// Extract the id_token's claims from the token.
		$id_token_claim = json_decode(
			base64_decode(
				str_replace( // Because token is encoded in base64 URL (and not just base64).
					array( '-', '_' ),
					array( '+', '/' ),
					$tmp[1]
				)
			),
			true
		);

		return $id_token_claim;
	}

	/**
	 * Ensure the id_token_claim contains the required values.
	 *
	 * @param array $id_token_claim The ID token claim.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_id_token_claim( $id_token_claim ) {
		if ( ! is_array( $id_token_claim ) ) {
			return new WP_Error( 'bad-id-token-claim', __( 'Bad ID token claim.', 'daggerhart-openid-connect-generic' ), $id_token_claim );
		}

		// Validate the identification data and it's value.
		if ( ! isset( $id_token_claim['sub'] ) || empty( $id_token_claim['sub'] ) ) {
			return new WP_Error( 'no-subject-identity', __( 'No subject identity.', 'daggerhart-openid-connect-generic' ), $id_token_claim );
		}

		// Validate acr values when the option is set in the configuration.
		if ( ! empty( $this->acr_values ) && isset( $id_token_claim['acr'] ) ) {
			if ( $this->acr_values != $id_token_claim['acr'] ) {
				return new WP_Error( 'no-match-acr', __( 'No matching acr values.', 'daggerhart-openid-connect-generic' ), $id_token_claim );
			}
		}

		return true;
	}

	/**
	 * Attempt to exchange the access_token for a user_claim.
	 *
	 * @param array $token_response The token response.
	 *
	 * @return array|WP_Error|null
	 */
	public function get_user_claim( $token_response ) {
		// Send a userinfo request to get user claim.
		$user_claim_result = $this->request_userinfo( $token_response['access_token'] );

		// Make sure we didn't get an error, and that the response body exists.
		if ( is_wp_error( $user_claim_result ) || ! isset( $user_claim_result['body'] ) ) {
			return new WP_Error( 'bad-claim', __( 'Bad user claim.', 'daggerhart-openid-connect-generic' ), $user_claim_result );
		}

		$user_claim = json_decode( $user_claim_result['body'], true );

		return $user_claim;
	}

	/**
	 * Make sure the user_claim has all required values, and that the subject
	 * identity matches of the id_token matches that of the user_claim.
	 *
	 * @param array $user_claim     The authenticated user claim.
	 * @param array $id_token_claim The ID token claim.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_user_claim( $user_claim, $id_token_claim ) {
		// Validate the user claim.
		if ( ! is_array( $user_claim ) ) {
			return new WP_Error( 'invalid-user-claim', __( 'Invalid user claim.', 'daggerhart-openid-connect-generic' ), $user_claim );
		}

		// Allow for errors from the IDP.
		if ( isset( $user_claim['error'] ) ) {
			$message = __( 'Error from the IDP.', 'daggerhart-openid-connect-generic' );
			if ( ! empty( $user_claim['error_description'] ) ) {
				$message = $user_claim['error_description'];
			}
			return new WP_Error( 'invalid-user-claim-' . $user_claim['error'], $message, $user_claim );
		}

		// Make sure the id_token sub equals the user_claim sub, according to spec.
		if ( $id_token_claim['sub'] !== $user_claim['sub'] ) {
			return new WP_Error( 'incorrect-user-claim', __( 'Incorrect user claim.', 'daggerhart-openid-connect-generic' ), func_get_args() );
		}

		// Allow for other plugins to alter the login success.
		$login_user = apply_filters( 'openid-connect-generic-user-login-test', true, $user_claim );

		if ( ! $login_user ) {
			return new WP_Error( 'unauthorized', __( 'Unauthorized access.', 'daggerhart-openid-connect-generic' ), $login_user );
		}

		return true;
	}

	/**
	 * Retrieve the subject identity from the id_token.
	 *
	 * @param array $id_token_claim The ID token claim.
	 *
	 * @return mixed
	 */
	public function get_subject_identity( $id_token_claim ) {
		return $id_token_claim['sub'];
	}
}
