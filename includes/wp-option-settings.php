<?php

if ( ! class_exists( 'WP_Option_Settings' ) ) :
 
class WP_Option_Settings {
	
	// wp option name/key
	private $option_name;
	
	// stored option values array
	private $values;
	
	// default plugin settings values
	private $default_settings;

	/**
	 * @param $option_name
	 * @param array $default_settings
	 */
	function __construct( $option_name, $default_settings = array() ){
		$this->option_name = $option_name;
		$this->default_settings = $default_settings;
		$this->values = get_option( $this->option_name, $this->default_settings );
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

endif;