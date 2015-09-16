<?php
/*
Plugin Name: OpenID Connect - Generic Client
Plugin URI: https://github.com/daggerhart/openid-connect-generic
Description:  Connect to an OpenID Connect identity provider with Authorization Code Flow
Version: 2.1
Author: daggerhart
Author URI: http://www.daggerhart.com
License: GPLv2 Copyright (c) 2015 daggerhart
*/

/* 
Notes
  Spec Doc - http://openid.net/specs/openid-connect-basic-1_0-32.html

  Filters
  - openid-connect-generic-alter-request      - 3 args: request array, plugin settings, specific request op
  - openid-connect-generic-settings-fields    - modify the fields provided on the settings page
  - openid-connect-generic-login-button-text  - modify the login button text 
  - openid-connect-generic-user-login-test    - (bool) should the user be logged in based on their claim
  - openid-connect-generic-user-creation-test - (bool) should the user be created based on their claim

  Actions
  - openid-connect-generic-user-create - 2 args: fires when a new user is created by this plugin

  User Meta
  - openid-connect-generic-user                - (bool) if the user was created by this plugin
  - openid-connect-generic-user-identity       - the identity of the user provided by the idp
  - openid-connect-generic-last-id-token-claim - the user's most recent id_token claim, decoded
  - openid-connect-generic-last-user-claim     - the user's most recent user_claim 
  
  Options
  - openid_connect_generic_settings     - plugin settings
  - openid-connect-generic-valid-states - locally stored generated states
*/

define( 'OPENID_CONNECT_GENERIC_DIR', dirname( __FILE__ ) );
define( 'OPENID_CONNECT_GENERIC_SETTINGS_NAME', 'openid_connect_generic_settings' );

class OpenID_Connect_Generic {
  
  private $cookie_id_key = 'openid-connect-generic-identity';
  
  // states are only valid for 3 minutes
  private $state_time_limit = 180;
  
  // default plugin settings values
  private $default_settings = array(
    'login_type' => 'button',
    'no_sslverify' => 0,
    'enforce_privacy' => 0,
    'identity_key' => 'sub',
  );
  
  // storage for plugin settings
  private $settings = array();
  
  // storage for error messages
  private $errors = array();
  
  private $redirect_uri;
  
  /**
   * Initialize the plugin
   */
  function __construct(){
    add_action( 'init', array( $this, 'init' ) );
    
    $this->redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );
    
