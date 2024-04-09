<?php
/**
 * Plugin OIDC/oAuth client warpper class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Authentication
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Client_Wrapper class.
 *
 * Plugin OIDC/oAuth client wrapper class.
 *
 * @package  OpenID_Connect_Generic
 * @category Authentication
 */
class OpenID_Connect_Generic_Client_Wrapper {

	/**
	 * The client object instance.
	 *
	 * @var OpenID_Connect_Generic_Client
	 */
	private $client;

	/**
	 * The settings object instance.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * The logger object instance.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * The token refresh info cookie key.
	 *
	 * @var string
	 */
	private $cookie_token_refresh_key = 'openid-connect-generic-refresh';

	/**
	 * The user redirect cookie key.
	 *
	 * @deprecated Redirection should be done via state transient and not cookies.
	 *
	 * @var string
	 */
	public $cookie_redirect_key = 'openid-connect-generic-redirect';

	/**
	 * The return error onject.
	 *
	 * @example WP_Error if there was a problem, or false if no error
	 *
	 * @var bool|WP_Error
	 */
	private $error = false;

	/**
	 * Used to pass the openid token refresh expiration time to the auth_cookie_expiration filter.
	 *
	 * @var integer
	 */
	private $openid_token_refresh_expires_in = 0;

	/**
	 * Inject necessary objects and services into the client.
	 *
	 * @param OpenID_Connect_Generic_Client          $client   A plugin client object instance.
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   A plugin logger object instance.
	 */
	public function __construct( OpenID_Connect_Generic_Client $client, OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$this->client = $client;
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * Hook the client into WordPress.
	 *
	 * @param \OpenID_Connect_Generic_Client          $client   The plugin client instance.
	 * @param \OpenID_Connect_Generic_Option_Settings $settings The plugin settings instance.
	 * @param \OpenID_Connect_Generic_Option_Logger   $logger   The plugin logger instance.
	 *
	 * @return \OpenID_Connect_Generic_Client_Wrapper
	 */
	public static function register( OpenID_Connect_Generic_Client $client, OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$client_wrapper  = new self( $client, $settings, $logger );

		// Integrated logout.
		if ( $settings->endpoint_end_session ) {
			add_filter( 'allowed_redirect_hosts', array( $client_wrapper, 'update_allowed_redirect_hosts' ), 99, 1 );
			add_filter( 'logout_redirect', array( $client_wrapper, 'get_end_session_logout_redirect_url' ), 99, 3 );
		}

		// Alter the requests according to settings.
		add_filter( 'openid-connect-generic-alter-request', array( $client_wrapper, 'alter_request' ), 10, 2 );

		if ( is_admin() ) {
			/*
			 * Use the ajax url to handle processing authorization without any html output
			 * this callback will occur when then IDP returns with an authenticated value
			 */
			add_action( 'wp_ajax_openid-connect-authorize', array( $client_wrapper, 'authentication_request_callback' ) );
			add_action( 'wp_ajax_nopriv_openid-connect-authorize', array( $client_wrapper, 'authentication_request_callback' ) );
		}

		if ( $settings->alternate_redirect_uri ) {
			// Provide an alternate route for authentication_request_callback.
			add_rewrite_rule( '^openid-connect-authorize/?', 'index.php?openid-connect-authorize=1', 'top' );
			add_rewrite_tag( '%openid-connect-authorize%', '1' );
			add_action( 'parse_request', array( $client_wrapper, 'alternate_redirect_uri_parse_request' ) );
		}

		// Verify token for any logged in user.
		if ( is_user_logged_in() ) {
			add_action( 'wp_loaded', array( $client_wrapper, 'ensure_tokens_still_fresh' ) );
		}

		return $client_wrapper;
	}

	/**
	 * Implements WordPress parse_request action.
	 *
	 * @param WP_Query $query The WordPress query object.
	 *
	 * @return void
	 */
	public function alternate_redirect_uri_parse_request( $query ) {
		if ( isset( $query->query_vars['openid-connect-authorize'] ) &&
			 '1' === $query->query_vars['openid-connect-authorize'] ) {
			$this->authentication_request_callback();
			exit;
		}
	}

	/**
	 * Get the client login redirect.
	 *
	 * @return string
	 */
	public function get_redirect_to() {
		/*
		 * @var WP $wp
		 */
		global $wp;

		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' == $GLOBALS['pagenow'] && isset( $_GET['action'] ) && 'logout' === $_GET['action'] ) {
			return '';
		}

		// Default redirect to the homepage.
		$redirect_url = home_url();

		// If using the login form, default redirect to the admin dashboard.
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' == $GLOBALS['pagenow'] ) {
			$redirect_url = admin_url();
		}

