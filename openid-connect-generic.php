<?php
/*
Plugin Name: OpenID Connect - Generic Client
Plugin URI: https://github.com/daggerhart/openid-connect-generic
Description:  Connect to an OpenID Connect identity provider with Authorization Code Flow
Version: 3.0
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


class OpenID_Connect_Generic {

	// plugin settings
	private $settings;
	
	// plugin logs
	private $logger;
	
	// openid connect generic client
	private $client;
	
	// settings admin page
	private $settings_page;
	
	// login form adjustments
	private $login_form;

	/**
	 * Setup the plugin
	 *
	 * @param \WP_Option_Settings $settings
	 * @param \WP_Option_Logger $logger
	 */
	function __construct( WP_Option_Settings $settings, WP_Option_Logger $logger ){
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * WP Hook 'init'
	 */
	function init(){
		$this->client = new OpenID_Connect_Generic_Client( 
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->ep_login,
			$this->settings->ep_userinfo,
			$this->settings->ep_token,
			// redirect uri
			admin_url( 'admin-ajax.php?action=openid-connect-authorize' )
		);
		
		$this->client_wrapper = OpenID_Connect_Generic_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		$this->login_form = OpenID_Connect_Generic_Login_Form::register( $this->settings, $this->client_wrapper );

		if ( is_admin() ){
			$this->settings_page = OpenID_Connect_Generic_Settings_Page::register( $this->settings, $this->logger );
		}
	}

	/**
	 * Autoloader
	 * 
	 * @param $class
	 */
	static public function autoload( $class ) {
		$filename = strtolower( str_replace( '_', '-', $class ) ) . '.php';

		if ( file_exists( OPENID_CONNECT_GENERIC_DIR . '/includes/' . $filename ) ) {
			require OPENID_CONNECT_GENERIC_DIR . '/includes/' . $filename;
		}
	}

	/**
	 * Instantiate the plugin and hook into WP
	 */
	static public function bootstrap(){
		spl_autoload_register( array( 'OpenID_Connect_Generic', 'autoload' ) );
		
		$settings = new WP_Option_Settings(
			'openid_connect_generic_settings',
			// default settings values
			array(
				// oauth client settings
				'client_id'       => '',
				'client_secret'   => '',
				'scope'           => '',
				'ep_login'        => '',
				'ep_userinfo'     => '',
				'ep_token'        => '',
				
				// non-standard settings
				'no_sslverify'    => 0,
				'identity_key'    => 'sub',
				'allowed_regex'   => '',

				// plugin settings
				'login_type'      => 'button',
				'enforce_privacy' => 0,
				'enable_logging'  => 0,
				'log_limit'       => 1000, 
			)
		);
		
		$logger = new WP_Option_Logger( 'openid-connect-generic-logs', 'error', $settings->enable_logging, $settings->log_limit );
		
		$plugin = new self( $settings, $logger );
		
		add_action( 'init', array( $plugin, 'init' ) );
	}
}

OpenID_Connect_Generic::bootstrap();