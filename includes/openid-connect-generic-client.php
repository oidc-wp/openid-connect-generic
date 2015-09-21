<?php

class OpenID_Connect_Generic_Client {

	private $client_id;
	private $client_secret;
	private $scope;
	private $endpoint_login;
	private $endpoint_userinfo;
	private $endpoint_token;

	// login flow "ajax" endpoint
	private $redirect_uri;

	// states are only valid for 3 minutes
	private $state_time_limit = 180;

	/**
	 * @param $client_id
	 * @param $client_secret
	 * @param $scope
	 * @param $endpoint_login
	 * @param $endpoint_userinfo
	 * @param $endpoint_token
	 * @param $redirect_uri
	 */
	function __construct( $client_id, $client_secret, $scope, $endpoint_login, $endpoint_userinfo, $endpoint_token, $redirect_uri ){
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->scope = $scope;
		$this->endpoint_login = $endpoint_login;
		$this->endpoint_userinfo = $endpoint_userinfo;
		$this->endpoint_token = $endpoint_token;
		$this->redirect_uri = $redirect_uri;
	}

	/**
	 * Create a single use authentication url
	 *
	 * @return string
	 */
	function make_authentication_url() {
		$url = sprintf( '%1$s?response_type=code&scope=%2$s&client_id=%3$s&state=%4$s&redirect_uri=%5$s',
			$this->endpoint_login,
			urlencode( $this->scope ),
			urlencode( $this->client_id ),
			$this->new_state(),
			urlencode( $this->redirect_uri )
		);

		return $url;
	}

	/**
	 * 
	 * 
	 * @param $request
	 * 
	 * @return \WP_Error
	 */
	function validate_authentication_request( $request ){
		// look for an existing error of some kind
		if ( isset( $request['error'] ) ) {
			return new WP_Error( 'unknown-error', 'An unknown error occurred.', $request );
		}

		// make sure we have a legitimate authentication code and valid state
		if ( ! isset( $request['code'] ) ) {
			return new WP_Error( 'no-code', 'No authentication code present in the request.', $request );
		}

		// check the client request state 
		if ( ! isset( $request['state'] ) || ! $this->check_state( $request['state'] ) ){
			return new WP_Error( 'missing-state', __( 'Missing state.' ), $request );
		}

		return $request;
	}
	
	/**
	 * Get the authorization code from the request
	 *
	 * @return string
	 */
	function get_authentication_code( $request ){
		return $request['code'];
	}

	/**
	 * Using the authorization_code, request an authentication token from the idp
	 *
	 * @param $code - authorization_code
	 * 
	 * @return array|\WP_Error
	 */
	function request_authentication_token( $code ) {
		$request = array(
			'body' => array(
				'code'          => $code,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'redirect_uri'  => $this->redirect_uri,
				'grant_type'    => 'authorization_code',
				'scope'         => $this->scope,
			)
		);

		// allow modifications to the request
		$request = apply_filters( 'openid-connect-generic-alter-request', $request, 'get-authentication-token' );

		// call the server and ask for a token
		$response = wp_remote_post( $this->endpoint_token, $request );

		return $response;
	}


	/**
	 *
	 *
	 * @param $token_result
	 * @return array|mixed|object
	 */
	function get_token_response( $token_result ){
		if ( ! isset( $token_result['body'] ) ){
			return new WP_Error( 'missing-token-body', __( 'Missing token body.' ), $token_response );
		}

		// extract token response from token
		$token_response = json_decode( $token_result['body'], TRUE );

		return $token_response;
	}

	
	/**
	 * Using an access_token, request the userinfo from the idp
	 *
	 * @param $access_token
	 * 
	 * @return array|\WP_Error
	 */
	function request_userinfo( $access_token ) {
		// allow modifications to the request
		$request = apply_filters( 'openid-connect-generic-alter-request', array(), 'get-userinfo' );

		// attempt the request
		$response = wp_remote_get( $this->endpoint_userinfo . '?access_token=' . $access_token, $request );

		return $response;
	}

	/**
	 * Generate a new state, save it to the states option with a timestamp,
	 *  and return it.
	 *
	 * @return string
	 */
	function new_state() {
		$states = get_option( 'openid-connect-generic-valid-states', array() );

		// new state w/ timestamp
		$new_state            = md5( mt_rand() . microtime( true ) );
		$states[ $new_state ] = time();

		// save state
		update_option( 'openid-connect-generic-valid-states', $states );

		return $new_state;
	}

