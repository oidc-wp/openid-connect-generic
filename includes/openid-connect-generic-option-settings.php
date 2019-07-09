<?php
/**
 * Class OpenId_Connect_Generic_Option_Settings
 *
 * @property $login_type
 * @property $client_id
 * @property $client_secret
 * @property $scope
 * @property $endpoint_login
 * @property $endpoint_userinfo
 * @property $endpoint_token
 * @property $endpoint_end_session
 * @property $identity_key
 * @property $no_sslverify
 * @property $http_request_timeout
 * @property $authenticate_filter
 * @property $enforce_privacy
 * @property $alternate_redirect_uri
 * @property $nickname_key
 * @property $email_format
 * @property $displayname_format
 * @property $identify_with_username
 * @property $state_time_limit
 * @property $link_existing_users
 * @property $redirect_user_back
 * @property $redirect_on_logout
 * @property $enable_logging
 * @property $log_limit
 *
 */
class OpenID_Connect_Generic_Option_Settings {
	
	// wp option name/key
	private $option_name;
	
	// stored option values array
	private $values;
	
	// default plugin settings values
	private $default_settings;

	/**
	 * @param $option_name
	 * @param array $default_settings
	 * @param bool|TRUE $granular_defaults
	 */
	function __construct( $option_name, $default_settings = array(), $granular_defaults = true ){
		$this->option_name = $option_name;
		$this->default_settings = $default_settings;
		$this->values = get_option( $this->option_name, $this->default_settings );
		
		if ( $granular_defaults ) {
			$this->values = array_replace_recursive( $this->default_settings, $this->values );
		}
	}
	
	function __get( $key ){
		if ( isset( $this->values[ $key ] ) ) {
			return $this->values[ $key ];
		}
	}
	
	function __set( $key, $value ){
		$this->values[ $key ] = $value;
	}
	
	function __isset( $key ){
		return isset( $this->values[ $key ] );
	}
	
	function __unset( $key ){
		unset( $this->values[ $key ]);
	}
	
	function get_values(){
		return $this->values;
	}
	
	function get_option_name() {
		return $this->option_name;
	}
	
	function save(){
		update_option( $this->option_name, $this->values );
	}
}