		// Honor Core WordPress & other plugin redirects.
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$redirect_url = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
		}

		// Capture the current URL if set to redirect back to origin page.
		if ( $this->settings->redirect_user_back ) {
			if ( ! empty( $wp->query_string ) ) {
				$redirect_url = home_url( '?' . $wp->query_string );
			}
			if ( ! empty( $wp->request ) ) {
				$redirect_url = home_url( add_query_arg( null, null ) );
				// @phpstan-ignore-next-line
				if ( $wp->did_permalink ) {
					$redirect_url = home_url( add_query_arg( $_GET, trailingslashit( $wp->request ) ) );
				}
			}
		}

		// This hook is being deprecated with the move away from cookies.
		$redirect_url = apply_filters_deprecated(
			'openid-connect-generic-cookie-redirect-url',
			array( $redirect_url ),
			'3.8.2',
			'openid-connect-generic-client-redirect-to'
		);

		// This is the new hook to use with the transients version of redirection.
		return apply_filters( 'openid-connect-generic-client-redirect-to', $redirect_url );
	}

	/**
	 * Create a single use authentication url
	 *
	 * @param array<string> $atts An optional array of override/feature attributes.
	 *
	 * @return string
	 */
	public function get_authentication_url( $atts = array() ) {

		$atts = shortcode_atts(
			array(
				'endpoint_login' => $this->settings->endpoint_login,
				'scope' => $this->settings->scope,
				'client_id' => $this->settings->client_id,
				'redirect_uri' => $this->client->get_redirect_uri(),
				'redirect_to' => $this->get_redirect_to(),
				'acr_values' => $this->settings->acr_values,
			),
			$atts,
			'openid_connect_generic_auth_url'
		);

		// Validate the redirect to value to prevent a redirection attack.
		if ( ! empty( $atts['redirect_to'] ) ) {
			$atts['redirect_to'] = wp_validate_redirect( $atts['redirect_to'], home_url() );
		}

		$separator = '?';
		if ( stripos( $this->settings->endpoint_login, '?' ) !== false ) {
			$separator = '&';
		}

		$url_format = '%1$s%2$sresponse_type=code&scope=%3$s&client_id=%4$s&state=%5$s&redirect_uri=%6$s';
		if ( ! empty( $atts['acr_values'] ) ) {
			$url_format .= '&acr_values=%7$s';
		}

		$url = sprintf(
			$url_format,
			$atts['endpoint_login'],
			$separator,
			rawurlencode( $atts['scope'] ),
			rawurlencode( $atts['client_id'] ),
			$this->client->new_state( $atts['redirect_to'] ),
			rawurlencode( $atts['redirect_uri'] ),
			rawurlencode( $atts['acr_values'] )
		);

		$url = apply_filters( 'openid-connect-generic-auth-url', $url );
		$this->logger->log( $url, 'make_authentication_url' );
		return $url;
	}

	/**
	 * Handle retrieval and validation of refresh_token.
	 *
	 * @return void
	 */
	public function ensure_tokens_still_fresh() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = wp_get_current_user()->ID;
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$token = wp_get_session_token();
		$session = $manager->get( $token );

		if ( ! isset( $session[ $this->cookie_token_refresh_key ] ) ) {
			// Not an OpenID-based session.
			return;
		}

		$current_time = time();
		$refresh_token_info = $session[ $this->cookie_token_refresh_key ];

		$next_access_token_refresh_time = $refresh_token_info['next_access_token_refresh_time'];

		if ( $current_time < $next_access_token_refresh_time ) {
			return;
		}

		$refresh_token = $refresh_token_info['refresh_token'];
		$refresh_expires = $refresh_token_info['refresh_expires'];

		if ( ! $refresh_token || ( $refresh_expires && $current_time > $refresh_expires ) ) {
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				do_action( 'openid-connect-generic-session-expired', wp_get_current_user(), esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
				wp_logout();

				if ( $this->settings->redirect_on_logout ) {
					$this->error_redirect( new WP_Error( 'access-token-expired', __( 'Session expired. Please login again.', 'daggerhart-openid-connect-generic' ) ) );
				}

				return;
			}
		}

		$token_result = $this->client->request_new_tokens( $refresh_token );

		if ( is_wp_error( $token_result ) ) {
			wp_logout();
			$this->error_redirect( $token_result );
		}

		$token_response = $this->client->get_token_response( $token_result );

		if ( is_wp_error( $token_response ) ) {
			wp_logout();
			$this->error_redirect( $token_response );
		}

		update_user_meta( $user_id, 'openid-connect-generic-last-token-response', $token_response );
		$this->save_refresh_token( $manager, $token, $token_response );
	}

	/**
	 * Handle errors by redirecting the user to the login form along with an
	 * error code
	 *
	 * @param WP_Error $error A WordPress error object.
	 *
	 * @return void
	 */
	public function error_redirect( $error ) {
		$this->logger->log( $error );

		// Redirect user back to login page.
		wp_redirect(
			wp_login_url() .
			'?login-error=' . $error->get_error_code() .
			'&message=' . urlencode( $error->get_error_message() )
		);
		exit;
	}

	/**
	 * Get the current error state.
	 *
	 * @return bool|WP_Error
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Add the end_session endpoint to WordPress core's whitelist of redirect hosts.
	 *
	 * @param array<string> $allowed The allowed redirect host names.
	 *
	 * @return array<string>|bool
	 */
	public function update_allowed_redirect_hosts( $allowed ) {
		$host = parse_url( $this->settings->endpoint_end_session, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}

		$allowed[] = $host;
		return $allowed;
	}

	/**
	 * Handle the logout redirect for end_session endpoint.
	 *
	 * @param string  $redirect_url          The requested redirect URL.
	 * @param string  $requested_redirect_to The user login source URL, or configured user redirect URL.
	 * @param WP_User $user                  The logged in user object.
	 *
	 * @return string
	 */
	public function get_end_session_logout_redirect_url( $redirect_url, $requested_redirect_to, $user ) {
		$url = $this->settings->endpoint_end_session;
		$query = parse_url( $url, PHP_URL_QUERY );
		$url .= $query ? '&' : '?';

		// Prevent redirect back to the IDP when logging out in auto mode.
		if ( 'auto' === $this->settings->login_type && strpos( $redirect_url, 'wp-login.php?loggedout=true' ) ) {
			// By default redirect back to the site home.
			$redirect_url = home_url();
		}

		$token_response = $user->get( 'openid-connect-generic-last-token-response' );
		if ( ! $token_response ) {
			// Happens if non-openid login was used.
			return $redirect_url;
		} else if ( ! parse_url( $redirect_url, PHP_URL_HOST ) ) {
			// Convert to absolute url if needed, site_url() to be friendly with non-standard (Bedrock) layout.
			$redirect_url = site_url( $redirect_url );
		}

		$claim = $user->get( 'openid-connect-generic-last-id-token-claim' );

		if ( isset( $claim['iss'] ) && 'https://accounts.google.com' == $claim['iss'] ) {
			/*
			 * Google revoke endpoint
			 * 1. expects the *access_token* to be passed as "token"
			 * 2. does not support redirection (post_logout_redirect_uri)
			 * So just redirect to regular WP logout URL.
			 * (we would *not* disconnect the user from any Google service even
			 * if he was initially disconnected to them)
			 */
			return $redirect_url;
		} else {
			return $url . sprintf( 'id_token_hint=%s&post_logout_redirect_uri=%s', $token_response['id_token'], urlencode( $redirect_url ) );
		}
	}

	/**
	 * Modify outgoing requests according to settings.
	 *
	 * @param array<mixed> $request   The outgoing request array.
	 * @param string       $operation The request operation name.
	 *
	 * @return mixed
	 */
	public function alter_request( $request, $operation ) {
		if ( ! empty( $this->settings->http_request_timeout ) ) {
			$request['timeout'] = intval( $this->settings->http_request_timeout );
		}

		if ( $this->settings->no_sslverify ) {
			$request['sslverify'] = false;
		}

		return $request;
	}

	/**
	 * Control the authentication and subsequent authorization of the user when
	 * returning from the IDP.
	 *
	 * @return void
	 */
	public function authentication_request_callback() {
		$client = $this->client;

		// Start the authentication flow.
		$authentication_request = $client->validate_authentication_request( $_GET );

		if ( is_wp_error( $authentication_request ) ) {
			$this->error_redirect( $authentication_request );
		}

		// Retrieve the authentication code from the authentication request.
		$code = $client->get_authentication_code( $authentication_request );

		if ( is_wp_error( $code ) ) {
			$this->error_redirect( $code );
		}

		// Retrieve the authentication state from the authentication request.
		$state = $client->get_authentication_state( $authentication_request );

		if ( is_wp_error( $state ) ) {
			$this->error_redirect( $state );
		}

		// Attempting to exchange an authorization code for an authentication token.
		$token_result = $client->request_authentication_token( $code );

		if ( is_wp_error( $token_result ) ) {
			$this->error_redirect( $token_result );
		}

		// Get the decoded response from the authentication request result.
		$token_response = $client->get_token_response( $token_result );

		// Allow for other plugins to alter data before validation.
		$token_response = apply_filters( 'openid-connect-modify-token-response-before-validation', $token_response );

		if ( is_wp_error( $token_response ) ) {
			$this->error_redirect( $token_response );
		}

		// Ensure the that response contains required information.
		$valid = $client->validate_token_response( $token_response );

		if ( is_wp_error( $valid ) ) {
			$this->error_redirect( $valid );
		}

		/**
		 * The id_token is used to identify the authenticated user, e.g. for SSO.
		 * The access_token must be used to prove access rights to protected
		 * resources e.g. for the userinfo endpoint
		 */
		$id_token_claim = $client->get_id_token_claim( $token_response );

		// Allow for other plugins to alter data before validation.
		$id_token_claim = apply_filters( 'openid-connect-modify-id-token-claim-before-validation', $id_token_claim );

		if ( is_wp_error( $id_token_claim ) ) {
			$this->error_redirect( $id_token_claim );
		}

		// Validate our id_token has required values.
		$valid = $client->validate_id_token_claim( $id_token_claim );

		if ( is_wp_error( $valid ) ) {
			$this->error_redirect( $valid );
		}

		// If userinfo endpoint is set, exchange the token_response for a user_claim.
		if ( ! empty( $this->settings->endpoint_userinfo ) && isset( $token_response['access_token'] ) ) {
			$user_claim = $client->get_user_claim( $token_response );
		} else {
			$user_claim = $id_token_claim;
		}

		if ( is_wp_error( $user_claim ) ) {
			$this->error_redirect( $user_claim );
		}

		// Validate our user_claim has required values.
		$valid = $client->validate_user_claim( $user_claim, $id_token_claim );

		if ( is_wp_error( $valid ) ) {
			$this->error_redirect( $valid );
		}

		/**
		 * End authorization
		 * -
		 * Request is authenticated and authorized - start user handling
		 */
		$subject_identity = $client->get_subject_identity( $id_token_claim );
		$user = $this->get_user_by_identity( $subject_identity );

		// A pre-existing IDP mapped user wasn't found.
		if ( ! $user ) {
			// If linking existing users or creating new ones call the `create_new_user` method which handles both cases.
			if ( $this->settings->link_existing_users || $this->settings->create_if_does_not_exist ) {
				$user = $this->create_new_user( $subject_identity, $user_claim );
				if ( is_wp_error( $user ) ) {
					$this->error_redirect( $user );
				}
			} else {
				$this->error_redirect( new WP_Error( 'identity-not-map-existing-user', __( 'User identity is not linked to an existing WordPress user.', 'daggerhart-openid-connect-generic' ), $user_claim ) );
			}
		}

		// Validate the found / created user.
		$valid = $this->validate_user( $user );

		if ( is_wp_error( $valid ) ) {
			$this->error_redirect( $valid );
		}

		// Login the found / created user.
		$start_time = microtime( true );
		$this->login_user( $user, $token_response, $id_token_claim, $user_claim, $subject_identity );
		$end_time = microtime( true );
		// Log our success.
		$this->logger->log( "Successful login for: {$user->user_login} ({$user->ID})", 'login-success', $end_time - $start_time );

		// Allow plugins / themes to take action once a user is logged in.
		$start_time = microtime( true );
		do_action( 'openid-connect-generic-user-logged-in', $user );
		$end_time = microtime( true );
		$this->logger->log( 'openid-connect-generic-user-logged-in', 'do_action', $end_time - $start_time );

		// Default redirect to the homepage.
		$redirect_url = home_url();
		// Redirect user according to redirect set in state.
		$state_object = get_transient( 'openid-connect-generic-state--' . $state );
		// Get the redirect URL stored with the corresponding authentication request state.
		if ( ! empty( $state_object ) && ! empty( $state_object[ $state ] ) && ! empty( $state_object[ $state ]['redirect_to'] ) ) {
			$redirect_url = $state_object[ $state ]['redirect_to'];
		}

		// Provide backwards compatibility for customization using the deprecated cookie method.
		if ( ! empty( $_COOKIE[ $this->cookie_redirect_key ] ) ) {
			$redirect_url = esc_url_raw( wp_unslash( $_COOKIE[ $this->cookie_redirect_key ] ) );
		}

		// Only do redirect-user-back action hook when the plugin is configured for it.
		if ( $this->settings->redirect_user_back ) {
			do_action( 'openid-connect-generic-redirect-user-back', $redirect_url, $user );
		}

		wp_redirect( $redirect_url );

		exit;
	}

	/**
	 * Validate the potential WP_User.
	 *
	 * @param WP_User|WP_Error|false $user The user object.
	 *
	 * @return true|WP_Error
	 */
	public function validate_user( $user ) {
		// Ensure the found user is a real WP_User.
		if ( ! is_a( $user, 'WP_User' ) || ! $user->exists() ) {
			return new WP_Error( 'invalid-user', __( 'Invalid user.', 'daggerhart-openid-connect-generic' ), $user );
		}

		return true;
	}

	/**
	 * Refresh user claim.
	 *
	 * @param WP_User $user             The user object.
	 * @param array   $token_response   The token response.
	 *
	 * @return WP_Error|array
	 */
	public function refresh_user_claim( $user, $token_response ) {
		$client = $this->client;

		/**
		 * The id_token is used to identify the authenticated user, e.g. for SSO.
		 * The access_token must be used to prove access rights to protected
		 * resources e.g. for the userinfo endpoint
		 */
		$id_token_claim = $client->get_id_token_claim( $token_response );

		// Allow for other plugins to alter data before validation.
		$id_token_claim = apply_filters( 'openid-connect-modify-id-token-claim-before-validation', $id_token_claim );

		if ( is_wp_error( $id_token_claim ) ) {
			return $id_token_claim;
		}

		// Validate our id_token has required values.
		$valid = $client->validate_id_token_claim( $id_token_claim );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// If userinfo endpoint is set, exchange the token_response for a user_claim.
		if ( ! empty( $this->settings->endpoint_userinfo ) && isset( $token_response['access_token'] ) ) {
			$user_claim = $client->get_user_claim( $token_response );
		} else {
			$user_claim = $id_token_claim;
		}

		if ( is_wp_error( $user_claim ) ) {
			return $user_claim;
		}

		// Validate our user_claim has required values.
		$valid = $client->validate_user_claim( $user_claim, $id_token_claim );

		if ( is_wp_error( $valid ) ) {
			$this->error_redirect( $valid );
			return $valid;
		}

		// Store the tokens for future reference.
		update_user_meta( $user->ID, 'openid-connect-generic-last-token-response', $token_response );
		update_user_meta( $user->ID, 'openid-connect-generic-last-id-token-claim', $id_token_claim );
		update_user_meta( $user->ID, 'openid-connect-generic-last-user-claim', $user_claim );

		return $user_claim;
	}

	/**
	 * Record user meta data, and provide an authorization cookie.
	 *
	 * @param WP_User $user             The user object.
	 * @param array   $token_response   The token response.
	 * @param array   $id_token_claim   The ID token claim.
	 * @param array   $user_claim       The authenticated user claim.
	 * @param string  $subject_identity The subject identity from the IDP.
	 *
	 * @return void
	 */
	public function login_user( $user, $token_response, $id_token_claim, $user_claim, $subject_identity ) {
		// Store the tokens for future reference.
		update_user_meta( $user->ID, 'openid-connect-generic-last-token-response', $token_response );
		update_user_meta( $user->ID, 'openid-connect-generic-last-id-token-claim', $id_token_claim );
		update_user_meta( $user->ID, 'openid-connect-generic-last-user-claim', $user_claim );
		// Allow plugins / themes to take action using current claims on existing user (e.g. update role).
		do_action( 'openid-connect-generic-update-user-using-current-claim', $user, $user_claim );

		// Determine the amount of days before the cookie expires.
		$remember_me = apply_filters( 'openid-connect-generic-remember-me', false, $user, $token_response, $id_token_claim, $user_claim, $subject_identity );
		$wp_expiration_days = $remember_me ? 14 : 2;

		// If remember-me is enabled, and using token expiration is enabled,
		// add a filter to overwrite the default cookie expiration with the
		// openid token expiration.
		if (
			$remember_me
			&& apply_filters( 'openid-connect-generic-use-token-refresh-expiration', false )
			&& ( $token_response['refresh_expires_in'] ?? 0 )
		) {
			$this->openid_token_refresh_expires_in = $token_response['refresh_expires_in'];
			add_filter( 'auth_cookie_expiration', array( $this, 'set_cookie_expiration_to_openid_token_refresh_expiration' ) );
		}

		// Create the WP session, so we know its token.
		$expiration = time() + apply_filters( 'auth_cookie_expiration', $wp_expiration_days * DAY_IN_SECONDS, $user->ID, false );
		$manager = WP_Session_Tokens::get_instance( $user->ID );
		$token = $manager->create( $expiration );

		// Save the refresh token in the session.
		$this->save_refresh_token( $manager, $token, $token_response );

		// you did great, have a cookie!
		wp_set_auth_cookie( $user->ID, $remember_me, '', $token );
		do_action( 'wp_login', $user->user_login, $user );

		// Remove the filter for the auth cookie expiration after all the auth cookies are set.
		remove_filter( 'auth_cookie_expiration', array( $this, 'set_cookie_expiration_to_openid_token_refresh_expiration' ) );
	}

	/**
	 * Filter callback to overwrite the default cookie expiration with the
	 * openid token refresh expiration. This is applied both when creating the session
	 * token as well as when wp_set_auth_cookie is called.
	 *
	 * @param integer $expiration_in_seconds The expiration time in seconds.
	 * @return integer
	 */
	public function set_cookie_expiration_to_openid_token_refresh_expiration( $expiration_in_seconds ) {
		$expiration_in_seconds = $this->openid_token_refresh_expires_in;
		return $expiration_in_seconds;
	}

	/**
	 * Save refresh token to WP session tokens
	 *
	 * @param WP_Session_Tokens   $manager        A user session tokens manager.
	 * @param string              $token          The current users session token.
	 * @param array|WP_Error|null $token_response The authentication token response.
	 */
	public function save_refresh_token( $manager, $token, $token_response ) {
		if ( ! $this->settings->token_refresh_enable ) {
			return;
		}
		$session = $manager->get( $token );
		$now = time();
		$session[ $this->cookie_token_refresh_key ] = array(
			'next_access_token_refresh_time' => $token_response['expires_in'] + $now,
			'refresh_token' => isset( $token_response['refresh_token'] ) ? $token_response['refresh_token'] : false,
			'refresh_expires' => false,
		);
		if ( isset( $token_response['refresh_expires_in'] ) ) {
			$refresh_expires_in = $token_response['refresh_expires_in'];
			if ( $refresh_expires_in > 0 ) {
				// Leave enough time for the actual refresh request to go through.
				$refresh_expires = $now + $refresh_expires_in - 5;
				$session[ $this->cookie_token_refresh_key ]['refresh_expires'] = $refresh_expires;
			}
		}
		$manager->update( $token, $session );
		return;
	}

	/**
	 * Get the user that has meta data matching a
	 *
	 * @param string $subject_identity The IDP identity of the user.
	 *
	 * @return false|WP_User
	 */
	public function get_user_by_identity( $subject_identity ) {
		// Look for user by their openid-connect-generic-subject-identity value.
		$user_query = new WP_User_Query(
			array(
				'meta_query' => array(
					array(
						'key'   => 'openid-connect-generic-subject-identity',
						'value' => $subject_identity,
					),
				),
				// Override the default blog_id (get_current_blog_id) to find users on different sites of a multisite install.
				'blog_id' => 0,
			)
		);

		// If we found existing users, grab the first one returned.
		if ( $user_query->get_total() > 0 ) {
			$users = $user_query->get_results();
			return $users[0];
		}

		return false;
	}

	/**
	 * Avoid user_login collisions by incrementing.
	 *
	 * @param array $user_claim The IDP authenticated user claim data.
	 *
	 * @return string|WP_Error
	 */
	private function get_username_from_claim( $user_claim ) {

		// @var string $desired_username
		$desired_username = '';

		// Allow settings to take first stab at username.
		if ( ! empty( $this->settings->identity_key ) && isset( $user_claim[ $this->settings->identity_key ] ) ) {
			$desired_username = $user_claim[ $this->settings->identity_key ];
		}
		if ( empty( $desired_username ) && isset( $user_claim['preferred_username'] ) && ! empty( $user_claim['preferred_username'] ) ) {
			$desired_username = $user_claim['preferred_username'];
		}
		if ( empty( $desired_username ) && isset( $user_claim['name'] ) && ! empty( $user_claim['name'] ) ) {
			$desired_username = $user_claim['name'];
		}
		if ( empty( $desired_username ) && isset( $user_claim['email'] ) && ! empty( $user_claim['email'] ) ) {
			$tmp = explode( '@', $user_claim['email'] );
			$desired_username = $tmp[0];
		}
		if ( empty( $desired_username ) ) {
			// Nothing to build a name from.
			return new WP_Error( 'no-username', __( 'No appropriate username found.', 'daggerhart-openid-connect-generic' ), $user_claim );
		}

		// Don't use the full email address for a username.
		$_desired_username = explode( '@', $desired_username );
		$desired_username = $_desired_username[0];
		// Use WordPress Core to sanitize the IDP username.
		$sanitized_username = sanitize_user( $desired_username, true );
		if ( empty( $sanitized_username ) ) {
			// translators: %1$s is the santitized version of the username from the IDP.
			return new WP_Error( 'username-sanitization-failed', sprintf( __( 'Username %1$s could not be sanitized.', 'daggerhart-openid-connect-generic' ), $desired_username ), $desired_username );
		}

		return $sanitized_username;
	}

	/**
	 * Get a nickname.
	 *
	 * @param array $user_claim The IDP authenticated user claim data.
	 *
	 * @return string|WP_Error|null
	 */
	private function get_nickname_from_claim( $user_claim ) {
		$desired_nickname = null;
		// Allow settings to take first stab at nickname.
		if ( ! empty( $this->settings->nickname_key ) && isset( $user_claim[ $this->settings->nickname_key ] ) ) {
			$desired_nickname = $user_claim[ $this->settings->nickname_key ];
		}

		if ( empty( $desired_nickname ) ) {
			// translators: %1$s is the configured User Claim nickname key.
			return new WP_Error( 'no-nickname', sprintf( __( 'No nickname found in user claim using key: %1$s.', 'daggerhart-openid-connect-generic' ), $this->settings->nickname_key ), $this->settings->nickname_key );
		}

		return $desired_nickname;
	}

	/**
	 * Checks if $claimname is in the body or _claim_names of the userinfo.
	 * If yes, returns the claim value. Otherwise, returns false.
	 *
	 * @param string $claimname the claim name to look for.
	 * @param array  $userinfo the JSON to look in.
	 * @param string $claimvalue the source claim value ( from the body of the JWT of the claim source).
	 * @return true|false
	 */
	private function get_claim( $claimname, $userinfo, &$claimvalue ) {
		/**
		 * If we find a simple claim, return it.
		 */
		if ( array_key_exists( $claimname, $userinfo ) ) {
			$claimvalue = $userinfo[ $claimname ];
			return true;
		}
		/**
		 * If there are no aggregated claims, it is over.
		 */
		if ( ! array_key_exists( '_claim_names', $userinfo ) ||
			! array_key_exists( '_claim_sources', $userinfo ) ) {
			return false;
		}
		$claim_src_ptr = $userinfo['_claim_names'];
		if ( ! isset( $claim_src_ptr ) ) {
			return false;
		}
		/**
		 * No reference found
		 */
		if ( ! array_key_exists( $claimname, $claim_src_ptr ) ) {
			return false;
		}
		$src_name = $claim_src_ptr[ $claimname ];
		// Reference found, but no corresponding JWT. This is a malformed userinfo.
		if ( ! array_key_exists( $src_name, $userinfo['_claim_sources'] ) ) {
			return false;
		}
		$src = $userinfo['_claim_sources'][ $src_name ];
		// Source claim is not a JWT. Abort.
		if ( ! array_key_exists( 'JWT', $src ) ) {
			return false;
		}
		/**
		 * Extract claim from JWT.
		 * FIXME: We probably want to verify the JWT signature/issuer here.
		 * For example, using JWKS if applicable. For symmetrically signed
		 * JWTs (HMAC), we need a way to specify the acceptable secrets
		 * and each possible issuer in the config.
		 */
		$jwt = $src['JWT'];
		list ( $header, $body, $rest ) = explode( '.', $jwt, 3 );
		$body_str = base64_decode( $body, false );
		if ( ! $body_str ) {
			return false;
		}
		$body_json = json_decode( $body_str, true );
		if ( ! isset( $body_json ) ) {
			return false;
		}
		if ( ! array_key_exists( $claimname, $body_json ) ) {
			return false;
		}
		$claimvalue = $body_json[ $claimname ];
		return true;
	}


	/**
	 * Build a string from the user claim according to the specified format.
	 *
	 * @param string $format               The format format of the user identity.
	 * @param array  $user_claim           The authorized user claim.
	 * @param bool   $error_on_missing_key Whether to return and error on a missing key.
	 *
	 * @return string|WP_Error
	 */
	private function format_string_with_claim( $format, $user_claim, $error_on_missing_key = false ) {
		$matches = null;
		$string = '';
		$info = '';
		$i = 0;
		if ( preg_match_all( '/\{[^}]*\}/u', $format, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$key = substr( $match[0], 1, -1 );
				$string .= substr( $format, $i, $match[1] - $i );
				if ( ! $this->get_claim( $key, $user_claim, $info ) ) {
					if ( $error_on_missing_key ) {
						return new WP_Error(
							'incomplete-user-claim',
							__( 'User claim incomplete.', 'daggerhart-openid-connect-generic' ),
							array(
								'message'    => 'Unable to find key: ' . $key . ' in user_claim',
								'hint'       => 'Verify OpenID Scope includes a scope with the attributes you need',
								'user_claim' => $user_claim,
								'format'     => $format,
							)
						);
					}
				} else {
					$string .= $info;
				}
				$i = $match[1] + strlen( $match[0] );
			}
		}
		$string .= substr( $format, $i );
		return $string;
	}

	/**
	 * Get a displayname.
	 *
	 * @param array $user_claim           The authorized user claim.
	 * @param bool  $error_on_missing_key Whether to return and error on a missing key.
	 *
	 * @return string|null|WP_Error
	 */
	private function get_displayname_from_claim( $user_claim, $error_on_missing_key = false ) {
		if ( ! empty( $this->settings->displayname_format ) ) {
			return $this->format_string_with_claim( $this->settings->displayname_format, $user_claim, $error_on_missing_key );
		}
		return null;
	}

	/**
	 * Get an email.
	 *
	 * @param array $user_claim           The authorized user claim.
	 * @param bool  $error_on_missing_key Whether to return and error on a missing key.
	 *
	 * @return string|null|WP_Error
	 */
	private function get_email_from_claim( $user_claim, $error_on_missing_key = false ) {
		if ( ! empty( $this->settings->email_format ) ) {
			return $this->format_string_with_claim( $this->settings->email_format, $user_claim, $error_on_missing_key );
		}
		return null;
	}

	/**
	 * Create a new user from details in a user_claim.
	 *
	 * @param string $subject_identity The authenticated user's identity with the IDP.
	 * @param array  $user_claim       The authorized user claim.
	 *
	 * @return \WP_Error | \WP_User
	 */
	public function create_new_user( $subject_identity, $user_claim ) {
		$start_time = microtime( true );
		$user_claim = apply_filters( 'openid-connect-generic-alter-user-claim', $user_claim );

		// Default username & email to the subject identity.
		$username       = $subject_identity;
		$email          = $subject_identity;
		$nickname       = $subject_identity;
		$displayname    = $subject_identity;
		$values_missing = false;

		// Allow claim details to determine username, email, nickname and displayname.
		$_email = $this->get_email_from_claim( $user_claim, true );
		if ( is_wp_error( $_email ) || empty( $_email ) ) {
			$values_missing = true;
		} else {
			$email = $_email;
		}

		$_username = $this->get_username_from_claim( $user_claim );
		if ( is_wp_error( $_username ) || empty( $_username ) ) {
			$values_missing = true;
		} else {
			$username = $_username;
		}

		$_nickname = $this->get_nickname_from_claim( $user_claim );
		if ( is_wp_error( $_nickname ) || empty( $_nickname ) ) {
			$values_missing = true;
		} else {
			$nickname = $_nickname;
		}

		$_displayname = $this->get_displayname_from_claim( $user_claim, true );
		if ( is_wp_error( $_displayname ) || empty( $_displayname ) ) {
			$values_missing = true;
		} else {
			$displayname = $_displayname;
		}

		// Attempt another request for userinfo if some values are missing.
		if ( $values_missing && isset( $user_claim['access_token'] ) && ! empty( $this->settings->endpoint_userinfo ) ) {
			$user_claim_result = $this->client->request_userinfo( $user_claim['access_token'] );

			// Make sure we didn't get an error.
			if ( is_wp_error( $user_claim_result ) ) {
				return new WP_Error( 'bad-user-claim-result', __( 'Bad user claim result.', 'daggerhart-openid-connect-generic' ), $user_claim_result );
			}

			$user_claim = json_decode( $user_claim_result['body'], true );
		}

		$_email = $this->get_email_from_claim( $user_claim, true );
		if ( is_wp_error( $_email ) ) {
			return $_email;
		}
		// Use the email address from the latest userinfo request if not empty.
		if ( ! empty( $_email ) ) {
			$email = $_email;
		}

		$_username = $this->get_username_from_claim( $user_claim );
		if ( is_wp_error( $_username ) ) {
			return $_username;
		}
		// Use the username from the latest userinfo request if not empty.
		if ( ! empty( $_username ) ) {
			$username = $_username;
		}

		$_nickname = $this->get_nickname_from_claim( $user_claim );
		if ( is_wp_error( $_nickname ) ) {
			return $_nickname;
		}
		// Use the username as the nickname if the userinfo request nickname is empty.
		if ( empty( $_nickname ) ) {
			$nickname = $username;
		}

		$_displayname = $this->get_displayname_from_claim( $user_claim, true );
		if ( is_wp_error( $_displayname ) ) {
			return $_displayname;
		}
		// Use the nickname as the displayname if the userinfo request displayname is empty.
		if ( empty( $_displayname ) ) {
			$displayname = $nickname;
		}

		// Before trying to create the user, first check if a matching user exists.
		if ( $this->settings->link_existing_users ) {
			$uid = null;
			if ( $this->settings->identify_with_username ) {
				$uid = username_exists( $username );
			} else {
				$uid = email_exists( $email );
			}
			if ( ! empty( $uid ) ) {
				$user = $this->update_existing_user( $uid, $subject_identity );
				do_action( 'openid-connect-generic-update-user-using-current-claim', $user, $user_claim );
				$end_time = microtime( true );
				$this->logger->log( "Existing user updated: {$user->user_login} ($uid)", __METHOD__, $end_time - $start_time );
				return $user;
			}
		}

		/**
		 * Allow other plugins / themes to determine authorization of new accounts
		 * based on the returned user claim.
		 */
		$create_user = apply_filters( 'openid-connect-generic-user-creation-test', $this->settings->create_if_does_not_exist, $user_claim );

		if ( ! $create_user ) {
			return new WP_Error( 'cannot-authorize', __( 'Can not authorize.', 'daggerhart-openid-connect-generic' ), $create_user );
		}

		// Copy the username for incrementing.
		$_username = $username;
		// Ensure prevention of linking usernames & collisions by incrementing the username if it exists.
		// @example Original user gets "name", second user gets "name2", etc.
		$count = 1;
		while ( username_exists( $username ) ) {
			$count++;
			$username = $_username . $count;
		}

		$user_data = array(
			'user_login' => $username,
			'user_pass' => wp_generate_password( 32, true, true ),
			'user_email' => $email,
			'display_name' => $displayname,
			'nickname' => $nickname,
			'first_name' => isset( $user_claim['given_name'] ) ? $user_claim['given_name'] : '',
			'last_name' => isset( $user_claim['family_name'] ) ? $user_claim['family_name'] : '',
		);
		$user_data = apply_filters( 'openid-connect-generic-alter-user-data', $user_data, $user_claim );

		// Create the new user.
		$uid = wp_insert_user( $user_data );

		// Make sure we didn't fail in creating the user.
		if ( is_wp_error( $uid ) ) {
			return new WP_Error( 'failed-user-creation', __( 'Failed user creation.', 'daggerhart-openid-connect-generic' ), $uid );
		}

		// Retrieve our new user.
		$user = get_user_by( 'id', $uid );

		// Save some meta data about this new user for the future.
		add_user_meta( $user->ID, 'openid-connect-generic-subject-identity', (string) $subject_identity, true );

		// Log the results.
		$end_time = microtime( true );
		$this->logger->log( "New user created: {$user->user_login} ($uid)", __METHOD__, $end_time - $start_time );

		// Allow plugins / themes to take action on new user creation.
		do_action( 'openid-connect-generic-user-create', $user, $user_claim );

		return $user;
	}

	/**
	 * Update an existing user with OpenID Connect meta data
	 *
	 * @param int    $uid              The WordPress User ID.
	 * @param string $subject_identity The subject identity from the IDP.
	 *
	 * @return WP_Error|WP_User
	 */
	public function update_existing_user( $uid, $subject_identity ) {
		// Add the OpenID Connect meta data.
		update_user_meta( $uid, 'openid-connect-generic-subject-identity', strval( $subject_identity ) );

		// Allow plugins / themes to take action on user update.
		do_action( 'openid-connect-generic-user-update', $uid );

		// Return our updated user.
		return get_user_by( 'id', $uid );
	}
}