	/**
	 * Check the validity of a given state
	 *
	 * @param $state
	 * 
	 * @return bool
	 */
	function check_state( $state ) {
		$states = get_option( 'openid-connect-generic-valid-states', array() );
		$valid  = FALSE;

		// remove any expired states
		foreach ( $states as $code => $timestamp ) {
			if ( ( $timestamp + $this->state_time_limit ) < time() ) {
				unset( $states[ $code ] );
			}
		}

		// see if the current state is still within the list of valid states
		if ( isset( $states[ $state ] ) ) {
			// state is valid, remove it
			unset( $states[ $state ] );

			$valid = TRUE;
		}

		// save our altered states
		update_option( 'openid-connect-generic-valid-states', $states );

		return $valid;
	}

	/**
	 * 
	 * 
	 * @param $token_response
	 *
	 * @return bool|\WP_Error
	 */
	function validate_token_response( $token_response ){
		// we need to ensure 3 specific items exist with the token response in order
		// to proceed with confidence:  id_token, access_token, and token_type == 'Bearer'
		if ( ! isset( $token_response['id_token'] ) || ! isset( $token_response['access_token'] ) ||
		     ! isset( $token_response['token_type'] ) || $token_response['token_type'] !== 'Bearer'
		) {
			return new WP_Error( 'invalid-token-response', 'Invalid token response', $token_response );
		}
		
		return true;
	}

	/**
	 * 
	 * 
	 * @param $token_response
	 *
	 * @return array|mixed|object|\WP_Error
	 */
	function get_id_token_claim( $token_response ){
		// name sure we have an id_token
		if ( ! isset( $token_response['id_token'] ) ) {
			return new WP_Error( 'no-identity-token', __( 'No identity token' ), $token_response );
		}

		// break apart the id_token in the response for decoding
		$tmp = explode( '.', $token_response['id_token'] );

		if ( ! isset( $tmp[1] ) ) {
			return new WP_Error( 'no-identity-token', __( 'No identity token' ), $token_response );
		}

		// Extract the id_token's claims from the token
		$id_token_claim = json_decode( base64_decode( $tmp[1] ), TRUE );

		return $id_token_claim;
	}

	/**
	 * 
	 * 
	 * @param $id_token_claim
	 *
	 * @return bool|\WP_Error
	 */
	function validate_id_token_claim( $id_token_claim ){
		// make sure we can find our identification data and that it has a value
		if ( ! isset( $id_token_claim['sub'] ) || empty( $id_token_claim['sub'] ) ) {
			return new WP_Error( 'no-subject-identity', __( 'No subject identity' ), $id_token_claim );
		}
		
		return true;
	}

	/**
	 * 
	 * 
	 * @param $token_response
	 * 
	 * @return array|mixed|object|\WP_Error
	 */
	function get_user_claim( $token_response ){
		// send a userinfo request to get user claim
		$user_claim_result = $this->request_userinfo( $token_response['access_token'] );

		// make sure we didn't get an error, and that the response body exists
		if ( is_wp_error( $user_claim_result ) || ! isset( $user_claim_result['body'] ) ) {
			return new WP_Error( 'bad-claim', __( 'Bad user claim' ), $user_claim_result );
		}

		$user_claim = json_decode( $user_claim_result['body'], TRUE );

		return $user_claim;
	}
	
	/**
	 * 
	 * 
	 * @param $user_claim
	 * @param $id_token_claim
	 *
	 * @return \WP_Error
	 */
	function validate_user_claim( $user_claim, $id_token_claim ) {
		// must be an array
		if ( ! is_array( $user_claim ) ){
			return new WP_Error( 'invalid-user-claim', __( 'Invalid user claim' ), $user_claim );
		}
		
		// make sure the id_token sub === user_claim sub, according to spec
		if ( $id_token_claim['sub' ] !== $user_claim['sub'] ) {
			return new WP_Error( 'invalid-user-claim', __( 'Invalid user claim' ), func_get_args() );
		}

		// allow for other plugins to alter the login success
		$login_user = apply_filters( 'openid-connect-generic-user-login-test', TRUE, $user_claim );
		
		if ( ! $login_user ) {
			return new WP_Error( 'unauthorized', __( 'Unauthorized' ), $login_user );
		}
		
		return true;
	}

	/**
	 * 
	 * 
	 * @return mixed
	 */
	function get_subject_identity( $id_token_claim ){
		return $id_token_claim['sub'];
	}
}