    // translatable errors
    $this->errors = array(
      1  => __('Cannot get authentication response'),
      2  => __('Cannot get token response'),
      3  => __('Cannot get user claims'),
      4  => __('Cannot get valid token'),
      5  => __('Cannot get user key'),
      6  => __('Cannot create authorized user'),
      7  => __('User not found'),
      8  => __('You do not have access to this site'),
      9  => __('Cannot get authorization to join this site'),
      99 => __('Unknown error')
    );
  }
  
  /**
   * Get plugin settings
   *  - settings field logic in admin/settings class
   * 
   * @return array
   */
  public function get_settings() {
    if ( ! empty( $this->settings ) ){
      return $this->settings;
    }

    $this->settings = wp_parse_args( get_option( OPENID_CONNECT_GENERIC_SETTINGS_NAME, array() ), $this->default_settings );
    return $this->settings;
  }
  
  /**
   * Implements hook init
   *  - hook plugin into WP as needed
   */
  public function init(){
    // check the user's status based on plugin settings
    $this->check_user_status();
    
    // remove cookies on logout
    add_action( 'wp_logout', array( $this, 'wp_logout' ) );
    
    // verify legitimacy of user token on admin pages
    add_action( 'admin_init', array( $this, 'check_user_token' ) );
    
    // alter the login form as dictated by settings
    add_filter( 'login_message', array( $this, 'login_message' ), 99 );
    
    // alter the requests according to settings
    add_filter( 'openid-connect-generic-alter-request', array( $this, 'alter_request' ), 10, 3 );
    
    // administration yo! 
    if ( is_admin() ) {
      // use the ajax url to handle processing authorization without any html output
      // this callback will occur when then IDP returns with an authenticated value
      add_action( 'wp_ajax_openid-connect-authorize', array( $this, 'auth_callback' ) );
      add_action( 'wp_ajax_nopriv_openid-connect-authorize', array( $this, 'auth_callback' ) );
      
      // initialize the settings page
      require_once OPENID_CONNECT_GENERIC_DIR . '/admin/openid-connect-generic-settings.php';
      new OpenID_Connect_Generic_Settings( $this->get_settings() );
    }
  }

  /**
   * Validate the user's status based on plugin settings
   */
  function check_user_status(){
    $settings = $this->get_settings();
    
    // check if privacy enforcement is enabled
    if ( $settings['enforce_privacy'] && 
         ! is_user_logged_in() &&
         // avoid redirects on cron or ajax
         ( ! defined( 'DOING_AJAX') || ! DOING_AJAX ) &&
         ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
       ) 
    {
      global $pagenow;
      
      // avoid redirect loop
      if ( $pagenow != 'wp-login.php' && ! isset( $_GET['loggedout'] ) && ! isset( $_GET['login-error'] ) ) {
        wp_redirect( wp_login_url() );
        exit;
      }
    }
    
    // verify token for any logged in user
    if ( is_user_logged_in() ) {
      $this->check_user_token();
    } 
  }

  /**
   * Check the user's cookie
   */
  function check_user_token(){
    $is_openid_connect_user = get_user_meta( wp_get_current_user()->ID, 'openid-connect-generic-user', true );

    if ( is_user_logged_in() && ! empty( $is_openid_connect_user ) && ! isset( $_COOKIE[ $this->cookie_id_key ] ) ) {
      wp_logout();
      wp_redirect( wp_login_url() );
      exit;
    }
  }
  
  /**
   * Control the authentication and subsequent authorization of the user when
   *  returning from the IDP.
   */
  function auth_callback(){
    $settings = $this->get_settings();
    
    // look for an existing error of some kind
    if ( isset( $_GET['error'] ) ) {
      $this->error_redirect( 99 );
    }

    // make sure we have a legitimate authentication code and valid state
    if ( !isset( $_GET['code'] ) || !isset( $_GET['state'] ) || !$this->check_state( $_GET['state'] ) ) {
      $this->error_redirect( 1 );
    }
    
    // we have an authorization code, make sure it is good by 
    // attempting to exchange it for an authentication token
    $token_result = $this->request_authentication_token( $_GET['code'] );
    
    // ensure the token is not an error generated by wp
    if ( is_wp_error( $token_result ) ){
      $this->error_redirect( 2 );
    }

    // extract token response from token
    $token_response = json_decode( $token_result['body'], true );
    
    // we need to ensure 3 specific items exist with the token response in order
    // to proceed with confidence:  id_token, access_token, and token_type == 'Bearer'
    if ( ! isset( $token_response['id_token'] ) || ! isset( $token_response['access_token'] ) || 
         ! isset( $token_response['token_type'] ) || $token_response['token_type'] !== 'Bearer' )
    {
      $this->error_redirect( 4 );
    }

    // - end authentication
    // - start authorization

    // The id_token is used to identify the authenticated user, e.g. for SSO.
    // The access_token must be used to prove access rights to protected resources
    // e.g. for the userinfo endpoint
    
    // break apart the id_token int eh response for decoding
    $tmp = explode('.', $token_response['id_token'] );

    // Extract the id_token's claims from the token
    $id_token_claim = json_decode( base64_decode( $tmp[1] ), true );
    
    // make sure we can find our identification data and that it has a value
    if ( ! isset( $id_token_claim[ $settings['identity_key'] ] ) || empty( $id_token_claim[ $settings['identity_key'] ] ) ) {
      $this->error_redirect( 5 );
    }

    // if desired, admins can use regex to determine if the identity value is valid
    // according to their own standards expectations  
    if ( isset( $settings['allowed_regex'] ) && !empty( $settings['allowed_regex'] ) && 
         preg_match( $settings['allowed_regex'], $id_token_claim[ $settings['identity_key'] ] ) !==  1) 
    {
      $this->error_redirect( 5 );
    }
    
    // send a userinfo request to get user claim
    $user_claim_result = $this->request_userinfo( $token_response['access_token'] );

    // make sure we didn't get an error, and that the response body exists
    if ( is_wp_error( $user_claim_result ) || ! isset( $user_claim_result['body'] ) ) {
      $this->error_redirect( 3 );
    }

    $user_claim = json_decode( $user_claim_result['body'], true );
    
    // make sure the id_token sub === user_claim sub, according to spec
    if ( $id_token_claim[ $settings['identity_key'] ] !== $user_claim['sub'] ) {
      $this->error_redirect( 4 );
    }

    // retrieve the identity from the id_token
    $user_identity = $id_token_claim[ $settings['identity_key'] ];

    // - end authorization
    // - start user handling
    
    // allow plugins / themes to halt the login process early
    // based on the user_claim 
    $login_user = apply_filters( 'openid-connect-generic-user-login-test', true, $user_claim );
    
    if ( ! $login_user ){
      $this->error_redirect( 8 );
    }
    
    // look for user by their openid-connect-generic-user-identity value
    $user_query = new WP_User_Query( array(
      'meta_query' => array(
        array(
          'key' => 'openid-connect-generic-user-identity',
          'value' => $user_identity,
        )
      )
    ));
    
    // if we found an existing users, grab the first one returned
    if ( $user_query->get_total() > 0 ) {
      $users = $user_query->get_results();
      $user = $users[0];  
    }
    // otherwise, user does not exist and we'll need to create it
    else {
      // default username & email to the user identity, since that is the only
      // thing we can be sure to have 
      $username = $user_identity;
      $email = $user_identity;
      
      // allow claim details to determine username
      if ( isset( $user_claim['email'] ) ) {
        $email = $user_claim['email'];
        $username = $this->get_username_from_claim( $user_claim );
      }
      // if no name exists, attempt another request for userinfo
      else if ( isset( $token_response['access_token'] ) ) {
        $user_claim_result = $this->request_userinfo( $token_response['access_token'] );

        // make sure we didn't get an error
        if ( is_wp_error( $user_claim_result ) ) {
          $this->error_redirect( 3 );
        }

        $user_claim = json_decode( $user_claim_result['body'], true );

        if ( isset( $user_claim['email'] ) ) {
          $email = $user_claim['email'];
          $username = $this->get_username_from_claim( $user_claim );
        }
      }
      
      // allow other plugins / themes to determine authorization 
      // of new accounts based on the returned user claim
      $create_user = apply_filters( 'openid-connect-generic-user-creation-test', true, $user_claim );
      
      if ( ! $create_user ) {
        $this->error_redirect( 9 );
      }
      
      // create the new user
      $uid = wp_create_user( $username, wp_generate_password( 32, true, true ), $email );

      // make sure we didn't fail in creating the user
      if ( is_wp_error( $uid ) ) {
        $this->error_redirect( 6 );
      }

      $user = get_user_by( 'id', $uid );

      // save some meta data about this new user for the future
      add_user_meta( $user->ID, 'openid-connect-generic-user', true, true );
      add_user_meta( $user->ID, 'openid-connect-generic-user-identity', (string) $user_identity, true );
      
      // allow plugins / themes to take action on new user creation
      do_action( 'openid-connect-generic-user-create', $user, $user_claim );
    }
    
    // ensure our found user is a real WP_User
    if ( ! is_a( $user, 'WP_User' ) || ! $user->exists() ) {
      $this->error_redirect( 7 );
    }
    
    // hey, we made it!
    // let's remember the tokens for future reference
    update_user_meta( $user->ID, 'openid-connect-generic-last-token-response', $token_response );
    update_user_meta( $user->ID, 'openid-connect-generic-last-id-token-claim', $id_token_claim );
    update_user_meta( $user->ID, 'openid-connect-generic-last-user-claim', $user_claim );

    // save our authorization cookie for the response expiration
    $oauth_expiry = $token_response['expires_in'] + current_time( 'timestamp', true );
    setcookie( $this->cookie_id_key, $user_identity, $oauth_expiry, COOKIEPATH, COOKIE_DOMAIN, true );
    
    // get a cookie and go home!
    wp_set_auth_cookie( $user->ID, false );
    wp_redirect( home_url() );
    // - end user handling
  }

  /**
   * Using the authorization_code, request an authentication token from the idp
   * 
   * @param $code - authorization_code
   * @return array|\WP_Error
   */
  function request_authentication_token( $code ){
    $settings = $this->get_settings();
    
    $request = array(
      'body' => array(
        'code' => $code,
        'client_id' => $settings['client_id'],
        'client_secret' => $settings['client_secret'],
        'redirect_uri' => $this->redirect_uri,
        'grant_type' => 'authorization_code',
        'scope' => $settings['scope'],
      )
    );
    
    // allow modifications to the request
    $request = apply_filters( 'openid-connect-generic-alter-request', $request, $settings, 'get-authentication-token' );
    
    // call the server and ask for a token
    $response = wp_remote_post( $settings['ep_token'], $request );
    
    return $response;
  }

  /**
   * Using an access_token, request the userinfo from the idp
   * 
   * @param $access_token 
   * @return array|\WP_Error
   */
  function request_userinfo( $access_token ){
    $settings = $this->get_settings();
    
    // allow modifications to the request
    $request = apply_filters( 'openid-connect-generic-alter-request', array(), $settings, 'get-userinfo' );

    // attempt the request
    $response = wp_remote_get( $settings['ep_userinfo'].'?access_token='.$access_token, $request );
    
    return $response;
  }

  /**
   * Modify outgoing requests according to settings
   * 
   * @param $request
   * @param $settings
   * @param $op
   * @return mixed
   */
  function alter_request( $request, $settings, $op ){
    if ( isset( $settings['no_sslverify'] ) && $settings['no_sslverify'] ) {
      $request['sslverify'] = false;
    }
    
    return $request;
  }

  /**
   * Create a single use authentication url
   * 
   * @return string
   */
  function make_authentication_url() {
    $settings = $this->get_settings();

    $url = sprintf( '%1$s?response_type=code&scope=%2$s&client_id=%3$s&state=%4$s&redirect_uri=%5$s',
      $settings['ep_login'],
      urlencode( $settings['scope'] ),
      urlencode( $settings['client_id'] ),
      $this->new_state(),
      urlencode( $this->redirect_uri )
    );
    
    return $url;
  }

  /**
   * Generate a new state, 
   *  save it to the states option with a timestamp, 
   *  and return it.
   * 
   * @return string
   */
  function new_state(){
    $states = get_option( 'openid-connect-generic-valid-states', array() );
    
    // new state w/ timestamp
    $new_state = md5( mt_rand() );
    $states[ $new_state ] = time();
    
    // save state
    update_option( 'openid-connect-generic-valid-states', $states );
    
    return $new_state;
  }

  /**
   * Check the validity of a given state
   * 
   * @param $state
   * @return bool
   */
  function check_state( $state ){
    $states = get_option( 'openid-connect-generic-valid-states', array() );
    $valid = false;
    
    // remove any expired states
    foreach ( $states as $code => $timestamp ){
      if ( ( $timestamp + $this->state_time_limit ) < time() ) {
        unset( $states[ $code ] );
      }
    }
    
    // see if the current state is still within the list of valid states
    if ( isset( $states[ $state ] ) ){
      // state is valid, remove it
      unset( $states[ $state ] );
      
      $valid = true;
    }
    
    // save our altered states
    update_option( 'openid-connect-generic-valid-states', $states );
    
    return $valid;
  }
  
  /**
   * Implements filter login_message
   * 
   * @param $message
   * @return string
   */
  function login_message( $message ){
    $settings = $this->get_settings();

    // errors and auto login can't happen at the same time
    if ( isset( $_GET['login-error'] ) ) {
      $message = $this->error_message( $_GET['login-error'] );
    }
    else if ( $settings['login_type'] == 'auto' ) {
      wp_redirect( $this->make_authentication_url() );
      exit;
    }
    
    // login button is appended to existing messages in case of error
    if ( $settings['login_type'] == 'button' ) {
      $message.= $this->login_button();
    }
    
    return $message;
  }

  /**
   * Handle errors by redirecting the user to the login form
   *  along with an error code
   *
   * @param $error_number
   */
  function error_redirect( $error_number ){
    $url = wp_login_url() . '?login-error=' . $error_number;

    wp_redirect( $url );
    exit;
  }

  /**
   * Display an error message to the user
   * 
   * @param $error_number
   * @return string
   */
  function error_message( $error_number ){
    // fallback to unknown error
    if ( ! isset( $this->errors[ $error_number ] ) ) {
      $error_number = 99;
    }
    
    ob_start();
      ?>
        <div id="login_error"><?php print $this->errors[ $error_number ]; ?></div>
      <?php
    return ob_get_clean();
  }

  /**
   * Create a login button (link)
   * 
   * @return string
   */
  function login_button() {
    $text = apply_filters( 'openid-connect-generic-login-button-text', __('Login with OpenID Connect') );
    $href =$this->make_authentication_url();
    
    ob_start();
      ?>
        <div class="openid-connect-login-button" style="margin: 1em 0; text-align: center;">
          <a class="button button-large" href="<?php print esc_url( $href ); ?>"><?php print $text; ?></a>
        </div>
      <?php
    return ob_get_clean();
  }

  /**
   * Implements hook wp_logout
   * 
   * Remove cookies
   */
  function wp_logout(){
    setcookie( $this->cookie_id_key , '1', 0, COOKIEPATH, COOKIE_DOMAIN, true );
  }

  /**
   * Avoid user_login collisions by incrementing
   * 
   * @param $user_claim array
   * @return string
   */
  function get_username_from_claim( $user_claim ){
    if ( isset( $user_claim['preferred_username'] ) && !empty( $user_claim['preferred_username'] ) ) {
      $desired_username = $user_claim['preferred_username'];
    }
    else if ( isset( $user_claim['name'] ) && !empty( $user_claim['name'] ) ) {
      $desired_username = $user_claim['name'];
    }
    else if ( isset( $user_claim['email'] ) && !empty( $user_claim['email'] ) ) {
      $tmp = explode( '@', $user_claim['email'] );
      $desired_username = $tmp[0];
    }
    else {
      // nothing to build a name from
      return false;
    }
    
    // normalize the data a bit
    $desired_username = strtolower( preg_replace( '/[^a-zA-Z\_0-9]/', '', $desired_username ) );
    
    // copy the username for incrementing
    $username = $desired_username;
    
    // original user gets "name"
    // second user gets "name2"
    // etc
    $count = 1;
    while ( username_exists( $username ) ) {
      $count++;
      $username = $desired_name . $count; 
    }
    
    return $username;
  }
}

new OpenID_Connect_Generic();
